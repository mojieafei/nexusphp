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
    // 修复并发问题：如果 autoclean 返回 false 且是并发导致的，也执行一次
    $result = autoclean(true);
    if (!$result) {
        // 检查是否是并发问题（时间间隔已到但 UPDATE 失败）
        global $autoclean_interval_one;
        $res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime'");
        $row = mysql_fetch_array($res);
        if ($row) {
            $ts = $row['value_u'];
            $now = time();
            // 如果时间间隔已到，说明是并发问题，强制执行
            if ($ts + $autoclean_interval_one <= $now) {
                do_log("$logPrefix, autoclean returned false but interval reached, likely concurrency issue, force execute");
                $result = docleanup(0, true);
            }
        }
    }
}
$log = "$logPrefix, DONE: $result, cost time in seconds: " . (time() - $begin);
do_log($log);
printProgress($log);

