"""
alert_checker.py  —  Resource Monitor server-side alert engine

Loaded by collector.py and called every N seconds (default 300).
All configuration (email addresses, thresholds, recipients) is read from
the database on every run — no collector restart needed for changes.

Alert categories
────────────────
  current     5-min average crosses a guard-rail floor or ceiling
  sustained   30-min average is >= sigma_high σ above baseline
  disk_full   Any disk >= disk_full_pct % used
  disk_change Free space on any disk changed > disk_change_pct% OR
              > disk_change_gb GB in the last hour (either direction)

Notification rules
──────────────────
  • Email sent on: new alert fires, alert resolves
  • Per-recipient: category subscriptions + minimum severity filter
  • All attempts logged to alert_notifications regardless of success
"""

import socket
import logging
import smtplib
import statistics
import threading
from datetime import datetime
from email.mime.text import MIMEText
from typing import Optional

import pymysql
from pymysql.cursors import DictCursor


class AlertChecker:
    """Runs alert checks against the DB and dispatches email notifications."""

    # Maps alert category name → alert_recipients column
    CATEGORY_COL = {
        'current':     'notify_current',
        'sustained':   'notify_sustained',
        'disk_full':   'notify_disk_full',
        'disk_change': 'notify_disk_change',
    }

    # metrics_1min columns we evaluate for guard-rail / sustained checks
    METRIC_MAP = [
        # (alert_rule metric key,  metrics_1min column,  display label,  bytes?)
        ('cpu_percent',   'cpu_percent_avg', 'CPU',              False),
        ('mem_percent',   'mem_percent_avg', 'Memory',           False),
        ('disk_io_write', 'disk_write_avg',  'Disk Write',       True),
        ('disk_io_read',  'disk_read_avg',   'Disk Read',        True),
        ('net_sent',      'net_sent_avg',    'Network Upload',   True),
        ('net_recv',      'net_recv_avg',    'Network Download', True),
    ]

    def __init__(self, config: dict, logger: logging.Logger):
        self.config = config
        self.logger = logger
        self.hostname = config.get('system', {}).get('hostname', 'auto')
        if self.hostname == 'auto':
            self.hostname = socket.gethostname()
        # Keep alert-check queries on a dedicated connection so the
        # background checker cannot interleave packets with collector writes.
        self._db: Optional[pymysql.connections.Connection] = None
        # check() and process_test_queue() may run on different threads.
        self._lock = threading.Lock()

    def _connect_db(self) -> None:
        db_config = self.config['database']
        options = {
            'user': db_config['user'],
            'password': db_config['password'],
            'database': db_config['database'],
            'charset': db_config['charset'],
            'cursorclass': DictCursor,
            'autocommit': True,
        }
        if db_config.get('unix_socket'):
            options['unix_socket'] = db_config['unix_socket']
        else:
            options['host'] = db_config['host']
            options['port'] = db_config['port']
        self._db = pymysql.connect(**options)
        self.logger.info("AlertChecker opened a dedicated database connection")

    def _ensure_db(self) -> None:
        if self._db is None:
            self._connect_db()
            return
        try:
            self._db.ping(reconnect=True)
        except Exception:
            try:
                self._db.close()
            except Exception:
                pass
            self._db = None
            self._connect_db()

    @property
    def db(self) -> pymysql.connections.Connection:
        "Return the alert checker own live DB connection."
        self._ensure_db()
        return self._db

    def close(self) -> None:
        "Close the dedicated connection after alert work completes."
        with self._lock:
            if self._db is not None:
                self._db.close()
                self._db = None

    # Public entry point
    # ═══════════════════════════════════════════════════════════════════════

    def check(self) -> None:
        """Run all checks, update state, send emails. Swallows top-level errors."""
        with self._lock:
          try:
            cfg        = self._load_settings()
            recipients = self._load_recipients()

            # Gather all currently-firing alerts
            firing = {}
            firing.update(self._check_guardrails(cfg))
            firing.update(self._check_sustained(cfg))
            firing.update(self._check_disk_full(cfg))
            firing.update(self._check_disk_change(cfg))

            # Reconcile with stored state and send emails
            self._process_state(firing, recipients, cfg)

          except Exception as exc:
            self.logger.error(f"AlertChecker.check() failed: {exc}", exc_info=True)

    # ═══════════════════════════════════════════════════════════════════════
    # Configuration loaders
    # ═══════════════════════════════════════════════════════════════════════

    def _load_settings(self) -> dict:
        defaults = {
            'sigma_high':              2.0,
            'baseline_min_days':       7,
            'disk_change_pct':         5.0,
            'disk_change_gb':          50.0,
            'disk_full_pct':           90.0,
            'email_from':              'monitor@localhost',
            'email_method':            'mail',
            # How long a metric must be elevated before a sustained alert fires.
            # Both are configurable in Settings → Intelligence.
            'sustained_minutes':       60,   # CPU, mem, network, disk I/O
            'temp_sustained_minutes':  15,   # Temperature (sooner is safer)
            'temp_unit':               'F',  # Display unit for alert messages (C or F)
            'alert_cooldown_hours':    4,    # Hours before a resolved alert can re-fire
        }
        try:
            rows = self._query("SELECT setting_key, setting_value FROM app_settings")
            for r in rows:
                k, v = r['setting_key'], r['setting_value']
                try:
                    defaults[k] = float(v) if '.' in str(v) else int(v)
                except (ValueError, TypeError):
                    defaults[k] = v
        except Exception as exc:
            self.logger.warning(f"Could not load app_settings: {exc}")
        return defaults

    def _fmt_temp(self, celsius: float, cfg: dict) -> str:
        """Format a Celsius value in the user's preferred unit (C or F)."""
        unit = cfg.get('temp_unit', 'F')
        if str(unit).upper() == 'F':
            return f"{(celsius * 9/5) + 32:.1f}\u00b0F"
        return f"{celsius:.1f}\u00b0C"

    def _load_recipients(self) -> list[dict]:
        try:
            return self._query(
                "SELECT * FROM alert_recipients WHERE enabled = 1 ORDER BY id"
            )
        except Exception as exc:
            self.logger.warning(f"Could not load alert_recipients: {exc}")
            return []

    # ═══════════════════════════════════════════════════════════════════════
    # Alert checks — each returns {alert_id: alert_dict}
    # ═══════════════════════════════════════════════════════════════════════

    def _check_guardrails(self, cfg: dict) -> dict:
        """5-minute average vs configured guard-rail floor / ceiling."""
        firing = {}
        try:
            cols = ', '.join(m[1] for m in self.METRIC_MAP)
            rows = self._query(
                f"SELECT {cols} FROM metrics_1min ORDER BY timestamp DESC LIMIT 5"
            )
            if not rows:
                return firing

            rules = {r['metric']: r for r in self._query(
                "SELECT metric, guard_rail_floor, guard_rail_ceiling "
                "FROM alert_rules WHERE enabled = 1"
            )}

            for metric_key, col, label, is_bytes in self.METRIC_MAP:
                vals = [float(r[col]) for r in rows if r[col] is not None]
                if not vals:
                    continue
                avg  = statistics.mean(vals)
                rule = rules.get(metric_key)
                if not rule:
                    continue

                fval = self._fmt_bytes(avg) if is_bytes else f"{avg:.1f}%"

                ceiling = rule.get('guard_rail_ceiling')
                floor_  = rule.get('guard_rail_floor')

                if ceiling is not None and avg >= float(ceiling):
                    fc  = self._fmt_bytes(float(ceiling)) if is_bytes else f"{float(ceiling):.1f}%"
                    aid = f"current_{metric_key}_critical"
                    firing[aid] = {
                        'alert_id': aid, 'category': 'current', 'severity': 'critical',
                        'title':   f"⚠ {label} Critical",
                        'message': f"{label} 5-min avg is {fval} — above critical ceiling of {fc}.",
                    }
                elif floor_ is not None and avg >= float(floor_):
                    ff  = self._fmt_bytes(float(floor_)) if is_bytes else f"{float(floor_):.1f}%"
                    aid = f"current_{metric_key}_warning"
                    firing[aid] = {
                        'alert_id': aid, 'category': 'current', 'severity': 'warning',
                        'title':   f"⚠ {label} Elevated",
                        'message': f"{label} 5-min avg is {fval} — above warning floor of {ff}.",
                    }

            # Temperature (temps_1min)
            temp_rule = rules.get('temp_avg')
            if temp_rule:
                tr = self._query(
                    "SELECT AVG(temp_avg) AS t FROM temps_1min "
                    "WHERE timestamp >= NOW() - INTERVAL 5 MINUTE"
                )
                t = tr[0]['t'] if tr and tr[0]['t'] is not None else None
                if t is not None:
                    t = float(t)
                    ceiling = temp_rule.get('guard_rail_ceiling')
                    floor_  = temp_rule.get('guard_rail_floor')
                    if ceiling is not None and t >= float(ceiling):
                        firing['current_temp_critical'] = {
                            'alert_id': 'current_temp_critical',
                            'category': 'current', 'severity': 'critical',
                            'title':   '🌡 Temperature Critical',
                            'message': f"Avg CPU temp is {self._fmt_temp(t, cfg)} — above critical ceiling of {self._fmt_temp(float(ceiling), cfg)}.",
                        }
                    elif floor_ is not None and t >= float(floor_):
                        firing['current_temp_warning'] = {
                            'alert_id': 'current_temp_warning',
                            'category': 'current', 'severity': 'warning',
                            'title':   '🌡 Temperature Elevated',
                            'message': f"Avg CPU temp is {self._fmt_temp(t, cfg)} — above warning floor of {self._fmt_temp(float(floor_), cfg)}.",
                        }
        except Exception as exc:
            self.logger.error(f"_check_guardrails: {exc}", exc_info=True)
        return firing

    def _check_sustained(self, cfg: dict) -> dict:
        """
        N-minute average vs baseline mean+stddev.  Only when enough history exists.

        Windows (configurable in Settings → Intelligence):
          sustained_minutes       — CPU, memory, network, disk I/O  (default 60)
          temp_sustained_minutes  — Temperature                      (default 15)
        """
        firing = {}
        try:
            # Enough historical data?
            r = self._query(
                # Use metrics_5min (30d retention) — metrics_1min only keeps 2d
            # (charts only) and can never reach baseline_min_days.
            "SELECT DATEDIFF(NOW(), MIN(timestamp)) AS d FROM metrics_5min"
            )
            data_days = int(r[0]['d'] or 0) if r else 0
            if data_days < int(cfg['baseline_min_days']):
                return firing

            dow  = datetime.now().weekday()          # 0=Mon
            hour = datetime.now().hour
            sigma_high = float(cfg['sigma_high'])

            # Configurable windows
            sustained_min      = max(10, int(cfg.get('sustained_minutes',      60)))
            temp_sustained_min = max(5,  int(cfg.get('temp_sustained_minutes', 15)))

            baselines = {b['metric']: b for b in self._query(
                "SELECT metric, mean, stddev FROM metric_baselines "
                "WHERE day_of_week = %s AND hour_of_day = %s",
                (dow, hour)
            )}

            # ── Main metrics (CPU, mem, network, disk I/O) ─────────────────
            cols = ', '.join(m[1] for m in self.METRIC_MAP)
            rows = self._query(
                f"SELECT {cols} FROM metrics_1min "
                f"ORDER BY timestamp DESC LIMIT {sustained_min}"
            )
            min_rows = max(5, sustained_min // 2)   # need at least half the window
            if len(rows) < min_rows:
                return firing

            for metric_key, col, label, is_bytes in self.METRIC_MAP:
                bl = baselines.get(metric_key)
                if not bl or bl['mean'] is None:
                    continue
                stddev = float(bl['stddev'] or 0)
                if stddev < 0.001:
                    continue

                vals = [float(r[col]) for r in rows if r[col] is not None]
                if len(vals) < min_rows:
                    continue

                avg    = statistics.mean(vals)
                mean   = float(bl['mean'])
                sigmas = (avg - mean) / stddev
                favg   = self._fmt_bytes(avg)   if is_bytes else f"{avg:.1f}%"
                fmean  = self._fmt_bytes(mean)  if is_bytes else f"{mean:.1f}%"
                fsig   = f"{sigmas:.1f}"

                if sigmas >= sigma_high * 1.5:
                    aid = f"sustained_{metric_key}"
                    firing[aid] = {
                        'alert_id': aid, 'category': 'sustained', 'severity': 'critical',
                        'title':   f"📈 {label} Sustained Critical",
                        'message': f"{label} averaged {favg} over {sustained_min} min "
                                   f"({fsig}σ above baseline of {fmean}).",
                    }
                elif sigmas >= sigma_high:
                    aid = f"sustained_{metric_key}"
                    firing[aid] = {
                        'alert_id': aid, 'category': 'sustained', 'severity': 'warning',
                        'title':   f"📈 {label} Sustained High",
                        'message': f"{label} averaged {favg} over {sustained_min} min "
                                   f"({fsig}σ above baseline of {fmean}).",
                    }

            # ── Temperature sustained (shorter window — thermal issues matter sooner) ──
            tbl = baselines.get('temp_avg')
            if tbl and tbl['mean'] is not None and float(tbl['stddev'] or 0) > 0.001:
                tr = self._query(
                    "SELECT AVG(temp_avg) AS t FROM temps_1min "
                    f"WHERE timestamp >= NOW() - INTERVAL {temp_sustained_min} MINUTE"
                )
                tavg = float(tr[0]['t']) if tr and tr[0]['t'] is not None else None
                if tavg is not None:
                    mean   = float(tbl['mean'])
                    stddev = float(tbl['stddev'])
                    sigmas = (tavg - mean) / stddev
                    if sigmas >= sigma_high * 1.5:
                        firing['sustained_temp'] = {
                            'alert_id': 'sustained_temp', 'category': 'sustained',
                            'severity': 'critical',
                            'title':   '🌡 Temperature Sustained Critical',
                            'message': f"CPU temps averaged {self._fmt_temp(tavg, cfg)} over "
                                       f"{temp_sustained_min} min ({sigmas:.1f}\u03c3 above normal).",
                        }
                    elif sigmas >= sigma_high:
                        firing['sustained_temp'] = {
                            'alert_id': 'sustained_temp', 'category': 'sustained',
                            'severity': 'warning',
                            'title':   '\U0001f321 Temperature Sustained High',
                            'message': f"CPU temps averaged {self._fmt_temp(tavg, cfg)} over "
                                       f"{temp_sustained_min} min ({sigmas:.1f}\u03c3 above normal).",
                        }
        except Exception as exc:
            self.logger.error(f"_check_sustained: {exc}", exc_info=True)
        return firing

    def _check_disk_full(self, cfg: dict) -> dict:
        """Alert when any disk exceeds disk_full_pct % used."""
        firing   = {}
        full_pct = float(cfg['disk_full_pct'])
        crit_pct = min(99.0, full_pct + 5.0)
        try:
            disks = self._query("""
                SELECT d.mount_point, d.free_bytes, d.percent_used
                FROM   disk_usage d
                INNER JOIN (
                    SELECT mount_point, MAX(timestamp) AS ts
                    FROM   disk_usage GROUP BY mount_point
                ) m ON d.mount_point = m.mount_point AND d.timestamp = m.ts
            """)
            for disk in disks:
                mount    = disk['mount_point']
                pct_used = float(disk['percent_used'])
                free_now = float(disk['free_bytes'])
                mid      = 'disk_' + self._safe_id(mount)

                if pct_used >= crit_pct:
                    aid = f"full_{mid}"
                    firing[aid] = {
                        'alert_id': aid, 'category': 'disk_full', 'severity': 'critical',
                        'title':   f"💾 Disk Critical: {mount}",
                        'message': f"{mount} is {pct_used:.1f}% full — only {self._fmt_free(free_now)} remaining.",
                    }
                elif pct_used >= full_pct:
                    aid = f"full_{mid}"
                    firing[aid] = {
                        'alert_id': aid, 'category': 'disk_full', 'severity': 'warning',
                        'title':   f"💾 Disk Nearly Full: {mount}",
                        'message': f"{mount} is {pct_used:.1f}% full — only {self._fmt_free(free_now)} remaining.",
                    }
        except Exception as exc:
            self.logger.error(f"_check_disk_full: {exc}", exc_info=True)
        return firing

    def _check_disk_change(self, cfg: dict) -> dict:
        """Alert when free space changes > pct% OR > GB in either direction in 1 hr."""
        firing    = {}
        chg_pct   = float(cfg['disk_change_pct'])
        chg_bytes = float(cfg['disk_change_gb']) * 1_073_741_824
        try:
            latest = self._query("""
                SELECT d.mount_point, d.free_bytes, d.total_bytes
                FROM   disk_usage d
                INNER JOIN (
                    SELECT mount_point, MAX(timestamp) AS ts
                    FROM   disk_usage GROUP BY mount_point
                ) m ON d.mount_point = m.mount_point AND d.timestamp = m.ts
            """)
            for disk in latest:
                mount    = disk['mount_point']
                free_now = float(disk['free_bytes'])
                total    = float(disk['total_bytes'])
                if total <= 0:
                    continue

                old = self._query(
                    "SELECT free_bytes FROM disk_usage "
                    "WHERE mount_point = %s AND timestamp <= NOW() - INTERVAL 55 MINUTE "
                    "ORDER BY timestamp DESC LIMIT 1",
                    (mount,)
                )
                if not old:
                    continue

                free_old     = float(old[0]['free_bytes'])
                delta_bytes  = free_old - free_now        # +ve = lost free space
                abs_bytes    = abs(delta_bytes)
                delta_pct    = abs_bytes / total * 100
                delta_gb     = abs_bytes / 1_073_741_824
                direction    = 'lost' if delta_bytes > 0 else 'gained'
                dir_icon     = '📉' if delta_bytes > 0 else '📈'
                dir_label    = 'Disk Filling' if delta_bytes > 0 else 'Disk Space Freed'
                amount       = self._fmt_free(abs_bytes)
                mid          = self._safe_id(mount)

                if delta_pct >= chg_pct or abs_bytes >= chg_bytes:
                    severity = (
                        'critical'
                        if (delta_pct >= chg_pct * 2 or abs_bytes >= chg_bytes * 2)
                        else 'warning'
                    )
                    aid = f"change_{mid}"
                    firing[aid] = {
                        'alert_id': aid, 'category': 'disk_change', 'severity': severity,
                        'title':   f"{dir_icon} {dir_label}: {mount}",
                        'message': (
                            f"{mount} {direction} {delta_pct:.1f}% ({amount}) "
                            f"of free space in the last hour."
                        ),
                    }
        except Exception as exc:
            self.logger.error(f"_check_disk_change: {exc}", exc_info=True)
        return firing

    # ═══════════════════════════════════════════════════════════════════════
    # State machine + email dispatch
    # ═══════════════════════════════════════════════════════════════════════

    def _process_state(self, firing: dict, recipients: list, cfg: dict) -> None:
        """
        Compare firing alerts against stored alert_state.
        • New alert   → INSERT state, email recipients
        • Still active → UPDATE last_fired_at (no email)
        • Resolved    → UPDATE is_active=0, email recipients
        """
        try:
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

            # Load currently-active alerts from DB
            active_rows = self._query(
                "SELECT alert_id FROM alert_state WHERE is_active = 1"
            )
            active_ids = {r['alert_id'] for r in active_rows}

            # ── New alerts ───────────────────────────────────────────────────
            cooldown_hours = int(cfg.get('alert_cooldown_hours', 4))
            for aid, alert in firing.items():
                if aid not in active_ids:
                    # Cooldown check — don't re-fire if recently resolved
                    cooling = self._query(
                        "SELECT resolved_at FROM alert_state "
                        "WHERE alert_id = %s AND is_active = 0 "
                        "AND resolved_at > NOW() - INTERVAL %s HOUR",
                        (aid, cooldown_hours)
                    )
                    if cooling:
                        self.logger.debug(
                            f"Alert {aid} suppressed — in {cooldown_hours}h cooldown "
                            f"(resolved at {cooling[0]['resolved_at']})"
                        )
                        continue
                    self._exec("""
                        INSERT INTO alert_state
                            (alert_id, category, severity, title, message,
                             first_fired_at, last_fired_at, is_active)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, 1)
                        ON DUPLICATE KEY UPDATE
                            severity       = VALUES(severity),
                            title          = VALUES(title),
                            message        = VALUES(message),
                            last_fired_at  = VALUES(last_fired_at),
                            resolved_at    = NULL,
                            is_active      = 1
                    """, (
                        aid, alert['category'], alert['severity'],
                        alert['title'], alert['message'], now, now,
                    ))
                    self._notify_recipients(alert, 'fired', recipients, cfg)
                else:
                    # Still firing — just touch last_fired_at
                    self._exec(
                        "UPDATE alert_state SET last_fired_at = %s, "
                        "severity = %s, message = %s WHERE alert_id = %s",
                        (now, alert['severity'], alert['message'], aid)
                    )

            # ── Resolved alerts ──────────────────────────────────────────────
            for aid in active_ids - set(firing.keys()):
                row = self._query(
                    "SELECT * FROM alert_state WHERE alert_id = %s", (aid,)
                )
                if not row:
                    continue
                self._exec(
                    "UPDATE alert_state SET is_active = 0, resolved_at = %s WHERE alert_id = %s",
                    (now, aid)
                )
                self._notify_recipients(row[0], 'resolved', recipients, cfg)

        except Exception as exc:
            self.logger.error(f"_process_state: {exc}", exc_info=True)

    def _notify_recipients(self, alert: dict, notif_type: str,
                           recipients: list, cfg: dict) -> None:
        """Send email to each eligible recipient and log the attempt."""
        severity     = alert.get('severity', 'info')
        category     = alert.get('category', '')
        cat_col      = self.CATEGORY_COL.get(category)
        email_from   = str(cfg.get('email_from', 'monitor@localhost'))
        sev_order    = {'warning': 1, 'critical': 2}
        alert_level  = sev_order.get(severity, 0)

        for rec in recipients:
            # Category subscription check
            if cat_col and not rec.get(cat_col):
                continue
            # Minimum severity check
            min_sev = sev_order.get(rec.get('min_severity', 'warning'), 1)
            if alert_level < min_sev and notif_type == 'fired':
                continue

            success, err = self._send_email(
                to=rec['email'],
                email_from=email_from,
                alert=alert,
                notif_type=notif_type,
            )
            self._log_notification(
                alert_id=alert.get('alert_id', 'unknown'),
                recipient_id=rec['id'],
                notif_type=notif_type,
                severity=severity,
                title=alert.get('title', ''),
                message=alert.get('message', ''),
                success=success,
                error=err,
            )

    def _send_email(self, to: str, email_from: str, alert: dict,
                    notif_type: str) -> tuple[bool, Optional[str]]:
        """Send via smtplib direct to localhost:25. No mail binary dependency."""
        severity   = alert.get('severity', 'info').upper()
        title      = alert.get('title', 'Resource Monitor Alert')
        message    = alert.get('message', '')
        alert_id   = alert.get('alert_id', '')
        now        = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        if notif_type == 'resolved':
            subject = f"[RESOLVED] {title} \u2014 {self.hostname}"
            status  = '\u2713 RESOLVED'
        elif notif_type == 'test':
            subject = f"[TEST] {title} \u2014 {self.hostname}"
            status  = '\U0001f514 TEST'
        else:
            subject = f"[{severity}] {title} \u2014 {self.hostname}"
            status  = f'\u25cf {severity}'

        body = "\n".join([
            f"Resource Monitor \u2014 {notif_type.upper()}",
            "\u2500" * 50,
            "",
            f"Status  : {status}",
            f"Alert   : {title}",
            f"Detail  : {message}",
            "",
            f"Server  : {self.hostname}",
            f"Time    : {now}",
            f"Alert ID: {alert_id}",
            "",
            "\u2500" * 50,
            f"Resource Monitor  |  {self.hostname}",
        ])

        try:
            msg = MIMEText(body, 'plain', 'utf-8')
            msg['Subject'] = subject
            msg['From']    = email_from
            msg['To']      = to

            with smtplib.SMTP('127.0.0.1', 25, timeout=10) as smtp:
                smtp.sendmail(email_from, [to], msg.as_string())

            self.logger.info(f"Alert email ({notif_type}) sent to {to}: {title}")
            return True, None
        except Exception as exc:
            self.logger.error(f"_send_email to {to}: {exc}")
            return False, str(exc)

    def _log_notification(self, alert_id: str, recipient_id: int,
                          notif_type: str, severity: str,
                          title: str, message: str,
                          success: bool, error: Optional[str]) -> None:
        try:
            self._exec("""
                INSERT INTO alert_notifications
                    (alert_id, recipient_id, notification_type,
                     severity, title, message, channel, success, error_message)
                VALUES (%s, %s, %s, %s, %s, %s, 'email', %s, %s)
            """, (alert_id, recipient_id, notif_type, severity,
                  title, message, 1 if success else 0, error))
        except Exception as exc:
            self.logger.warning(f"_log_notification: {exc}")

    # ═══════════════════════════════════════════════════════════════════════
    # Test email queue (called every collector cycle from main loop)
    # ═══════════════════════════════════════════════════════════════════════

    def process_test_queue(self) -> None:
        """
        Pick up pending test-email requests queued by the web UI and send
        them via smtplib — the same path as real alerts.

        Uses a non-blocking lock acquire so if check() is mid-flight we
        simply skip this cycle and try again in 10 seconds.
        """
        if not self._lock.acquire(blocking=False):
            return   # check() is running; try next cycle
        try:
            pending = self._query("""
                SELECT q.id, q.recipient_id, q.requested_at,
                       r.email, r.display_name
                FROM   alert_test_queue q
                JOIN   alert_recipients r ON r.id = q.recipient_id
                WHERE  q.status = 'pending'
                ORDER  BY q.requested_at
                LIMIT  10
            """)
            if not pending:
                return

            cfg        = self._load_settings()
            email_from = str(cfg.get('email_from', 'noreply@localhost'))

            for row in pending:
                qid = row['id']
                # Claim the row (prevents duplicate processing if ever called
                # concurrently despite the lock guard)
                self._exec(
                    "UPDATE alert_test_queue "
                    "SET status='sending', picked_up_at=NOW() "
                    "WHERE id=%s AND status='pending'",
                    (qid,)
                )

                name_part = f" ({row['display_name']})" if row['display_name'] else ''
                alert = {
                    'alert_id': f"test_{qid}",
                    'category': 'test',
                    'severity': 'info',
                    'title':   'Resource Monitor — Test Email',
                    'message': (
                        f"This is a test email sent by the collector process.\n"
                        f"If you received this, the collector → Postfix path is working.\n\n"
                        f"Recipient : {row['email']}{name_part}"
                    ),
                }

                success, err = self._send_email(
                    to=row['email'],
                    email_from=email_from,
                    alert=alert,
                    notif_type='test',
                )

                status = 'success' if success else 'failed'
                self._exec(
                    "UPDATE alert_test_queue "
                    "SET status=%s, error_message=%s, completed_at=NOW() "
                    "WHERE id=%s",
                    (status, err, qid)
                )
                self._log_notification(
                    alert_id=f"test_{qid}",
                    recipient_id=int(row['recipient_id']),
                    notif_type='test',
                    severity='info',
                    title='Test Email',
                    message=f"Test to {row['email']}",
                    success=success,
                    error=err,
                )
                self.logger.info(
                    f"Test queue id={qid} → {status}"
                    + (f": {err}" if err else "")
                )

            # Housekeeping: remove completed rows older than 1 hour
            self._exec(
                "DELETE FROM alert_test_queue "
                "WHERE status IN ('success','failed') "
                "AND completed_at < NOW() - INTERVAL 1 HOUR"
            )
        except Exception as exc:
            self.logger.error(f"process_test_queue: {exc}", exc_info=True)
        finally:
            self._lock.release()

    # ═══════════════════════════════════════════════════════════════════════
    # DB helpers
    # ═══════════════════════════════════════════════════════════════════════

    def _query(self, sql: str, params: tuple = ()) -> list[dict]:
        with self.db.cursor(DictCursor) as cur:
            cur.execute(sql, params)
            return cur.fetchall()

    def _exec(self, sql: str, params: tuple = ()) -> None:
        with self.db.cursor() as cur:
            cur.execute(sql, params)
        self.db.commit()

    # ═══════════════════════════════════════════════════════════════════════
    # Formatting helpers
    # ═══════════════════════════════════════════════════════════════════════

    @staticmethod
    def _fmt_bytes(b: float) -> str:
        if b >= 1_073_741_824: return f"{b / 1_073_741_824:.2f} GB/s"
        if b >= 1_048_576:     return f"{b / 1_048_576:.2f} MB/s"
        if b >= 1_024:         return f"{b / 1_024:.1f} KB/s"
        return f"{b:.0f} B/s"

    @staticmethod
    def _fmt_free(b: float) -> str:
        if b >= 1_073_741_824: return f"{b / 1_073_741_824:.2f} GB"
        if b >= 1_048_576:     return f"{b / 1_048_576:.2f} MB"
        return f"{b / 1_024:.1f} KB"

    @staticmethod
    def _safe_id(s: str) -> str:
        import re
        return re.sub(r'[^a-zA-Z0-9]', '_', s)
