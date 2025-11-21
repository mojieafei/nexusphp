#!/bin/bash
# 检查 cleanup 是否正常执行

echo "=== 检查 cleanup 执行结果 ==="
echo ""

# 1. 检查 cleanup_cli 日志
echo "1. cleanup_cli 最近日志："
tail -10 /tmp/cleanup_cli_dubhe.log
echo ""

# 2. 检查是否有任务分发日志
echo "2. 检查任务分发日志："
grep -i "batch_key\|runBatchJob\|cleanup --action" /tmp/nexus-cli-root-*.log 2>/dev/null | tail -10 || echo "未找到相关日志"
echo ""

# 3. 检查队列任务
echo "3. 检查队列任务（如果有队列处理器）："
php artisan queue:work --once --timeout=1 2>/dev/null || echo "队列未配置或无法执行"
echo ""

echo "=== 检查完成 ==="

