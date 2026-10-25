<?php
/**
 * Intelligence API  —  /web/api/intelligence.php
 *
 * GET  — returns full intelligence config:
 *         app_settings (sigma values, baseline mode/window),
 *         learning status (days collected vs minimum),
 *         per-metric guard rails + manual_override + current baseline bucket
 *
 * PUT  — accepts one or both of:
 *         { "app_settings": { "sigma_elevated": 1.2, ... } }
 *         { "metric_rule":  { "metric": "cpu_percent",
 *                             "guard_rail_floor": 55,
 *                             "guard_rail_ceiling": 90,
 *                             "manual_override": false } }
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------------------
// Metadata for each intelligence-tracked metric
// ---------------------------------------------------------------------------
$metricMeta = [
    'cpu_percent'   => ['label' => 'CPU Usage',          'unit' => 'percent'],
    'mem_percent'   => ['label' => 'Memory Usage',        'unit' => 'percent'],
    'disk_io_write' => ['label' => 'Disk Write',          'unit' => 'bytes'],
    'disk_io_read'  => ['label' => 'Disk Read',           'unit' => 'bytes'],
    'net_sent'      => ['label' => 'Network Upload',      'unit' => 'bytes'],
    'net_recv'      => ['label' => 'Network Download',    'unit' => 'bytes'],
    'temp_avg'      => ['label' => 'CPU Temperature',     'unit' => 'celsius'],
];

try {

    // -----------------------------------------------------------------------
    // GET — read full intelligence config
    // -----------------------------------------------------------------------
    if ($method === 'GET') {

        // 1. Load app_settings with fallback defaults
        $defaults = [
            'sigma_elevated'       => '1.0',
            'sigma_high'           => '2.0',
            'sigma_low'            => '1.5',
            'baseline_mode'        => 'hourly',
            'baseline_window_days' => '30',
            'baseline_min_days'    => '7',
            'sustained_minutes'    => '60',
            'temp_sustained_minutes' => '15',
            'alert_cooldown_hours'  => '4',
        ];
        $appSettings = $defaults;
        try {
            $rows = query("SELECT setting_key, setting_value FROM app_settings");
            foreach ($rows as $r) {
                $appSettings[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) { /* table not yet created — use defaults */ }

        // 2. Learning status — use metrics_5min (30d retention) as the
        //    authoritative day count. metrics_1min only keeps 2 days (charts
        //    only) and can never reach baseline_min_days. Baselines are
        //    computed from metrics_5min so that's the correct source here.
        $dataDays = 0;
        try {
            $row = query("SELECT DATEDIFF(NOW(), MIN(timestamp)) AS days FROM metrics_5min");
            $dataDays = (int) ($row[0]['days'] ?? 0);
        } catch (Exception $e) {}
        $minDays     = (int) $appSettings['baseline_min_days'];
        $hasBaseline = $dataDays >= $minDays;

        // 3. Load alert_rules for all intelligence metrics
        $placeholders = implode(',', array_fill(0, count($metricMeta), '?'));
        $ruleRows = query(
            "SELECT * FROM alert_rules WHERE metric IN ($placeholders)",
            array_keys($metricMeta)
        );
        $rules = [];
        foreach ($ruleRows as $r) {
            $rules[$r['metric']] = $r;
        }

        // 4. Current baseline bucket
        $dow  = (int) date('N') - 1;   // 0=Mon … 6=Sun
        $hour = (int) date('G');        // 0–23
        $baselineRows = [];
        try {
            $baselineRows = query(
                "SELECT metric, mean, stddev, p05, p95, sample_count
                 FROM metric_baselines
                 WHERE day_of_week = ? AND hour_of_day = ?",
                [$dow, $hour]
            );
        } catch (Exception $e) {}
        $baselines = [];
        foreach ($baselineRows as $b) {
            $baselines[$b['metric']] = $b;
        }

        // 5. Build per-metric output
        $metrics = [];
        foreach ($metricMeta as $key => $meta) {
            $rule     = $rules[$key]     ?? null;
            $baseline = $baselines[$key] ?? null;
            $metrics[$key] = [
                'label'              => $meta['label'],
                'unit'               => $meta['unit'],
                'rule_id'            => $rule ? (int) $rule['id'] : null,
                'guard_rail_floor'   => ($rule && $rule['guard_rail_floor']  !== null) ? (float) $rule['guard_rail_floor']  : null,
                'guard_rail_ceiling' => ($rule && $rule['guard_rail_ceiling'] !== null) ? (float) $rule['guard_rail_ceiling'] : null,
                'manual_override'    => $rule ? (bool) $rule['manual_override'] : true,
                'threshold'          => $rule ? (float) $rule['threshold'] : null,
                'baseline'           => ($hasBaseline && $baseline) ? [
                    'mean'         => $baseline['mean']   !== null ? (float) $baseline['mean']   : null,
                    'stddev'       => $baseline['stddev'] !== null ? (float) $baseline['stddev'] : null,
                    'p05'          => $baseline['p05']    !== null ? (float) $baseline['p05']    : null,
                    'p95'          => $baseline['p95']    !== null ? (float) $baseline['p95']    : null,
                    'sample_count' => (int) $baseline['sample_count'],
                ] : null,
            ];
        }

        jsonResponse([
            'app_settings'    => [
                'sigma_elevated'         => (float) $appSettings['sigma_elevated'],
                'sigma_high'             => (float) $appSettings['sigma_high'],
                'sigma_low'              => (float) $appSettings['sigma_low'],
                'baseline_mode'          => $appSettings['baseline_mode'],
                'baseline_window_days'   => (int) $appSettings['baseline_window_days'],
                'baseline_min_days'      => (int) $appSettings['baseline_min_days'],
                'sustained_minutes'      => (int) $appSettings['sustained_minutes'],
                'temp_sustained_minutes' => (int) $appSettings['temp_sustained_minutes'],
                'alert_cooldown_hours'   => (int) $appSettings['alert_cooldown_hours'],
            ],
            'learning_status' => [
                'data_days'    => $dataDays,
                'min_days'     => $minDays,
                'has_baseline' => $hasBaseline,
            ],
            'metrics'         => $metrics,
            'bucket'          => ['dow' => $dow, 'hour' => $hour],
        ]);

    // -----------------------------------------------------------------------
    // PUT — save app_settings and/or a single metric rule
    // -----------------------------------------------------------------------
    } elseif ($method === 'PUT') {

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { jsonError('Invalid JSON', 400); }

        // Update app_settings (sigma values + baseline config)
        if (isset($data['app_settings'])) {
            $allowed = [
                'sigma_elevated', 'sigma_high', 'sigma_low',
                'baseline_mode', 'baseline_window_days', 'baseline_min_days',
                'sustained_minutes', 'temp_sustained_minutes', 'alert_cooldown_hours',
            ];
            foreach ($data['app_settings'] as $key => $value) {
                if (!in_array($key, $allowed)) continue;
                execute(
                    "INSERT INTO app_settings (setting_key, setting_value)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$key, $value]
                );
            }
        }

        // Update a single metric rule's guard rails + manual_override
        if (isset($data['metric_rule'])) {
            $mr = $data['metric_rule'];
            if (empty($mr['metric'])) { jsonError('metric_rule.metric required', 400); }

            $updates = [];
            $params  = [];

            if (array_key_exists('guard_rail_floor', $mr)) {
                $updates[] = 'guard_rail_floor = ?';
                $params[]  = ($mr['guard_rail_floor'] !== null && $mr['guard_rail_floor'] !== '')
                             ? (float) $mr['guard_rail_floor'] : null;
            }
            if (array_key_exists('guard_rail_ceiling', $mr)) {
                $updates[] = 'guard_rail_ceiling = ?';
                $params[]  = ($mr['guard_rail_ceiling'] !== null && $mr['guard_rail_ceiling'] !== '')
                             ? (float) $mr['guard_rail_ceiling'] : null;
            }
            if (array_key_exists('manual_override', $mr)) {
                $updates[] = 'manual_override = ?';
                $params[]  = $mr['manual_override'] ? 1 : 0;
            }

            if (!empty($updates)) {
                $params[] = $mr['metric'];
                execute(
                    "UPDATE alert_rules SET " . implode(', ', $updates) . " WHERE metric = ?",
                    $params
                );
            }
        }

        jsonResponse(['message' => 'Saved']);

    } else {
        jsonError('Method not allowed', 405);
    }

} catch (Exception $e) {
    jsonError(DEBUG_MODE ? $e->getMessage() : 'Internal server error', 500);
}
