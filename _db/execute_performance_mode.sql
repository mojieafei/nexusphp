-- ============================================
-- 性能模式功能 - 数据库升级脚本
-- ============================================
-- 
-- 功能说明：
-- 为用户表添加 performance_mode 字段，用户可选择性能模式
-- - default: 默认模式（完整特效）
-- - performance: 性能模式（禁用动画和特效）
--
-- 执行方式：
-- 方式1（推荐）：在 MySQL 客户端执行
--   mysql -u root -p nexus < _db/execute_performance_mode.sql
--
-- 方式2：在 phpMyAdmin 中导入此文件
--
-- 方式3：复制下面的 SQL 语句在数据库管理工具中执行
-- ============================================

-- 检查字段是否已存在
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'performance_mode'
);

-- 如果字段不存在则添加
SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `performance_mode` ENUM(''default'', ''performance'') NOT NULL DEFAULT ''default'' COMMENT ''性能模式：default=默认 performance=性能优先'' AFTER `fontsize`',
    'SELECT ''字段 performance_mode 已存在，跳过添加'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 确保所有现有用户都有默认值
UPDATE `users` SET `performance_mode` = 'default' WHERE `performance_mode` IS NULL OR `performance_mode` = '';

-- 显示结果
SELECT 
    '性能模式字段添加成功！' AS status,
    COUNT(*) AS total_users,
    SUM(CASE WHEN performance_mode = 'default' THEN 1 ELSE 0 END) AS default_mode_users,
    SUM(CASE WHEN performance_mode = 'performance' THEN 1 ELSE 0 END) AS performance_mode_users
FROM `users`;

-- ============================================
-- 升级完成！
-- ============================================
-- 
-- 后续步骤：
-- 1. 检查上方输出，确认字段已成功添加
-- 2. 用户可在 控制面板 > 个人设置 中选择性能模式
-- 3. 低配置用户建议开启性能模式以提升流畅度
--
-- 如需回滚（删除此功能）：
-- ALTER TABLE `users` DROP COLUMN `performance_mode`;
-- ============================================

