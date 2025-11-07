-- 创建论坛打赏记录表
CREATE TABLE IF NOT EXISTS `forum_tips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_uid` mediumint(8) unsigned NOT NULL COMMENT '打赏者用户ID',
  `to_uid` mediumint(8) unsigned NOT NULL COMMENT '接收者用户ID',
  `post_id` int(10) unsigned NOT NULL COMMENT '帖子ID',
  `topic_id` mediumint(8) unsigned NOT NULL COMMENT '主题ID',
  `amount` decimal(12,2) NOT NULL COMMENT '打赏金额（税前）',
  `amount_after_tax` decimal(12,2) NOT NULL COMMENT '实际到账金额（税后）',
  `message` varchar(200) DEFAULT NULL COMMENT '打赏留言',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_topic_id` (`topic_id`),
  KEY `idx_from_uid` (`from_uid`),
  KEY `idx_to_uid` (`to_uid`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='论坛打赏记录表';

