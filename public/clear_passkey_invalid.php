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

echo "Starting to clear passkey_invalid cache keys...\n";
echo "Pattern: $pattern\n";

// 方法1: 使用 KEYS 命令（仅用于调试，生产环境建议用 SCAN）
// 注意：KEYS 命令在生产环境可能阻塞 Redis，但可以快速检查是否存在这些键
echo "\n=== Method 1: Using KEYS command (for checking) ===\n";
$allKeys = $redis->keys($pattern);
if ($allKeys !== false) {
    echo "Found " . count($allKeys) . " keys using KEYS command\n";
    if (count($allKeys) > 0) {
        echo "Sample keys:\n";
        foreach (array_slice($allKeys, 0, 10) as $key) {
            echo "  - $key\n";
        }
        if (count($allKeys) > 10) {
            echo "  ... and " . (count($allKeys) - 10) . " more\n";
        }
    }
} else {
    echo "KEYS command returned false\n";
}

// 方法2: 使用 SCAN 命令（推荐用于生产环境）
echo "\n=== Method 2: Using SCAN command (recommended) ===\n";
$it = NULL;
$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
$scannedKeys = [];

do {
    $keys = $redis->scan($it, $pattern, 1000);
    
    if ($keys !== false) {
        if (is_array($keys) && !empty($keys)) {
            $scannedKeys = array_merge($scannedKeys, $keys);
            foreach ($keys as $key) {
                if ($redis->del($key)) {
                    $deletedCount++;
                    echo "Deleted: $key\n";
                }
            }
        }
    } else {
        break;
    }
} while ($it > 0);

echo "Scanned " . count($scannedKeys) . " keys using SCAN command\n";

// 如果 SCAN 没找到，但 KEYS 找到了，使用 KEYS 的结果删除
if ($deletedCount == 0 && !empty($allKeys)) {
    echo "\n=== Deleting keys found by KEYS command ===\n";
    foreach ($allKeys as $key) {
        if ($redis->del($key)) {
            $deletedCount++;
            echo "Deleted: $key\n";
        }
    }
}

$log = sprintf('[%s] Cleared %d passkey_invalid cache keys', date('Y-m-d H:i:s'), $deletedCount);
echo "\n$log\n";
do_log($log);

echo "\nSuccessfully cleared $deletedCount passkey_invalid cache keys.\n";

// 最终验证
echo "\n=== Final verification ===\n";
$remainingKeys = $redis->keys($pattern);
if ($remainingKeys !== false) {
    $remainingCount = count($remainingKeys);
    if ($remainingCount > 0) {
        echo "WARNING: Still found $remainingCount remaining keys:\n";
        foreach (array_slice($remainingKeys, 0, 5) as $key) {
            echo "  - $key\n";
        }
    } else {
        echo "✓ All passkey_invalid keys have been cleared!\n";
    }
}

