<?php
/**
 * 诊断魔力值不更新的问题
 * 位置：/var/www/nexusphp/check_seed_bonus_issue.php
 */

require "bittorrent.php";

echo "=== 魔力值更新问题诊断 ===\n\n";

// 1. 检查 batch_key:user_seed_bonus
echo "1. 检查 batch_key:user_seed_bonus：\n";
$redis = NexusDB::redis();
$batchKey = 'batch_key:user_seed_bonus';
$batch = $redis->get($batchKey);
if ($batch === false) {
    echo "   ❌ batch_key:user_seed_bonus 不存在！\n";
    echo "   这说明没有用户在做种，或者 Redis key 被清除了\n";
    echo "   解决方案：等待用户做种汇报，或者手动触发一次做种汇报\n";
} else {
    echo "   ✓ batch_key:user_seed_bonus 存在，指向: $batch\n";
    if (!$redis->exists($batch)) {
        echo "   ❌ 指向的 hash key ($batch) 不存在！\n";
        echo "   这是问题所在！batch_key 指向了一个不存在的 hash\n";
        echo "   解决方案：需要重新生成 batch_key，等待用户做种汇报\n";
    } else {
        $count = $redis->hLen($batch);
        echo "   ✓ hash key ($batch) 存在，包含 $count 个用户\n";
        if ($count == 0) {
            echo "   ⚠️  hash 为空，没有做种用户\n";
        } else {
            // 检查有多少用户是有效的（未过期的）
            $userSeedBonusDeadline = deadtime();
            echo "   过期时间戳: $userSeedBonusDeadline (" . date('Y-m-d H:i:s', $userSeedBonusDeadline) . ")\n";
            
            $validCount = 0;
            $expiredCount = 0;
            $sample = [];
            $it = NULL;
            $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
            $i = 0;
            while($arr_keys = $redis->hScan($batch, $it, "*", 100)) {
                foreach ($arr_keys as $field => $value) {
                    if ($value < $userSeedBonusDeadline) {
                        $expiredCount++;
                    } else {
                        $validCount++;
                        if ($i < 5) {
                            $sample[] = "用户ID: $field, 时间戳: $value (" . date('Y-m-d H:i:s', $value) . ")";
                            $i++;
                        }
                    }
                }
            }
            echo "   有效用户数: $validCount\n";
            echo "   过期用户数: $expiredCount\n";
            if ($validCount == 0) {
                echo "   ❌ 所有用户都已过期！这是问题所在！\n";
                echo "   说明所有做种用户的最后汇报时间都超过了 deadtime()\n";
            } else if (!empty($sample)) {
                echo "   前5个有效用户示例：\n";
                foreach ($sample as $item) {
                    echo "     - $item\n";
                }
            }
        }
    }
}

// 2. 检查最近的 cleanup 日志
echo "\n2. 检查最近的 cleanup 日志：\n";
$logFile = '/tmp/nexus-cli-root-' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    echo "   日志文件: $logFile\n";
    $cmd = "grep -i 'runBatchJobCalculateUserSeedBonus\|batch_key:user_seed_bonus.*no batch\|batch_key:user_seed_bonus.*DONE' '$logFile' | tail -10";
    $output = shell_exec($cmd);
    if ($output) {
        echo "   最近的日志：\n";
        echo $output;
    } else {
        echo "   ⚠️  没有找到相关日志\n";
    }
    
    // 检查是否有错误
    $cmd = "grep -i 'batch_key:user_seed_bonus.*error\|batch_key:user_seed_bonus.*no batch' '$logFile' | tail -5";
    $output = shell_exec($cmd);
    if ($output) {
        echo "\n   相关错误：\n";
        echo $output;
    }
} else {
    echo "   ⚠️  日志文件不存在: $logFile\n";
}

// 3. 检查队列状态
echo "\n3. 检查队列状态：\n";
$queueConnection = config('queue.default');
echo "   队列连接: $queueConnection\n";

if ($queueConnection == 'redis') {
    $queueName = config('queue.connections.redis.queue', 'nexus_queue');
    echo "   队列名称: $queueName\n";
    
    // 检查队列中的任务数
    $queueKey = "queues:$queueName";
    $pendingCount = $redis->llen($queueKey);
    echo "   待处理任务数: $pendingCount\n";
    
    // 检查延迟队列
    $delayedKey = "queues:$queueName:delayed";
    $delayedCount = $redis->zcard($delayedKey);
    echo "   延迟任务数: $delayedCount\n";
    
    if ($delayedCount > 0) {
        echo "   ⚠️  有 $delayedCount 个延迟任务，检查最近的延迟任务：\n";
        $recent = $redis->zrange($delayedKey, 0, 4, true);
        $found = false;
        foreach ($recent as $job => $score) {
            $delayUntil = date('Y-m-d H:i:s', $score);
            $now = time();
            $remaining = $score - $now;
            if (strpos($job, 'CalculateUserSeedBonus') !== false) {
                $found = true;
                echo "     - CalculateUserSeedBonus: 延迟到 $delayUntil (剩余 " . ($remaining > 0 ? $remaining : 0) . " 秒)\n";
            }
        }
        if (!$found) {
            echo "     (没有找到 CalculateUserSeedBonus 任务)\n";
        }
    }
    
    // 检查保留队列
    $reservedKey = "queues:$queueName:reserved";
    $reservedCount = $redis->zcard($reservedKey);
    echo "   保留任务数: $reservedCount\n";
}

// 4. 检查最近的 CalculateUserSeedBonus 任务执行日志
echo "\n4. 检查最近的 CalculateUserSeedBonus 任务执行日志：\n";
if (file_exists($logFile)) {
    $cmd = "grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB' '$logFile' | tail -5";
    $output = shell_exec($cmd);
    if ($output) {
        echo "   最近的执行日志：\n";
        echo $output;
    } else {
        echo "   ⚠️  没有找到任务执行日志，说明任务可能没有被执行\n";
    }
    
    // 检查 SQL 执行结果
    $cmd = "grep -i 'SQL_SUCCESS\|SQL_ERROR\|SQL_BEFORE_EXECUTE' '$logFile' | grep -i 'CLEANUP_CLI_CALCULATE_SEED_BONUS' | tail -5";
    $output = shell_exec($cmd);
    if ($output) {
        echo "\n   SQL 执行结果：\n";
        echo $output;
    }
}

// 5. 检查队列 worker 是否运行
echo "\n5. 检查队列 worker 是否运行：\n";
$cmd = "ps aux | grep -E 'queue:work|horizon' | grep -v grep";
$output = shell_exec($cmd);
if ($output) {
    echo "   ✓ 队列 worker 正在运行：\n";
    echo $output;
} else {
    echo "   ❌ 队列 worker 没有运行！这是问题所在！\n";
    echo "   需要启动队列 worker：\n";
    echo "   - php artisan queue:work --queue=nexus_queue\n";
    echo "   或者使用 Horizon：\n";
    echo "   - php artisan horizon\n";
}

// 6. 检查 executeCommand 的输出
echo "\n6. 检查 executeCommand 的输出（最近的 cleanup 命令执行）：\n";
if (file_exists($logFile)) {
    $cmd = "grep -i 'output:' '$logFile' | grep -i 'cleanup.*action.*seed_bonus' | tail -5";
    $output = shell_exec($cmd);
    if ($output) {
        echo "   最近的命令输出：\n";
        echo $output;
    } else {
        echo "   ⚠️  没有找到 cleanup 命令输出\n";
    }
}

// 7. 检查最近的 cleanup_cli 日志
echo "\n7. 检查最近的 cleanup_cli 日志：\n";
$cleanupLog = '/tmp/cleanup_cli_dubhe.log';
if (file_exists($cleanupLog)) {
    $cmd = "tail -30 '$cleanupLog' | grep -E 'runBatchJobCalculateUserSeedBonus|batch_key:user_seed_bonus|calculate seeding bonus|output:'";
    $output = shell_exec($cmd);
    if ($output) {
        echo "   最近的 cleanup_cli 日志：\n";
        echo $output;
    }
} else {
    echo "   ⚠️  cleanup 日志文件不存在: $cleanupLog\n";
    echo "   尝试查找其他 cleanup 日志文件：\n";
    $cmd = "ls -lt /tmp/cleanup_cli_*.log 2>/dev/null | head -1";
    $output = shell_exec($cmd);
    if ($output) {
        echo "   找到: " . trim($output) . "\n";
    }
}

// 8. 检查 deadtime() 函数
echo "\n8. 检查 deadtime() 配置：\n";
$deadtime = deadtime();
$deadtimeStr = date('Y-m-d H:i:s', $deadtime);
echo "   deadtime(): $deadtime ($deadtimeStr)\n";
$now = time();
$nowStr = date('Y-m-d H:i:s', $now);
echo "   当前时间: $now ($nowStr)\n";
$diff = $now - $deadtime;
echo "   时间差: $diff 秒 (" . round($diff / 3600, 2) . " 小时)\n";

echo "\n=== 诊断完成 ===\n";
echo "\n建议检查项：\n";
echo "1. 如果 batch_key:user_seed_bonus 不存在或指向的 hash 不存在，需要等待用户做种汇报\n";
echo "2. 如果所有用户都已过期，说明 deadtime() 设置可能有问题，或者用户很久没有做种汇报\n";
echo "3. 如果队列 worker 没有运行，需要启动队列 worker\n";
echo "4. 检查 executeCommand 的输出，看 cleanup 命令是否成功执行\n";
