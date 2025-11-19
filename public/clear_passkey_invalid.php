<?php
/**
 * 清除 Redis 中所有 passkey_invalid 缓存键
 * 使用方法: 在浏览器访问此文件或通过命令行运行: php public/clear_passkey_invalid.php
 */

require_once __DIR__ . '/../include/bittorrent.php';
require_once ROOT_PATH . 'include/core.php';

$redis = $Cache->getRedis();
if (!$redis) {
    die("Redis connection failed!\n");
}

$pattern = 'passkey_invalid:*';
$deletedCount = 0;
$it = NULL;

// 使用 SCAN 命令遍历所有匹配的键
$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);

echo "Starting to clear passkey_invalid cache keys...\n";

do {
    $keys = $redis->scan($it, $pattern, 1000);
    
    if ($keys !== false && !empty($keys)) {
        foreach ($keys as $key) {
            if ($redis->del($key)) {
                $deletedCount++;
                echo "Deleted: $key\n";
            }
        }
    }
} while ($it > 0);

$log = sprintf('[%s] Cleared %d passkey_invalid cache keys', date('Y-m-d H:i:s'), $deletedCount);
echo "\n$log\n";
do_log($log);

echo "\nSuccessfully cleared $deletedCount passkey_invalid cache keys.\n";

