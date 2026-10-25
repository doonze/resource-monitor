<?php
/**
 * API Endpoint: Process Details (On-Demand)
 * Returns detailed information about a specific process by PID
 * Fetches live data from the system, not from database
 */

header('Content-Type: application/json');

// This endpoint doesn't need database - it queries the system directly
// But we include it for the jsonResponse helper
require_once __DIR__ . '/../includes/db.php';

try {
    $pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
    
    if ($pid <= 0) {
        jsonError('Invalid PID', 400);
    }
    
    // Execute Python script to get process details
    // We use Python with psutil because it's already used by the collector
    $pythonScript = '/opt/resource-monitor/collector/get_process_details.py';
    
    if (!file_exists($pythonScript)) {
        jsonError('Process details script not found', 500);
    }
    
    // Execute and capture output
    $command = escapeshellcmd("python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($pid));
    $output = shell_exec($command . " 2>&1");
    
    if ($output === null) {
        jsonError('Failed to execute process details script', 500);
    }
    
    // Parse JSON output from Python script
    $data = json_decode($output, true);
    
    if ($data === null) {
        jsonError('Failed to parse process details: ' . $output, 500);
    }
    
    if (isset($data['error'])) {
        jsonError($data['error'], isset($data['code']) ? $data['code'] : 404);
    }
    
    jsonResponse([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        jsonError($e->getMessage(), 500);
    } else {
        jsonError('Internal server error', 500);
    }
}
