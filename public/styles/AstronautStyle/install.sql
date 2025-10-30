-- 宇航员主题安装SQL
-- 执行此SQL将主题添加到数据库

-- 检查并插入宇航员主题
INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme')
ON DUPLICATE KEY UPDATE 
    `uri` = 'styles/AstronautStyle/',
    `name` = '宇航员 (Astronaut)',
    `designer` = 'NexusPHP Team',
    `comment` = '探索浩瀚星海 - Space Explorer Theme';

-- 查看所有可用主题
SELECT * FROM `stylesheets`;

