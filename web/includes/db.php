<?php
/**
 * Database Connection and Utility Functions
 */

// Load configuration and version constants
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/version.php';

/**
 * Get database connection
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please check configuration.");
            }
        }
    }
    
    return $pdo;
}

/**
 * Execute a query and return results
 */
function query($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a query and return single row
 */
function queryOne($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Execute an insert/update/delete query
 */
function execute($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get last insert ID
 */
function lastInsertId() {
    return getDB()->lastInsertId();
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(log($bytes) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

/**
 * Format number with commas
 */
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals);
}

/**
 * Format percentage
 */
function formatPercent($value, $decimals = 1) {
    return number_format($value, $decimals) . '%';
}

/**
 * Get status color based on value and thresholds
 */
function getStatusColor($value, $warning = 70, $critical = 85) {
    if ($value >= $critical) {
        return 'red';
    } elseif ($value >= $warning) {
        return 'yellow';
    } else {
        return 'green';
    }
}

/**
 * Get status badge HTML
 */
function getStatusBadge($value, $warning = 70, $critical = 85) {
    $color = getStatusColor($value, $warning, $critical);
    
    $colors = [
        'green' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'
    ];
    
    $icons = [
        'green' => '✓',
        'yellow' => '⚠',
        'red' => '✗'
    ];
    
    return sprintf(
        '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s %s%%</span>',
        $colors[$color],
        $icons[$color],
        number_format($value, 1)
    );
}

/**
 * Escape HTML output
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Return JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Return error JSON response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Get time ago in human readable format
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } else {
        return floor($diff / 86400) . ' days ago';
    }
}

/**
 * Parse time range to SQL WHERE clause
 */
function parseTimeRange($range) {
    $intervals = [
        '5m'  => '5 MINUTE',
        '15m' => '15 MINUTE',
        '30m' => '30 MINUTE',
        '1h'  => '1 HOUR',
        '3h'  => '3 HOUR',
        '6h'  => '6 HOUR',
        '12h' => '12 HOUR',
        '24h' => '24 HOUR',
        '7d'  => '7 DAY',
        '30d' => '30 DAY'
    ];
    
    if (!isset($intervals[$range])) {
        $range = '1h';
    }
    
    return "timestamp >= DATE_SUB(NOW(), INTERVAL {$intervals[$range]})";
}

/**
 * Get current theme from cookie
 */
function getTheme() {
    return $_COOKIE['theme'] ?? 'dark';
}

/**
 * Set theme cookie
 */
function setTheme($theme) {
    setcookie('theme', $theme, time() + (86400 * 365), '/');
}
