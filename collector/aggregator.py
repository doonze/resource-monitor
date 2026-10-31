"""
Resource Monitor — Aggregation Manager

Replicates the logic of the MariaDB stored procedures + scheduled events,
allowing the DB events to be cleanly dropped (v001 migration) while keeping
identical aggregation behaviour.

Aggregation schedule (mirrors original MariaDB events):
  Every   1 min  → aggregate_to_1min, aggregate_temps_to_1min,
                    aggregate_process_history_to_1min
  Every   5 min  → aggregate_to_5min, aggregate_temps_to_5min,
                    aggregate_process_history_to_5min
  Every   1 hour → aggregate_to_1hour
  Every  10 min  → cleanup_raw_samples       (was: cleanup_old_samples event)
  Every  15 min  → cleanup_process_snapshots (was: cleanup_processes_15min event)
  Every   1 day  → cleanup_aggregates        (was: cleanup_old_aggregates event)

Retention policy (unchanged from procedures):
  system_metrics / cpu_cores / cpu_temps /
    disk_usage / network_connections       →  10 minutes  (raw samples)
  process_snapshots                        →  30 minutes
  metrics_1min / temps_1min               →   2d / 7d
  metrics_5min                            →  30 days
  metrics_1hour / temps_5min              →   1 year
  process_history_1min                    →   7 days
  process_history_5min                    →  30 days
"""


class Aggregator:
    """
    Runs all time-series aggregation and cleanup that was previously
    handled by MariaDB stored procedures and scheduled events.

    The Aggregator stores a reference to the parent ResourceCollector so
    it always uses the collector's *current* live DB connection — picking
    up reconnections transparently without needing a restart.
    """

    def __init__(self, collector):
        """
        Args:
            collector: The parent ResourceCollector instance.
        """
        self._collector = collector
        self.logger = collector.logger

    # ------------------------------------------------------------------ #
    #  Internal helpers                                                    #
    # ------------------------------------------------------------------ #

    @property
    def _db(self):
        """Always returns the collector's current live DB connection."""
        return self._collector.db

    def _run(self, sql: str, label: str) -> None:
        """
        Execute one SQL statement, commit, log affected rows.
        Swallows exceptions so one bad aggregation never kills the loop.
        """
        try:
            cursor = self._db.cursor()
            cursor.execute(sql)
            rows = cursor.rowcount
            self._db.commit()
            if rows > 0:
                self.logger.debug(f"[aggregator] {label}: {rows} row(s) affected")
        except Exception as exc:
            self.logger.error(f"[aggregator] {label} failed: {exc}")

    # ------------------------------------------------------------------ #
    #  Aggregation — metrics                                               #
    # ------------------------------------------------------------------ #

    def aggregate_to_1min(self) -> None:
        """
        Aggregate system_metrics → metrics_1min (1-minute buckets).
        Looks at the window: NOW()-2min to NOW()-1min.
        ON DUPLICATE KEY UPDATE makes reruns safe.
        """
        self._run("""
            INSERT INTO metrics_1min (
                timestamp,
                cpu_percent_avg, cpu_percent_max, cpu_percent_min,
                mem_percent_avg, mem_percent_max, mem_percent_min,
                disk_read_avg,   disk_write_avg,
                net_sent_avg,    net_recv_avg,
                load_1min_avg,   process_count_avg,
                tcp_conn_avg
            )
            SELECT
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 60) * 60),
                AVG(cpu_percent),       MAX(cpu_percent),       MIN(cpu_percent),
                AVG(mem_percent),       MAX(mem_percent),       MIN(mem_percent),
                AVG(disk_read_bytes),   AVG(disk_write_bytes),
                AVG(net_bytes_sent),    AVG(net_bytes_recv),
                AVG(load_1min),         AVG(process_count),
                -- Scalar subquery pulls avg tcp_established from network_connections
                -- over the same 1-min window. Returns NULL if no rows exist.
                (SELECT ROUND(AVG(tcp_established))
                 FROM network_connections
                 WHERE timestamp >= NOW() - INTERVAL 2 MINUTE
                   AND timestamp <  NOW() - INTERVAL 1 MINUTE)
            FROM system_metrics
            WHERE timestamp >= NOW() - INTERVAL 2 MINUTE
              AND timestamp <  NOW() - INTERVAL 1 MINUTE
            GROUP BY FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 60) * 60)
            ON DUPLICATE KEY UPDATE
                cpu_percent_avg   = VALUES(cpu_percent_avg),
                cpu_percent_max   = VALUES(cpu_percent_max),
                cpu_percent_min   = VALUES(cpu_percent_min),
                mem_percent_avg   = VALUES(mem_percent_avg),
                mem_percent_max   = VALUES(mem_percent_max),
                mem_percent_min   = VALUES(mem_percent_min),
                disk_read_avg     = VALUES(disk_read_avg),
                disk_write_avg    = VALUES(disk_write_avg),
                net_sent_avg      = VALUES(net_sent_avg),
                net_recv_avg      = VALUES(net_recv_avg),
                load_1min_avg     = VALUES(load_1min_avg),
                process_count_avg = VALUES(process_count_avg),
                tcp_conn_avg      = VALUES(tcp_conn_avg)
        """, "aggregate_to_1min")

    def aggregate_to_5min(self) -> None:
        """
        Aggregate metrics_1min → metrics_5min (5-minute buckets).
        Looks at the window: NOW()-10min to NOW()-5min.
        """
        self._run("""
            INSERT INTO metrics_5min (
                timestamp,
                cpu_percent_avg, cpu_percent_max,
                mem_percent_avg, mem_percent_max,
                disk_read_avg,   disk_write_avg,
                net_sent_avg,    net_recv_avg,
                load_1min_avg,   tcp_conn_avg
            )
            SELECT
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 300) * 300),
                AVG(cpu_percent_avg), MAX(cpu_percent_max),
                AVG(mem_percent_avg), MAX(mem_percent_max),
                AVG(disk_read_avg),   AVG(disk_write_avg),
                AVG(net_sent_avg),    AVG(net_recv_avg),
                AVG(load_1min_avg),   ROUND(AVG(tcp_conn_avg))
            FROM metrics_1min
            WHERE timestamp >= NOW() - INTERVAL 10 MINUTE
              AND timestamp <  NOW() - INTERVAL 5 MINUTE
            GROUP BY FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 300) * 300)
            ON DUPLICATE KEY UPDATE
                cpu_percent_avg = VALUES(cpu_percent_avg),
                cpu_percent_max = VALUES(cpu_percent_max),
                mem_percent_avg = VALUES(mem_percent_avg),
                mem_percent_max = VALUES(mem_percent_max),
                disk_read_avg   = VALUES(disk_read_avg),
                disk_write_avg  = VALUES(disk_write_avg),
                net_sent_avg    = VALUES(net_sent_avg),
                net_recv_avg    = VALUES(net_recv_avg),
                load_1min_avg   = VALUES(load_1min_avg),
                tcp_conn_avg    = VALUES(tcp_conn_avg)
        """, "aggregate_to_5min")

    def aggregate_to_1hour(self) -> None:
        """
        Aggregate metrics_5min → metrics_1hour (1-hour buckets).
        Looks at the window: NOW()-2h to NOW()-1h.
        """
        self._run("""
            INSERT INTO metrics_1hour (
                timestamp,
                cpu_percent_avg, cpu_percent_max,
                mem_percent_avg, mem_percent_max,
                disk_read_avg,   disk_write_avg,
                net_sent_avg,    net_recv_avg,
                load_1min_avg,   tcp_conn_avg
            )
            SELECT
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 3600) * 3600),
                AVG(cpu_percent_avg), MAX(cpu_percent_max),
                AVG(mem_percent_avg), MAX(mem_percent_max),
                AVG(disk_read_avg),   AVG(disk_write_avg),
                AVG(net_sent_avg),    AVG(net_recv_avg),
                AVG(load_1min_avg),   ROUND(AVG(tcp_conn_avg))
            FROM metrics_5min
            WHERE timestamp >= NOW() - INTERVAL 2 HOUR
              AND timestamp <  NOW() - INTERVAL 1 HOUR
            GROUP BY FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 3600) * 3600)
            ON DUPLICATE KEY UPDATE
                cpu_percent_avg = VALUES(cpu_percent_avg),
                cpu_percent_max = VALUES(cpu_percent_max),
                mem_percent_avg = VALUES(mem_percent_avg),
                mem_percent_max = VALUES(mem_percent_max),
                disk_read_avg   = VALUES(disk_read_avg),
                disk_write_avg  = VALUES(disk_write_avg),
                net_sent_avg    = VALUES(net_sent_avg),
                net_recv_avg    = VALUES(net_recv_avg),
                load_1min_avg   = VALUES(load_1min_avg),
                tcp_conn_avg    = VALUES(tcp_conn_avg)
        """, "aggregate_to_1hour")

    # ------------------------------------------------------------------ #
    #  Aggregation — temperatures                                          #
    # ------------------------------------------------------------------ #

    def aggregate_temps_to_1min(self) -> None:
        """
        Aggregate cpu_temps → temps_1min (1-minute buckets, per sensor).
        """
        self._run("""
            INSERT INTO temps_1min (timestamp, sensor_label, temp_avg, temp_max, sample_count)
            SELECT
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 60) * 60),
                sensor_label,
                ROUND(AVG(temperature_celsius), 2),
                ROUND(MAX(temperature_celsius), 2),
                COUNT(*)
            FROM cpu_temps
            WHERE timestamp >= NOW() - INTERVAL 2 MINUTE
              AND timestamp <  NOW() - INTERVAL 1 MINUTE
            GROUP BY FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 60) * 60), sensor_label
            ON DUPLICATE KEY UPDATE
                temp_avg     = VALUES(temp_avg),
                temp_max     = VALUES(temp_max),
                sample_count = VALUES(sample_count)
        """, "aggregate_temps_to_1min")

    def aggregate_temps_to_5min(self) -> None:
        """
        Aggregate temps_1min → temps_5min (5-minute buckets, per sensor).
        """
        self._run("""
            INSERT INTO temps_5min (timestamp, sensor_label, temp_avg, temp_max, sample_count)
            SELECT
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 300) * 300),
                sensor_label,
                ROUND(AVG(temp_avg), 2),
                ROUND(MAX(temp_max), 2),
                SUM(sample_count)
            FROM temps_1min
            WHERE timestamp >= NOW() - INTERVAL 10 MINUTE
              AND timestamp <  NOW() - INTERVAL 5 MINUTE
            GROUP BY FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 300) * 300), sensor_label
            ON DUPLICATE KEY UPDATE
                temp_avg     = VALUES(temp_avg),
                temp_max     = VALUES(temp_max),
                sample_count = VALUES(sample_count)
        """, "aggregate_temps_to_5min")

    # ------------------------------------------------------------------ #
    #  Process history aggregation                                         #
    # ------------------------------------------------------------------ #

    def aggregate_process_history_to_1min(self) -> None:
        """
        Aggregate process_snapshots → process_history_1min (1-minute buckets).
        Groups by (minute_bucket, process_name, username) so different users
        running the same binary are tracked separately.
        Runs every 1 minute alongside the system metrics 1-min aggregation.
        """
        self._run("""
            INSERT INTO process_history_1min
                (timestamp, process_name, username,
                 cpu_avg, cpu_max, mem_avg_mb, mem_max_mb, sample_count,
                 tcp_conns_avg)
            SELECT
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 60) * 60),
                name,
                username,
                ROUND(AVG(cpu_percent), 2),
                ROUND(MAX(cpu_percent), 2),
                ROUND(AVG(mem_rss) / 1048576, 2),
                ROUND(MAX(mem_rss) / 1048576, 2),
                COUNT(*),
                ROUND(AVG(tcp_conns), 1)
            FROM process_snapshots
            WHERE timestamp >= NOW() - INTERVAL 2 MINUTE
              AND timestamp <  NOW() - INTERVAL 1 MINUTE
            GROUP BY
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 60) * 60),
                name,
                username
            ON DUPLICATE KEY UPDATE
                cpu_avg      = VALUES(cpu_avg),
                cpu_max      = VALUES(cpu_max),
                mem_avg_mb   = VALUES(mem_avg_mb),
                mem_max_mb   = VALUES(mem_max_mb),
                sample_count = VALUES(sample_count),
                tcp_conns_avg = VALUES(tcp_conns_avg)
        """, "aggregate_process_history_to_1min")

    def aggregate_process_history_to_5min(self) -> None:
        """
        Aggregate process_history_1min → process_history_5min (5-minute buckets).
        Runs every 5 minutes alongside the system metrics 5-min aggregation.
        """
        self._run("""
            INSERT INTO process_history_5min
                (timestamp, process_name, username,
                 cpu_avg, cpu_max, mem_avg_mb, mem_max_mb, sample_count,
                 tcp_conns_avg)
            SELECT
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 300) * 300),
                process_name,
                username,
                ROUND(AVG(cpu_avg), 2),
                ROUND(MAX(cpu_max), 2),
                ROUND(AVG(mem_avg_mb), 2),
                ROUND(MAX(mem_max_mb), 2),
                SUM(sample_count),
                ROUND(AVG(tcp_conns_avg), 1)
            FROM process_history_1min
            WHERE timestamp >= NOW() - INTERVAL 10 MINUTE
              AND timestamp <  NOW() - INTERVAL 5 MINUTE
            GROUP BY
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 300) * 300),
                process_name,
                username
            ON DUPLICATE KEY UPDATE
                cpu_avg      = VALUES(cpu_avg),
                cpu_max      = VALUES(cpu_max),
                mem_avg_mb   = VALUES(mem_avg_mb),
                mem_max_mb   = VALUES(mem_max_mb),
                sample_count = VALUES(sample_count),
                tcp_conns_avg = VALUES(tcp_conns_avg)
        """, "aggregate_process_history_to_5min")

    # ------------------------------------------------------------------ #
    #  Cleanup                                                             #
    # ------------------------------------------------------------------ #

    def aggregate_gpu_to_1min(self) -> None:
        """Roll raw GPU samples into one-minute buckets."""
        self._run("""
            INSERT INTO gpu_metrics_1min (
                timestamp, gpu_id, gpu_name, gpu_percent_avg, gpu_percent_max,
                memory_used_mb_avg, memory_used_mb_max, memory_total_mb,
                memory_percent_avg, memory_percent_max, temperature_celsius_avg,
                temperature_celsius_max, power_watts_avg, power_watts_max, sample_count
            )
            SELECT FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 60) * 60),
                   gpu_id, MAX(gpu_name), ROUND(AVG(gpu_percent), 2), MAX(gpu_percent),
                   ROUND(AVG(memory_used_mb), 2), MAX(memory_used_mb), MAX(memory_total_mb),
                   ROUND(AVG(memory_percent), 2), MAX(memory_percent),
                   ROUND(AVG(temperature_celsius), 2), MAX(temperature_celsius),
                   ROUND(AVG(power_watts), 2), MAX(power_watts), COUNT(*)
            FROM gpu_metrics
            WHERE timestamp >= NOW() - INTERVAL 2 MINUTE
              AND timestamp < NOW() - INTERVAL 1 MINUTE
            GROUP BY gpu_id, FLOOR(UNIX_TIMESTAMP(timestamp) / 60)
            ON DUPLICATE KEY UPDATE
                gpu_name = VALUES(gpu_name),
                gpu_percent_avg = VALUES(gpu_percent_avg),
                gpu_percent_max = VALUES(gpu_percent_max),
                memory_used_mb_avg = VALUES(memory_used_mb_avg),
                memory_used_mb_max = VALUES(memory_used_mb_max),
                memory_total_mb = VALUES(memory_total_mb),
                memory_percent_avg = VALUES(memory_percent_avg),
                memory_percent_max = VALUES(memory_percent_max),
                temperature_celsius_avg = VALUES(temperature_celsius_avg),
                temperature_celsius_max = VALUES(temperature_celsius_max),
                power_watts_avg = VALUES(power_watts_avg),
                power_watts_max = VALUES(power_watts_max),
                sample_count = VALUES(sample_count)
        """, "aggregate_gpu_1min")

    def aggregate_gpu_to_5min(self) -> None:
        """Roll one-minute GPU buckets into five-minute buckets."""
        self._run("""
            INSERT INTO gpu_metrics_5min (
                timestamp, gpu_id, gpu_name, gpu_percent_avg, gpu_percent_max,
                memory_used_mb_avg, memory_used_mb_max, memory_total_mb,
                memory_percent_avg, memory_percent_max, temperature_celsius_avg,
                temperature_celsius_max, power_watts_avg, power_watts_max, sample_count
            )
            SELECT FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 300) * 300),
                   gpu_id, MAX(gpu_name), ROUND(AVG(gpu_percent_avg), 2), MAX(gpu_percent_max),
                   ROUND(AVG(memory_used_mb_avg), 2), MAX(memory_used_mb_max), MAX(memory_total_mb),
                   ROUND(AVG(memory_percent_avg), 2), MAX(memory_percent_max),
                   ROUND(AVG(temperature_celsius_avg), 2), MAX(temperature_celsius_max),
                   ROUND(AVG(power_watts_avg), 2), MAX(power_watts_max), SUM(sample_count)
            FROM gpu_metrics_1min
            WHERE timestamp >= NOW() - INTERVAL 10 MINUTE
              AND timestamp < NOW() - INTERVAL 5 MINUTE
            GROUP BY gpu_id, FLOOR(UNIX_TIMESTAMP(timestamp) / 300)
            ON DUPLICATE KEY UPDATE
                gpu_name = VALUES(gpu_name),
                gpu_percent_avg = VALUES(gpu_percent_avg),
                gpu_percent_max = VALUES(gpu_percent_max),
                memory_used_mb_avg = VALUES(memory_used_mb_avg),
                memory_used_mb_max = VALUES(memory_used_mb_max),
                memory_total_mb = VALUES(memory_total_mb),
                memory_percent_avg = VALUES(memory_percent_avg),
                memory_percent_max = VALUES(memory_percent_max),
                temperature_celsius_avg = VALUES(temperature_celsius_avg),
                temperature_celsius_max = VALUES(temperature_celsius_max),
                power_watts_avg = VALUES(power_watts_avg),
                power_watts_max = VALUES(power_watts_max),
                sample_count = VALUES(sample_count)
        """, "aggregate_gpu_5min")

    def aggregate_gpu_to_1hour(self) -> None:
        """Roll five-minute GPU buckets into hourly buckets."""
        self._run("""
            INSERT INTO gpu_metrics_1hour (
                timestamp, gpu_id, gpu_name, gpu_percent_avg, gpu_percent_max,
                memory_used_mb_avg, memory_used_mb_max, memory_total_mb,
                memory_percent_avg, memory_percent_max, temperature_celsius_avg,
                temperature_celsius_max, power_watts_avg, power_watts_max, sample_count
            )
            SELECT FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp) / 3600) * 3600),
                   gpu_id, MAX(gpu_name), ROUND(AVG(gpu_percent_avg), 2), MAX(gpu_percent_max),
                   ROUND(AVG(memory_used_mb_avg), 2), MAX(memory_used_mb_max), MAX(memory_total_mb),
                   ROUND(AVG(memory_percent_avg), 2), MAX(memory_percent_max),
                   ROUND(AVG(temperature_celsius_avg), 2), MAX(temperature_celsius_max),
                   ROUND(AVG(power_watts_avg), 2), MAX(power_watts_max), SUM(sample_count)
            FROM gpu_metrics_5min
            WHERE timestamp >= NOW() - INTERVAL 2 HOUR
              AND timestamp < NOW() - INTERVAL 1 HOUR
            GROUP BY gpu_id, FLOOR(UNIX_TIMESTAMP(timestamp) / 3600)
            ON DUPLICATE KEY UPDATE
                gpu_name = VALUES(gpu_name),
                gpu_percent_avg = VALUES(gpu_percent_avg),
                gpu_percent_max = VALUES(gpu_percent_max),
                memory_used_mb_avg = VALUES(memory_used_mb_avg),
                memory_used_mb_max = VALUES(memory_used_mb_max),
                memory_total_mb = VALUES(memory_total_mb),
                memory_percent_avg = VALUES(memory_percent_avg),
                memory_percent_max = VALUES(memory_percent_max),
                temperature_celsius_avg = VALUES(temperature_celsius_avg),
                temperature_celsius_max = VALUES(temperature_celsius_max),
                power_watts_avg = VALUES(power_watts_avg),
                power_watts_max = VALUES(power_watts_max),
                sample_count = VALUES(sample_count)
        """, "aggregate_gpu_1hour")

    def cleanup_raw_samples(self) -> None:
        """
        Purge raw sample tables older than 10 minutes.
        Runs every 10 minutes — mirrors the cleanup_old_samples event.
        """
        for table in ('system_metrics', 'cpu_cores', 'cpu_temps', 'gpu_metrics',
                      'disk_usage', 'network_connections'):
            self._run(
                f"DELETE FROM {table} WHERE timestamp < NOW() - INTERVAL 10 MINUTE",
                f"cleanup_raw:{table}",
            )

    def cleanup_process_snapshots(self) -> None:
        """
        Purge process_snapshots older than 10 minutes.
        Views only ever query MAX(timestamp) so no history is needed.
        Reduced from 30 min after bumping process collection to 100 processes,
        which made 30 min retention (18k rows) wasteful for a MEMORY table.
        """
        self._run(
            "DELETE FROM process_snapshots WHERE timestamp < NOW() - INTERVAL 10 MINUTE",
            "cleanup_process_snapshots",
        )

    def cleanup_aggregates(self) -> None:
        """
        Daily purge of old aggregate data.
        Retention: metrics_1min→2d (charts only, baselines use metrics_5min),
        5min→30d, 1hour→1yr, temps_1min→7d, temps_5min→1yr.
        """
        jobs = [
            ("metrics_1min",          "2 DAY"),
            ("metrics_5min",          "30 DAY"),
            ("metrics_1hour",         "1 YEAR"),
            ("gpu_metrics_1min",      "7 DAY"),
            ("gpu_metrics_5min",      "30 DAY"),
            ("gpu_metrics_1hour",     "1 YEAR"),
            ("temps_1min",            "7 DAY"),
            ("temps_5min",            "1 YEAR"),
            ("process_history_1min",  "7 DAY"),
            ("process_history_5min",  "30 DAY"),
        ]
        for table, interval in jobs:
            self._run(
                f"DELETE FROM {table} WHERE timestamp < NOW() - INTERVAL {interval}",
                f"cleanup_aggregates:{table}",
            )
