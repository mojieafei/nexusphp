<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 修复队列问题 ===\n\n";

global $Cache;
$redis = $Cache->getRedis();

// 1. 检查 batch_key:user_seed_bonus
echo "1. 检查 batch_key:user_seed_bonus：\n";
$batchKey = 'batch_key:user_seed_bonus';
$batch = $redis->get($batchKey);
if ($batch) {
    echo "  ✓ batch_key:user_seed_bonus 存在，指向: $batch\n";
    $exists = $redis->exists($batch);
    if ($exists) {
        $count = $redis->hLen($batch);
        echo "  ✓ batch hash 存在，用户数: $count\n";
    } else {
        echo "  ❌ batch hash 不存在！需要重新生成\n";
    }
} else {
    echo "  ❌ batch_key:user_seed_bonus 不存在！需要重新生成\n";
}

// 2. 检查队列中的任务
echo "\n2. 检查队列中的任务：\n";
$queueKey = 'queues:nexus_queue';
$pending = $redis->llen($queueKey);
echo "  待处理任务数: $pending\n";

if ($pending > 0) {
    echo "  前 5 个任务：\n";
    $tasks = $redis->lrange($queueKey, 0, 4);
    foreach ($tasks as $index => $task) {
        $data = json_decode($task, true);
        $jobClass = $data['displayName'] ?? 'unknown';
        echo "    " . ($index + 1) . ". $jobClass\n";
    }
}

// 3. 检查正在执行的任务
echo "\n3. 检查正在执行的任务：\n";
$reservedKey = 'queues:nexus_queue:reserved';
$reserved = $redis->zcard($reservedKey);
echo "  正在执行的任务数: $reserved\n";

// 4. 检查 cleanup_batch_job_ids 相关的 key
echo "\n4. 检查 cleanup_batch_job_ids 相关的 key：\n";
$keys = $redis->keys('cleanup_batch_job_ids:*');
echo "  key 数量: " . count($keys) . "\n";
if (count($keys) > 0) {
    $expiredCount = 0;
    foreach ($keys as $key) {
        $ttl = $redis->ttl($key);
        if ($ttl == -2) {
            $expiredCount++;
        }
    }
    echo "  已过期的 key 数: $expiredCount\n";
}

// 5. 建议操作
echo "\n=== 建议操作 ===\n";
if ($pending > 10) {
    echo "⚠️  队列中任务过多（$pending 个），建议清理：\n";
    echo "   php artisan queue:flush\n";
    echo "   或者等待任务自然过期\n";
}

if (!$batch || !$redis->exists($batch)) {
    echo "⚠️  batch_key:user_seed_bonus 不存在或已过期，需要等待用户做种汇报重新生成\n";
    echo "   或者手动触发一次 cleanup：\n";
    echo "   sudo -u www-data php include/cleanup_cli.php 0\n";
}

echo "\n✅ 检查完成\n";

