<?php
require 'include/bittorrent.php';
dbconn();

// 检查用户ID 1 的当前魔力值
$res = sql_query("SELECT id, seedbonus, seed_points, seed_points_updated_at FROM users WHERE id = 1");
$row = mysql_fetch_array($res);
echo "=== 用户ID 1 当前状态 ===\n";
echo "seedbonus: {$row['seedbonus']}\n";
echo "seed_points: {$row['seed_points']}\n";
echo "seed_points_updated_at: {$row['seed_points_updated_at']}\n\n";

// 检查最近的日志
echo "=== 最近的日志（最后5条） ===\n";
$logFile = "/tmp/nexus-seed-bonus-points-cli-www-data-" . date('Y-m-d') . ".log";
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -5);
    foreach ($lastLines as $line) {
        $parts = explode('|', trim($line));
        if (count($parts) >= 8 && $parts[1] == '1') {
            echo "时间: {$parts[0]}\n";
            echo "用户ID: {$parts[1]}\n";
            echo "seed_points: {$parts[2]} -> {$parts[4]} (增量: {$parts[3]})\n";
            echo "seedbonus: {$parts[5]} -> {$parts[7]} (增量: {$parts[6]})\n";
            echo "---\n";
        }
    }
}

// 检查是否有 SQL 执行错误日志
echo "\n=== 检查 SQL 执行日志 ===\n";
$logFiles = glob("/tmp/nexus-cli-root-*.log");
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    echo "最新日志文件: $latestLog\n";
    $cmd = "grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB.*DONE\|update user count\|sql:' '$latestLog' | tail -10";
    echo "执行命令: $cmd\n";
    $output = shell_exec($cmd);
    if ($output) {
        echo $output;
    } else {
        echo "没有找到相关日志（可能是 debug 级别）\n";
    }
}

// 检查是否有其他代码在更新 seedbonus
echo "\n=== 检查是否有其他更新 ===\n";
$res = sql_query("SELECT id, seedbonus, updated_at FROM users WHERE id = 1");
$row = mysql_fetch_array($res);
echo "updated_at: {$row['updated_at']}\n";

