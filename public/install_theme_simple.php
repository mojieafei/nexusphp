<?php
/**
 * 宇航员主题简易安装 - 无需管理员权限
 */

// 简单的验证：需要在URL中加上密码
$install_password = "astronaut2025"; // 修改这个密码
if (!isset($_GET['password']) || $_GET['password'] !== $install_password) {
    die('请在URL后添加 ?password=astronaut2025');
}

require "../include/bittorrent.php";
dbconn(true);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>宇航员主题快速安装</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #1e1b4b 50%, #2d1b69 100%);
            color: #e5e7eb;
            padding: 40px;
            min-height: 100vh;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: rgba(10, 22, 40, 0.9);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 30px;
        }
        h1 { color: #00d4ff; text-align: center; }
        .ok { color: #10b981; }
        .error { color: #ef4444; }
        .step { margin: 20px 0; padding: 15px; background: rgba(30, 27, 75, 0.6); border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 宇航员主题快速安装</h1>
        
<?php
if (isset($_GET['action']) && $_GET['action'] == 'install') {
    
    echo "<div class='step'><h2>正在安装...</h2>";
    
    // 插入主题
    try {
        $sql = "INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme')
ON DUPLICATE KEY UPDATE 
    `uri` = 'styles/AstronautStyle/',
    `name` = '宇航员 (Astronaut)'";
        
        sql_query($sql);
        echo "<p class='ok'>✅ 数据库记录已添加</p>";
        
        // 清除缓存
        global $Cache;
        $Cache->delete_value('stylesheet_content');
        echo "<p class='ok'>✅ 缓存已清除</p>";
        
        echo "<h2 class='ok'>🎉 安装完成！</h2>";
        echo "<p>现在请：</p>";
        echo "<ol>";
        echo "<li>访问 <strong>用户设置 (usercp.php)</strong></li>";
        echo "<li>找到 <strong>样式 (Stylesheet)</strong> 选项</li>";
        echo "<li>选择 <strong>宇航员 (Astronaut)</strong></li>";
        echo "<li>保存后按 <strong>Ctrl+F5</strong> 刷新页面</li>";
        echo "</ol>";
        echo "<p><a href='http://localhost:8000/usercp.php' style='color: #00d4ff;'>点击这里去用户设置</a></p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ 安装失败: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
    
} else {
    // 显示安装确认
    echo "<div class='step'>";
    echo "<h2>准备安装宇航员主题</h2>";
    echo "<p>点击下面的按钮将会：</p>";
    echo "<ul>";
    echo "<li>在数据库中添加主题记录</li>";
    echo "<li>清除缓存</li>";
    echo "</ul>";
    echo "<p><a href='?password={$install_password}&action=install' style='display:inline-block;background:#00d4ff;color:#000;padding:15px 30px;text-decoration:none;border-radius:8px;font-weight:bold;'>开始安装</a></p>";
    echo "</div>";
    
    // 检查文件
    echo "<div class='step'>";
    echo "<h2>文件检查</h2>";
    if (file_exists('styles/AstronautStyle/theme.css')) {
        echo "<p class='ok'>✅ theme.css 存在</p>";
    } else {
        echo "<p class='error'>❌ theme.css 不存在</p>";
    }
    if (file_exists('styles/AstronautStyle/DomTT.css')) {
        echo "<p class='ok'>✅ DomTT.css 存在</p>";
    } else {
        echo "<p class='error'>❌ DomTT.css 不存在</p>";
    }
    echo "</div>";
}
?>
        
    </div>
</body>
</html>

