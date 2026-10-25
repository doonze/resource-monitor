-- ============================================================
-- Migration:    v003 — TCP Connection Count History
-- Description:  Add tcp_conn_avg to metrics_1min, metrics_5min, metrics_1hour
--               so system-wide active TCP connection counts are aggregated
--               alongside CPU/memory/disk/network and exposed on the
--               History page chart. Enables visual correlation between
--               connection count and network I/O to validate whether
--               per-process connection count is a useful spike indicator.
-- App Version:  1.3.0
-- Rollback Risk: low
-- Rollback Note: Drops tcp_conn_avg from all three aggregate tables.
--               Any accumulated connection-count history is lost. The
--               aggregator will stop writing to these columns until the
--               migration is re-applied.
-- ============================================================

-- ==UP==

ALTER TABLE `metrics_1min`
    ADD COLUMN `tcp_conn_avg` INT UNSIGNED DEFAULT NULL
    COMMENT 'Avg tcp_established count in this 1-min window';

ALTER TABLE `metrics_5min`
    ADD COLUMN `tcp_conn_avg` INT UNSIGNED DEFAULT NULL
    COMMENT 'Avg tcp_conn_avg from metrics_1min in this 5-min window';

ALTER TABLE `metrics_1hour`
    ADD COLUMN `tcp_conn_avg` INT UNSIGNED DEFAULT NULL
    COMMENT 'Avg tcp_conn_avg from metrics_5min in this 1-hour window';

-- ==DOWN==

ALTER TABLE `metrics_1min`  DROP COLUMN IF EXISTS `tcp_conn_avg`;
ALTER TABLE `metrics_5min`  DROP COLUMN IF EXISTS `tcp_conn_avg`;
ALTER TABLE `metrics_1hour` DROP COLUMN IF EXISTS `tcp_conn_avg`;
