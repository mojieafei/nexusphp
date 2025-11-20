#!/bin/bash
# 检查 cleanup 是否真的在执行

echo "=== 检查 cleanup 执行情况 ==="
echo ""

# 1. 检查 crontab 中的 cleanup 任务
echo "1. 检查 crontab 任务："
crontab -l 2>/dev/null | grep -E "cleanup_cli|schedule:run" || echo "未找到相关 crontab 任务"
echo ""

# 2. 检查 cleanup_cli 日志文件
echo "2. 检查 cleanup_cli 日志文件："
CLEANUP_LOG="/tmp/cleanup_cli_*.log"
if ls $CLEANUP_LOG 1> /dev/null 2>&1; then
    for log in $CLEANUP_LOG; do
        echo "   文件: $log"
        echo "   最后 20 行："
        tail -20 "$log"
        echo ""
    done
else
    echo "   未找到 cleanup_cli 日志文件"
    echo "   可能路径：/tmp/cleanup_cli_DOMAIN.log（请替换 DOMAIN 为实际域名）"
fi
echo ""

# 3. 手动执行一次 cleanup 测试
echo "3. 手动执行 cleanup_cli.php 测试："
cd /var/www/nexusphp
php include/cleanup_cli.php 2>&1 | head -30
echo ""

# 4. 检查是否有 cleanup 进程在运行
echo "4. 检查 cleanup 相关进程："
ps aux | grep -E "cleanup_cli|php.*cleanup" | grep -v grep || echo "没有 cleanup 进程在运行"
echo ""

echo "=== 检查完成 ==="

