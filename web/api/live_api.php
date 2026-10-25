<?php
/**
 * API Endpoint: Live Metrics
 * Returns current system metrics
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    // Get latest system metrics
    $metrics = queryOne("SELECT * FROM v_latest_metrics");
    
    if (!$metrics) {
        jsonError('No data available', 404);
    }
    
    // Get latest CPU core usage
    $cores = query("
        SELECT core_number, usage_percent 
        FROM cpu_cores 
        WHERE timestamp = (SELECT MAX(timestamp) FROM cpu_cores)
        ORDER BY core_number
    ");
    
    // Get latest temperatures
    $temps = query("
        SELECT sensor_label, temperature_celsius, high_threshold, critical_threshold
        FROM cpu_temps
        WHERE timestamp = (SELECT MAX(timestamp) FROM cpu_temps)
        ORDER BY sensor_label
    ");
    
    // Get latest disk usage
    $disks = query("
        SELECT device, mount_point as mountpoint, total_bytes, used_bytes, free_bytes, percent_used
        FROM disk_usage
        WHERE timestamp = (SELECT MAX(timestamp) FROM disk_usage)
        ORDER BY mount_point
    ");
    
    // Get collector status
    $collector = queryOne("
        SELECT hostname, last_heartbeat, status, 
               TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) as seconds_ago
        FROM collector_status 
        ORDER BY last_heartbeat DESC 
        LIMIT 1
    ");
    
    // Get latest network connections
    $connections = queryOne("
        SELECT tcp_established, tcp_listen, tcp_time_wait, tcp_close_wait, tcp_other, udp_connections
        FROM network_connections
        WHERE timestamp = (SELECT MAX(timestamp) FROM network_connections)
    ");
    
    // Format response
    $response = [
        'timestamp' => $metrics['timestamp'],
        'system' => [
            'cpu' => [
                'percent' => (float) $metrics['cpu_percent'],
                'cores' => array_map(function($c) {
                    return [
                        'number' => (int) $c['core_number'],
                        'percent' => (float) $c['usage_percent']
                    ];
                }, $cores),
                'load' => [
                    '1min' => (float) $metrics['load_1min'],
                    '5min' => (float) $metrics['load_5min'],
                    '15min' => (float) $metrics['load_15min']
                ]
            ],
            'memory' => [
                'total' => (int) $metrics['mem_total'],
                'used' => (int) $metrics['mem_used'],
                'available' => (int) $metrics['mem_available'],
                'percent' => (float) $metrics['mem_percent']
            ],
            'swap' => [
                'total' => (int) $metrics['swap_total'],
                'used' => (int) $metrics['swap_used'],
                'percent' => (float) $metrics['swap_percent']
            ],
            'disk_io' => [
                'read_bytes' => (int) $metrics['disk_read_bytes'],
                'write_bytes' => (int) $metrics['disk_write_bytes'],
                'read_count' => (int) $metrics['disk_read_count'],
                'write_count' => (int) $metrics['disk_write_count']
            ],
            'network' => [
                'bytes_sent' => (int) $metrics['net_bytes_sent'],
                'bytes_recv' => (int) $metrics['net_bytes_recv'],
                'packets_sent' => (int) $metrics['net_packets_sent'],
                'packets_recv' => (int) $metrics['net_packets_recv']
            ],
            'uptime_seconds' => (int) $metrics['uptime_seconds'],
            'process_count' => (int) $metrics['process_count']
        ],
        'temperatures' => array_map(function($t) {
            return [
                'label' => $t['sensor_label'],
                'current' => (float) $t['temperature_celsius'],
                'high' => $t['high_threshold'] ? (float) $t['high_threshold'] : null,
                'critical' => $t['critical_threshold'] ? (float) $t['critical_threshold'] : null
            ];
        }, $temps),
        'disks' => array_map(function($d) {
            return [
                'device' => $d['device'],
                'mountpoint' => $d['mountpoint'],
                'total' => (int) $d['total_bytes'],
                'used' => (int) $d['used_bytes'],
                'free' => (int) $d['free_bytes'],
                'percent' => (float) $d['percent_used']
            ];
        }, $disks),
        'collector' => $collector ? [
            'hostname' => $collector['hostname'],
            'last_heartbeat' => $collector['last_heartbeat'],
            'status' => $collector['status'],
            'seconds_ago' => (int) $collector['seconds_ago'],
            'is_healthy' => (int) $collector['seconds_ago'] < 30
        ] : null,
        'connections' => $connections ? [
            'tcp_established' => (int) $connections['tcp_established'],
            'tcp_listen' => (int) $connections['tcp_listen'],
            'tcp_time_wait' => (int) $connections['tcp_time_wait'],
            'tcp_close_wait' => (int) $connections['tcp_close_wait'],
            'tcp_other' => (int) $connections['tcp_other'],
            'udp' => (int) $connections['udp_connections'],
            'total' => (int) ($connections['tcp_established'] + $connections['tcp_listen'] + 
                             $connections['tcp_time_wait'] + $connections['tcp_close_wait'] + 
                             $connections['tcp_other'] + $connections['udp_connections'])
        ] : null
    ];
    
    jsonResponse($response);
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        jsonError($e->getMessage(), 500);
    } else {
        jsonError('Internal server error', 500);
    }
}
