<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查队列任务状态 ===\n\n";

// 1. 检查 Horizon 是否运行
echo "1. 检查 Horizon 进程：\n";
$output = shell_exec("ps aux | grep -E 'horizon|queue:work' | grep -v grep");
if ($output) {
    echo $output . "\n";
} else {
    echo "❌ Horizon 没有运行！\n";
}

// 2. 检查队列中是否有任务
echo "\n2. 检查 Redis 队列中的任务：\n";
global $Cache;
$redis = $Cache->getRedis();
$queueKey = 'queues:nexus_queue';
$pending = $redis->llen($queueKey);
echo "队列 'nexus_queue' 中待处理的任务数: $pending\n";

// 3. 检查是否有失败的任务
echo "\n3. 检查失败的任务：\n";
$failedJobs = sql_query("SELECT COUNT(*) as cnt FROM failed_jobs WHERE queue = 'nexus_queue'");
$row = mysql_fetch_array($failedJobs);
echo "失败的任务数: {$row['cnt']}\n";
if ($row['cnt'] > 0) {
    $recentFailed = sql_query("SELECT id, queue, payload, failed_at FROM failed_jobs WHERE queue = 'nexus_queue' ORDER BY failed_at DESC LIMIT 5");
    echo "最近失败的任务：\n";
    while ($failed = mysql_fetch_array($recentFailed)) {
        $payload = json_decode($failed['payload'], true);
        $jobClass = $payload['displayName'] ?? 'unknown';
        echo "  - ID: {$failed['id']}, Job: $jobClass, Failed at: {$failed['failed_at']}\n";
    }
}

// 4. 检查最近的日志
echo "\n4. 检查最近的日志（查找任务执行记录）：\n";
$logFiles = glob("/tmp/nexus-cli-root-*.log");
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    echo "最新日志文件: $latestLog\n";
    
    // 查找任务执行记录
    $cmd = "grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB' '$latestLog' | tail -10";
    $output = shell_exec($cmd);
    if ($output) {
        echo "任务执行记录:\n$output\n";
    } else {
        echo "❌ 没有找到任务执行记录\n";
    }
}

// 5. 检查用户ID 1 的当前状态
echo "\n5. 检查用户ID 1 的当前状态：\n";
$res = sql_query("SELECT id, seedbonus, seed_points, seed_points_updated_at FROM users WHERE id = 1");
$row = mysql_fetch_array($res);
echo "seedbonus: {$row['seedbonus']}\n";
echo "seed_points: {$row['seed_points']}\n";
echo "seed_points_updated_at: {$row['seed_points_updated_at']}\n";

