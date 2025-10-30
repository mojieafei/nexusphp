<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
define('IN_NEXUS', true);
define('NEXUS_START', microtime(true));
// Support both ROOT_PATH at project root and ROOT_PATH at project_root/nexus
// Try requiring files from ROOT_PATH, then fall back to its parent directory
$__requireBases = [
    rtrim(ROOT_PATH, '/') . '/',
    rtrim(dirname(ROOT_PATH), '/') . '/',
];
$__requireOnce = function (string $relative) use ($__requireBases) {
    foreach ($__requireBases as $base) {
        $candidate = $base . ltrim($relative, '/');
        if (file_exists($candidate)) {
            require $candidate;
            return true;
        }
    }
    throw new \RuntimeException("Required file not found in candidates for: {$relative}");
};

$__requireOnce('include/globalfunctions.php');
$__requireOnce('include/functions.php');
$__requireOnce('vendor/autoload.php');
$__requireOnce('nexus/Database/helpers.php');
$__requireOnce('include/constants.php');
$withLaravel = false;
if (file_exists(ROOT_PATH . '.env')) {
    require ROOT_PATH . 'include/eloquent.php';
    require ROOT_PATH . 'classes/class_cache_redis.php';
    $Cache = new class_cache_redis();
    $withLaravel = true;
}
define('WITH_LARAVEL', $withLaravel);
\Nexus\Nexus::boot();
$hook = new \Nexus\Plugin\Hook();
$plugin = new \Nexus\Plugin\Plugin();
