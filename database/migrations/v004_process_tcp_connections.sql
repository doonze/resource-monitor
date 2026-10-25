-- ============================================================
-- Migration:    v004 — Per-Process TCP Connection Tracking
-- Description:  Add tcp_conns to process_snapshots so the collector
--               can store how many ESTABLISHED TCP connections each
--               process owns at snapshot time. Add tcp_conns_avg to
--               process_history_1min and process_history_5min so those
--               counts survive aggregation and are queryable for
--               historical network spike attribution.
-- App Version:  1.4.0
-- Rollback Risk: low
-- Rollback Note: Drops tcp_conns/tcp_conns_avg from all three tables.
--               Accumulated per-process connection history is lost.
--               The collector stops writing tcp_conns after restart —
--               no errors, the column just goes away.
-- ============================================================

-- ==UP==

ALTER TABLE `process_snapshots`
    ADD COLUMN `tcp_conns` SMALLINT UNSIGNED DEFAULT NULL
    COMMENT 'ESTABLISHED TCP connections owned by this PID at snapshot time';

ALTER TABLE `process_history_1min`
    ADD COLUMN `tcp_conns_avg` DECIMAL(5,1) DEFAULT NULL
    COMMENT 'Avg ESTABLISHED TCP connections per process in this 1-min window';

ALTER TABLE `process_history_5min`
    ADD COLUMN `tcp_conns_avg` DECIMAL(5,1) DEFAULT NULL
    COMMENT 'Avg tcp_conns_avg from process_history_1min in this 5-min window';

-- ==DOWN==

ALTER TABLE `process_snapshots`    DROP COLUMN IF EXISTS `tcp_conns`;
ALTER TABLE `process_history_1min` DROP COLUMN IF EXISTS `tcp_conns_avg`;
ALTER TABLE `process_history_5min` DROP COLUMN IF EXISTS `tcp_conns_avg`;
