<?php
/**
 * 查看失败任务的错误信息
 */

require 'include/bittorrent.php';
dbconn();

echo "=== 查看失败任务错误信息 ===\n\n";

// 查看最近的失败任务
$res = sql_query("SELECT id, uuid, queue, payload, exception, failed_at FROM failed_jobs WHERE queue = 'nexus_queue' ORDER BY failed_at DESC LIMIT 5");

if (mysql_num_rows($res) == 0) {
    echo "没有失败任务\n";
    exit;
}

while ($row = mysql_fetch_array($res)) {
    echo "ID: {$row['id']}\n";
    echo "UUID: {$row['uuid']}\n";
    echo "失败时间: {$row['failed_at']}\n";
    
    // 解析 payload 查看任务类型
    $payload = json_decode($row['payload'], true);
    if (isset($payload['displayName'])) {
        echo "任务类型: {$payload['displayName']}\n";
    }
    
    // 显示错误信息
    if ($row['exception']) {
        $exception = $row['exception'];
        // 提取错误信息的前几行
        $lines = explode("\n", $exception);
        echo "错误信息（前10行）：\n";
        foreach (array_slice($lines, 0, 10) as $line) {
            echo "  " . trim($line) . "\n";
        }
    }
    echo "\n" . str_repeat("-", 80) . "\n\n";
}

