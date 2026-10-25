<?php
/**
 * Alerts API  —  /web/api/alerts.php
 *
 * Reads the collector's canonical alert_state table — alerts persist whether
 * or not the dashboard was open when they fired.  Supports permanent,
 * per-firing dismissals backed by the alert_dismissals table.
 *
 * GET  → { alerts: [...], generated_at }
 *         Returns is_active alerts, filtered by dashboard display settings,
 *         with already-dismissed firings excluded.
 *
 * POST { action: 'dismiss',     alert_id, first_fired_at }
 *      { action: 'dismiss_all', alerts: [{alert_id, first_fired_at}, ...] }
 *         Inserts into alert_dismissals (INSERT IGNORE — idempotent).
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// ─────────────────────────────────────────────────────────────────────────────
// POST  —  record dismissals
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    $toDismiss = [];

    if (($d['action'] ?? '') === 'dismiss' && !empty($d['alert_id'])) {
        $toDismiss[] = [$d['alert_id'], $d['first_fired_at'] ?? null];

    } elseif (($d['action'] ?? '') === 'dismiss_all' && is_array($d['alerts'] ?? null)) {
        foreach ($d['alerts'] as $a) {
            if (!empty($a['alert_id'])) {
                $toDismiss[] = [$a['alert_id'], $a['first_fired_at'] ?? null];
            }
        }
    } else {
        jsonError('Invalid action', 400);
    }

    $inserted = 0;
    foreach ($toDismiss as [$aid, $firedAt]) {
        if (!$aid || !$firedAt) continue;
        try {
            execute(
                "INSERT IGNORE INTO alert_dismissals (alert_id, first_fired_at)
                 VALUES (?, ?)",
                [$aid, $firedAt]
            );
            $inserted++;
        } catch (Exception $e) {}
    }

    jsonResponse(['dismissed' => $inserted]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET  —  fetch active, non-dismissed alerts with dash-settings filtering
// ─────────────────────────────────────────────────────────────────────────────

// Load dashboard display preferences from app_settings
$dashDefaults = [
    'dash_show_cpu'         => 1,
    'dash_show_memory'      => 1,
    'dash_show_disk_io'     => 1,
    'dash_show_network'     => 1,
    'dash_show_temp'        => 1,
    'dash_show_disk_full'   => 1,
    'dash_show_disk_change' => 1,
];
try {
    $keys = "'" . implode("','", array_keys($dashDefaults)) . "'";
    foreach (query("SELECT setting_key, setting_value FROM app_settings
                     WHERE setting_key IN ({$keys})") as $r) {
        $dashDefaults[$r['setting_key']] = (int) $r['setting_value'];
    }
} catch (Exception $e) {}

// Fetch all active alerts that have NOT been dismissed for this specific firing
try {
    $rows = query(
        "SELECT s.alert_id, s.category, s.severity, s.title, s.message,
                s.first_fired_at, s.last_fired_at
         FROM   alert_state s
         LEFT   JOIN alert_dismissals d
                ON  d.alert_id       = s.alert_id
                AND d.first_fired_at = s.first_fired_at
         WHERE  s.is_active = 1
           AND  d.id IS NULL
         ORDER  BY CASE s.severity WHEN 'critical' THEN 0 ELSE 1 END,
                   s.first_fired_at ASC"
    );
} catch (Exception $e) {
    jsonResponse(['alerts' => [], 'generated_at' => date('c')]);
}

// ── Map alert_id patterns → dashboard setting key ─────────────────────────────
//   current_cpu_percent_critical  → dash_show_cpu
//   sustained_net_recv            → dash_show_network
//   full_disk___                  → dash_show_disk_full
//   change_disk___                → dash_show_disk_change

function alertShowKey(string $alertId, string $category): string {
    if ($category === 'disk_full')   return 'dash_show_disk_full';
    if ($category === 'disk_change') return 'dash_show_disk_change';
    if (str_contains($alertId, 'cpu_percent'))  return 'dash_show_cpu';
    if (str_contains($alertId, 'mem_percent'))  return 'dash_show_memory';
    if (str_contains($alertId, 'disk_io'))      return 'dash_show_disk_io';
    if (str_contains($alertId, 'net_'))         return 'dash_show_network';
    if (str_contains($alertId, 'temp'))         return 'dash_show_temp';
    return '';   // unknown — always show
}

$alerts = [];
foreach ($rows as $r) {
    $key = alertShowKey($r['alert_id'], $r['category']);
    if ($key !== '' && empty($dashDefaults[$key])) {
        continue;   // user has hidden this category
    }
    $alerts[] = [
        'alert_id'       => $r['alert_id'],
        'category'       => $r['category'],
        'severity'       => $r['severity'],
        'title'          => $r['title'],
        'message'        => $r['message'],
        'first_fired_at' => $r['first_fired_at'],   // needed by dismiss POST
        'last_fired_at'  => $r['last_fired_at'],
    ];
}

jsonResponse(['alerts' => $alerts, 'generated_at' => date('c')]);
