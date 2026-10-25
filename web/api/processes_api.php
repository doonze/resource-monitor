<?php
/**
 * API Endpoint: Top Processes
 * Returns top processes by CPU and Memory
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $sortBy = $_GET['sort'] ?? 'cpu';  // 'cpu' or 'memory'
    $limit = min((int) ($_GET['limit'] ?? 50), 100);  // Default 50, Max 100
    
    // Get the most recent timestamp
    $latestTimestamp = queryOne("SELECT MAX(timestamp) as ts FROM process_snapshots");
    
    if (!$latestTimestamp || !$latestTimestamp['ts']) {
        jsonResponse([
            'success' => true,
            'data' => []
        ]);
    }
    
    if ($sortBy === 'memory') {
        $processes = query("
            SELECT 
                pid,
                name,
                username,
                cpu_percent,
                mem_rss,
                mem_percent,
                status,
                num_threads
            FROM process_snapshots
            WHERE timestamp = ?
            ORDER BY mem_percent DESC
            LIMIT ?
        ", [$latestTimestamp['ts'], $limit]);
    } else {
        $processes = query("
            SELECT 
                pid,
                name,
                username,
                cpu_percent,
                mem_rss,
                mem_percent,
                status,
                num_threads
            FROM process_snapshots
            WHERE timestamp = ?
            ORDER BY cpu_percent DESC
            LIMIT ?
        ", [$latestTimestamp['ts'], $limit]);
    }
    
    $response = array_map(function($p) {
        return [
            'pid' => (int) $p['pid'],
            'name' => $p['name'],
            'username' => $p['username'],
            'cpu_percent' => (float) $p['cpu_percent'],
            'mem_rss' => (int) $p['mem_rss'],
            'mem_percent' => (float) $p['mem_percent'],
            'status' => $p['status'],
            'threads' => (int) $p['num_threads']
        ];
    }, $processes);
    
    jsonResponse([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        jsonError($e->getMessage(), 500);
    } else {
        jsonError('Internal server error', 500);
    }
}
