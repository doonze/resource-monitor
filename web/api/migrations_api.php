<?php
/**
 * Resource Monitor — Migrations API
 *
 * Handles reading migration files from database/migrations/ and applying
 * or rolling back schema changes against the live database.
 *
 * GET  migrations_api.php           → return full status JSON
 * POST migrations_api.php           → run an action (see below)
 *
 * POST body (JSON):
 *   { "action": "status"       }   → same as GET
 *   { "action": "migrate_up"   }   → apply the next pending migration
 *   { "action": "migrate_down" }   → rollback the most recently applied migration
 *
 * The DB user configured in config.php must have CREATE TABLE, DROP TABLE,
 * and ALTER TABLE privileges during migrations. Normal app operations only
 * require SELECT/INSERT/UPDATE/DELETE.
 */

define('MIGRATIONS_DIR', realpath(__DIR__ . '/../../database/migrations'));

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ================================================================
// Helpers
// ================================================================

/**
 * Ensure schema_migrations tracking table exists.
 * Called before every operation — safe to call multiple times (IF NOT EXISTS).
 */
function bootstrapMigrationTable(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version       INT UNSIGNED  NOT NULL,
            description   VARCHAR(255)  NOT NULL,
            app_version   VARCHAR(20)   NOT NULL  COMMENT 'App version that introduced this migration',
            applied_at    DATETIME      NOT NULL  DEFAULT CURRENT_TIMESTAMP,
            execution_ms  INT UNSIGNED  DEFAULT NULL  COMMENT 'How long the migration took in milliseconds',
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Applied migration tracking. Managed by the web UI migration runner.'
    ");
}

/**
 * Return all applied migrations keyed by version number.
 */
function getAppliedVersions(PDO $db): array
{
    $rows = $db->query(
        "SELECT version, description, app_version, applied_at, execution_ms
           FROM schema_migrations
          ORDER BY version ASC"
    )->fetchAll();

    $applied = [];
    foreach ($rows as $row) {
        $applied[(int)$row['version']] = $row;
    }
    return $applied;
}

/**
 * Scan the migrations directory for versioned SQL files.
 * Files must match: v000_*.sql, v001_*.sql, etc.
 * Returns array keyed by integer version number.
 */
function scanMigrationFiles(): array
{
    $dir = MIGRATIONS_DIR;
    if (!$dir || !is_dir($dir)) {
        return [];
    }

    $files      = glob($dir . '/v[0-9][0-9][0-9]_*.sql') ?: [];
    $migrations = [];

    foreach ($files as $file) {
        $base = basename($file, '.sql');
        if (!preg_match('/^v(\d{3})_(.+)$/', $base, $m)) {
            continue;
        }

        $version     = (int)$m[1];
        $slug        = $m[2];
        $description = ucwords(str_replace(['_', '-'], ' ', $slug));

        // Override description/app_version from file header if present
        $header = parseFileHeader($file);
        if (!empty($header['description'])) {
            $description = $header['description'];
        }

        $migrations[$version] = [
            'version'       => $version,
            'filename'      => basename($file),
            'path'          => $file,
            'description'   => $description,
            'app_version'   => $header['app_version']   ?? '?',
            'rollback_risk' => $header['rollback_risk'] ?? null,
            'rollback_note' => $header['rollback_note'] ?? null,
        ];
    }

    ksort($migrations);
    return $migrations;
}

/**
 * Parse key metadata from the header block at the top of a migration file.
 * Reads lines until the first ==UP== marker, then stops.
 */
function parseFileHeader(string $file): array
{
    $result = [];
    $handle = fopen($file, 'r');
    if (!$handle) {
        return $result;
    }

    $linesRead = 0;
    while (($line = fgets($handle)) !== false && $linesRead < 30) {
        $line = trim($line);
        if (str_contains($line, '==UP==')) {
            break;
        }
        if (preg_match('/^--\s+Description:\s*(.+)$/i', $line, $m)) {
            $result['description'] = trim($m[1]);
        }
        if (preg_match('/^--\s+App Version:\s*(.+)$/i', $line, $m)) {
            $result['app_version'] = trim($m[1]);
        }
        if (preg_match('/^--\s+Rollback Risk:\s*(.+)$/i', $line, $m)) {
            $result['rollback_risk'] = strtolower(trim($m[1]));
        }
        if (preg_match('/^--\s+Rollback Note:\s*(.+)$/i', $line, $m)) {
            $result['rollback_note'] = trim($m[1]);
        }
        $linesRead++;
    }

    fclose($handle);
    return $result;
}

/**
 * Split a SQL file section into individual executable statements.
 * Strips single-line comments FIRST so semicolons inside comment text
 * (e.g. "-- lost on restart; repopulates") don't cause false splits.
 */
function splitStatements(string $sql): array
{
    // Remove all -- comments before splitting on semicolons.
    // This prevents semicolons inside comment text from creating phantom statements.
    $stripped = preg_replace('/--[^\n]*/', '', $sql);

    $statements = explode(';', $stripped);
    $result     = [];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $result[] = $stmt;
    }

    return $result;
}

/**
 * Parse the UP and DOWN SQL sections from a migration file.
 * Returns ['up' => [...statements], 'down' => [...statements]].
 */
function parseMigrationSections(string $file): array
{
    $content = file_get_contents($file);

    $upPos   = strpos($content, '-- ==UP==');
    $downPos = strpos($content, '-- ==DOWN==');

    if ($upPos === false) {
        throw new RuntimeException("Missing '-- ==UP==' marker in " . basename($file));
    }
    if ($downPos === false) {
        throw new RuntimeException("Missing '-- ==DOWN==' marker in " . basename($file));
    }
    if ($upPos >= $downPos) {
        throw new RuntimeException("'-- ==UP==' must appear before '-- ==DOWN==' in " . basename($file));
    }

    $upLen  = strlen('-- ==UP==');
    $dnLen  = strlen('-- ==DOWN==');

    $upSql   = substr($content, $upPos + $upLen, $downPos - $upPos - $upLen);
    $downSql = substr($content, $downPos + $dnLen);

    return [
        'up'   => splitStatements($upSql),
        'down' => splitStatements($downSql),
    ];
}

// ================================================================
// Core operations
// ================================================================

/**
 * Build and return the full migration status payload.
 */
function buildStatus(PDO $db): array
{
    bootstrapMigrationTable($db);

    $applied    = getAppliedVersions($db);
    $files      = scanMigrationFiles();
    $migrations = [];

    $currentVersion  = empty($applied) ? -1 : max(array_keys($applied));
    $requiredVersion = REQUIRED_SCHEMA_VERSION;

    foreach ($files as $version => $info) {
        $isApplied  = isset($applied[$version]);
        $appliedRow = $isApplied ? $applied[$version] : null;

        $migrations[] = [
            'version'       => $version,
            'filename'      => $info['filename'],
            'description'   => $info['description'],
            'app_version'   => $info['app_version'],
            'rollback_risk' => $info['rollback_risk'] ?? null,
            'rollback_note' => $info['rollback_note'] ?? null,
            'status'        => $isApplied ? 'applied' : 'pending',
            'applied_at'    => $appliedRow['applied_at']  ?? null,
            'execution_ms'  => $appliedRow['execution_ms'] ?? null,
        ];
    }

    // Flag any applied versions with no corresponding file (orphan)
    foreach ($applied as $version => $row) {
        if (!isset($files[$version])) {
            $migrations[] = [
                'version'      => $version,
                'filename'     => null,
                'description'  => $row['description'],
                'app_version'  => $row['app_version'],
                'status'       => 'orphan',
                'applied_at'   => $row['applied_at'],
                'execution_ms' => $row['execution_ms'],
            ];
        }
    }

    usort($migrations, fn($a, $b) => $a['version'] <=> $b['version']);

    $pendingCount = count(array_filter($migrations, fn($m) => $m['status'] === 'pending'));

    return [
        'success'          => true,
        'current_version'  => $currentVersion,
        'required_version' => $requiredVersion,
        'is_current'       => ($currentVersion >= $requiredVersion),
        'pending_count'    => $pendingCount,
        'migrations'       => $migrations,
    ];
}

/**
 * Apply the next pending migration (UP).
 */
function migrateUp(PDO $db): array
{
    bootstrapMigrationTable($db);

    $applied = getAppliedVersions($db);
    $files   = scanMigrationFiles();

    // Find the first file version that has not been applied
    $target = null;
    foreach ($files as $version => $info) {
        if (!isset($applied[$version])) {
            $target = $info;
            break;
        }
    }

    if ($target === null) {
        return ['success' => false, 'message' => 'No pending migrations to apply.'];
    }

    $sections   = parseMigrationSections($target['path']);
    $statements = $sections['up'];

    if (empty($statements)) {
        return ['success' => false, 'message' => 'UP section contains no executable statements in ' . $target['filename']];
    }

    $startTime = microtime(true);
    $ran       = 0;
    $errors    = [];

    foreach ($statements as $sql) {
        try {
            $db->exec($sql);
            $ran++;
        } catch (PDOException $e) {
            $errors[] = [
                'statement_preview' => mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 120) . '…',
                'error'             => $e->getMessage(),
            ];
            break; // Hard stop on first error
        }
    }

    $executionMs = (int)((microtime(true) - $startTime) * 1000);

    if (!empty($errors)) {
        return [
            'success'        => false,
            'version'        => $target['version'],
            'direction'      => 'up',
            'statements_run' => $ran,
            'total_statements' => count($statements),
            'execution_ms'   => $executionMs,
            'message'        => "Migration v{$target['version']} failed after {$ran} of " . count($statements) . " statement(s).",
            'errors'         => $errors,
        ];
    }

    // Record successful migration
    $stmt = $db->prepare("
        INSERT INTO schema_migrations (version, description, app_version, execution_ms)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP, execution_ms = VALUES(execution_ms)
    ");
    $stmt->execute([$target['version'], $target['description'], APP_VERSION, $executionMs]);

    return [
        'success'          => true,
        'version'          => $target['version'],
        'direction'        => 'up',
        'statements_run'   => $ran,
        'total_statements' => count($statements),
        'execution_ms'     => $executionMs,
        'message'          => "v{$target['version']} — {$target['description']} applied successfully in {$executionMs}ms.",
    ];
}

/**
 * Roll back the most recently applied migration (DOWN).
 */
function migrateDown(PDO $db): array
{
    bootstrapMigrationTable($db);

    $applied = getAppliedVersions($db);

    if (empty($applied)) {
        return ['success' => false, 'message' => 'No applied migrations to roll back.'];
    }

    $latestVersion = max(array_keys($applied));
    $files         = scanMigrationFiles();

    if (!isset($files[$latestVersion])) {
        return [
            'success' => false,
            'message' => "Cannot roll back v{$latestVersion}: migration file not found. Remove it from schema_migrations manually if you are sure.",
        ];
    }

    $target     = $files[$latestVersion];
    $sections   = parseMigrationSections($target['path']);
    $statements = $sections['down'];

    if (empty($statements)) {
        return ['success' => false, 'message' => 'DOWN section contains no executable statements in ' . $target['filename']];
    }

    $startTime = microtime(true);
    $ran       = 0;
    $errors    = [];

    foreach ($statements as $sql) {
        try {
            $db->exec($sql);
            $ran++;
        } catch (PDOException $e) {
            $errors[] = [
                'statement_preview' => mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 120) . '…',
                'error'             => $e->getMessage(),
            ];
            break;
        }
    }

    $executionMs = (int)((microtime(true) - $startTime) * 1000);

    if (!empty($errors)) {
        return [
            'success'          => false,
            'version'          => $latestVersion,
            'direction'        => 'down',
            'statements_run'   => $ran,
            'total_statements' => count($statements),
            'execution_ms'     => $executionMs,
            'message'          => "Rollback of v{$latestVersion} failed after {$ran} statement(s).",
            'errors'           => $errors,
        ];
    }

    // Remove from schema_migrations
    $stmt = $db->prepare("DELETE FROM schema_migrations WHERE version = ?");
    $stmt->execute([$latestVersion]);

    return [
        'success'          => true,
        'version'          => $latestVersion,
        'direction'        => 'down',
        'statements_run'   => $ran,
        'total_statements' => count($statements),
        'execution_ms'     => $executionMs,
        'message'          => "v{$latestVersion} — {$target['description']} rolled back successfully in {$executionMs}ms.",
    ];
}

// ================================================================
// Schema Dump
// ================================================================

/**
 * Dump the current database schema (no data) to database/schema_MM-DD-YY_HH-MM.sql.
 * Credentials are passed via a temp file so they never appear in the process list.
 */
function handleDumpSchema(): array
{
    // Resolve database/ folder (one level up from migrations/)
    $dumpDir = dirname(MIGRATIONS_DIR);
    if (!$dumpDir || !is_dir($dumpDir)) {
        return ['success' => false, 'message' => 'Database directory not found.'];
    }
    if (!is_writable($dumpDir)) {
        return [
            'success' => false,
            'message' => 'Database directory is not writable by the web server. '
                       . 'Run: chown www-data:www-data ' . $dumpDir,
        ];
    }
    if (!function_exists('exec')) {
        return ['success' => false, 'message' => 'PHP exec() is disabled on this server.'];
    }

    // MM-DD-YY_HH-MM  (slashes/colons break filenames)
    $timestamp = date('m-d-y_H-i');
    $filename  = 'schema_' . $timestamp . '.sql';
    $filepath  = $dumpDir . '/' . $filename;

    // Write temp credentials file — keeps password out of the process list
    $tmpFile = tempnam(sys_get_temp_dir(), 'resmon_db_');
    // Force TCP (127.0.0.1) instead of Unix socket so mysqldump always
    // authenticates as resmon_web@'%' — socket connections bind to @'localhost'
    // which is a different user entry and may lack SHOW VIEW.
    $dumpHost = (DB_HOST === 'localhost') ? '127.0.0.1' : DB_HOST;
    $cnf     = "[client]\nuser=" . DB_USER
             . "\npassword=" . DB_PASS
             . "\nhost=" . $dumpHost
             . "\nport=" . DB_PORT . "\n";
    file_put_contents($tmpFile, $cnf);
    chmod($tmpFile, 0600);

    // Separate stderr so we get the real error, not the dump header.
    // stdout goes straight to the file — no PHP memory overhead.
    $errFile = tempnam(sys_get_temp_dir(), 'resmon_err_');

    // Build command — schema only, no data.
    // --single-transaction + --skip-lock-tables avoids needing LOCK TABLES privilege.
    // --no-tablespaces avoids needing PROCESS privilege.
    // --routines includes stored procedures (needs SELECT on mysql.proc).
    // --events includes scheduled events (needs EVENT privilege on the DB).
    $cmd = sprintf(
        'mysqldump --defaults-file=%s --no-data --routines --events --add-drop-table --single-transaction --skip-lock-tables --no-tablespaces %s > %s 2>%s',
        escapeshellarg($tmpFile),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath),
        escapeshellarg($errFile)
    );

    $exitCode = 0;
    exec($cmd, $ignored, $exitCode);

    // Always remove temp credentials file
    @unlink($tmpFile);

    if ($exitCode !== 0) {
        $errMsg = trim((string)file_get_contents($errFile));
        @unlink($errFile);
        @unlink($filepath); // remove partial dump
        return [
            'success' => false,
            'message' => 'mysqldump failed (exit ' . $exitCode . '): ' . ($errMsg ?: 'no error output captured'),
        ];
    }

    @unlink($errFile);
    $size = (int)filesize($filepath);

    return [
        'success'  => true,
        'message'  => 'Schema dumped successfully.',
        'filename' => $filename,
        'filepath' => 'database/' . $filename,
        'size_fmt' => formatBytes($size),
    ];
}

// ================================================================
// Router
// ================================================================

try {
    $db     = getDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        echo json_encode(buildStatus($db), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'POST') {
        $raw    = file_get_contents('php://input');
        $body   = json_decode($raw, true) ?? [];
        $action = $body['action'] ?? '';

        switch ($action) {
            case 'status':
                echo json_encode(buildStatus($db), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;

            case 'migrate_up':
                $result = migrateUp($db);
                http_response_code($result['success'] ? 200 : 422);
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;

            case 'migrate_down':
                $result = migrateDown($db);
                http_response_code($result['success'] ? 200 : 422);
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;

            case 'dump_schema':
                $result = handleDumpSchema();
                http_response_code($result['success'] ? 200 : 500);
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Unknown action: '{$action}'. Valid actions: status, migrate_up, migrate_down, dump_schema."]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET or POST.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'file'    => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getFile() . ':' . $e->getLine() : null,
    ], JSON_PRETTY_PRINT);
}
