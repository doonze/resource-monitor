-- ============================================================
-- Migration:    v001 — Drop Legacy DB Aggregation
-- Description:  Remove MariaDB events and stored procedures replaced by Python aggregator
-- App Version:  1.1.0
-- Rollback Risk: high
-- Rollback Note: Cannot be automatically reversed. The 8 events and 12 stored
--                procedures are no longer needed — all aggregation and cleanup
--                is now handled by collector/aggregator.py. To restore them,
--                re-apply from database/rm_schema_dump.sql manually.
--                Do NOT roll this back unless you are also reverting to a
--                collector version without aggregator.py.
-- ============================================================

-- ==UP==

-- ----------------------------------------------------------------
-- Drop the 8 scheduled events
-- (were already DISABLED on 2026-05-12 when Python aggregation
--  went live — dropping them completes the cleanup)
-- ----------------------------------------------------------------
DROP EVENT IF EXISTS `cleanup_old_aggregates`;
DROP EVENT IF EXISTS `cleanup_old_samples`;
DROP EVENT IF EXISTS `cleanup_processes_15min`;
DROP EVENT IF EXISTS `event_aggregate_1hour`;
DROP EVENT IF EXISTS `event_aggregate_1min`;
DROP EVENT IF EXISTS `event_aggregate_5min`;
DROP EVENT IF EXISTS `event_aggregate_temps_1min`;
DROP EVENT IF EXISTS `event_aggregate_temps_5min`;

-- ----------------------------------------------------------------
-- Drop the 12 stored procedures
-- ----------------------------------------------------------------
DROP PROCEDURE IF EXISTS `aggregate_temps_to_1min`;
DROP PROCEDURE IF EXISTS `aggregate_temps_to_5min`;
DROP PROCEDURE IF EXISTS `aggregate_to_1hour`;
DROP PROCEDURE IF EXISTS `aggregate_to_1min`;
DROP PROCEDURE IF EXISTS `aggregate_to_5min`;
DROP PROCEDURE IF EXISTS `cleanup_old_1hour`;
DROP PROCEDURE IF EXISTS `cleanup_old_1min`;
DROP PROCEDURE IF EXISTS `cleanup_old_5min`;
DROP PROCEDURE IF EXISTS `cleanup_old_samples`;
DROP PROCEDURE IF EXISTS `cleanup_old_temps_1min`;
DROP PROCEDURE IF EXISTS `cleanup_old_temps_5min`;
DROP PROCEDURE IF EXISTS `cleanup_process_snapshots`;

-- ==DOWN==

-- ----------------------------------------------------------------
-- ⚠ High-risk rollback — cannot be automatically reversed.
-- Restoring the 8 events and 12 stored procedures requires manual
-- intervention. See database/rm_schema_dump.sql for the original
-- definitions, or re-apply v000 on a fresh install.
-- The Python aggregator (collector/aggregator.py) must also be
-- reverted or disabled before the DB events would be useful again.
-- ----------------------------------------------------------------
SELECT 'v001 rollback: events and procedures must be manually restored from rm_schema_dump.sql. See Rollback Note in migration header.' AS rollback_warning
