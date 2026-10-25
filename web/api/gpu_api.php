<?php
/**
 * API Endpoint: Latest GPU telemetry, one row per detected GPU.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/db.php';

try {
    $rows = query("
        SELECT
            DATE_FORMAT(g.timestamp, '%Y-%m-%d %H:%i:%s') AS timestamp,
            g.gpu_id,
            g.gpu_name,
            g.gpu_percent,
            g.memory_used_mb,
            g.memory_total_mb,
            g.memory_percent,
            g.temperature_celsius,
            g.power_watts
        FROM gpu_metrics AS g
        INNER JOIN (
            SELECT gpu_id, MAX(id) AS latest_id
            FROM gpu_metrics
            GROUP BY gpu_id
        ) AS latest ON latest.latest_id = g.id
        ORDER BY g.gpu_id ASC
    ");

    $devices = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['gpu_id'],
            'name' => $row['gpu_name'],
            'timestamp' => $row['timestamp'],
            'utilization_percent' => $row['gpu_percent'] !== null ? (float) $row['gpu_percent'] : null,
            'memory_used_mb' => $row['memory_used_mb'] !== null ? (int) $row['memory_used_mb'] : null,
            'memory_total_mb' => $row['memory_total_mb'] !== null ? (int) $row['memory_total_mb'] : null,
            'memory_percent' => $row['memory_percent'] !== null ? (float) $row['memory_percent'] : null,
            'temperature_celsius' => $row['temperature_celsius'] !== null ? (float) $row['temperature_celsius'] : null,
            'power_watts' => $row['power_watts'] !== null ? (float) $row['power_watts'] : null,
        ];
    }, $rows);

    jsonResponse([
        'success' => true,
        'devices' => $devices,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    if (DEBUG_MODE) {
        jsonError($e->getMessage(), 500);
    }
    jsonError('GPU data unavailable', 500);
}
