<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查队列中的任务 ===\n\n";

global $Cache;
$redis = $Cache->getRedis();
$queueKey = 'queues:nexus_queue';

// 检查队列中的任务
$pending = $redis->llen($queueKey);
echo "队列中待处理的任务数: $pending\n\n";

if ($pending > 0) {
    echo "队列中的任务（前 10 个）：\n";
    $tasks = $redis->lrange($queueKey, 0, 9);
    foreach ($tasks as $index => $task) {
        $data = json_decode($task, true);
        $jobClass = $data['displayName'] ?? 'unknown';
        $id = $data['id'] ?? 'unknown';
        echo "  " . ($index + 1) . ". Job: $jobClass, ID: $id\n";
        if (strpos($jobClass, 'CalculateUserSeedBonus') !== false) {
            echo "     ✓ 这是 CalculateUserSeedBonus 任务\n";
            if (isset($data['data']['commandName'])) {
                echo "     Command: {$data['data']['commandName']}\n";
            }
        }
    }
}

// 检查是否有正在执行的任务
echo "\n=== 检查正在执行的任务 ===\n";
$reservedKey = 'queues:nexus_queue:reserved';
$reserved = $redis->zcard($reservedKey);
echo "正在执行的任务数: $reserved\n";

if ($reserved > 0) {
    $reservedTasks = $redis->zrange($reservedKey, 0, 4);
    foreach ($reservedTasks as $task) {
        $data = json_decode($task, true);
        $jobClass = $data['displayName'] ?? 'unknown';
        echo "  - Job: $jobClass\n";
    }
}

// 检查最近的 cleanup 是否调用了 runBatchJobCalculateUserSeedBonus
echo "\n=== 检查最近的 cleanup 调用 ===\n";
$logFiles = glob("/tmp/nexus-cli-root-*.log");
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    $cmd = "grep -i 'runBatchJobCalculateUserSeedBonus\|batch_key:user_seed_bonus' '$latestLog' | tail -5";
    $output = shell_exec($cmd);
    if ($output) {
        echo "最近的调用记录:\n$output\n";
    } else {
        echo "❌ 没有找到调用记录\n";
    }
}

