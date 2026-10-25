<?php
/**
 * API Endpoint: Historical Data
 * Returns time-series data for charting
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../includes/db.php';

// Get parameters
$range = $_GET['range'] ?? DEFAULT_TIME_RANGE;  // 1h, 6h, 24h, 7d, 30d
$metric = $_GET['metric'] ?? 'all';  // all, cpu, memory, disk, network, temp

// Determine which table and time range to query
function getQueryConfig($range) {
    switch ($range) {
        case '1h':
            return [
                'table'    => 'metrics_1min',
                'interval' => '1 HOUR',
                'has_min'  => true
            ];
        case '6h':
            return [
                'table'    => 'metrics_1min',
                'interval' => '6 HOUR',
                'has_min'  => true
            ];
        case '24h':
            return [
                'table'    => 'metrics_1min',
                'interval' => '24 HOUR',
                'has_min'  => true
            ];
        case '7d':
            return [
                'table'    => 'metrics_5min',
                'interval' => '7 DAY',
                'has_min'  => false  // metrics_5min has no min columns
            ];
        case '30d':
            return [
                'table'    => 'metrics_5min',
                'interval' => '30 DAY',
                'has_min'  => false
            ];
        case '1year':
            return [
                'table'    => 'metrics_1hour',
                'interval' => '1 YEAR',
                'has_min'  => false
            ];
        default:
            return [
                'table'    => 'metrics_1min',
                'interval' => '24 HOUR',
                'has_min'  => true
            ];
    }
}

$config = getQueryConfig($range);

try {
    $response = [
        'success' => true,
        'range'   => $range,
        'config'  => $config,
        'data'    => []
    ];
    
    $db = getDB();
    
    // All ranges now query aggregated tables (metrics_1min or metrics_5min).
    // metrics_1min has avg/max/min; metrics_5min has avg/max only.
    $min_cpu = $config['has_min'] ? 'cpu_percent_min' : 'cpu_percent_avg';
    $min_mem = $config['has_min'] ? 'mem_percent_min' : 'mem_percent_avg';
    
    $sql = "SELECT
        DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s') AS timestamp,
        cpu_percent_avg  AS cpu_avg,
        cpu_percent_max  AS cpu_max,
        {$min_cpu}       AS cpu_min,
        mem_percent_avg  AS mem_avg,
        mem_percent_max  AS mem_max,
        {$min_mem}       AS mem_min,
        disk_read_avg    AS disk_read,
        disk_write_avg   AS disk_write,
        net_sent_avg     AS net_sent,
        net_recv_avg     AS net_recv,
        load_1min_avg    AS load_avg,
        tcp_conn_avg     AS tcp_conn
    FROM {$config['table']}
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL {$config['interval']})
    ORDER BY timestamp ASC";
    
    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for Chart.js
    $timestamps  = [];
    $cpu_avg     = []; $cpu_max  = []; $cpu_min  = [];
    $mem_avg     = []; $mem_max  = []; $mem_min  = [];
    $disk_read   = []; $disk_write = [];
    $net_sent    = []; $net_recv   = [];
    $load_avg    = [];
    $tcp_conn    = [];
    
    foreach ($rows as $row) {
        $timestamps[]  = $row['timestamp'];
        $cpu_avg[]     = floatval($row['cpu_avg']);
        $cpu_max[]     = floatval($row['cpu_max']);
        $cpu_min[]     = floatval($row['cpu_min']);
        $mem_avg[]     = floatval($row['mem_avg']);
        $mem_max[]     = floatval($row['mem_max']);
        $mem_min[]     = floatval($row['mem_min']);
        $disk_read[]   = intval($row['disk_read']  ?? 0);
        $disk_write[]  = intval($row['disk_write'] ?? 0);
        $net_sent[]    = intval($row['net_sent']   ?? 0);
        $net_recv[]    = intval($row['net_recv']   ?? 0);
        $load_avg[]    = floatval($row['load_avg'] ?? 0);
        $tcp_conn[]    = isset($row['tcp_conn']) && $row['tcp_conn'] !== null
                            ? intval($row['tcp_conn']) : null;
    }
    
    $response['data'] = [
        'timestamps'  => $timestamps,
        'cpu'         => ['avg' => $cpu_avg,   'max' => $cpu_max,   'min' => $cpu_min],
        'memory'      => ['avg' => $mem_avg,   'max' => $mem_max,   'min' => $mem_min],
        'disk_io'     => ['read' => $disk_read, 'write' => $disk_write],
        'network'     => ['sent' => $net_sent,  'recv' => $net_recv],
        'load'        => $load_avg,
        'connections' => $tcp_conn,
    ];
    
    // GPU telemetry uses matching rollup resolutions for each history range.
    $gpu_table = [
        'metrics_1min' => 'gpu_metrics_1min',
        'metrics_5min' => 'gpu_metrics_5min',
        'metrics_1hour' => 'gpu_metrics_1hour',
    ][$config['table']] ?? 'gpu_metrics_1min';

    $gpu_sql = "SELECT
        DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s') AS ts,
        gpu_id, gpu_name,
        gpu_percent_avg AS utilization,
        memory_used_mb_avg AS memory_used_mb,
        memory_total_mb,
        memory_percent_avg AS memory_percent,
        temperature_celsius_avg AS temperature_celsius,
        power_watts_avg AS power_watts
    FROM {$gpu_table}
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL {$config['interval']})
    ORDER BY gpu_id ASC, timestamp ASC";

    $gpu_rows = $db->query($gpu_sql)->fetchAll(PDO::FETCH_ASSOC);
    $gpu_devices = [];
    foreach ($gpu_rows as $row) {
        $id = intval($row['gpu_id']);
        if (!isset($gpu_devices[$id])) {
            $gpu_devices[$id] = [
                'id' => $id,
                'name' => $row['gpu_name'],
                'timestamps' => [],
                'utilization' => [],
                'memory_used_mb' => [],
                'memory_total_mb' => [],
                'memory_percent' => [],
                'temperature_celsius' => [],
                'power_watts' => [],
            ];
        }
        $gpu_devices[$id]['timestamps'][] = $row['ts'];
        foreach (['utilization', 'memory_used_mb', 'memory_total_mb', 'memory_percent', 'temperature_celsius', 'power_watts'] as $field) {
            $gpu_devices[$id][$field][] = $row[$field] !== null ? floatval($row[$field]) : null;
        }
    }
    $response['data']['gpu'] = array_values($gpu_devices);

    // Temperature — per-sensor from aggregate tables.
    // 1h/6h/24h/7d  → temps_1min (7-day retention)
    // 30d/1year     → temps_5min (1-year retention)
    if ($metric === 'all' || $metric === 'temp') {
        $temp_table = in_array($range, ['30d', '1year']) ? 'temps_5min' : 'temps_1min';

        $temp_sql = "SELECT
            DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s') AS ts,
            sensor_label,
            temp_avg,
            temp_max
        FROM {$temp_table}
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL {$config['interval']})
        ORDER BY timestamp ASC, sensor_label ASC";

        $temp_stmt = $db->query($temp_sql);
        $temp_rows = $temp_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pivot into per-sensor structure for Chart.js multi-line rendering.
        // Each sensor gets its own timestamps + avg + max arrays.
        $sensors = [];
        foreach ($temp_rows as $row) {
            $label = $row['sensor_label'];
            if (!isset($sensors[$label])) {
                $sensors[$label] = ['timestamps' => [], 'avg' => [], 'max' => []];
            }
            $sensors[$label]['timestamps'][] = $row['ts'];
            $sensors[$label]['avg'][]        = floatval($row['temp_avg']);
            $sensors[$label]['max'][]        = floatval($row['temp_max']);
        }

        $response['data']['temperature'] = [
            'sensors' => $sensors
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
