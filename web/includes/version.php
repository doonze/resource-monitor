<?php
/**
 * Resource Monitor — Application Version Constants
 *
 * APP_VERSION             — Semantic version of this application release.
 * REQUIRED_SCHEMA_VERSION — The minimum schema_migrations version this app requires.
 *                           Bump this whenever a new migration is added and the app
 *                           depends on it. The UI will warn operators if the DB is behind.
 *
 * DO NOT define these in config.php — they belong in source control, not per-env config.
 */

if (!defined('APP_VERSION') || !defined('REQUIRED_SCHEMA_VERSION')) {
    // Single source of truth — read from repo root version.json.
    // Bump that file only; both PHP and Python pick it up automatically.
    $versionFile = realpath(__DIR__ . '/../../version.json');
    $versionData = $versionFile ? json_decode(file_get_contents($versionFile), true) : [];

    if (!defined('APP_VERSION')) {
        define('APP_VERSION', $versionData['app_version'] ?? 'unknown');
    }
    if (!defined('REQUIRED_SCHEMA_VERSION')) {
        define('REQUIRED_SCHEMA_VERSION', (int) ($versionData['schema_version'] ?? 0));
    }
}
