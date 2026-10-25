<?php
/**
 * API Endpoint: Process History
 *
 * Two views:
 *   ?view=time&at=YYYY-MM-DD HH:MM&sort=cpu|mem|tcp
 *       Returns all processes recorded at (or nearest to) the given time,
 *       sorted by CPU (default), memory, or TCP connections. Uses
 *       process_history_1min for times within 7 days, process_history_5min
 *       for older.
 *
 *   ?view=process&name=<process_name>&range=1h|6h|24h|7d|30d
 *       Returns a time-series for a named process (cpu_avg, cpu_max,
 *       mem_avg_mb, mem_max_mb) for the requested range. Uses 1min table
 *       for ranges ≤ 7d, 5min for ≤ 30d.
 *
 *   ?view=names
 *       Returns distinct process names available in history (for the
 *       Process View dropdown/search).
 *
 *   ?view=group&prefix=<prefix>&range=1h|6h|24h|7d|30d
 *       Aggregates ALL processes whose name begins with <prefix> into a
 *       single time-series. cpu_avg = SUM across matched processes (total
 *       CPU load the group consumed); mem_avg_mb = SUM across matched
 *       processes (total RAM). Also returns matched_names[] so the UI can
 *       show which processes are included in the group.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// ================================================================
// Helpers
// ================================================================

/**
 * Pick the right history table and bucket size based on how far back
 * the requested time is, and the range of data needed.
 * Returns ['table' => string, 'bucket_seconds' => int]
 */
function resolveTable(int $ageSeconds): array
{
    // 1min table covers 7 days. 5min covers 30 days.
    if ($ageSeconds <= 7 * 86400) {
        return ['table' => 'process_history_1min', 'bucket_seconds' => 60];
    }
    return ['table' => 'process_history_5min', 'bucket_seconds' => 300];
}

// ================================================================
// Route
// ================================================================

$view = $_GET['view'] ?? '';

try {
    switch ($view) {

        // ── Time View ────────────────────────────────────────────────────
        case 'time':
            $atRaw = trim($_GET['at'] ?? '');
            $sortRaw = $_GET['sort'] ?? 'cpu';
            $sort = in_array($sortRaw, ['mem', 'tcp'], true) ? $sortRaw : 'cpu';

            if (!$atRaw) {
                jsonError('Missing required parameter: at', 400);
            }

            $atTs = strtotime($atRaw);
            if ($atTs === false) {
                jsonError('Invalid datetime format for parameter: at', 400);
            }

            // Reject if older than 30 days (outside any table's retention)
            if ((time() - $atTs) > 30 * 86400 + 3600) {
                jsonError('Requested time is outside the 30-day retention window', 400);
            }

            ['table' => $table, 'bucket_seconds' => $bucket] = resolveTable(time() - $atTs);

            // Find the nearest bucket timestamp to what the user asked for
            $nearest = queryOne(
                "SELECT timestamp
                 FROM {$table}
                 WHERE timestamp BETWEEN
                       DATE_SUB(?, INTERVAL ? SECOND) AND
                       DATE_ADD(?, INTERVAL ? SECOND)
                 ORDER BY ABS(TIMESTAMPDIFF(SECOND, timestamp, ?))
                 LIMIT 1",
                [$atRaw, $bucket * 3, $atRaw, $bucket * 3, $atRaw]
            );

            if (!$nearest) {
                jsonResponse([
                    'success'   => true,
                    'at'        => $atRaw,
                    'resolved'  => null,
                    'table'     => $table,
                    'processes' => [],
                    'message'   => 'No data found near that time. Collection may not have been running.'
                ]);
            }

            $resolvedTs = $nearest['timestamp'];

            $orderCol = match($sort) {
                'mem' => 'mem_avg_mb',
                'tcp' => 'tcp_conns_avg',
                default => 'cpu_avg',
            };

            $processes = query(
                "SELECT process_name, username, cpu_avg, cpu_max, mem_avg_mb, mem_max_mb,
                        sample_count, tcp_conns_avg
                 FROM {$table}
                 WHERE timestamp = ?
                 ORDER BY {$orderCol} DESC",
                [$resolvedTs]
            );

            jsonResponse([
                'success'   => true,
                'at'        => $atRaw,
                'resolved'  => $resolvedTs,
                'table'     => $table,
                'sort'      => $sort,
                'processes' => array_map(fn($p) => [
                    'process_name' => $p['process_name'],
                    'username'     => $p['username'],
                    'cpu_avg'      => (float) $p['cpu_avg'],
                    'cpu_max'      => (float) $p['cpu_max'],
                    'mem_avg_mb'   => (float) $p['mem_avg_mb'],
                    'mem_max_mb'   => (float) $p['mem_max_mb'],
                    'sample_count' => (int)   $p['sample_count'],
                    'tcp_conns_avg'=> $p['tcp_conns_avg'] !== null ? (float) $p['tcp_conns_avg'] : null,
                ], $processes),
            ]);
            break;

        // ── Process View ─────────────────────────────────────────────────
        case 'process':
            $name  = trim($_GET['name'] ?? '');
            $range = $_GET['range'] ?? '24h';

            if (!$name) {
                jsonError('Missing required parameter: name', 400);
            }

            $validRanges = [
                '1h'  => ['seconds' => 3600,       'label' => 'Last 1 hour'],
                '6h'  => ['seconds' => 21600,      'label' => 'Last 6 hours'],
                '24h' => ['seconds' => 86400,      'label' => 'Last 24 hours'],
                '7d'  => ['seconds' => 604800,     'label' => 'Last 7 days'],
                '30d' => ['seconds' => 2592000,    'label' => 'Last 30 days'],
            ];

            if (!isset($validRanges[$range])) {
                $range = '24h';
            }

            $rangeSeconds = $validRanges[$range]['seconds'];
            ['table' => $table, 'bucket_seconds' => $bucketSec] = resolveTable($rangeSeconds);

            $rows = query(
                "SELECT timestamp, cpu_avg, cpu_max, mem_avg_mb, mem_max_mb, username,
                        sample_count, tcp_conns_avg
                 FROM {$table}
                 WHERE process_name = ?
                   AND timestamp >= NOW() - INTERVAL {$rangeSeconds} SECOND
                 ORDER BY timestamp ASC",
                [$name]
            );

            // ── Gap-filling ───────────────────────────────────────────────
            // The DB only has rows for buckets where the process was in the
            // top-N list. Any gap between real rows means the process wasn't
            // running (or didn't make the list). Without filling those gaps,
            // Chart.js draws a straight line across the silence, making it
            // look like the process ran continuously at peak usage.
            //
            // Fix: build the complete bucket grid for the requested range and
            // emit 0.0 for every bucket that has no DB row. The chart then
            // correctly drops to zero during those windows.

            // Index DB rows by bucket-aligned Unix timestamp.
            // round() absorbs any ±1-second jitter in stored timestamps.
            $rowByBucket = [];
            foreach ($rows as $r) {
                $key = (int) round(strtotime($r['timestamp']) / $bucketSec) * $bucketSec;
                $rowByBucket[$key] = $r;
            }

            // Compute the expected bucket grid boundaries.
            $now            = time();
            $latestBucket   = (int) (floor($now / $bucketSec) * $bucketSec);
            $earliestBucket = (int) (ceil(($now - $rangeSeconds) / $bucketSec) * $bucketSec);

            $points = [];
            for ($ts = $earliestBucket; $ts <= $latestBucket; $ts += $bucketSec) {
                if (isset($rowByBucket[$ts])) {
                    $r = $rowByBucket[$ts];
                    $points[] = [
                        'timestamp'     => $r['timestamp'],
                        'cpu_avg'       => (float) $r['cpu_avg'],
                        'cpu_max'       => (float) $r['cpu_max'],
                        'mem_avg_mb'    => (float) $r['mem_avg_mb'],
                        'mem_max_mb'    => (float) $r['mem_max_mb'],
                        'username'      => $r['username'],
                        'tcp_conns_avg' => $r['tcp_conns_avg'] !== null ? (float) $r['tcp_conns_avg'] : null,
                    ];
                } else {
                    // Process absent this bucket — plot explicit zero so the
                    // chart drops to baseline instead of bridging the gap.
                    $points[] = [
                        'timestamp'     => date('Y-m-d H:i:s', $ts),
                        'cpu_avg'       => 0.0,
                        'cpu_max'       => 0.0,
                        'mem_avg_mb'    => 0.0,
                        'mem_max_mb'    => 0.0,
                        'username'      => '',
                        'tcp_conns_avg' => null,
                    ];
                }
            }

            jsonResponse([
                'success'  => true,
                'name'     => $name,
                'range'    => $range,
                'label'    => $validRanges[$range]['label'],
                'table'    => $table,
                'points'   => $points,
            ]);
            break;

        // ── Group View ───────────────────────────────────────────────────
        // Aggregates all processes matching prefix% into a single time-series.
        // cpu_avg/mem_avg_mb are SUM (total load), cpu_max/mem_max_mb are MAX
        // (peak any single member hit). Gap-fills identical to view=process.
        case 'group':
            $prefix = trim($_GET['prefix'] ?? '');
            $range  = $_GET['range'] ?? '24h';

            if ($prefix === '') {
                jsonError('Missing required parameter: prefix', 400);
            }

            $validRanges = [
                '1h'  => ['seconds' => 3600,    'label' => 'Last 1 hour'],
                '6h'  => ['seconds' => 21600,   'label' => 'Last 6 hours'],
                '24h' => ['seconds' => 86400,   'label' => 'Last 24 hours'],
                '7d'  => ['seconds' => 604800,  'label' => 'Last 7 days'],
                '30d' => ['seconds' => 2592000, 'label' => 'Last 30 days'],
            ];

            if (!isset($validRanges[$range])) {
                $range = '24h';
            }

            $rangeSeconds = $validRanges[$range]['seconds'];
            ['table' => $table, 'bucket_seconds' => $bucketSec] = resolveTable($rangeSeconds);
            $likePrefix = $prefix . '%';

            // Aggregate per-bucket: SUM gives total load the group imposed.
            $rows = query(
                "SELECT
                    timestamp,
                    SUM(cpu_avg)        AS cpu_avg,
                    MAX(cpu_max)        AS cpu_max,
                    SUM(mem_avg_mb)     AS mem_avg_mb,
                    MAX(mem_max_mb)     AS mem_max_mb,
                    SUM(sample_count)   AS sample_count,
                    SUM(tcp_conns_avg)  AS tcp_conns_avg
                 FROM {$table}
                 WHERE process_name LIKE ?
                   AND timestamp >= NOW() - INTERVAL {$rangeSeconds} SECOND
                 GROUP BY timestamp
                 ORDER BY timestamp ASC",
                [$likePrefix]
            );

            // Distinct names that matched — shown in the UI as the group manifest.
            $matchedRows = query(
                "SELECT DISTINCT process_name, username
                 FROM {$table}
                 WHERE process_name LIKE ?
                   AND timestamp >= NOW() - INTERVAL {$rangeSeconds} SECOND
                 ORDER BY process_name ASC",
                [$likePrefix]
            );
            $matchedNames = array_map(fn($r) => [
                'process_name' => $r['process_name'],
                'username'     => $r['username'],
            ], $matchedRows);

            // Gap-fill (same logic as view=process)
            $rowByBucket = [];
            foreach ($rows as $r) {
                $key = (int) round(strtotime($r['timestamp']) / $bucketSec) * $bucketSec;
                $rowByBucket[$key] = $r;
            }

            $now            = time();
            $latestBucket   = (int) (floor($now / $bucketSec) * $bucketSec);
            $earliestBucket = (int) (ceil(($now - $rangeSeconds) / $bucketSec) * $bucketSec);

            $points = [];
            for ($ts = $earliestBucket; $ts <= $latestBucket; $ts += $bucketSec) {
                if (isset($rowByBucket[$ts])) {
                    $r = $rowByBucket[$ts];
                    $points[] = [
                        'timestamp'     => $r['timestamp'],
                        'cpu_avg'       => (float) $r['cpu_avg'],
                        'cpu_max'       => (float) $r['cpu_max'],
                        'mem_avg_mb'    => (float) $r['mem_avg_mb'],
                        'mem_max_mb'    => (float) $r['mem_max_mb'],
                        'tcp_conns_avg' => $r['tcp_conns_avg'] !== null ? (float) $r['tcp_conns_avg'] : null,
                    ];
                } else {
                    $points[] = [
                        'timestamp'     => date('Y-m-d H:i:s', $ts),
                        'cpu_avg'       => 0.0,
                        'cpu_max'       => 0.0,
                        'mem_avg_mb'    => 0.0,
                        'mem_max_mb'    => 0.0,
                        'tcp_conns_avg' => null,
                    ];
                }
            }

            jsonResponse([
                'success'       => true,
                'prefix'        => $prefix,
                'range'         => $range,
                'label'         => $validRanges[$range]['label'],
                'table'         => $table,
                'matched_names' => $matchedNames,
                'points'        => $points,
            ]);
            break;

        // ── Names list ───────────────────────────────────────────────────
        case 'names':
            // Pull distinct names from the 30-day table for broadest coverage.
            // Frontend search filters this client-side.
            $rows = query(
                "SELECT DISTINCT process_name, username
                 FROM process_history_5min
                 ORDER BY process_name ASC"
            );

            jsonResponse([
                'success' => true,
                'names'   => array_map(fn($r) => [
                    'process_name' => $r['process_name'],
                    'username'     => $r['username'],
                ], $rows),
            ]);
            break;

        default:
            jsonError("Unknown view. Use: time | process | group | names", 400);
    }

} catch (Exception $e) {
    if (DEBUG_MODE) {
        jsonError($e->getMessage(), 500);
    } else {
        jsonError('Internal server error', 500);
    }
}
