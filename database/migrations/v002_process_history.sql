-- ============================================================
-- Migration:    v002 — Process History Tables
-- Description:  Add process_history_1min and process_history_5min for process CPU/memory history
-- App Version:  1.2.0
-- Rollback Risk: low
-- Rollback Note: Drops both process history tables. All accumulated history
--                data is permanently lost. Aggregation in collector will
--                stop producing history rows until tables are recreated.
-- ============================================================

-- ==UP==

-- ----------------------------------------------------------------
-- process_history_1min
-- 1-minute aggregated CPU/memory per process name + username.
-- Sourced from process_snapshots every minute by the Python collector.
-- Retention: 7 days (cleanup_aggregates in aggregator.py)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `process_history_1min` (
    `timestamp`     DATETIME(0)       NOT NULL,
    `process_name`  VARCHAR(255)      NOT NULL,
    `username`      VARCHAR(100)      NOT NULL DEFAULT '',
    `cpu_avg`       DECIMAL(5,2)      NOT NULL,
    `cpu_max`       DECIMAL(5,2)      NOT NULL,
    `mem_avg_mb`    DECIMAL(8,2)      NOT NULL,
    `mem_max_mb`    DECIMAL(8,2)      NOT NULL,
    `sample_count`  TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    PRIMARY KEY (`timestamp`, `process_name`(191), `username`(100)),
    KEY `idx_name_time`  (`process_name`(191), `timestamp`),
    KEY `idx_time`        (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='1-minute process CPU/memory aggregates. 7-day retention.';

-- ----------------------------------------------------------------
-- process_history_5min
-- 5-minute aggregated CPU/memory per process name + username.
-- Rolled up from process_history_1min every 5 minutes.
-- Retention: 30 days (cleanup_aggregates in aggregator.py)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `process_history_5min` (
    `timestamp`     DATETIME(0)       NOT NULL,
    `process_name`  VARCHAR(255)      NOT NULL,
    `username`      VARCHAR(100)      NOT NULL DEFAULT '',
    `cpu_avg`       DECIMAL(5,2)      NOT NULL,
    `cpu_max`       DECIMAL(5,2)      NOT NULL,
    `mem_avg_mb`    DECIMAL(8,2)      NOT NULL,
    `mem_max_mb`    DECIMAL(8,2)      NOT NULL,
    `sample_count`  TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    PRIMARY KEY (`timestamp`, `process_name`(191), `username`(100)),
    KEY `idx_name_time`  (`process_name`(191), `timestamp`),
    KEY `idx_time`        (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='5-minute process CPU/memory aggregates. 30-day retention.';

-- ==DOWN==

DROP TABLE IF EXISTS `process_history_5min`;
DROP TABLE IF EXISTS `process_history_1min`;
