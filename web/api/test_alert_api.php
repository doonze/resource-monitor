<?php
/**
 * API: Alert Test Email Queue  —  /web/api/test_alert.php
 *
 * Web UI queues a test; the collector process picks it up and sends via
 * smtplib — the exact same code path as real alert emails.
 *
 * POST { recipient_id: N }    → queue test for one recipient
 *                               → { queue_id: N }
 *
 * POST { recipient_id: null } → queue tests for all enabled recipients
 *                               → { queued: [{ recipient_id, queue_id }, ...] }
 *
 * GET  ?queue_id=N            → poll result
 *                               → { status, error_message, completed_at }
 *                                 status: pending | sending | success | failed
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ── Poll a queued test ────────────────────────────────────────────────────────
if ($method === 'GET') {
    $queueId = (int)($_GET['queue_id'] ?? 0);
    if (!$queueId) jsonError('queue_id required', 400);

    $row = queryOne(
        "SELECT status, error_message, completed_at FROM alert_test_queue WHERE id = ?",
        [$queueId]
    );
    if (!$row) jsonError('Queue item not found', 404);

    jsonResponse($row);
}

if ($method !== 'POST') {
    jsonError('Method not allowed', 405);
}

// ── Queue a new test ──────────────────────────────────────────────────────────
$d = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if (isset($d['recipient_id']) && $d['recipient_id'] !== null) {
        // Single recipient
        $rec = queryOne(
            "SELECT id FROM alert_recipients WHERE id = ? AND enabled = 1",
            [(int)$d['recipient_id']]
        );
        if (!$rec) jsonError('Recipient not found or not enabled', 404);

        execute("INSERT INTO alert_test_queue (recipient_id) VALUES (?)", [(int)$d['recipient_id']]);
        jsonResponse(['queue_id' => (int)lastInsertId()]);

    } else {
        // All enabled recipients
        $recipients = query("SELECT id FROM alert_recipients WHERE enabled = 1 ORDER BY id");
        if (empty($recipients)) jsonError('No enabled recipients found', 404);

        $queued = [];
        foreach ($recipients as $rec) {
            execute("INSERT INTO alert_test_queue (recipient_id) VALUES (?)", [(int)$rec['id']]);
            $queued[] = [
                'recipient_id' => (int)$rec['id'],
                'queue_id'     => (int)lastInsertId(),
            ];
        }
        jsonResponse(['queued' => $queued]);
    }

} catch (Exception $e) {
    jsonError(DEBUG_MODE ? $e->getMessage() : 'Server error', 500);
}
