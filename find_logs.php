<?php
/**
 * 查找日志文件位置
 */

require 'include/bittorrent.php';

echo "=== 查找日志文件位置 ===\n\n";

// 1. 检查环境变量
echo "1. 检查日志相关环境变量：\n";
$logFile = nexus_env('LOG_FILE');
$logDir = getenv('NEXUS_LOG_DIR', true);
echo "   LOG_FILE: " . ($logFile ?: '未设置') . "\n";
echo "   NEXUS_LOG_DIR: " . ($logDir ?: '未设置') . "\n";
echo "\n";

// 2. 获取实际日志文件路径
echo "2. 实际日志文件路径：\n";
$actualLogFile = getLogFile();
echo "   " . $actualLogFile . "\n";
if (file_exists($actualLogFile)) {
    $size = filesize($actualLogFile);
    echo "   文件存在，大小: " . number_format($size) . " 字节\n";
} else {
    echo "   文件不存在\n";
}
echo "\n";

// 3. 查找所有可能的日志文件
echo "3. 查找所有可能的日志文件：\n";
$searchPaths = [
    '/tmp',
    sys_get_temp_dir(),
    storage_path('logs'),
    ROOT_PATH . 'storage/logs',
];

foreach ($searchPaths as $path) {
    if (is_dir($path)) {
        $files = glob($path . '/nexus.log*');
        if (!empty($files)) {
            echo "   在 $path 找到：\n";
            foreach ($files as $file) {
                $size = filesize($file);
                $mtime = date('Y-m-d H:i:s', filemtime($file));
                echo "     - $file (大小: " . number_format($size) . ", 修改时间: $mtime)\n";
            }
        }
    }
}
echo "\n";

// 4. 检查 cleanup 相关日志
echo "4. 检查 cleanup 相关日志（最近 20 行）：\n";
if (file_exists($actualLogFile)) {
    $lines = file($actualLogFile);
    $cleanupLines = [];
    foreach ($lines as $line) {
        if (stripos($line, 'cleanup') !== false || 
            stripos($line, 'batch_key') !== false || 
            stripos($line, 'runBatchJob') !== false ||
            stripos($line, 'CalculateUserSeedBonus') !== false) {
            $cleanupLines[] = $line;
        }
    }
    if (!empty($cleanupLines)) {
        echo "   找到 " . count($cleanupLines) . " 条相关日志，显示最后 20 条：\n";
        $recent = array_slice($cleanupLines, -20);
        foreach ($recent as $line) {
            echo "   " . trim($line) . "\n";
        }
    } else {
        echo "   没有找到 cleanup 相关日志\n";
    }
} else {
    echo "   日志文件不存在，无法检查\n";
}
echo "\n";

echo "=== 检查完成 ===\n";

