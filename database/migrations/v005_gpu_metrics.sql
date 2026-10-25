-- ============================================================
-- Migration:    v005 — NVIDIA GPU Telemetry
-- Description:  Add raw and aggregated GPU telemetry tables
-- App Version:  1.4.0
-- Rollback Risk: medium
-- Rollback Note: Drops the GPU tables and all collected GPU samples.
-- ============================================================

-- ==UP==

CREATE TABLE IF NOT EXISTS gpu_metrics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    timestamp DATETIME(3) NOT NULL,
    gpu_id INT UNSIGNED NOT NULL,
    gpu_name VARCHAR(255) NOT NULL,
    gpu_percent DECIMAL(5,2) DEFAULT NULL,
    memory_used_mb INT UNSIGNED DEFAULT NULL,
    memory_total_mb INT UNSIGNED DEFAULT NULL,
    memory_percent DECIMAL(5,2) DEFAULT NULL,
    temperature_celsius DECIMAL(5,2) DEFAULT NULL,
    power_watts DECIMAL(8,2) DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_gpu_timestamp (gpu_id, timestamp),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gpu_metrics_1min (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    timestamp DATETIME NOT NULL,
    gpu_id INT UNSIGNED NOT NULL,
    gpu_name VARCHAR(255) NOT NULL,
    gpu_percent_avg DECIMAL(5,2) DEFAULT NULL,
    gpu_percent_max DECIMAL(5,2) DEFAULT NULL,
    memory_used_mb_avg DECIMAL(10,2) DEFAULT NULL,
    memory_used_mb_max INT UNSIGNED DEFAULT NULL,
    memory_total_mb INT UNSIGNED DEFAULT NULL,
    memory_percent_avg DECIMAL(5,2) DEFAULT NULL,
    memory_percent_max DECIMAL(5,2) DEFAULT NULL,
    temperature_celsius_avg DECIMAL(5,2) DEFAULT NULL,
    temperature_celsius_max DECIMAL(5,2) DEFAULT NULL,
    power_watts_avg DECIMAL(8,2) DEFAULT NULL,
    power_watts_max DECIMAL(8,2) DEFAULT NULL,
    sample_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gpu_timestamp (gpu_id, timestamp),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gpu_metrics_5min LIKE gpu_metrics_1min;
CREATE TABLE IF NOT EXISTS gpu_metrics_1hour LIKE gpu_metrics_1min;

-- ==DOWN==

DROP TABLE IF EXISTS gpu_metrics_1hour;
DROP TABLE IF EXISTS gpu_metrics_5min;
DROP TABLE IF EXISTS gpu_metrics_1min;
DROP TABLE IF EXISTS gpu_metrics;
