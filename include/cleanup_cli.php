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
    // 直接执行 cleanup，不检查时间间隔（因为每分钟执行一次，时间间隔检查会导致并发问题）
    $result = docleanup(0, true);
}
$log = "$logPrefix, DONE: $result, cost time in seconds: " . (time() - $begin);
do_log($log);
printProgress($log);

