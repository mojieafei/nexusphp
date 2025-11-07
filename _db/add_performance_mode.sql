-- 添加性能模式字段到用户表
ALTER TABLE `users` ADD COLUMN `performance_mode` ENUM('default', 'performance') NOT NULL DEFAULT 'default' COMMENT '性能模式：default=默认 performance=性能优先' AFTER `fontsize`;

-- 如果字段已存在但位置不对，先删除再添加
-- ALTER TABLE `users` DROP COLUMN `performance_mode`;
-- ALTER TABLE `users` ADD COLUMN `performance_mode` ENUM('default', 'performance') NOT NULL DEFAULT 'default' COMMENT '性能模式：default=默认 performance=性能优先' AFTER `fontsize`;

