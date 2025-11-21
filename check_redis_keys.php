<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查 Redis 中的 key ===\n\n";

global $Cache;
$redis = $Cache->getRedis();

// 检查 cleanup_batch_job_ids 相关的 key
$keys = $redis->keys('cleanup_batch_job_ids:*');
echo "cleanup_batch_job_ids 相关的 key 数: " . count($keys) . "\n";
if (count($keys) > 0) {
    echo "前 10 个 key:\n";
    foreach (array_slice($keys, 0, 10) as $key) {
        $value = $redis->get($key);
        echo "  $key: " . ($value ? "存在，值: " . substr($value, 0, 100) : "不存在") . "\n";
    }
}

// 检查 batch_key:user_seed_bonus
echo "\n=== 检查 batch_key:user_seed_bonus ===\n";
$batchKey = 'batch_key:user_seed_bonus';
$batch = $redis->get($batchKey);
if ($batch) {
    echo "batch_key:user_seed_bonus 存在，指向: $batch\n";
    $count = $redis->hLen($batch);
    echo "用户数: $count\n";
    
    // 检查用户ID 1 是否在其中
    $user1Exists = $redis->hExists($batch, '1');
    echo "用户ID 1 是否存在: " . ($user1Exists ? "是" : "否") . "\n";
    
    if ($user1Exists) {
        $user1Value = $redis->hGet($batch, '1');
        echo "用户ID 1 的值: $user1Value\n";
        
        // 检查 deadtime
        $anninterthree = (int)get_setting("main.anninterthree");
        $deadtime = time() - floor($anninterthree * 1.3);
        echo "deadtime: $deadtime (" . date('Y-m-d H:i:s', $deadtime) . ")\n";
        echo "用户ID 1 的值是否小于 deadtime: " . ($user1Value < $deadtime ? "是（会被跳过）" : "否（会被处理）") . "\n";
    }
} else {
    echo "batch_key:user_seed_bonus 不存在\n";
}

// 检查正在执行的任务的 idRedisKey
echo "\n=== 检查正在执行的任务的 idRedisKey ===\n";
$reservedKey = 'queues:nexus_queue:reserved';
$reservedTasks = $redis->zrange($reservedKey, 0, -1);
foreach ($reservedTasks as $task) {
    $data = json_decode($task, true);
    if (strpos($data['displayName'] ?? '', 'CalculateUserSeedBonus') !== false) {
        if (isset($data['data']['command'])) {
            // 解析 command 中的 id_redis_key
            if (preg_match('/--id_redis_key=([^\s]+)/', $data['data']['command'], $matches)) {
                $idRedisKey = $matches[1];
                echo "任务的 idRedisKey: $idRedisKey\n";
                $value = $redis->get($idRedisKey);
                if ($value) {
                    echo "  ✓ Redis key 存在，值: " . substr($value, 0, 200) . "...\n";
                } else {
                    echo "  ❌ Redis key 不存在或已过期！\n";
                }
            }
        }
    }
}

