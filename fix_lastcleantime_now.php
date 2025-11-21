<?php
/**
 * 修复 lastcleantime，让它立即可以执行 cleanup
 */

require 'include/bittorrent.php';
dbconn();

echo "=== 修复 lastcleantime ===\n\n";

$now = time();
// 设置为当前时间减去 1.1 小时，这样立即可以执行
$newTime = $now - (3600 * 1.1);

echo "当前时间: $now (" . date('Y-m-d H:i:s', $now) . ")\n";
echo "设置 lastcleantime 为: $newTime (" . date('Y-m-d H:i:s', $newTime) . ")\n";
echo "这样时间差是: " . ($now - $newTime) . " 秒 (" . round(($now - $newTime) / 3600, 2) . " 小时)\n";
echo "\n";

sql_query("UPDATE avps SET value_u = $newTime WHERE arg = 'lastcleantime'");
$affected = mysql_affected_rows();

if ($affected > 0) {
    echo "✓ 修复成功！affected_rows = $affected\n";
    echo "下次 cleanup 执行时应该会正常工作了\n";
} else {
    echo "❌ 修复失败！affected_rows = $affected\n";
}

echo "\n=== 修复完成 ===\n";

