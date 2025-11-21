<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查正在执行的任务详情 ===\n\n";

global $Cache;
$redis = $Cache->getRedis();
$reservedKey = 'queues:nexus_queue:reserved';

// 检查正在执行的任务
$reservedTasks = $redis->zrange($reservedKey, 0, -1);
echo "正在执行的任务数: " . count($reservedTasks) . "\n\n";

foreach ($reservedTasks as $index => $task) {
    $data = json_decode($task, true);
    $jobClass = $data['displayName'] ?? 'unknown';
    $id = $data['id'] ?? 'unknown';
    $reservedAt = $data['reserved_at'] ?? 'unknown';
    
    echo "任务 " . ($index + 1) . ":\n";
    echo "  Job: $jobClass\n";
    echo "  ID: $id\n";
    echo "  Reserved at: $reservedAt\n";
    
    if (strpos($jobClass, 'CalculateUserSeedBonus') !== false) {
        echo "  ✓ 这是 CalculateUserSeedBonus 任务\n";
        if (isset($data['data']['commandName'])) {
            echo "  Command: {$data['data']['commandName']}\n";
        }
        if (isset($data['data']['command'])) {
            echo "  Command args: " . substr($data['data']['command'], 0, 200) . "...\n";
        }
    }
    echo "\n";
}

// 检查任务执行时间
if (count($reservedTasks) > 0) {
    echo "=== 检查任务执行时间 ===\n";
    foreach ($reservedTasks as $task) {
        $data = json_decode($task, true);
        if (isset($data['reserved_at'])) {
            $reservedAt = $data['reserved_at'];
            $now = time();
            $elapsed = $now - $reservedAt;
            echo "任务执行时间: {$elapsed} 秒 (" . round($elapsed / 60, 2) . " 分钟)\n";
            if ($elapsed > 600) {
                echo "  ⚠️ 警告：任务执行时间超过 10 分钟，可能卡住了！\n";
            }
        }
    }
}

