<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查任务执行日志 ===\n\n";

// 1. 检查最新的日志文件
$logFiles = glob("/tmp/nexus-cli-root-*.log");
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    echo "最新日志文件: $latestLog\n";
    echo "文件大小: " . filesize($latestLog) . " 字节\n";
    echo "最后修改时间: " . date('Y-m-d H:i:s', filemtime($latestLog)) . "\n\n";
    
    // 2. 查找所有包含 CalculateUserSeedBonus 的日志
    echo "=== 查找 CalculateUserSeedBonus 相关日志 ===\n";
    $cmd = "grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB' '$latestLog' | tail -20";
    $output = shell_exec($cmd);
    if ($output) {
        echo $output . "\n";
    } else {
        echo "❌ 没有找到任务执行日志\n";
    }
    
    // 3. 查找 SQL 相关日志
    echo "\n=== 查找 SQL 执行日志 ===\n";
    $cmd = "grep -i 'SQL_SUCCESS\|SQL_ERROR\|SQL_BEFORE_EXECUTE' '$latestLog' | tail -20";
    $output = shell_exec($cmd);
    if ($output) {
        echo $output . "\n";
    } else {
        echo "❌ 没有找到 SQL 执行日志（可能任务还没执行，或者日志级别不对）\n";
    }
    
    // 4. 查找任务分发日志
    echo "\n=== 查找任务分发日志 ===\n";
    $cmd = "grep -i 'runBatchJobCalculateUserSeedBonus\|batch_key:user_seed_bonus.*DONE' '$latestLog' | tail -10";
    $output = shell_exec($cmd);
    if ($output) {
        echo $output . "\n";
    } else {
        echo "❌ 没有找到任务分发日志\n";
    }
    
    // 5. 查找错误日志
    echo "\n=== 查找错误日志 ===\n";
    $cmd = "grep -i 'ERROR\|WARNING' '$latestLog' | grep -i 'CalculateUserSeedBonus\|seed_bonus\|Redis key expired' | tail -10";
    $output = shell_exec($cmd);
    if ($output) {
        echo $output . "\n";
    } else {
        echo "没有找到相关错误日志\n";
    }
    
    // 6. 查看最新的日志内容（最后 50 行）
    echo "\n=== 最新日志内容（最后 50 行） ===\n";
    $lines = file($latestLog);
    $lastLines = array_slice($lines, -50);
    foreach ($lastLines as $line) {
        if (stripos($line, 'CalculateUserSeedBonus') !== false || 
            stripos($line, 'seed_bonus') !== false || 
            stripos($line, 'SQL') !== false) {
            echo $line;
        }
    }
}

// 7. 检查队列中的任务
echo "\n=== 检查队列中的任务 ===\n";
global $Cache;
$redis = $Cache->getRedis();
$queueKey = 'queues:nexus_queue';
$pending = $redis->llen($queueKey);
echo "队列中待处理的任务数: $pending\n";

if ($pending > 0) {
    $tasks = $redis->lrange($queueKey, 0, 4);
    foreach ($tasks as $index => $task) {
        $data = json_decode($task, true);
        $jobClass = $data['displayName'] ?? 'unknown';
        if (strpos($jobClass, 'CalculateUserSeedBonus') !== false) {
            echo "  任务 " . ($index + 1) . ": $jobClass\n";
        }
    }
}

