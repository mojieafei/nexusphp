#!/bin/bash
# 检查 cleanup 相关日志

echo "=== 检查 cleanup 相关日志 ===\n"

# 1. 检查 cleanup_cli 日志
echo "1. cleanup_cli.php 最近日志："
if [ -f "/tmp/cleanup_cli_DOMAIN.log" ]; then
    tail -30 /tmp/cleanup_cli_DOMAIN.log | grep -E "batch_key|runBatchJob|executeCommand|cleanup --action"
else
    echo "日志文件不存在"
fi
echo ""

# 2. 检查 Laravel 日志中的 cleanup 相关
echo "2. Laravel 日志中的 cleanup 相关："
if [ -f "storage/logs/laravel.log" ]; then
    tail -100 storage/logs/laravel.log | grep -E "batch_key:user_seed_bonus|runBatchJob|executeCommand|cleanup --action" | tail -20
else
    echo "Laravel 日志文件不存在"
fi
echo ""

# 3. 检查是否有错误日志
echo "3. 最近的错误日志："
if [ -f "storage/logs/laravel.log" ]; then
    tail -50 storage/logs/laravel.log | grep -i "error\|exception\|failed" | tail -10
fi
echo ""

echo "=== 检查完成 ==="

