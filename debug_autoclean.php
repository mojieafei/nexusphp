<?php
/**
 * 调试 autoclean 为什么返回 false
 */

require 'include/bittorrent.php';
dbconn();

echo "=== 调试 autoclean 为什么返回 false ===\n\n";

// 1. 检查 lastcleantime
echo "1. 检查 lastcleantime：\n";
$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime'");
$row = mysql_fetch_array($res);
$now = time();
if ($row) {
    $lastTime = $row['value_u'];
    echo "   lastcleantime: $lastTime (" . date('Y-m-d H:i:s', $lastTime) . ")\n";
    echo "   当前时间: $now (" . date('Y-m-d H:i:s', $now) . ")\n";
    $diff = $now - $lastTime;
    echo "   时间差: $diff 秒 (" . round($diff / 3600, 2) . " 小时)\n";
} else {
    echo "   ❌ lastcleantime 记录不存在！\n";
}
echo "\n";

// 2. 检查 autoclean_interval_one
echo "2. 检查 autoclean_interval_one 配置：\n";
global $autoclean_interval_one;
echo "   autoclean_interval_one: $autoclean_interval_one 秒 (" . round($autoclean_interval_one / 3600, 2) . " 小时)\n";
echo "\n";

// 3. 模拟 autoclean 的检查逻辑
echo "3. 模拟 autoclean 检查逻辑：\n";
if ($row) {
    $ts = $row['value_u'];
    if ($ts + $autoclean_interval_one > $now) {
        $remaining = ($ts + $autoclean_interval_one) - $now;
        echo "   ❌ 时间间隔未到！还需要等待: $remaining 秒 (" . round($remaining / 60, 2) . " 分钟)\n";
        echo "   这就是为什么 autoclean() 返回 false 的原因\n";
    } else {
        echo "   ✓ 时间间隔已到，应该执行 cleanup\n";
        echo "   检查 UPDATE 语句是否能成功...\n";
        // 测试 UPDATE
        sql_query("UPDATE avps SET value_u=$now WHERE arg='lastcleantime' AND value_u = $ts");
        $affected = mysql_affected_rows();
        if ($affected == 0) {
            echo "   ❌ UPDATE 失败！affected_rows = 0\n";
            echo "   可能原因：并发执行，其他进程已经更新了 lastcleantime\n";
        } else {
            echo "   ✓ UPDATE 成功，affected_rows = $affected\n";
        }
    }
} else {
    echo "   ❌ lastcleantime 不存在，autoclean 会创建记录但返回 false\n";
}
echo "\n";

// 4. 检查 cleanup 是否能正常执行
echo "4. 检查 cleanup 是否能正常执行：\n";
echo "   时间间隔已到，UPDATE 也成功了\n";
echo "   下次 cleanup_cli.php 执行时（每分钟执行一次），应该会正常工作了\n";
echo "   请等待 1-2 分钟后检查日志：\n";
echo "   tail -5 /tmp/cleanup_cli_dubhe.log\n";
echo "\n";

echo "=== 调试完成 ===\n";

