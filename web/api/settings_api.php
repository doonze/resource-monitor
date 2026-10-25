<?php
/**
 * API Endpoint: Settings
 * Manages application settings and thresholds
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - Fetch settings
    if ($method === 'GET') {
        // Get alert rules (thresholds)
        $rules = query("SELECT * FROM alert_rules ORDER BY name");
        
        jsonResponse([
            'thresholds' => array_map(function($r) {
                return [
                    'id' => (int) $r['id'],
                    'name' => $r['name'],
                    'metric' => $r['metric'],
                    'condition' => $r['condition'],
                    'threshold' => (float) $r['threshold'],
                    'duration_seconds' => (int) $r['duration_seconds'],
                    'enabled' => (bool) $r['enabled']
                ];
            }, $rules)
        ]);
    }
    
    // POST - Create new threshold
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['name'], $data['metric'], $data['threshold'])) {
            jsonError('Missing required fields', 400);
        }
        
        execute("
            INSERT INTO alert_rules (name, metric, condition, threshold, duration_seconds, enabled)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [
            $data['name'],
            $data['metric'],
            $data['condition'] ?? 'gt',
            $data['threshold'],
            $data['duration_seconds'] ?? 60,
            $data['enabled'] ?? true
        ]);
        
        jsonResponse(['id' => lastInsertId(), 'message' => 'Threshold created'], 201);
    }
    
    // PUT - Update threshold
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            jsonError('Missing ID', 400);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (isset($data['threshold'])) {
            $updates[] = 'threshold = ?';
            $params[] = $data['threshold'];
        }
        if (isset($data['enabled'])) {
            $updates[] = 'enabled = ?';
            $params[] = $data['enabled'] ? 1 : 0;
        }
        if (isset($data['duration_seconds'])) {
            $updates[] = 'duration_seconds = ?';
            $params[] = $data['duration_seconds'];
        }
        
        if (empty($updates)) {
            jsonError('No fields to update', 400);
        }
        
        $params[] = $data['id'];
        
        execute("
            UPDATE alert_rules 
            SET " . implode(', ', $updates) . "
            WHERE id = ?
        ", $params);
        
        jsonResponse(['message' => 'Threshold updated']);
    }
    
    // DELETE - Delete threshold
    elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            jsonError('Missing ID', 400);
        }
        
        execute("DELETE FROM alert_rules WHERE id = ?", [$id]);
        
        jsonResponse(['message' => 'Threshold deleted']);
    }
    
    else {
        jsonError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        jsonError($e->getMessage(), 500);
    } else {
        jsonError('Internal server error', 500);
    }
}
