<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查最近的 cleanup 执行 ===\n\n";

// 1. 检查 cleanup_cli 日志
echo "1. cleanup_cli 日志（最后 5 条）：\n";
$cleanupLog = "/tmp/cleanup_cli_dubhe.log";
if (file_exists($cleanupLog)) {
    $lines = file($cleanupLog);
    $lastLines = array_slice($lines, -5);
    foreach ($lastLines as $line) {
        echo "  " . trim($line) . "\n";
    }
}

// 2. 检查任务分发日志
echo "\n2. 检查任务分发日志（最近 10 条）：\n";
$logFiles = glob("/tmp/nexus-cli-root-*.log");
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    $cmd = "grep -i 'runBatchJobCalculateUserSeedBonus\|batch_key:user_seed_bonus.*DONE' '$latestLog' | tail -10";
    $output = shell_exec($cmd);
    if ($output) {
        echo $output . "\n";
    } else {
        echo "  ❌ 没有找到任务分发日志\n";
    }
}

// 3. 检查任务执行日志
echo "\n3. 检查任务执行日志（最近 10 条）：\n";
$cmd = "grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB' '$latestLog' | tail -10";
$output = shell_exec($cmd);
if ($output) {
    echo $output . "\n";
} else {
    echo "  ❌ 没有找到任务执行日志\n";
}

// 4. 检查 SQL 执行日志
echo "\n4. 检查 SQL 执行日志（最近 10 条）：\n";
$cmd = "grep -i 'SQL_SUCCESS\|SQL_ERROR\|SQL_BEFORE_EXECUTE' '$latestLog' | tail -10";
$output = shell_exec($cmd);
if ($output) {
    echo $output . "\n";
} else {
    echo "  ❌ 没有找到 SQL 执行日志\n";
    echo "  可能原因：\n";
    echo "    - 任务还没执行（代码刚改，需要等待）\n";
    echo "    - 任务执行了但 idStr 为空，直接 return 了\n";
    echo "    - 任务执行了但遇到了其他问题\n";
}

// 5. 检查队列中的任务
echo "\n5. 检查队列中的任务：\n";
global $Cache;
$redis = $Cache->getRedis();
$queueKey = 'queues:nexus_queue';
$pending = $redis->llen($queueKey);
echo "  队列中待处理的任务数: $pending\n";

$reservedKey = 'queues:nexus_queue:reserved';
$reserved = $redis->zcard($reservedKey);
echo "  正在执行的任务数: $reserved\n";

// 6. 检查 batch_key:user_seed_bonus
echo "\n6. 检查 batch_key:user_seed_bonus：\n";
$batchKey = 'batch_key:user_seed_bonus';
$batch = $redis->get($batchKey);
if ($batch) {
    $exists = $redis->exists($batch);
    if ($exists) {
        $count = $redis->hLen($batch);
        echo "  ✓ batch 存在，用户数: $count\n";
    } else {
        echo "  ❌ batch 不存在！\n";
    }
} else {
    echo "  ❌ batch_key 不存在！\n";
}

// 7. 检查最近的 cleanup 执行时间
echo "\n7. 检查最近的 cleanup 执行时间：\n";
$cmd = "grep -i 'calculate seeding bonus' '$latestLog' | tail -5";
$output = shell_exec($cmd);
if ($output) {
    echo $output . "\n";
} else {
    echo "  没有找到相关日志\n";
}

