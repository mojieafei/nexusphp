<?php
/**
 * 清理旧的失败任务
 */

require 'include/bittorrent.php';
dbconn();

echo "=== 清理旧的失败任务 ===\n\n";

// 删除 11-18 之前的失败任务
$beforeDate = '2025-11-18 00:00:00';
$res = sql_query("SELECT COUNT(*) as count FROM failed_jobs WHERE queue = 'nexus_queue' AND failed_at < '$beforeDate'");
$row = mysql_fetch_array($res);
$count = $row['count'] ?? 0;

echo "找到 $count 个旧的失败任务（11-18之前）\n";

if ($count > 0) {
    sql_query("DELETE FROM failed_jobs WHERE queue = 'nexus_queue' AND failed_at < '$beforeDate'");
    $deleted = mysql_affected_rows();
    echo "已删除 $deleted 个旧任务\n";
} else {
    echo "没有需要清理的任务\n";
}

echo "\n检查最近的失败任务（11-18之后）：\n";
$res = sql_query("SELECT COUNT(*) as count FROM failed_jobs WHERE queue = 'nexus_queue' AND failed_at >= '$beforeDate'");
$row = mysql_fetch_array($res);
$recentCount = $row['count'] ?? 0;
echo "11-18之后还有 $recentCount 个失败任务\n";

if ($recentCount > 0) {
    echo "这些是最近的失败任务，需要检查原因\n";
}

echo "\n=== 清理完成 ===\n";

