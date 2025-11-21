<?php

/**
 * do clean in cli
 *
 */

require "bittorrent.php";
require 'cleanup.php';

$fd = fopen(sprintf('%s/nexus_cleanup_cli.lock', sys_get_temp_dir()), 'w+');
if (!flock($fd, LOCK_EX|LOCK_NB)) {
    do_log("can not get lock, skip!");
    exit();
}
register_shutdown_function(function () use ($fd) {
    flock($fd, LOCK_UN);
    fclose($fd);
});

$force = 0;
if (isset($_SERVER['argv'][1])) {
    $force = $_SERVER['argv'][1] ? 1 : 0;
}
$logPrefix = "[CLEANUP_CLI]";
$begin = time();
if ($force) {
    $result = docleanup(1, true);
} else {
    $result = autoclean(true);
    // 如果 autoclean 返回 false，检查是否是并发问题
    if (!$result) {
        global $autoclean_interval_one;
        $res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime'");
        $row = mysql_fetch_array($res);
        if ($row) {
            $ts = $row['value_u'];
            $now = time();
            $timeDiff = $now - $ts;
            // 如果时间间隔已到（超过1小时），但 UPDATE 失败（可能是并发），强制执行一次
            // 但要确保不会频繁执行（至少间隔50分钟）
            if ($timeDiff >= $autoclean_interval_one && $timeDiff < $autoclean_interval_one + 300) {
                do_log("$logPrefix, autoclean returned false but interval reached (diff: {$timeDiff}s), likely concurrency issue, force execute");
                $result = docleanup(0, true);
                // 强制执行后，更新 lastcleantime 避免重复执行
                if ($result) {
                    sql_query("UPDATE avps SET value_u=$now WHERE arg='lastcleantime'");
                }
            }
        }
    }
}
$log = "$logPrefix, DONE: $result, cost time in seconds: " . (time() - $begin);
do_log($log);
printProgress($log);

