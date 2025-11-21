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
    // 强制模式：执行所有 cleanup
    $result = docleanup(1, true);
} else {
    // 正常模式：直接执行 cleanup，跳过时间间隔检查
    // 因为有锁文件机制，每分钟只会执行一次，不会重复执行
    $result = docleanup(0, true);
}
$log = "$logPrefix, DONE: $result, cost time in seconds: " . (time() - $begin);
do_log($log);
printProgress($log);

