<?php
/**
 * API: Alert Recipients  —  /web/api/alert_recipients.php
 *
 * Manages email recipients for server-side alert notifications.
 * Also handles GET/PUT for email-related app_settings.
 *
 * GET    → { recipients: [...], config: { email_from, disk_change_pct,
 *             disk_change_gb, disk_full_pct } }
 * POST   → create recipient        { email, display_name, ...flags }
 * PUT    → update recipient or     { id, ...fields }
 *           update config          { config: { key: value, ... } }
 * DELETE → delete recipient        ?id=N
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ── config keys that live in app_settings ────────────────────────────────────
const CONFIG_KEYS = [
    'email_from', 'email_method',
    'disk_change_pct', 'disk_change_gb', 'disk_full_pct',
    // Dashboard alert banner display toggles (1 = show, 0 = hide)
    'dash_show_cpu', 'dash_show_memory', 'dash_show_disk_io',
    'dash_show_network', 'dash_show_temp',
    'dash_show_disk_full', 'dash_show_disk_change',
    // Temperature display unit — used by collector to format alert messages
    'temp_unit',
];

function loadEmailConfig(): array {
    $defaults = [
        'email_from'           => 'monitor@localhost',
        'email_method'         => 'mail',
        'disk_change_pct'      => 5,
        'disk_change_gb'       => 50,
        'disk_full_pct'        => 90,
        'dash_show_cpu'        => 1,
        'dash_show_memory'     => 1,
        'dash_show_disk_io'    => 1,
        'dash_show_network'    => 1,
        'dash_show_temp'       => 1,
        'dash_show_disk_full'  => 1,
        'dash_show_disk_change'=> 1,
        'temp_unit'            => 'F',
    ];
    try {
        $keys = "'" . implode("','", CONFIG_KEYS) . "'";
        foreach (query("SELECT setting_key, setting_value FROM app_settings
                         WHERE setting_key IN ({$keys})") as $r) {
            $defaults[$r['setting_key']] = is_numeric($r['setting_value'])
                ? (strpos($r['setting_value'], '.') !== false ? (float)$r['setting_value'] : (int)$r['setting_value'])
                : $r['setting_value'];
        }
    } catch (Exception $e) {}
    return $defaults;
}

function castRecipient(array $r): array {
    return [
        'id'                => (int)  $r['id'],
        'display_name'      =>        $r['display_name'],
        'email'             =>        $r['email'],
        'enabled'           => (bool) $r['enabled'],
        'min_severity'      =>        $r['min_severity'],
        'notify_current'    => (bool) $r['notify_current'],
        'notify_sustained'  => (bool) $r['notify_sustained'],
        'notify_disk_full'  => (bool) $r['notify_disk_full'],
        'notify_disk_change'=> (bool) $r['notify_disk_change'],
        'created_at'        =>        $r['created_at'],
    ];
}

try {
    // ── GET ──────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $rows = query("SELECT * FROM alert_recipients ORDER BY created_at ASC");
        jsonResponse([
            'recipients' => array_map('castRecipient', $rows),
            'config'     => loadEmailConfig(),
        ]);
    }

    // ── POST — create recipient ───────────────────────────────────────────────
    elseif ($method === 'POST') {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($d['email'])) {
            jsonError('email is required', 400);
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            jsonError('Invalid email address', 400);
        }

        execute("
            INSERT INTO alert_recipients
                (display_name, email, enabled, min_severity,
                 notify_current, notify_sustained, notify_disk_full, notify_disk_change)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $d['display_name']       ?? '',
            $d['email'],
            isset($d['enabled'])           ? (int)(bool)$d['enabled']            : 1,
            in_array($d['min_severity'] ?? '', ['warning','critical'])
                ? $d['min_severity'] : 'warning',
            isset($d['notify_current'])    ? (int)(bool)$d['notify_current']     : 1,
            isset($d['notify_sustained'])  ? (int)(bool)$d['notify_sustained']   : 1,
            isset($d['notify_disk_full'])  ? (int)(bool)$d['notify_disk_full']   : 1,
            isset($d['notify_disk_change'])? (int)(bool)$d['notify_disk_change'] : 1,
        ]);

        $new = query("SELECT * FROM alert_recipients WHERE id = ?", [lastInsertId()]);
        jsonResponse(['recipient' => castRecipient($new[0])], 201);
    }

    // ── PUT — update recipient OR config ─────────────────────────────────────
    elseif ($method === 'PUT') {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];

        // Config update
        if (isset($d['config']) && is_array($d['config'])) {
            foreach ($d['config'] as $key => $value) {
                if (!in_array($key, CONFIG_KEYS)) continue;
                execute("
                    INSERT INTO app_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ", [$key, (string)$value]);
            }
            jsonResponse(['config' => loadEmailConfig()]);
        }

        // Recipient update
        if (empty($d['id'])) {
            jsonError('id or config required', 400);
        }

        $allowed = [
            'display_name', 'email', 'enabled', 'min_severity',
            'notify_current', 'notify_sustained', 'notify_disk_full', 'notify_disk_change',
        ];
        $sets = []; $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $d)) continue;
            $sets[]   = "{$col} = ?";
            $params[] = in_array($col, ['enabled','notify_current','notify_sustained','notify_disk_full','notify_disk_change'])
                ? (int)(bool)$d[$col] : $d[$col];
        }
        if (empty($sets)) {
            jsonError('No updatable fields provided', 400);
        }
        $params[] = (int)$d['id'];
        execute("UPDATE alert_recipients SET " . implode(', ', $sets) . " WHERE id = ?", $params);

        $updated = query("SELECT * FROM alert_recipients WHERE id = ?", [(int)$d['id']]);
        jsonResponse(['recipient' => castRecipient($updated[0])]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('id required', 400);
        execute("DELETE FROM alert_recipients WHERE id = ?", [$id]);
        jsonResponse(['message' => 'Recipient deleted']);
    }

    else {
        jsonError('Method not allowed', 405);
    }

} catch (Exception $e) {
    jsonError(DEBUG_MODE ? $e->getMessage() : 'Server error', 500);
}
