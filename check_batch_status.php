<?php
require 'include/bittorrent.php';
dbconn();

echo "=== 检查 batch 状态 ===\n\n";

global $Cache;
$redis = $Cache->getRedis();

// 1. 检查 batch_key:user_seed_bonus
$batchKey = 'batch_key:user_seed_bonus';
echo "1. 检查 batch_key:user_seed_bonus：\n";
$batch = $redis->get($batchKey);
if ($batch) {
    echo "  ✓ batch_key 存在，指向: $batch\n";
    $exists = $redis->exists($batch);
    if ($exists) {
        $count = $redis->hLen($batch);
        echo "  ✓ batch hash 存在，用户数: $count\n";
        
        // 检查用户ID 1
        $user1Exists = $redis->hExists($batch, '1');
        echo "  用户ID 1 是否存在: " . ($user1Exists ? "是" : "否") . "\n";
        if ($user1Exists) {
            $user1Value = $redis->hGet($batch, '1');
            echo "  用户ID 1 的值: $user1Value (" . date('Y-m-d H:i:s', $user1Value) . ")\n";
        }
    } else {
        echo "  ❌ batch hash 不存在！这就是问题所在！\n";
        echo "  原因：batch_key 指向的 hash key 已过期或被删除\n";
    }
} else {
    echo "  ❌ batch_key 不存在！\n";
    echo "  原因：没有用户做种汇报，或者 batch 被清空了\n";
}

// 2. 检查错误日志
echo "\n2. 检查错误日志（查找 'no batch'）：\n";
$logFiles = glob("/tmp/nexus-cli-root-*.log");
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    $cmd = "grep -i 'no batch\|batch.*not exists' '$latestLog' | tail -10";
    $output = shell_exec($cmd);
    if ($output) {
        echo $output . "\n";
    } else {
        echo "  没有找到相关错误日志\n";
    }
}

// 3. 检查最近的 cleanup 执行
echo "\n3. 检查最近的 cleanup 执行（16:53）：\n";
$cmd = "grep -i '16:53.*batch\|16:53.*runBatchJob' '$latestLog' | tail -10";
$output = shell_exec($cmd);
if ($output) {
    echo $output . "\n";
} else {
    echo "  没有找到相关日志\n";
    echo "  这说明 runBatchJobCalculateUserSeedBonus() 可能直接 return 了（batch 不存在）\n";
}

// 4. 建议
echo "\n=== 建议 ===\n";
if (!$batch || !$redis->exists($batch)) {
    echo "⚠️  batch 不存在，需要等待用户做种汇报重新生成\n";
    echo "   或者检查是否有用户在做种：\n";
    echo "   SELECT COUNT(DISTINCT userid) FROM peers WHERE seeder = 'yes';\n";
} else {
    echo "✓ batch 存在，应该可以正常分发任务\n";
    echo "  如果还是没有任务分发，可能是其他问题\n";
}

