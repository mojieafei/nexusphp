<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查任务执行情况 ===\n\n";

// 1. 检查最近的日志文件，看任务是否执行
$logFile = "/tmp/nexus-seed-bonus-points-cli-www-data-" . date('Y-m-d') . ".log";
if (file_exists($logFile)) {
    $lines = file($logFile);
    $user1Lines = [];
    foreach ($lines as $line) {
        $parts = explode('|', trim($line));
        if (count($parts) >= 8 && $parts[1] == '1') {
            $user1Lines[] = $line;
        }
    }
    echo "用户ID 1 的日志记录数: " . count($user1Lines) . "\n";
    if (!empty($user1Lines)) {
        $lastLine = trim(end($user1Lines));
        $parts = explode('|', $lastLine);
        echo "最后一条记录: $lastLine\n";
        echo "  时间: {$parts[0]}\n";
        echo "  用户ID: {$parts[1]}\n";
        echo "  seed_points: {$parts[2]} -> {$parts[4]} (增量: {$parts[3]})\n";
        echo "  seedbonus: {$parts[5]} -> {$parts[7]} (增量: {$parts[6]})\n";
    }
}

// 2. 检查数据库当前值
echo "\n=== 数据库当前值 ===\n";
$res = sql_query("SELECT id, seedbonus, seed_points, seed_points_updated_at FROM users WHERE id = 1");
$row = mysql_fetch_array($res);
echo "seedbonus: {$row['seedbonus']}\n";
echo "seed_points: {$row['seed_points']}\n";
echo "seed_points_updated_at: {$row['seed_points_updated_at']}\n";

// 3. 检查任务执行日志（看是否有 DONE 或错误）
echo "\n=== 检查任务执行日志 ===\n";
$logFiles = glob("/tmp/nexus-cli-root-*.log");
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    echo "最新日志文件: $latestLog\n";
    
    // 查找包含用户ID 1 的任务执行记录
    $cmd = "grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB' '$latestLog' | grep -i '1\|DONE\|GET_UID_REAL' | tail -20";
    $output = shell_exec($cmd);
    if ($output) {
        echo "任务执行记录:\n$output\n";
    } else {
        echo "没有找到任务执行记录\n";
    }
    
    // 查找 SQL 执行记录
    $cmd = "grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB.*SQL\|SQL_SUCCESS\|SQL.*ERROR' '$latestLog' | tail -10";
    $output = shell_exec($cmd);
    if ($output) {
        echo "\nSQL 执行记录:\n$output\n";
    } else {
        echo "\n没有找到 SQL 执行记录（可能代码还没执行，或者日志级别不对）\n";
    }
}

// 4. 检查是否有其他代码在更新 seedbonus（检查最近是否有其他更新）
echo "\n=== 检查是否有其他更新 ===\n";
$res = sql_query("SELECT id, seedbonus FROM users WHERE id = 1");
$row = mysql_fetch_array($res);
echo "当前 seedbonus: {$row['seedbonus']}\n";

// 5. 手动测试 SQL 更新（模拟）
echo "\n=== 测试 SQL 更新（只查询，不更新） ===\n";
$testSql = "SELECT id, seedbonus, seed_points FROM users WHERE id = 1";
$res = sql_query($testSql);
$row = mysql_fetch_array($res);
echo "查询成功，当前值: seedbonus={$row['seedbonus']}, seed_points={$row['seed_points']}\n";

