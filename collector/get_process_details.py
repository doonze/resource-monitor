#!/usr/bin/env python3
"""
Get detailed process information by PID
Returns JSON with process details or error
"""

import sys
import json
import psutil
from datetime import datetime

def get_process_details(pid):
    """Get detailed information about a process by PID"""
    try:
        proc = psutil.Process(pid)
        
        # Get process info with exception handling for each field
        details = {}
        
        # Basic info
        try:
            details['pid'] = proc.pid
        except:
            details['pid'] = pid
        
        try:
            details['name'] = proc.name()
        except:
            details['name'] = 'Unknown'
        
        try:
            details['status'] = proc.status()
        except:
            details['status'] = 'unknown'
        
        # Command line and executable
        try:
            cmdline = proc.cmdline()
            details['cmdline'] = ' '.join(cmdline) if cmdline else ''
            details['cmdline_list'] = cmdline
        except (psutil.AccessDenied, psutil.NoSuchProcess):
            details['cmdline'] = 'Access Denied'
            details['cmdline_list'] = []
        
        try:
            details['exe'] = proc.exe()
        except (psutil.AccessDenied, psutil.NoSuchProcess):
            details['exe'] = None
        
        try:
            details['cwd'] = proc.cwd()
        except (psutil.AccessDenied, psutil.NoSuchProcess):
            details['cwd'] = None
        
        # Timing info
        try:
            create_time = proc.create_time()
            details['create_time'] = create_time
            details['create_time_formatted'] = datetime.fromtimestamp(create_time).strftime('%Y-%m-%d %H:%M:%S')
            
            # Calculate running time
            running_seconds = datetime.now().timestamp() - create_time
            hours = int(running_seconds // 3600)
            minutes = int((running_seconds % 3600) // 60)
            seconds = int(running_seconds % 60)
            
            if hours > 0:
                details['running_time'] = f"{hours}h {minutes}m"
            elif minutes > 0:
                details['running_time'] = f"{minutes}m {seconds}s"
            else:
                details['running_time'] = f"{seconds}s"
        except:
            details['create_time'] = None
            details['create_time_formatted'] = None
            details['running_time'] = None
        
        # User info
        try:
            details['username'] = proc.username()
        except (psutil.AccessDenied, psutil.NoSuchProcess):
            details['username'] = 'Unknown'
        
        # Resource usage
        try:
            details['cpu_percent'] = proc.cpu_percent(interval=0.1)
        except:
            details['cpu_percent'] = 0.0
        
        try:
            mem_info = proc.memory_info()
            details['memory_rss'] = mem_info.rss
            details['memory_rss_mb'] = mem_info.rss / 1024 / 1024
            details['memory_vms'] = mem_info.vms
            details['memory_percent'] = proc.memory_percent()
        except:
            details['memory_rss'] = 0
            details['memory_rss_mb'] = 0
            details['memory_vms'] = 0
            details['memory_percent'] = 0.0
        
        # Process details
        try:
            details['num_threads'] = proc.num_threads()
        except:
            details['num_threads'] = 0
        
        try:
            details['nice'] = proc.nice()
        except:
            details['nice'] = 0
        
        # Parent process
        try:
            parent = proc.parent()
            if parent:
                details['parent_pid'] = parent.pid
                details['parent_name'] = parent.name()
            else:
                details['parent_pid'] = None
                details['parent_name'] = None
        except:
            details['parent_pid'] = None
            details['parent_name'] = None
        
        # Open files count
        try:
            details['open_files_count'] = len(proc.open_files())
        except (psutil.AccessDenied, psutil.NoSuchProcess):
            details['open_files_count'] = None
        
        # Network connections count
        try:
            details['connections_count'] = len(proc.connections())
        except (psutil.AccessDenied, psutil.NoSuchProcess):
            details['connections_count'] = None
        
        return details
        
    except psutil.NoSuchProcess:
        return {'error': 'Process no longer running', 'code': 404}
    except psutil.AccessDenied:
        return {'error': 'Access denied to process', 'code': 403}
    except Exception as e:
        return {'error': f'Error getting process details: {str(e)}', 'code': 500}

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'PID required', 'code': 400}))
        sys.exit(1)
    
    try:
        pid = int(sys.argv[1])
    except ValueError:
        print(json.dumps({'error': 'Invalid PID', 'code': 400}))
        sys.exit(1)
    
    result = get_process_details(pid)
    print(json.dumps(result, indent=2))
