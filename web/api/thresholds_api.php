<?php
/**
 * Thresholds API  —  /web/api/thresholds.php
 *
 * Returns everything the dashboard needs to evaluate the 6-level status system:
 *   - Alert rules (guard rails + manual overrides) for all 5 metrics
 *   - Sigma sensitivity settings from app_settings
 *   - Current metric_baselines for this exact hour / day-of-week bucket
 *   - How many days of baseline data we have (drives "Learning..." badge)
 *
 * Called once on dashboard init, result cached in Alpine component state.
 * GET only — read-only endpoint.
 *
 * Day convention: 0=Monday … 6=Sunday (matches Python weekday() in collector).
 * PHP date('N') gives 1=Mon … 7=Sun, so we subtract 1 to align.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ---------------------------------------------------------------------------
// 1. Load app_settings — fall back to hardcoded defaults if table missing
// ---------------------------------------------------------------------------
$settings = [
    'baseline_mode'       => 'hourly',
    'baseline_window_days'=> '30',
    'baseline_min_days'   => '7',
    'sigma_elevated'      => '1.0',
    'sigma_high'          => '2.0',
    'sigma_low'           => '1.5',
];

try {
    $rows = query("SELECT setting_key, setting_value FROM app_settings");
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Exception $e) {
    // app_settings table doesn't exist yet — migration not run, use defaults
}

$sigmaElevated = (float) $settings['sigma_elevated'];
$sigmaHigh     = (float) $settings['sigma_high'];
$sigmaLow      = (float) $settings['sigma_low'];
$minDays       = (int)   $settings['baseline_min_days'];

// ---------------------------------------------------------------------------
// 2. Current time bucket for baseline lookup
//    PHP date('N') = 1 (Mon) … 7 (Sun)  →  subtract 1 = 0 (Mon) … 6 (Sun)
// ---------------------------------------------------------------------------
$currentDow  = (int) date('N') - 1;   // 0=Mon … 6=Sun
$currentHour = (int) date('G');        // 0–23

// ---------------------------------------------------------------------------
// 3. Load alert_rules — one row per metric, keyed by metric name
// ---------------------------------------------------------------------------
$ruleRows = query("
    SELECT metric, threshold, guard_rail_floor, guard_rail_ceiling, manual_override
    FROM   alert_rules
    WHERE  enabled = 1
");

$rules = [];
foreach ($ruleRows as $r) {
    $rules[$r['metric']] = [
        'threshold'         => (float) $r['threshold'],
        'guard_rail_floor'  => $r['guard_rail_floor']  !== null ? (float) $r['guard_rail_floor']  : null,
        'guard_rail_ceiling'=> $r['guard_rail_ceiling'] !== null ? (float) $r['guard_rail_ceiling'] : null,
        'manual_override'   => (bool)  $r['manual_override'],
    ];
}

// ---------------------------------------------------------------------------
// 4. Load metric_baselines for this hour / day bucket
// ---------------------------------------------------------------------------
$baselineRows = [];
try {
    $baselineRows = query("
        SELECT metric, mean, stddev, p05, p95, sample_count, computed_at
        FROM   metric_baselines
        WHERE  day_of_week = ?
          AND  hour_of_day = ?
    ", [$currentDow, $currentHour]);
} catch (Exception $e) {
    // metric_baselines table doesn't exist yet — baselines will be null
}

$baselines = [];
foreach ($baselineRows as $b) {
    $baselines[$b['metric']] = [
        'mean'         => $b['mean']  !== null ? (float) $b['mean']  : null,
        'stddev'       => $b['stddev']!== null ? (float) $b['stddev']: null,
        'p05'          => $b['p05']   !== null ? (float) $b['p05']   : null,
        'p95'          => $b['p95']   !== null ? (float) $b['p95']   : null,
        'sample_count' => (int) $b['sample_count'],
        'computed_at'  => $b['computed_at'],
    ];
}

// ---------------------------------------------------------------------------
// 5. How many days of baseline data do we have?
//    Uses metrics_5min (30d retention) as the authoritative source.
//    metrics_1min only keeps 2 days (charts only) and can never reach
//    baseline_min_days — baselines are computed from metrics_5min, so
//    that's the correct table to measure against.
// ---------------------------------------------------------------------------
$dataDays = 0;
try {
    $ageRow = query("
        SELECT DATEDIFF(NOW(), MIN(timestamp)) AS days_of_data
        FROM   metrics_5min
    ");
    $dataDays = (int) ($ageRow[0]['days_of_data'] ?? 0);
} catch (Exception $e) {
    $dataDays = 0;
}

$hasBaseline = $dataDays >= $minDays;

// ---------------------------------------------------------------------------
// 6. Build per-metric threshold objects
//    Each metric gets everything the dashboard needs to call getStatus():
//      guard_rail_floor / guard_rail_ceiling — absolute hard stops
//      manual_override   — skip baseline, use threshold directly
//      baseline          — mean/stddev for sigma math (null during learning)
//      sigma_*           — sensitivity knobs
//    The dashboard JS applies this logic:
//      CRITICAL  if value > guard_rail_ceiling
//      WARNING   if value > guard_rail_floor
//      HIGH      if value > mean + sigma_high × stddev  (baseline only)
//      ELEVATED  if value > mean + sigma_elevated × stddev
//      LOW       if value < mean - sigma_low × stddev
//      NORMAL    otherwise
// ---------------------------------------------------------------------------
$metrics = ['cpu_percent', 'mem_percent', 'disk_io_write', 'disk_io_read', 'net_sent', 'net_recv', 'temp_avg'];

$output = [];
foreach ($metrics as $metric) {
    $rule     = $rules[$metric]     ?? null;
    $baseline = $baselines[$metric] ?? null;

    $output[$metric] = [
        // Guard rails — null means "not configured, skip that check"
        'guard_rail_floor'   => $rule['guard_rail_floor']   ?? null,
        'guard_rail_ceiling' => $rule['guard_rail_ceiling']  ?? null,

        // Manual override — true = use threshold, ignore baseline
        'manual_override'    => $rule ? (bool) $rule['manual_override'] : true,
        'threshold'          => $rule ? (float) $rule['threshold']      : null,

        // Baseline data for this bucket (null during learning period)
        'baseline'           => $hasBaseline ? $baseline : null,

        // Sensitivity settings (same for all metrics, but per-metric
        // overrides are easy to add later — just move into alert_rules)
        'sigma_elevated'     => $sigmaElevated,
        'sigma_high'         => $sigmaHigh,
        'sigma_low'          => $sigmaLow,
    ];
}

echo json_encode([
    'metrics'          => $output,
    'has_baseline'     => $hasBaseline,
    'data_days'        => $dataDays,
    'min_days'         => $minDays,
    'baseline_mode'    => $settings['baseline_mode'],
    'generated_at'     => date('c'),
    'bucket'           => ['dow' => $currentDow, 'hour' => $currentHour],
]);
