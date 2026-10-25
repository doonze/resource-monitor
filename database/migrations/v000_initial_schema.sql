-- ============================================================
-- Migration:   v000 — Initial Schema
-- Description: Complete baseline schema for Resource Monitor v1.0.0
-- App Version: 1.0.0
-- Author:      Resource Monitor
-- Rollback Risk: destructive
-- Rollback Note: Drops ALL application tables and ALL data permanently.
--                Only use for a complete reinstall. Historical metrics,
--                settings, and alert history will be unrecoverable.
--
-- Design principles:
--   • No stored procedures. No scheduled events.
--   • All aggregation is handled by the Python collector.
--   • MEMORY tables for high-frequency raw data (volatile by design).
--   • InnoDB tables for all persisted/aggregated data.
--   • CREATE TABLE IF NOT EXISTS — safe to run on existing databases.
--   • INSERT IGNORE for seed data — preserves existing rows.
-- ============================================================

-- ==UP==

-- ----------------------------------------------------------------
-- app_settings
-- Key-value application configuration.
-- Used by both the collector and web UI.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key    VARCHAR(50)   NOT NULL,
    setting_value  VARCHAR(255)  NOT NULL,
    label          VARCHAR(100)  DEFAULT NULL     COMMENT 'Human-readable label for Settings UI',
    description    TEXT          DEFAULT NULL     COMMENT 'Help text shown in Settings UI',
    updated_at     DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application-wide configuration used by collector and web UI.';

-- ----------------------------------------------------------------
-- collector_status
-- Heartbeat and health tracking for each collector instance.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS collector_status (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    hostname        VARCHAR(255)   NOT NULL,
    last_heartbeat  DATETIME       NOT NULL,
    version         VARCHAR(20)    DEFAULT NULL,
    status          ENUM('running','stopped','error') DEFAULT 'running',
    error_message   TEXT           DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hostname (hostname),
    KEY idx_last_heartbeat (last_heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks last-known status and heartbeat of each collector instance.';------------------------------------------------------------
-- system_metrics  [MEMORY — volatile]
-- Raw system-level metrics sampled every ~5 seconds.
-- The collector aggregates this into metrics_1min before rows expire.
-- Data is intentionally lost on DB restart; the collector repopulates it.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_metrics (
    id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp         DATETIME(3)      NOT NULL,
    cpu_percent       DECIMAL(5,2)     NOT NULL,
    cpu_count         INT              NOT NULL,
    load_1min         DECIMAL(5,2)     DEFAULT NULL,
    load_5min         DECIMAL(5,2)     DEFAULT NULL,
    load_15min        DECIMAL(5,2)     DEFAULT NULL,
    mem_total         BIGINT UNSIGNED  NOT NULL,
    mem_available     BIGINT UNSIGNED  NOT NULL,
    mem_used          BIGINT UNSIGNED  NOT NULL,
    mem_percent       DECIMAL(5,2)     NOT NULL,
    swap_total        BIGINT UNSIGNED  DEFAULT NULL,
    swap_used         BIGINT UNSIGNED  DEFAULT NULL,
    swap_percent      DECIMAL(5,2)     DEFAULT NULL,
    disk_read_bytes   BIGINT UNSIGNED  DEFAULT NULL,
    disk_write_bytes  BIGINT UNSIGNED  DEFAULT NULL,
    disk_read_count   INT UNSIGNED     DEFAULT NULL,
    disk_write_count  INT UNSIGNED     DEFAULT NULL,
    net_bytes_sent    BIGINT UNSIGNED  DEFAULT NULL,
    net_bytes_recv    BIGINT UNSIGNED  DEFAULT NULL,
    net_packets_sent  INT UNSIGNED     DEFAULT NULL,
    net_packets_recv  INT UNSIGNED     DEFAULT NULL,
    process_count     INT UNSIGNED     DEFAULT NULL,
    uptime_seconds    BIGINT UNSIGNED  DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp   (timestamp),
    KEY idx_cpu_percent (cpu_percent),
    KEY idx_mem_percent (mem_percent)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Raw system metrics. MEMORY table — volatile, purged on DB restart.';

-- ----------------------------------------------------------------
-- cpu_cores  [MEMORY — volatile]
-- Per-core CPU usage, sampled alongside system_metrics.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cpu_cores (
    id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp      DATETIME(3)      NOT NULL,
    core_number    INT UNSIGNED     NOT NULL,
    usage_percent  DECIMAL(5,2)     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp      (timestamp),
    KEY idx_core_timestamp (core_number, timestamp)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Per-core CPU usage. MEMORY table — volatile.';

-- ----------------------------------------------------------------
-- cpu_temps  [MEMORY — volatile]
-- Temperature sensor readings. Aggregated into temps_1min by collector.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cpu_temps (
    id                   BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp            DATETIME(3)      NOT NULL,
    sensor_label         VARCHAR(100)     NOT NULL,
    temperature_celsius  DECIMAL(5,2)     NOT NULL,
    high_threshold       DECIMAL(5,2)     DEFAULT NULL,
    critical_threshold   DECIMAL(5,2)     DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp        (timestamp),
    KEY idx_sensor_timestamp (sensor_label(50), timestamp)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='CPU temperature sensor readings. MEMORY table — volatile.';

-- ----------------------------------------------------------------
-- process_snapshots  [MEMORY — volatile]
-- Top process snapshots. Purged by collector after ~30 minutes.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS process_snapshots (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp       DATETIME(3)      NOT NULL,
    pid             INT UNSIGNED     NOT NULL,
    name            VARCHAR(255)     NOT NULL,
    username        VARCHAR(100)     DEFAULT NULL,
    cpu_percent     DECIMAL(5,2)     NOT NULL,
    cpu_num         INT              DEFAULT NULL,
    mem_rss         BIGINT UNSIGNED  NOT NULL,
    mem_vms         BIGINT UNSIGNED  DEFAULT NULL,
    mem_percent     DECIMAL(5,2)     NOT NULL,
    io_read_bytes   BIGINT UNSIGNED  DEFAULT NULL,
    io_write_bytes  BIGINT UNSIGNED  DEFAULT NULL,
    net_bytes_sent  BIGINT UNSIGNED  DEFAULT NULL,
    net_bytes_recv  BIGINT UNSIGNED  DEFAULT NULL,
    status          VARCHAR(20)      DEFAULT NULL,
    num_threads     INT UNSIGNED     DEFAULT NULL,
    nice            INT              NOT NULL DEFAULT 0,
    rank_cpu        INT              DEFAULT NULL,
    rank_mem        INT              DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp   (timestamp),
    KEY idx_pid_ts      (pid, timestamp),
    KEY idx_cpu_percent (cpu_percent),
    KEY idx_mem_percent (mem_percent)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Top process snapshots. MEMORY table — purged after ~30 min by collector.';

-- ----------------------------------------------------------------
-- disk_usage  [MEMORY — volatile]
-- Per-mount disk usage snapshots.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS disk_usage (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp     DATETIME(3)      NOT NULL,
    mount_point   VARCHAR(255)     NOT NULL,
    device        VARCHAR(255)     NOT NULL,
    fstype        VARCHAR(50)      DEFAULT NULL,
    total_bytes   BIGINT UNSIGNED  NOT NULL,
    used_bytes    BIGINT UNSIGNED  NOT NULL,
    free_bytes    BIGINT UNSIGNED  NOT NULL,
    percent_used  DECIMAL(5,2)     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp      (timestamp),
    KEY idx_mount_ts       (mount_point(100), timestamp)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Per-mount disk usage snapshots. MEMORY table — volatile.';

-- ----------------------------------------------------------------
-- network_connections  [MEMORY — volatile]
-- TCP/UDP connection counts by state.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS network_connections (
    id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp        DATETIME(3)      NOT NULL,
    tcp_established  INT UNSIGNED     DEFAULT NULL,
    tcp_listen       INT UNSIGNED     DEFAULT NULL,
    tcp_time_wait    INT UNSIGNED     DEFAULT NULL,
    tcp_close_wait   INT UNSIGNED     DEFAULT NULL,
    tcp_other        INT UNSIGNED     DEFAULT NULL,
    udp_connections  INT UNSIGNED     DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp (timestamp)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Network connection counts by state. MEMORY table — volatile.';

-- ----------------------------------------------------------------
-- metrics_1min  [InnoDB — persisted]
-- 1-minute rollups aggregated from system_metrics by the collector.
-- Default retention: 7 days (see app_settings.data_retention_1min).
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS metrics_1min (
    id                 BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp          DATETIME         NOT NULL,
    cpu_percent_avg    DECIMAL(5,2)     DEFAULT NULL,
    cpu_percent_max    DECIMAL(5,2)     DEFAULT NULL,
    cpu_percent_min    DECIMAL(5,2)     DEFAULT NULL,
    mem_percent_avg    DECIMAL(5,2)     DEFAULT NULL,
    mem_percent_max    DECIMAL(5,2)     DEFAULT NULL,
    mem_percent_min    DECIMAL(5,2)     DEFAULT NULL,
    disk_read_avg      BIGINT UNSIGNED  DEFAULT NULL,
    disk_write_avg     BIGINT UNSIGNED  DEFAULT NULL,
    net_sent_avg       BIGINT UNSIGNED  DEFAULT NULL,
    net_recv_avg       BIGINT UNSIGNED  DEFAULT NULL,
    load_1min_avg      DECIMAL(5,2)     DEFAULT NULL,
    process_count_avg  INT UNSIGNED     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='1-minute metric aggregates. Written by collector. Retained 7 days.';

-- ----------------------------------------------------------------
-- metrics_5min  [InnoDB — persisted]
-- 5-minute rollups aggregated from metrics_1min by the collector.
-- Default retention: 90 days (see app_settings.data_retention_5min).
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS metrics_5min (
    id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp        DATETIME         NOT NULL,
    cpu_percent_avg  DECIMAL(5,2)     DEFAULT NULL,
    cpu_percent_max  DECIMAL(5,2)     DEFAULT NULL,
    mem_percent_avg  DECIMAL(5,2)     DEFAULT NULL,
    mem_percent_max  DECIMAL(5,2)     DEFAULT NULL,
    disk_read_avg    BIGINT UNSIGNED  DEFAULT NULL,
    disk_write_avg   BIGINT UNSIGNED  DEFAULT NULL,
    net_sent_avg     BIGINT UNSIGNED  DEFAULT NULL,
    net_recv_avg     BIGINT UNSIGNED  DEFAULT NULL,
    load_1min_avg    DECIMAL(5,2)     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='5-minute metric aggregates. Written by collector. Retained 90 days.';

-- ----------------------------------------------------------------
-- metrics_1hour  [InnoDB — persisted]
-- Hourly rollups aggregated from metrics_5min by the collector.
-- Default retention: indefinite (see app_settings.data_retention_1hour).
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS metrics_1hour (
    id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    timestamp        DATETIME         NOT NULL,
    cpu_percent_avg  DECIMAL(5,2)     DEFAULT NULL,
    cpu_percent_max  DECIMAL(5,2)     DEFAULT NULL,
    mem_percent_avg  DECIMAL(5,2)     DEFAULT NULL,
    mem_percent_max  DECIMAL(5,2)     DEFAULT NULL,
    disk_read_avg    BIGINT UNSIGNED  DEFAULT NULL,
    disk_write_avg   BIGINT UNSIGNED  DEFAULT NULL,
    net_sent_avg     BIGINT UNSIGNED  DEFAULT NULL,
    net_recv_avg     BIGINT UNSIGNED  DEFAULT NULL,
    load_1min_avg    DECIMAL(5,2)     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Hourly metric aggregates. Written by collector. Retained indefinitely.';

-- ----------------------------------------------------------------
-- temps_1min  [InnoDB — persisted]
-- 1-minute per-sensor temperature aggregates.
-- Default retention: 7 days (see app_settings.data_retention_temps_1min).
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS temps_1min (
    timestamp     DATETIME          NOT NULL,
    sensor_label  VARCHAR(30)       NOT NULL,
    temp_avg      DECIMAL(5,2)      NOT NULL,
    temp_max      DECIMAL(5,2)      NOT NULL,
    sample_count  TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    PRIMARY KEY (timestamp, sensor_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='1-minute per-sensor temperature aggregates. Retained 7 days.';

-- ----------------------------------------------------------------
-- temps_5min  [InnoDB — persisted]
-- 5-minute per-sensor temperature aggregates.
-- Default retention: 1 year (see app_settings.data_retention_temps_5min).
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS temps_5min (
    timestamp     DATETIME          NOT NULL,
    sensor_label  VARCHAR(30)       NOT NULL,
    temp_avg      DECIMAL(5,2)      NOT NULL,
    temp_max      DECIMAL(5,2)      NOT NULL,
    sample_count  TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    PRIMARY KEY (timestamp, sensor_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='5-minute per-sensor temperature aggregates. Retained 1 year.';

-- ----------------------------------------------------------------
-- metric_baselines  [InnoDB — persisted]
-- Statistical baselines for intelligent alerting.
-- Written hourly by the Python collector.
-- 168 rows per metric (7 days × 24 hours). Day 0 = Monday.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS metric_baselines (
    metric        VARCHAR(30)       NOT NULL  COMMENT 'cpu_percent | mem_percent | disk_io_write | net_sent | temp_avg',
    day_of_week   TINYINT UNSIGNED  NOT NULL  COMMENT '0=Mon … 6=Sun (Python weekday convention)',
    hour_of_day   TINYINT UNSIGNED  NOT NULL  COMMENT '0–23',
    mean          DECIMAL(14,4)     DEFAULT NULL  COMMENT 'Mean of 1-min readings in this bucket',
    stddev        DECIMAL(14,4)     DEFAULT NULL  COMMENT 'Sample std deviation',
    p05           DECIMAL(14,4)     DEFAULT NULL  COMMENT '5th percentile (low anchor)',
    p95           DECIMAL(14,4)     DEFAULT NULL  COMMENT '95th percentile (high anchor)',
    sample_count  INT UNSIGNED      DEFAULT NULL  COMMENT 'Number of 1-min readings used to compute this row',
    computed_at   DATETIME          DEFAULT NULL  COMMENT 'When this row was last recomputed',
    PRIMARY KEY (metric, day_of_week, hour_of_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Statistical baselines for intelligent alerting. Written hourly by collector.';

-- ----------------------------------------------------------------
-- alert_rules  [InnoDB — persisted]
-- Per-metric alerting configuration.
-- Supports both fixed-threshold and dynamic baseline modes.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_rules (
    id                  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    name                VARCHAR(255)        NOT NULL,
    metric              VARCHAR(50)         NOT NULL,
    `condition`         ENUM('gt','lt','eq') NOT NULL,
    threshold           DECIMAL(10,2)       NOT NULL,
    duration_seconds    INT UNSIGNED        DEFAULT 60,
    enabled             TINYINT(1)          DEFAULT 1,
    guard_rail_floor    DECIMAL(14,4)       DEFAULT NULL  COMMENT 'WARNING level — absolute floor, fires regardless of baseline',
    guard_rail_ceiling  DECIMAL(14,4)       DEFAULT NULL  COMMENT 'CRITICAL level — absolute ceiling, always fires',
    manual_override     TINYINT(1)          NOT NULL DEFAULT 1  COMMENT '1=use fixed threshold, 0=use dynamic baseline system',
    created_at          DATETIME            DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_enabled (enabled),
    KEY idx_metric  (metric)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-metric alert rules. Supports fixed-threshold and dynamic baseline modes.';

-- ----------------------------------------------------------------
-- alert_state  [InnoDB — persisted]
-- Current lifecycle state for each named alert condition.
-- One row per alert_id — upserted by collector on each evaluation cycle.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_state (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    alert_id       VARCHAR(120)  NOT NULL  COMMENT 'Stable identifier, e.g. current_cpu_critical',
    category       VARCHAR(20)   NOT NULL  COMMENT 'current | sustained | disk_full | disk_change',
    severity       VARCHAR(10)   NOT NULL  COMMENT 'warning | critical',
    title          VARCHAR(200)  NOT NULL,
    message        TEXT          NOT NULL,
    first_fired_at DATETIME      NOT NULL,
    last_fired_at  DATETIME      NOT NULL,
    resolved_at    DATETIME      DEFAULT NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alert_id  (alert_id),
    KEY idx_active     (is_active),
    KEY idx_last_fired (last_fired_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Current lifecycle state for each alert condition. One row per alert_id.';

-- ----------------------------------------------------------------
-- alert_history  [InnoDB — persisted]
-- Immutable record of all alert firings and resolutions.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_history (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    rule_id       INT UNSIGNED     NOT NULL,
    triggered_at  DATETIME         NOT NULL,
    resolved_at   DATETIME         DEFAULT NULL,
    peak_value    DECIMAL(10,2)    DEFAULT NULL,
    message       TEXT             DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_triggered_at (triggered_at),
    KEY idx_rule_id      (rule_id),
    CONSTRAINT fk_ah_rule FOREIGN KEY (rule_id) REFERENCES alert_rules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable firing history for all alert rules.';

-- ----------------------------------------------------------------
-- alert_notifications  [InnoDB — persisted]
-- Immutable audit log of every notification attempt (email, etc.)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_notifications (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    alert_id           VARCHAR(120)  NOT NULL,
    recipient_id       INT UNSIGNED  DEFAULT NULL  COMMENT 'NULL = broadcast / test',
    notification_type  VARCHAR(20)   NOT NULL DEFAULT 'fired'  COMMENT 'fired | resolved | test',
    severity           VARCHAR(10)   NOT NULL,
    title              VARCHAR(200)  NOT NULL,
    message            TEXT          NOT NULL,
    channel            VARCHAR(50)   NOT NULL DEFAULT 'email',
    success            TINYINT(1)    NOT NULL DEFAULT 1,
    error_message      TEXT          DEFAULT NULL,
    notified_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alert_id    (alert_id),
    KEY idx_recipient   (recipient_id),
    KEY idx_notified_at (notified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit log of all alert notification attempts.';

-- ----------------------------------------------------------------
-- alert_recipients  [InnoDB — persisted]
-- Email recipients for server-side alert notifications.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_recipients (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    display_name       VARCHAR(100)  NOT NULL DEFAULT '',
    email              VARCHAR(255)  NOT NULL,
    enabled            TINYINT(1)    NOT NULL DEFAULT 1,
    min_severity       ENUM('warning','critical') NOT NULL DEFAULT 'warning',
    notify_current     TINYINT(1)    NOT NULL DEFAULT 1  COMMENT 'Guard-rail / threshold alerts',
    notify_sustained   TINYINT(1)    NOT NULL DEFAULT 1  COMMENT 'Sustained baseline alerts',
    notify_disk_full   TINYINT(1)    NOT NULL DEFAULT 1  COMMENT 'Disk near-full alerts',
    notify_disk_change TINYINT(1)    NOT NULL DEFAULT 1  COMMENT 'Significant disk space change alerts',
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Email recipients for server-side alert notifications.';

-- ----------------------------------------------------------------
-- alert_dismissals  [InnoDB — persisted]
-- Tracks which specific alert firings the user dismissed.
-- Keyed on (alert_id, first_fired_at) — unique per firing.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_dismissals (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    alert_id        VARCHAR(120)  NOT NULL  COMMENT 'Matches alert_state.alert_id',
    first_fired_at  DATETIME      NOT NULL  COMMENT 'Matches alert_state.first_fired_at for this firing',
    dismissed_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alert_firing (alert_id, first_fired_at),
    KEY idx_dismissed_at (dismissed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Records which specific alert firings the user dismissed from the dashboard.';

-- ----------------------------------------------------------------
-- alert_test_queue  [InnoDB — persisted]
-- Queue for collector-driven test emails triggered from the web UI.
-- The collector polls this table and sends the email, then marks complete.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alert_test_queue (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    recipient_id   INT UNSIGNED  NOT NULL  COMMENT 'References alert_recipients.id',
    requested_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    picked_up_at   DATETIME      DEFAULT NULL,
    completed_at   DATETIME      DEFAULT NULL,
    status         ENUM('pending','sending','success','failed') NOT NULL DEFAULT 'pending',
    error_message  TEXT          DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_status       (status),
    KEY idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Queue for collector-driven test emails triggered from the web UI.';

-- ----------------------------------------------------------------
-- Seed data: app_settings
-- INSERT IGNORE — safe on existing databases; preserves custom values.
-- ----------------------------------------------------------------
INSERT IGNORE INTO app_settings (setting_key, setting_value, label, description) VALUES
    ('alert_sigma',               '2.0',             'Alert Sigma Multiplier',        'Std deviations above baseline to trigger a sustained alert'),
    ('sustained_minutes',         '30',              'Sustained Alert Window (min)',   'Minutes above baseline before a sustained alert fires'),
    ('baseline_mode',             'hourly',          'Baseline Mode',                 'hourly | daily | weekly — granularity for baseline computation'),
    ('smtp_host',                 '',                'SMTP Host',                     'Outbound mail server hostname'),
    ('smtp_port',                 '587',             'SMTP Port',                     '587=STARTTLS  465=SSL  25=plain'),
    ('smtp_user',                 '',                'SMTP Username',                 'SMTP authentication username'),
    ('smtp_pass',                 '',                'SMTP Password',                 'SMTP authentication password'),
    ('smtp_from',                 '',                'From Address',                  'Sender address for alert emails'),
    ('smtp_from_name',            'Resource Monitor','From Name',                     'Display name for alert emails'),
    ('smtp_tls',                  '1',               'Use TLS',                       '1=STARTTLS/SSL  0=plain (not recommended)'),
    ('data_retention_1min',       '7',               '1-Min Retention (days)',        'Days to keep 1-minute aggregates'),
    ('data_retention_5min',       '90',              '5-Min Retention (days)',        'Days to keep 5-minute aggregates'),
    ('data_retention_1hour',      '0',               '1-Hour Retention (days)',       '0 = keep indefinitely'),
    ('data_retention_temps_1min', '7',               'Temp 1-Min Retention (days)',   'Days to keep 1-minute temperature aggregates'),
    ('data_retention_temps_5min', '365',             'Temp 5-Min Retention (days)',   'Days to keep 5-minute temperature aggregates');

-- ----------------------------------------------------------------
-- Seed data: alert_rules
-- INSERT IGNORE — preserves any existing custom rules.
-- Explicit IDs allow deterministic inserts across environments.
-- ----------------------------------------------------------------
INSERT IGNORE INTO alert_rules
    (id, name, metric, `condition`, threshold, duration_seconds, enabled, guard_rail_floor, guard_rail_ceiling, manual_override)
VALUES
    (1, 'CPU High',        'cpu_percent',   'gt', 85.00,        60, 1,  50.00,        95.00,         1),
    (2, 'Memory High',     'mem_percent',   'gt', 85.00,        60, 1,  60.00,        95.00,         1),
    (3, 'Disk Full',       'disk_percent',  'gt', 85.00,        60, 1,  80.00,        95.00,         1),
    (4, 'Swap High',       'swap_percent',  'gt', 75.00,        60, 1,  70.00,        95.00,         1),
    (5, 'Disk I/O Write',  'disk_io_write', 'gt', 52428800.00,  60, 1,  52428800.00,  524288000.00,  1),
    (6, 'Network Sent',    'net_sent',      'gt', 10485760.00,  60, 1,  5242880.00,   524288000.00,  1),
    (7, 'CPU Temperature', 'temp_avg',      'gt', 80.00,        60, 1,  75.00,        92.00,         1);

-- ==DOWN==

-- ----------------------------------------------------------------
-- Drop all application tables in reverse dependency order.
--
-- ⚠️  WARNING: This is a FULL WIPE. All data will be permanently lost.
--     Only use this for a complete reinstall or test teardown.
--     The schema_migrations table is dropped last so the runner can
--     still record the rollback before it disappears.
-- ----------------------------------------------------------------

DROP TABLE IF EXISTS alert_test_queue;
DROP TABLE IF EXISTS alert_dismissals;
DROP TABLE IF EXISTS alert_notifications;
DROP TABLE IF EXISTS alert_recipients;
DROP TABLE IF EXISTS alert_history;
DROP TABLE IF EXISTS alert_state;
DROP TABLE IF EXISTS alert_rules;
DROP TABLE IF EXISTS metric_baselines;
DROP TABLE IF EXISTS temps_5min;
DROP TABLE IF EXISTS temps_1min;
DROP TABLE IF EXISTS metrics_1hour;
DROP TABLE IF EXISTS metrics_5min;
DROP TABLE IF EXISTS metrics_1min;
DROP TABLE IF EXISTS network_connections;
DROP TABLE IF EXISTS disk_usage;
DROP TABLE IF EXISTS process_snapshots;
DROP TABLE IF EXISTS cpu_temps;
DROP TABLE IF EXISTS cpu_cores;
DROP TABLE IF EXISTS system_metrics;
DROP TABLE IF EXISTS collector_status;
DROP TABLE IF EXISTS app_settings;
