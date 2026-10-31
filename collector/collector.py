#!/usr/bin/env python3
"""
Resource Monitor - Data Collector
Collects system metrics and stores them in MariaDB
"""

import sys
import time
import socket
import logging
import signal
import csv
import subprocess
from datetime import datetime
from pathlib import Path
from logging.handlers import RotatingFileHandler

import fnmatch

import statistics

import yaml
import psutil
import pymysql
from pymysql.cursors import DictCursor
import threading

from alert_checker import AlertChecker
from aggregator import Aggregator


class ResourceCollector:
    """Main collector class for system resource monitoring"""
    
    def __init__(self, config_path='config.yaml'):
        """Initialize the collector with configuration"""
        self.running = True
        self._last_gpu_warning = 0
        self.config = self._load_config(config_path)
        self._setup_logging()
        self.logger.info("=" * 60)
        self.logger.info("Resource Monitor Collector Starting")
        self.logger.info("=" * 60)
        
        # Get hostname
        if self.config['system']['hostname'] == 'auto':
            self.hostname = socket.gethostname()
        else:
            self.hostname = self.config['system']['hostname']
        
        self.logger.info(f"Hostname: {self.hostname}")
        self.logger.info(f"Collection interval: {self.config['collection']['interval_seconds']}s")
        
        # Database connection
        self.db = None
        self._connect_db()
        
        # Previous values for delta calculations
        self.prev_disk_io = None
        self.prev_net_io = None
        self.prev_proc_io = {}
        
        # User-defined process naming rules (from config)
        self.naming_rules = self.config.get('process_naming', {}).get('rules', [])
        self.logger.info(f"Loaded {len(self.naming_rules)} process naming rule(s)")
        self.last_disk_collection = 0    # Track when disk usage was last collected
        self.last_baseline_compute = 0   # Track when baselines were last computed (epoch)
        self.last_alert_check = 0        # Track when alert checks were last run

        # Aggregation timers — track last run time for each job (0 = never)
        self.last_agg_1min    = 0   # aggregate_to_1min + aggregate_temps_to_1min
        self.last_agg_5min    = 0   # aggregate_to_5min + aggregate_temps_to_5min
        self.last_agg_1hour   = 0   # aggregate_to_1hour
        self.last_cleanup_raw = 0   # cleanup_raw_samples (every 10 min)
        self.last_cleanup_proc = 0  # cleanup_process_snapshots (every 15 min)
        self.last_cleanup_agg  = 0  # cleanup_aggregates (every 24 hrs)
        
        # Setup signal handlers for graceful shutdown
        signal.signal(signal.SIGTERM, self._signal_handler)
        signal.signal(signal.SIGINT, self._signal_handler)

        # Alert checker (reads all config from DB — no restart needed for changes)
        self.alert_checker = AlertChecker(self.config, self.logger)
        self.logger.info("Alert checker initialised")

        # Aggregation manager — replaces MariaDB stored procedures + events
        self.aggregator = Aggregator(self)
        self.logger.info("Aggregator initialised")
        
    def _load_config(self, config_path):
        """Load YAML configuration file"""
        with open(config_path, 'r') as f:
            return yaml.safe_load(f)
    
    def _setup_logging(self):
        """Configure logging"""
        log_config = self.config['logging']
        
        # Create log directory if it doesn't exist
        log_path = Path(log_config['file'])
        log_path.parent.mkdir(parents=True, exist_ok=True)
        
        # Setup logger
        self.logger = logging.getLogger('ResourceCollector')
        self.logger.setLevel(getattr(logging, log_config['level']))
        
        # Rotating file handler
        handler = RotatingFileHandler(
            log_config['file'],
            maxBytes=log_config['max_bytes'],
            backupCount=log_config['backup_count']
        )
        
        # Console handler
        console_handler = logging.StreamHandler(sys.stdout)
        
        # Formatter
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        handler.setFormatter(formatter)
        console_handler.setFormatter(formatter)
        
        self.logger.addHandler(handler)
        self.logger.addHandler(console_handler)
    
    def _do_connect(self):
        """Perform a single connection attempt. Raises on failure."""
        db_config = self.config['database']
        if 'unix_socket' in db_config and db_config['unix_socket']:
            self.db = pymysql.connect(
                unix_socket=db_config['unix_socket'],
                user=db_config['user'],
                password=db_config['password'],
                database=db_config['database'],
                charset=db_config['charset'],
                cursorclass=DictCursor,
                autocommit=True
            )
            self.logger.info(f"Connected to database via unix socket: {db_config['unix_socket']}")
        else:
            self.db = pymysql.connect(
                host=db_config['host'],
                port=db_config['port'],
                user=db_config['user'],
                password=db_config['password'],
                database=db_config['database'],
                charset=db_config['charset'],
                cursorclass=DictCursor,
                autocommit=True
            )
            self.logger.info(f"Connected to database: {db_config['host']}:{db_config['port']}/{db_config['database']}")
    
    def _connect_db(self):
        """Connect to MariaDB at startup, retrying indefinitely until successful.
        
        Retries rather than exiting so the collector survives MariaDB starting
        slowly or the collector being brought up before MariaDB is ready.
        """
        attempt = 0
        retry_delay = 5
        while True:
            attempt += 1
            try:
                self._do_connect()
                return
            except Exception as e:
                self.logger.error(f"Failed to connect to database (attempt {attempt}): {e}")
                self.logger.info(f"Retrying in {retry_delay}s...")
                time.sleep(retry_delay)
    
    def _ensure_db_connection(self):
        """Ensure the database connection is alive, reconnecting if needed.
        
        Called at the top of save_metrics() on every collection cycle.
        Returns True if connected and ready, False if reconnect failed
        (caller should skip this cycle and try again next interval).
        """
        # Lightweight ping first — handles the common case cheaply. If it
        # fails, the explicit reconnect path below creates a fresh connection.
        try:
            self.db.ping()
            return True
        except Exception:
            pass
        
        # Ping failed — full reconnect with retries
        self.logger.warning("Database connection lost, attempting to reconnect...")
        max_retries = 5
        retry_delay = 5
        
        for attempt in range(1, max_retries + 1):
            try:
                if self.db:
                    try:
                        self.db.close()
                    except Exception:
                        pass
                    self.db = None
                self._do_connect()
                self.logger.info("Reconnected to database successfully")
                return True
            except Exception as e:
                self.logger.warning(f"Reconnect attempt {attempt}/{max_retries} failed: {e}")
                if attempt < max_retries:
                    time.sleep(retry_delay)
        
        self.logger.error(
            f"Could not reconnect to database after {max_retries} attempts — "
            "skipping this collection cycle"
        )
        return False
    
    def _signal_handler(self, signum, frame):
        """Handle shutdown signals"""
        self.logger.info(f"Received signal {signum}, shutting down gracefully...")
        self.running = False
    
    def _clamp_value(self, value, min_val, max_val):
        """Clamp a value to a safe range, handling None/NaN/Infinity"""
        # Handle None
        if value is None:
            return 0.0
        
        # Handle NaN and Infinity
        try:
            if not isinstance(value, (int, float)):
                value = float(value)
            
            # Check for NaN or Infinity
            if value != value or value == float('inf') or value == float('-inf'):
                self.logger.warning(f"Invalid value detected (NaN/Inf), using 0.0")
                return 0.0
            
            # Clamp to range
            if value < min_val:
                self.logger.warning(f"Value {value} below minimum {min_val}, clamping")
                return min_val
            if value > max_val:
                self.logger.warning(f"Value {value} above maximum {max_val}, clamping")
                return max_val
            
            return value
        except (ValueError, TypeError) as e:
            self.logger.warning(f"Error converting value '{value}': {e}, using 0.0")
            return 0.0
    
    def collect_cpu_metrics(self):
        """Collect CPU usage metrics"""
        if not self.config['metrics']['cpu']:
            return None
        
        try:
            cpu_count = psutil.cpu_count()
            
            # Load averages
            load_1, load_5, load_15 = psutil.getloadavg()
            
            # Per-core usage — non-blocking, compares against previous call (~10s ago).
            # This gives a 10-second average window, matching exactly how psutil measures
            # per-process cpu_percent via process_iter. Both metrics now reflect the same
            # time period so they're directly comparable on the dashboard.
            # First call after startup will return 0.0 — that's expected and harmless.
            per_cpu = psutil.cpu_percent(interval=None, percpu=True)
            
            # Derive overall % from per-core values so both numbers
            # are always mathematically consistent (one sample, one formula)
            cpu_percent = sum(per_cpu) / len(per_cpu) if per_cpu else 0.0
            
            return {
                'cpu_percent': cpu_percent,
                'cpu_count': cpu_count,
                'load_1': load_1,
                'load_5': load_5,
                'load_15': load_15,
                'per_core': per_cpu
            }
        except Exception as e:
            self.logger.error(f"Error collecting CPU metrics: {e}")
            return None
    
    def collect_memory_metrics(self):
        """Collect memory usage metrics"""
        if not self.config['metrics']['memory']:
            return None
        
        try:
            mem = psutil.virtual_memory()
            swap = psutil.swap_memory()
            
            return {
                'total': mem.total,
                'available': mem.available,
                'used': mem.used,
                'percent': mem.percent,
                'swap_total': swap.total,
                'swap_used': swap.used,
                'swap_percent': swap.percent
            }
        except Exception as e:
            self.logger.error(f"Error collecting memory metrics: {e}")
            return None
    
    def collect_disk_io(self):
        """Collect disk I/O metrics"""
        if not self.config['metrics']['disk_io']:
            return None
        
        try:
            disk_io = psutil.disk_io_counters()
            
            # Calculate delta since last sample
            if self.prev_disk_io:
                read_bytes = disk_io.read_bytes - self.prev_disk_io.read_bytes
                write_bytes = disk_io.write_bytes - self.prev_disk_io.write_bytes
                read_count = disk_io.read_count - self.prev_disk_io.read_count
                write_count = disk_io.write_count - self.prev_disk_io.write_count
            else:
                read_bytes = write_bytes = read_count = write_count = 0
            
            self.prev_disk_io = disk_io
            
            return {
                'read_bytes': read_bytes,
                'write_bytes': write_bytes,
                'read_count': read_count,
                'write_count': write_count
            }
        except Exception as e:
            self.logger.error(f"Error collecting disk I/O metrics: {e}")
            return None
    
    def _matches_pattern(self, path, pattern):
        """Check if path matches a wildcard pattern"""
        import fnmatch
        return fnmatch.fnmatch(path, pattern)
    
    def _should_include_disk(self, mountpoint):
        """Check if disk should be included based on filter rules"""
        filter_config = self.config.get('disk_usage_filter', {})
        whitelist = filter_config.get('whitelist', [])
        blacklist = filter_config.get('blacklist', [])
        
        # Whitelist overrides everything
        for pattern in whitelist:
            if self._matches_pattern(mountpoint, pattern):
                return True
        
        # Check blacklist
        for pattern in blacklist:
            if self._matches_pattern(mountpoint, pattern):
                return False
        
        # If not in whitelist or blacklist, include by default
        return True
    
    def collect_disk_usage(self):
        """Collect disk usage per partition"""
        if not self.config['metrics']['disk_usage']:
            return None
        
        try:
            partitions = []
            filter_config = self.config.get('disk_usage_filter', {})
            custom_paths = filter_config.get('custom_paths', [])
            
            # Collect from system partitions
            for partition in psutil.disk_partitions(all=False):
                try:
                    # Apply filtering
                    if not self._should_include_disk(partition.mountpoint):
                        continue
                    
                    usage = psutil.disk_usage(partition.mountpoint)
                    partitions.append({
                        'device': partition.device,
                        'mountpoint': partition.mountpoint,
                        'fstype': partition.fstype,
                        'total': usage.total,
                        'used': usage.used,
                        'free': usage.free,
                        'percent': usage.percent
                    })
                except PermissionError:
                    # Skip partitions we can't access
                    continue
            
            # Add custom paths
            for custom_path in custom_paths:
                try:
                    if not self._should_include_disk(custom_path):
                        continue
                    
                    usage = psutil.disk_usage(custom_path)
                    partitions.append({
                        'device': 'custom',
                        'mountpoint': custom_path,
                        'fstype': 'custom',
                        'total': usage.total,
                        'used': usage.used,
                        'free': usage.free,
                        'percent': usage.percent
                    })
                except (PermissionError, FileNotFoundError) as e:
                    self.logger.warning(f"Cannot access custom path {custom_path}: {e}")
                    continue
            
            return partitions
        except Exception as e:
            self.logger.error(f"Error collecting disk usage: {e}")
            return None
    
    def collect_network_io(self):
        """Collect network I/O metrics, excluding loopback.
        
        Uses per-NIC counters so loopback (lo) can be excluded — local services
        talking to each other over localhost aren't real traffic. Only external-facing
        interfaces are summed, giving a true picture of what's entering and leaving
        the server.
        
        Per-NIC previous values are tracked individually so that interfaces appearing
        mid-session (e.g. a VPN connecting) don't cause a spike on first measurement.
        """
        if not self.config['metrics']['network']:
            return None
        
        try:
            per_nic = psutil.net_io_counters(pernic=True)
            
            # Exclude loopback — everything except lo is a real interface
            external_nics = {nic: counters for nic, counters in per_nic.items()
                             if nic != 'lo'}
            
            bytes_sent = bytes_recv = packets_sent = packets_recv = 0
            
            if self.prev_net_io:
                for nic, counters in external_nics.items():
                    if nic in self.prev_net_io:
                        # Known NIC — calculate delta against previous reading
                        prev = self.prev_net_io[nic]
                        bytes_sent   += counters.bytes_sent   - prev.bytes_sent
                        bytes_recv   += counters.bytes_recv   - prev.bytes_recv
                        packets_sent += counters.packets_sent - prev.packets_sent
                        packets_recv += counters.packets_recv - prev.packets_recv
                    # New NIC this cycle (VPN just connected, etc.) — skip, measure next cycle
            
            # Store full per-NIC snapshot as baseline for next cycle
            self.prev_net_io = external_nics
            
            return {
                'bytes_sent': bytes_sent,
                'bytes_recv': bytes_recv,
                'packets_sent': packets_sent,
                'packets_recv': packets_recv
            }
        except Exception as e:
            self.logger.error(f"Error collecting network I/O: {e}")
            return None
    
    def collect_network_connections(self):
        """Collect network connection counts"""
        if not self.config['metrics']['connections']:
            return None
        
        try:
            connections = psutil.net_connections(kind='inet')
            
            # Count by status
            status_counts = {
                'tcp_established': 0,
                'tcp_listen': 0,
                'tcp_time_wait': 0,
                'tcp_close_wait': 0,
                'tcp_other': 0,
                'udp': 0
            }
            # Per-PID count of ESTABLISHED connections only.
            # Used to populate tcp_conns on process_snapshots so we can
            # attribute network spikes to specific processes.
            per_pid = {}

            for conn in connections:
                if conn.type == socket.SOCK_STREAM:  # TCP
                    if conn.status == 'ESTABLISHED':
                        status_counts['tcp_established'] += 1
                        if conn.pid:  # pid is None for some kernel sockets
                            per_pid[conn.pid] = per_pid.get(conn.pid, 0) + 1
                    elif conn.status == 'LISTEN':
                        status_counts['tcp_listen'] += 1
                    elif conn.status == 'TIME_WAIT':
                        status_counts['tcp_time_wait'] += 1
                    elif conn.status == 'CLOSE_WAIT':
                        status_counts['tcp_close_wait'] += 1
                    else:
                        status_counts['tcp_other'] += 1
                elif conn.type == socket.SOCK_DGRAM:  # UDP
                    status_counts['udp'] += 1

            status_counts['per_pid'] = per_pid
            return status_counts
        except Exception as e:
            self.logger.error(f"Error collecting network connections: {e}")
            return None
    
    def collect_gpu_metrics(self):
        """Collect NVIDIA GPU utilization, memory, temperature, and power."""
        if not self.config.get('metrics', {}).get('gpu', True):
            return []

        command = [
            'nvidia-smi',
            '--query-gpu=index,name,utilization.gpu,memory.used,memory.total,temperature.gpu,power.draw',
            '--format=csv,noheader,nounits',
        ]
        try:
            result = subprocess.run(
                command, capture_output=True, text=True, timeout=5, check=True
            )
            rows = csv.reader(result.stdout.splitlines())
            gpu_metrics = []

            def optional_number(value):
                value = value.strip()
                if not value or value.upper() in ('N/A', '[N/A]'):
                    return None
                try:
                    return float(value)
                except ValueError:
                    return None

            for row in rows:
                if len(row) != 7:
                    continue
                try:
                    gpu_id = int(row[0].strip())
                except ValueError:
                    continue

                memory_used = optional_number(row[3])
                memory_total = optional_number(row[4])
                memory_percent = (
                    memory_used * 100 / memory_total
                    if memory_used is not None and memory_total
                    else None
                )
                gpu_metrics.append({
                    'gpu_id': gpu_id,
                    'gpu_name': row[1].strip(),
                    'gpu_percent': optional_number(row[2]),
                    'memory_used_mb': int(memory_used) if memory_used is not None else None,
                    'memory_total_mb': int(memory_total) if memory_total is not None else None,
                    'memory_percent': memory_percent,
                    'temperature_celsius': optional_number(row[5]),
                    'power_watts': optional_number(row[6]),
                })
            return gpu_metrics
        except (OSError, subprocess.SubprocessError) as exc:
            now = time.time()
            if now - self._last_gpu_warning >= 300:
                self.logger.warning("GPU telemetry unavailable; skipping sample: %s", exc)
                self._last_gpu_warning = now
            return []

    def collect_temperatures(self):
        """Collect CPU temperature readings"""
        if not self.config['metrics']['temperatures']:
            return None
        
        try:
            temps = psutil.sensors_temperatures()
            if not temps:
                return None
            
            temp_readings = []
            for name, entries in temps.items():
                for entry in entries:
                    temp_readings.append({
                        'label': f"{name}_{entry.label}" if entry.label else name,
                        'current': entry.current,
                        'high': entry.high,
                        'critical': entry.critical
                    })
            
            return temp_readings
        except Exception as e:
            self.logger.error(f"Error collecting temperatures: {e}")
            return None
    
    def get_smart_process_name(self, name, cmdline):
        """Extract intelligent process name from command line.
        
        Checks user-defined rules from config.yaml first, then falls back
        to built-in interpreter logic.
        
        Examples:
            python3 /path/to/script.py → python: script.py
            node /app/server.js → node: server.js
            /usr/bin/python3 -m module.name → python: module.name
            java -jar app.jar → java: app.jar
            /usr/bin/chromium --flag → chromium
        """
        if not cmdline or len(cmdline) == 0:
            return name
        
        # ── 1. User-defined naming rules (config.yaml process_naming.rules) ──
        # Match against both the raw process name AND the executable basename
        # from cmdline[0], so rules work regardless of how the process was launched.
        exe_basename = cmdline[0].split('/')[-1].split('\\')[-1] if cmdline else name
        
        for rule in self.naming_rules:
            pattern = rule.get('match_exe', '')
            if not pattern:
                continue
            
            # Glob match against exe basename or process name
            if fnmatch.fnmatch(exe_basename, pattern) or fnmatch.fnmatch(name, pattern):
                # Optional: require the full exe path to contain a specific string.
                # Lets you target a specific binary (e.g. /opt/emby-server/bin/ffmpeg)
                # without matching every process named ffmpeg.
                exe_path_required = rule.get('exe_path_contains')
                if exe_path_required and (not cmdline or exe_path_required not in cmdline[0]):
                    continue  # Full exe path doesn't match — skip this rule

                display_name     = rule.get('display_name', name)
                cmdline_arg      = rule.get('cmdline_arg')
                strip_prefix     = rule.get('strip_prefix', '')
                strip_prefix_any = rule.get('strip_prefix_any', [])
                
                if cmdline_arg and cmdline_arg in cmdline:
                    try:
                        arg_index = cmdline.index(cmdline_arg)
                        if arg_index + 1 < len(cmdline):
                            label = cmdline[arg_index + 1]
                            # strip_prefix_any: try each prefix in order, apply first match.
                            # Use this when the target arg can start with multiple different
                            # prefixes (e.g. /nfs/series/ vs /nfs/movies/).
                            if strip_prefix_any:
                                for prefix in strip_prefix_any:
                                    if label.startswith(prefix):
                                        label = label[len(prefix):]
                                        break
                            elif strip_prefix and label.startswith(strip_prefix):
                                label = label[len(strip_prefix):]
                            split_at = rule.get('split_at')
                            if split_at and split_at in label:
                                label = label.split(split_at)[0]
                            return f"{display_name}: {label}"
                    except (ValueError, IndexError):
                        pass
                
                # Rule matched but no label extracted — return display_name alone
                return display_name
        
        # ── 2. Built-in interpreter logic (fallback) ──
        
        # Common interpreters and their patterns
        interpreters = ['python', 'python3', 'python2', 'node', 'ruby', 'perl', 'php', 'java', 'bash', 'sh']
        
        base_name = name.lower()
        
        # Check if this is an interpreter
        is_interpreter = any(interp in base_name for interp in interpreters)
        
        if is_interpreter and len(cmdline) > 1:
            # Handle -m module syntax (python -m module.name)
            if '-m' in cmdline:
                try:
                    m_index = cmdline.index('-m')
                    if m_index + 1 < len(cmdline):
                        module_name = cmdline[m_index + 1]
                        # Remove .py extension if present
                        if module_name.endswith('.py'):
                            module_name = module_name[:-3]
                        return f"{base_name}: {module_name}"
                except (ValueError, IndexError):
                    pass
            
            # Handle -jar syntax (java -jar app.jar)
            if '-jar' in cmdline:
                try:
                    jar_index = cmdline.index('-jar')
                    if jar_index + 1 < len(cmdline):
                        jar_path = cmdline[jar_index + 1]
                        jar_name = jar_path.split('/')[-1].split('\\')[-1]
                        return f"java: {jar_name}"
                except (ValueError, IndexError):
                    pass
            
            # Find the first non-flag argument (script/file)
            for arg in cmdline[1:]:
                # Skip flags and common options
                if arg.startswith('-'):
                    continue
                # Skip empty args
                if not arg.strip():
                    continue
                    
                # Extract filename from path
                script_name = arg.split('/')[-1].split('\\')[-1]
                
                # If it looks like a script file, use it
                if any(script_name.endswith(ext) for ext in ['.py', '.js', '.rb', '.pl', '.php', '.sh']):
                    return f"{base_name}: {script_name}"
                
                # If it's not a common system path, use it
                if not arg.startswith(('/usr/', '/bin/', '/lib/', '/etc/')):
                    return f"{base_name}: {script_name}"
        
        # For regular binaries, just return the name
        return name
    
    def collect_top_processes(self):
        """Collect top processes by CPU and Memory"""
        if not self.config['metrics']['processes']:
            return None
        
        try:
            top_count = self.config['collection']['top_processes_count']
            cpu_count = psutil.cpu_count()  # For normalizing CPU percentages
            processes = []
            
            for proc in psutil.process_iter(['pid', 'name', 'username', 'cpu_percent', 
                                              'memory_info', 'memory_percent', 'status',
                                              'num_threads', 'nice', 'cpu_num', 'cmdline']):
                try:
                    info = proc.info
                    pid = info['pid']
                    
                    # Get I/O counters (may not be available on all systems)
                    try:
                        io = proc.io_counters()
                        # Calculate delta if we have previous values
                        if pid in self.prev_proc_io:
                            io_read = io.read_bytes - self.prev_proc_io[pid]['read']
                            io_write = io.write_bytes - self.prev_proc_io[pid]['write']
                        else:
                            io_read = io_write = 0
                        self.prev_proc_io[pid] = {'read': io.read_bytes, 'write': io.write_bytes}
                    except (psutil.AccessDenied, psutil.NoSuchProcess, AttributeError):
                        io_read = io_write = None
                    
                    # Get network I/O per process (if available)
                    # Note: This requires special permissions on most systems
                    net_sent = net_recv = None
                    
                    mem_info = info.get('memory_info')
                    
                    # Normalize CPU percentage to match htop/btop behavior
                    # psutil returns per-core percentage (0-100% per core)
                    # We want system-wide percentage (0-100% of total system)
                    raw_cpu = info.get('cpu_percent', 0.0)
                    normalized_cpu = raw_cpu / cpu_count if cpu_count > 0 else raw_cpu
                    
                    # Get smart process name from cmdline
                    base_name = info.get('name', 'Unknown')
                    cmdline = info.get('cmdline', [])
                    smart_name = self.get_smart_process_name(base_name, cmdline)
                    
                    processes.append({
                        'pid': pid,
                        'name': smart_name,
                        'username': info.get('username', 'Unknown'),
                        'cpu_percent': normalized_cpu,
                        'cpu_num': info.get('cpu_num'),
                        'mem_rss': mem_info.rss if mem_info else 0,
                        'mem_vms': mem_info.vms if mem_info else 0,
                        'mem_percent': info.get('memory_percent', 0.0),
                        'io_read': io_read,
                        'io_write': io_write,
                        'net_sent': net_sent,
                        'net_recv': net_recv,
                        'status': info.get('status', 'unknown'),
                        'num_threads': info.get('num_threads', 0),
                        'nice': info.get('nice', 0)
                    })
                except (psutil.NoSuchProcess, psutil.AccessDenied):
                    continue
            
            # Sort by CPU and Memory
            processes_by_cpu = sorted(processes, key=lambda x: x['cpu_percent'], reverse=True)
            processes_by_mem = sorted(processes, key=lambda x: x['mem_percent'], reverse=True)
            
            # Collect top 50 by CPU and top 50 by Memory separately
            # This ensures stable data for processes page (shows top 50 in each view)
            # Main dashboard will query top 10 CPU from this dataset
            collection_count = 50
            
            # Add rankings
            for rank, proc in enumerate(processes_by_cpu[:collection_count], 1):
                proc['rank_cpu'] = rank
            for rank, proc in enumerate(processes_by_mem[:collection_count], 1):
                proc['rank_mem'] = rank
            
            # Get union of top 50 CPU + top 50 memory (max ~100 processes)
            top_pids = set()
            top_processes = []
            
            for proc in processes_by_cpu[:collection_count]:
                if proc['pid'] not in top_pids:
                    top_processes.append(proc)
                    top_pids.add(proc['pid'])
            
            for proc in processes_by_mem[:collection_count]:
                if proc['pid'] not in top_pids:
                    top_processes.append(proc)
                    top_pids.add(proc['pid'])
            
            return top_processes
        except Exception as e:
            self.logger.error(f"Error collecting process metrics: {e}")
            return None
    
    def save_metrics(self, timestamp, metrics):
        """Save collected metrics to database"""
        if not self._ensure_db_connection():
            return  # DB is down, skip cycle and try again next interval
        try:
            cursor = self.db.cursor()
            
            # Save system metrics
            if metrics.get('cpu') and metrics.get('memory') and metrics.get('network') and metrics.get('disk_io'):
                cpu = metrics['cpu']
                mem = metrics['memory']
                net = metrics['network']
                disk = metrics['disk_io']
                
                # Sanitize percentage values to prevent DB errors
                # DECIMAL(5,2) can hold -999.99 to 999.99
                cpu_percent = self._clamp_value(cpu['cpu_percent'], 0, 999.99)
                mem_percent = self._clamp_value(mem['percent'], 0, 999.99)
                swap_percent = self._clamp_value(mem['swap_percent'], 0, 999.99)
                
                cursor.execute("""
                    INSERT INTO system_metrics (
                        timestamp, cpu_percent, cpu_count, load_1min, load_5min, load_15min,
                        mem_total, mem_available, mem_used, mem_percent,
                        swap_total, swap_used, swap_percent,
                        disk_read_bytes, disk_write_bytes, disk_read_count, disk_write_count,
                        net_bytes_sent, net_bytes_recv, net_packets_sent, net_packets_recv,
                        process_count, uptime_seconds
                    ) VALUES (
                        %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s
                    )
                """, (
                    timestamp, cpu_percent, cpu['cpu_count'], cpu['load_1'], cpu['load_5'], cpu['load_15'],
                    mem['total'], mem['available'], mem['used'], mem_percent,
                    mem['swap_total'], mem['swap_used'], swap_percent,
                    disk['read_bytes'], disk['write_bytes'], disk['read_count'], disk['write_count'],
                    net['bytes_sent'], net['bytes_recv'], net['packets_sent'], net['packets_recv'],
                    len(psutil.pids()), int(time.time() - psutil.boot_time())
                ))
            
            # Save per-core CPU usage
            if metrics.get('cpu') and metrics['cpu'].get('per_core'):
                for core_num, usage in enumerate(metrics['cpu']['per_core']):
                    # Clamp per-core usage too
                    usage_clamped = self._clamp_value(usage, 0, 999.99)
                    cursor.execute("""
                        INSERT INTO cpu_cores (timestamp, core_number, usage_percent)
                        VALUES (%s, %s, %s)
                    """, (timestamp, core_num, usage_clamped))
            
            # Save CPU temperatures
            if metrics.get('temps'):
                for temp in metrics['temps']:
                    cursor.execute("""
                        INSERT INTO cpu_temps (timestamp, sensor_label, temperature_celsius, 
                                              high_threshold, critical_threshold)
                        VALUES (%s, %s, %s, %s, %s)
                    """, (timestamp, temp['label'], temp['current'], temp['high'], temp['critical']))
            
            # Save NVIDIA GPU telemetry
            for gpu in metrics.get('gpu') or []:
                cursor.execute("""
                    INSERT INTO gpu_metrics (
                        timestamp, gpu_id, gpu_name, gpu_percent,
                        memory_used_mb, memory_total_mb, memory_percent,
                        temperature_celsius, power_watts
                    ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """, (
                    timestamp, gpu['gpu_id'], gpu['gpu_name'], gpu['gpu_percent'],
                    gpu['memory_used_mb'], gpu['memory_total_mb'], gpu['memory_percent'],
                    gpu['temperature_celsius'], gpu['power_watts'],
                ))

            # Save disk usage
            if metrics.get('disk_usage'):
                for partition in metrics['disk_usage']:
                    # Sanitize disk percentage
                    disk_percent = self._clamp_value(partition['percent'], 0, 999.99)
                    
                    cursor.execute("""
                        INSERT INTO disk_usage (timestamp, device, mount_point, fstype,
                                               total_bytes, used_bytes, free_bytes, percent_used)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                    """, (timestamp, partition['device'], partition['mountpoint'], partition['fstype'],
                          partition['total'], partition['used'], partition['free'], disk_percent))
            
            # Save network connections
            if metrics.get('connections'):
                conn = metrics['connections']
                cursor.execute("""
                    INSERT INTO network_connections (
                        timestamp, tcp_established, tcp_listen, tcp_time_wait, 
                        tcp_close_wait, tcp_other, udp_connections
                    ) VALUES (%s, %s, %s, %s, %s, %s, %s)
                """, (timestamp, conn['tcp_established'], conn['tcp_listen'], conn['tcp_time_wait'],
                      conn['tcp_close_wait'], conn['tcp_other'], conn['udp']))
            
            # Save top processes
            if metrics.get('processes'):
                # Build PID→tcp_conns lookup from the connection snapshot
                # collected this same cycle. None means no connection data at all
                # (metric disabled); 0 means collected but this PID had none.
                conn_data = metrics.get('connections') or {}
                tcp_by_pid = conn_data.get('per_pid', None)

                for proc in metrics['processes']:
                    # Sanitize percentage values for processes
                    proc_cpu = self._clamp_value(proc['cpu_percent'], 0, 999.99)
                    proc_mem = self._clamp_value(proc['mem_percent'], 0, 999.99)

                    # tcp_conns: None if connection collection is off,
                    # else the ESTABLISHED count for this PID (0 if not in map).
                    tcp_conns = tcp_by_pid.get(proc['pid'], 0) if tcp_by_pid is not None else None

                    cursor.execute("""
                        INSERT INTO process_snapshots (
                            timestamp, pid, name, username, cpu_percent, cpu_num,
                            mem_rss, mem_vms, mem_percent, io_read_bytes, io_write_bytes,
                            net_bytes_sent, net_bytes_recv, status, num_threads, nice,
                            rank_cpu, rank_mem, tcp_conns
                        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """, (timestamp, proc['pid'], proc['name'], proc['username'], proc_cpu, proc['cpu_num'],
                          proc['mem_rss'], proc['mem_vms'], proc_mem, proc['io_read'], proc['io_write'],
                          proc['net_sent'], proc['net_recv'], proc['status'], proc['num_threads'], proc['nice'],
                          proc.get('rank_cpu'), proc.get('rank_mem'), tcp_conns))
            
            # Update collector status (heartbeat)
            cursor.execute("""
                INSERT INTO collector_status (hostname, last_heartbeat, version, status)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                    last_heartbeat = VALUES(last_heartbeat),
                    version = VALUES(version),
                    status = VALUES(status)
            """, (self.hostname, timestamp, '1.0.0', 'running'))
            
            cursor.close()
            
        except Exception as e:
            self.logger.error(f"Error saving metrics to database: {e}")
    
    def _load_app_settings(self):
        """Load app_settings table into a plain dict.

        Returns defaults silently if the table doesn't exist yet (e.g. migration
        hasn't been run) so the collector never crashes on a missing table.
        """
        defaults = {
            'baseline_mode':       'hourly',
            'baseline_window_days': '30',
            'baseline_min_days':    '7',
            'sigma_elevated':       '1.0',
            'sigma_high':           '2.0',
            'sigma_low':            '1.5',
        }
        try:
            cursor = self.db.cursor()
            cursor.execute("SELECT setting_key, setting_value FROM app_settings")
            rows = cursor.fetchall()
            cursor.close()
            for row in rows:
                defaults[row['setting_key']] = row['setting_value']
        except Exception:
            pass  # Table not created yet — use defaults
        return defaults

    def _compute_baselines(self):
        """Compute statistical baselines for all monitored metrics.

        Runs hourly. Reads `metrics_5min` (and `temps_5min` for temperature)
        and writes mean / stddev / p05 / p95 into `metric_baselines`.

        Bucketing is controlled by the baseline_mode app setting:
          hourly      — 24 buckets (hour of day only). Same value written to
                        all 7 day_of_week slots so reads are always consistent.
          day_type    — 48 buckets (weekday 0-4 vs weekend 5-6, per hour).
          day_of_week — 168 buckets (full Mon-Sun x 24h).

        Day convention: 0=Monday … 6=Sunday (Python weekday()).
        """
        if not self._ensure_db_connection():
            return

        try:
            cfg          = self._load_app_settings()
            mode         = cfg.get('baseline_mode', 'hourly')
            window_days  = int(cfg.get('baseline_window_days', 30))
            min_samples  = 5   # Minimum readings per bucket to write a baseline

            self.logger.info(
                f"Computing baselines: mode={mode}, window={window_days}d"
            )

            # ------------------------------------------------------------------
            # Metric definitions: (metric_key, sql_column, source_table)
            # Temperature handled separately below (needs cross-sensor avg first)
            # ------------------------------------------------------------------
            # Use metrics_5min (30-day retention) as the baseline source.
            # metrics_1min only keeps 2 days (charts only) which is too short
            # for a meaningful baseline window. 5-min data gives 12 samples
            # per hour per day — statistically solid for hourly buckets.
            standard_metrics = [
                ('cpu_percent',  'cpu_percent_avg', 'metrics_5min'),
                ('mem_percent',  'mem_percent_avg', 'metrics_5min'),
                ('disk_io_write','disk_write_avg',  'metrics_5min'),
                ('disk_io_read', 'disk_read_avg',   'metrics_5min'),
                ('net_sent',     'net_sent_avg',    'metrics_5min'),
                ('net_recv',     'net_recv_avg',    'metrics_5min'),
            ]

            cursor = self.db.cursor()

            for metric_key, col, table in standard_metrics:
                self._compute_metric_baseline(
                    cursor, metric_key, col, table, mode, window_days, min_samples
                )

            # Temperature: average all sensors per 1-min bucket first, then baseline
            self._compute_temp_baseline(cursor, mode, window_days, min_samples)

            cursor.close()
            self.logger.info("Baseline computation complete")

        except Exception as e:
            self.logger.error(f"Error computing baselines: {e}")

    def _compute_metric_baseline(self, cursor, metric_key, col,
                                  table, mode, window_days, min_samples):
        """Fetch raw values for one metric and write baselines to metric_baselines."""

        # Pull every 1-min reading in the window, tagged with Python-convention
        # day_of_week (0=Mon) and hour_of_day.
        cursor.execute(f"""
            SELECT
                WEEKDAY(timestamp)  AS dow,
                HOUR(timestamp)     AS hod,
                {col}               AS val
            FROM   {table}
            WHERE  timestamp >= NOW() - INTERVAL %s DAY
              AND  {col} IS NOT NULL
        """, (window_days,))
        rows = cursor.fetchall()

        if not rows:
            return

        # Bucket raw values according to the active mode.
        # Keys are (day_of_week, hour_of_day); values are lists of floats.
        buckets = {}
        for r in rows:
            dow, hod, val = r['dow'], r['hod'], float(r['val'])
            if mode == 'hourly':
                # Collapse all days into a single hour slot (day=0 as placeholder)
                key = (0, hod)
            elif mode == 'day_type':
                # 0-4 = weekday, 5-6 = weekend
                day_slot = 0 if dow <= 4 else 5
                key = (day_slot, hod)
            else:  # day_of_week
                key = (dow, hod)
            buckets.setdefault(key, []).append(val)

        self._write_baselines(cursor, metric_key, buckets, mode, min_samples)

    def _compute_temp_baseline(self, cursor, mode, window_days, min_samples):
        """Compute baselines for temp_avg by averaging all sensors per 5-min bucket.
        Uses temps_5min (30-day retention) rather than temps_1min (7-day) so
        the full baseline_window_days window is always available.
        """

        # Collapse per-sensor rows into one avg-temp-per-minute reading
        cursor.execute("""
            SELECT
                WEEKDAY(timestamp)       AS dow,
                HOUR(timestamp)          AS hod,
                AVG(temp_avg)            AS val
            FROM   temps_5min
            WHERE  timestamp >= NOW() - INTERVAL %s DAY
            GROUP  BY timestamp, dow, hod
        """, (window_days,))
        rows = cursor.fetchall()

        if not rows:
            return

        buckets = {}
        for r in rows:
            dow, hod, val = r['dow'], r['hod'], float(r['val'])
            if mode == 'hourly':
                key = (0, hod)
            elif mode == 'day_type':
                day_slot = 0 if dow <= 4 else 5
                key = (day_slot, hod)
            else:
                key = (dow, hod)
            buckets.setdefault(key, []).append(val)

        self._write_baselines(cursor, 'temp_avg', buckets, mode, min_samples)

    def _write_baselines(self, cursor, metric_key, buckets, mode, min_samples):
        """Compute stats for each bucket and upsert into metric_baselines.

        For hourly / day_type modes, the computed value is replicated across
        all day_of_week slots so dashboard queries always use the same lookup:
            WHERE metric=? AND day_of_week=? AND hour_of_day=?
        """
        now = datetime.now()

        for (day_slot, hod), vals in buckets.items():
            if len(vals) < min_samples:
                continue

            mean   = statistics.mean(vals)
            stddev = statistics.pstdev(vals) if len(vals) >= 2 else 0.0
            sorted_vals = sorted(vals)
            n    = len(sorted_vals)
            p05  = sorted_vals[max(0, int(n * 0.05))]
            p95  = sorted_vals[min(n - 1, int(n * 0.95))]

            # Decide which day_of_week slots to write to
            if mode == 'hourly':
                target_days = range(7)          # all 7 days get same value
            elif mode == 'day_type':
                target_days = range(5) if day_slot == 0 else [5, 6]  # weekday or weekend
            else:
                target_days = [day_slot]        # exactly one day

            for dow in target_days:
                cursor.execute("""
                    INSERT INTO metric_baselines
                        (metric, day_of_week, hour_of_day,
                         mean, stddev, p05, p95, sample_count, computed_at)
                    VALUES
                        (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        mean         = VALUES(mean),
                        stddev       = VALUES(stddev),
                        p05          = VALUES(p05),
                        p95          = VALUES(p95),
                        sample_count = VALUES(sample_count),
                        computed_at  = VALUES(computed_at)
                """, (
                    metric_key, dow, hod,
                    round(mean, 4), round(stddev, 4),
                    round(p05, 4), round(p95, 4),
                    len(vals), now
                ))

    def run(self):
        """Main collection loop"""
        self.logger.info("Starting collection loop")
        interval = self.config['collection']['interval_seconds']
        disk_usage_interval = self.config['collection'].get('disk_usage_interval', interval)
        
        self.logger.info(f"Disk usage collection interval: {disk_usage_interval}s (vs {interval}s for other metrics)")
        
        while self.running:
            start_time = time.time()
            timestamp = datetime.now()
            
            self.logger.debug(f"Collecting metrics at {timestamp}")
            
            # Check if it's time to collect disk usage
            should_collect_disk = (time.time() - self.last_disk_collection) >= disk_usage_interval
            
            # Collect all metrics
            metrics = {
                'cpu': self.collect_cpu_metrics(),
                'memory': self.collect_memory_metrics(),
                'disk_io': self.collect_disk_io(),
                'disk_usage': self.collect_disk_usage() if should_collect_disk else None,
                'network': self.collect_network_io(),
                'connections': self.collect_network_connections(),
                'temps': self.collect_temperatures(),
                'gpu': self.collect_gpu_metrics(),
                'processes': self.collect_top_processes()
            }
            
            # Update last disk collection time if we collected it
            if should_collect_disk and metrics['disk_usage'] is not None:
                self.last_disk_collection = time.time()
            
            # Save to database
            self.save_metrics(timestamp, metrics)

            # ── Aggregation ───────────────────────────────────────────────
            # Each block mirrors the schedule of the old MariaDB events.
            # All jobs are non-blocking and swallow their own exceptions.

            now = time.time()

            # Every 1 min: raw → 1-min buckets
            if now - self.last_agg_1min >= 60:
                self.aggregator.aggregate_to_1min()
                self.aggregator.aggregate_gpu_to_1min()
                self.aggregator.aggregate_temps_to_1min()
                self.aggregator.aggregate_process_history_to_1min()
                self.last_agg_1min = now

            # Every 5 min: 1-min → 5-min buckets
            if now - self.last_agg_5min >= 300:
                self.aggregator.aggregate_to_5min()
                self.aggregator.aggregate_gpu_to_5min()
                self.aggregator.aggregate_temps_to_5min()
                self.aggregator.aggregate_process_history_to_5min()
                self.last_agg_5min = now

            # Every 1 hour: 5-min → 1-hour buckets
            if now - self.last_agg_1hour >= 3600:
                self.aggregator.aggregate_to_1hour()
                self.aggregator.aggregate_gpu_to_1hour()
                self.last_agg_1hour = now

            # Every 10 min: purge raw sample tables (keeps last 10 min)
            if now - self.last_cleanup_raw >= 600:
                self.aggregator.cleanup_raw_samples()
                self.last_cleanup_raw = now

            # Every 15 min: purge process snapshots (keeps last 30 min)
            if now - self.last_cleanup_proc >= 900:
                self.aggregator.cleanup_process_snapshots()
                self.last_cleanup_proc = now

            # Every 24 hrs: purge old aggregate data per retention policy
            if now - self.last_cleanup_agg >= 86400:
                self.aggregator.cleanup_aggregates()
                self.last_cleanup_agg = now
            # ─────────────────────────────────────────────────────────────

            # Compute baselines once per hour (non-blocking — skips quietly on error)
            baseline_interval = 3600  # seconds
            if (time.time() - self.last_baseline_compute) >= baseline_interval:
                self._compute_baselines()
                self.last_baseline_compute = time.time()

            # Process test email queue every cycle — keeps UI test button
            # responsive (~10s turnaround). Non-blocking: skips if check()
            # is mid-flight and retries next cycle.
            self.alert_checker.process_test_queue()

            # Run alert checks on configured interval — in a background thread
            # so a slow/hanging mail server NEVER blocks the collection loop.
            alert_interval = int(
                self.config.get('alerts', {}).get('check_interval_seconds', 300)
            )
            if (time.time() - self.last_alert_check) >= alert_interval:
                self.last_alert_check = time.time()   # stamp BEFORE launch
                t = threading.Thread(
                    target=self.alert_checker.check,
                    daemon=True,
                    name='alert-check',
                )
                t.start()

            # Calculate sleep time to maintain interval
            elapsed = time.time() - start_time
            sleep_time = max(0, interval - elapsed)
            
            if elapsed > interval:
                self.logger.warning(f"Collection took {elapsed:.2f}s (longer than interval {interval}s)")
            
            time.sleep(sleep_time)
        
        # Cleanup
        self.logger.info("Shutting down collector")
        self.alert_checker.close()
        if self.db:
            self.db.close()
        self.logger.info("Collector stopped")


def main():
    """Main entry point"""
    # Check for config file
    config_path = Path('config.yaml')
    if not config_path.exists():
        print("ERROR: config.yaml not found!")
        print("Please copy config.yaml.example to config.yaml and edit with your settings.")
        sys.exit(1)
    
    # Create and run collector
    collector = ResourceCollector(str(config_path))
    collector.run()


if __name__ == '__main__':
    main()
