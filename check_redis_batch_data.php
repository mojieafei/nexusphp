<?php
/**
 * 检查为什么 cleanup 没有日志
 * 直接运行: php check_redis_batch_data.php
 */

require 'include/bittorrent.php';
dbconn();

echo "=== 检查 cleanup 没有日志的原因 ===\n\n";

global $Cache;
try {
    $redis = $Cache->getRedis();
    
    // 检查 batch_key:user_seed_bonus（这是关键）
    $batchKey = 'batch_key:user_seed_bonus';
    echo "1. 检查 Redis 中的 $batchKey:\n";
    $actualBatch = $redis->get($batchKey);
    
    if ($actualBatch === false) {
        echo "   ❌ 问题找到了！$batchKey 不存在！\n";
        echo "   这就是为什么没有日志的原因：\n";
        echo "   - cleanup 执行时，runBatchJobCalculateUserSeedBonus() 被调用\n";
        echo "   - 但 getBatch() 发现 Redis 中没有数据，直接返回了\n";
        echo "   - 所以没有分发任何任务，也就没有日志\n\n";
    } else {
        echo "   ✓ $batchKey 存在，指向: $actualBatch\n";
        if (!$redis->exists($actualBatch)) {
            echo "   ❌ 但 $actualBatch 这个 hash 不存在！\n";
        } else {
            $count = $redis->hLen($actualBatch);
            echo "   待处理的用户数: $count\n";
            if ($count == 0) {
                echo "   ⚠️  用户数为 0，所以没有任务分发\n";
            }
        }
    }
    
    // 检查做种用户
    echo "\n2. 检查数据库中的做种用户：\n";
    $res = sql_query("SELECT COUNT(DISTINCT userid) as seeding_users FROM peers WHERE seeder = 'yes'");
    $row = mysql_fetch_array($res);
    $seedingUsers = $row['seeding_users'] ?? 0;
    echo "   做种用户数: $seedingUsers\n";
    
    if ($seedingUsers > 0 && ($actualBatch === false || !$redis->exists($actualBatch))) {
        echo "\n   ⚠️  问题确认：数据库中有做种用户，但 Redis 中没有数据！\n";
        echo "   原因：数据库迁移时 Redis 数据丢失了\n";
        echo "   解决：用户需要重新做种（重新连接 tracker），announce.php 会重新写入 Redis\n";
    }
    
} catch (Exception $e) {
    echo "❌ Redis 错误: " . $e->getMessage() . "\n";
}

echo "\n=== 检查完成 ===\n";

