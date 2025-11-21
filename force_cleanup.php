<?php
require 'include/bittorrent.php';
require 'include/cleanup.php';
dbconn();

echo "强制执行 cleanup...\n";
$result = docleanup(1, true);
echo "结果: " . ($result ?: '空') . "\n";

