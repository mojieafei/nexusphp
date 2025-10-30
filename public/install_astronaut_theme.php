<?php
/**
 * 宇航员主题一键安装脚本
 * 访问此页面即可自动安装主题
 */

require "../include/bittorrent.php";
dbconn(true);
// 不需要加载语言文件
loggedinorreturn(true);

// 只允许管理员访问
if (get_user_class() < UC_ADMINISTRATOR) {
    die("需要管理员权限");
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>宇航员主题安装</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #1e1b4b 50%, #2d1b69 100%);
            color: #e5e7eb;
            padding: 40px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(10, 22, 40, 0.9);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
        }
        h1 {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
            text-align: center;
            font-size: 32px;
            margin-bottom: 30px;
        }
        .step {
            background: rgba(30, 27, 75, 0.6);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .step h2 {
            color: #a855f7;
            margin-top: 0;
        }
        .ok {
            color: #10b981;
            font-weight: bold;
        }
        .error {
            color: #ef4444;
            font-weight: bold;
        }
        .warning {
            color: #fbbf24;
            font-weight: bold;
        }
        .btn {
            background: linear-gradient(135deg, #00d4ff, #a855f7);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 10px 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 212, 255, 0.5);
        }
        pre {
            background: #000;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #00d4ff;
            overflow-x: auto;
        }
        .center {
            text-align: center;
        }
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 宇航员主题安装程序</h1>
        
<?php
$errors = [];
$success = [];

// 执行安装
if (isset($_GET['action']) && $_GET['action'] == 'install') {
    echo "<div class='step'><h2>⚙️ 正在安装...</h2>";
    
    // 步骤1：检查文件
    echo "<p>检查主题文件...</p>";
    $required_files = [
        'styles/AstronautStyle/theme.css',
        'styles/AstronautStyle/DomTT.css',
    ];
    
    $files_ok = true;
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            echo "<p class='ok'>✅ {$file}</p>";
        } else {
            echo "<p class='error'>❌ {$file} 不存在</p>";
            $errors[] = "文件不存在: {$file}";
            $files_ok = false;
        }
    }
    
    if (!$files_ok) {
        echo "<p class='error'>❌ 主题文件不完整，请检查文件上传！</p>";
        echo "</div>";
    } else {
        // 步骤2：插入数据库
        echo "<p>插入数据库记录...</p>";
        try {
            $sql = "INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme')
ON DUPLICATE KEY UPDATE 
    `uri` = 'styles/AstronautStyle/',
    `name` = '宇航员 (Astronaut)',
    `designer` = 'NexusPHP Team',
    `comment` = '探索浩瀚星海 - Space Explorer Theme'";
            
            sql_query($sql);
            echo "<p class='ok'>✅ 数据库记录已插入/更新</p>";
            $success[] = "数据库插入成功";
        } catch (Exception $e) {
            echo "<p class='error'>❌ 数据库操作失败: " . $e->getMessage() . "</p>";
            $errors[] = "数据库错误";
        }
        
        // 步骤3：清除缓存
        echo "<p>清除缓存...</p>";
        global $Cache;
        $Cache->delete_value('stylesheet_content');
        $Cache->delete_value('user_'.$CURUSER['id'].'_content');
        echo "<p class='ok'>✅ 缓存已清除</p>";
        
        // 步骤4：自动为当前用户应用主题
        echo "<p>为当前用户应用主题...</p>";
        sql_query("UPDATE users SET stylesheet = 8 WHERE id = " . sqlesc($CURUSER['id']));
        echo "<p class='ok'>✅ 已自动为您切换到宇航员主题</p>";
        
        echo "</div>";
        
        if (empty($errors)) {
            echo "<div class='step center'>";
            echo "<h2 class='ok'>🎉 安装成功！</h2>";
            echo "<p style='font-size: 18px;'>宇航员主题已成功安装并应用！</p>";
            echo "<p><a href='index.php' class='btn'>🚀 返回首页查看效果</a></p>";
            echo "<p style='color: #9ca3af; margin-top: 20px;'>如果页面样式没有变化，请按 <strong>Ctrl+F5</strong> 强制刷新</p>";
            echo "</div>";
        }
    }
} else {
    // 显示安装前检查
    echo "<div class='step'>";
    echo "<h2>📋 安装前检查</h2>";
    
    // 检查文件
    echo "<p><strong>检查主题文件：</strong></p><ul>";
    $files = [
        'styles/AstronautStyle/theme.css' => '主题CSS',
        'styles/AstronautStyle/DomTT.css' => 'Tooltip CSS',
        'styles/AstronautStyle/index-enhance.css' => '首页增强（可选）',
    ];
    
    $all_files_exist = true;
    foreach ($files as $file => $desc) {
        if (file_exists($file)) {
            echo "<li class='ok'>✅ {$desc}</li>";
        } else {
            echo "<li class='error'>❌ {$desc} - 文件不存在: {$file}</li>";
            if ($file !== 'styles/AstronautStyle/index-enhance.css') {
                $all_files_exist = false;
            }
        }
    }
    echo "</ul>";
    
    // 检查数据库
    echo "<p><strong>检查数据库：</strong></p>";
    $existing = sql_query("SELECT * FROM stylesheets WHERE id = 8");
    $theme_exists = mysql_num_rows($existing) > 0;
    
    if ($theme_exists) {
        echo "<p class='warning'>⚠️ 主题记录已存在，安装将更新现有记录</p>";
    } else {
        echo "<p class='ok'>✅ 准备插入新主题记录</p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>📦 安装内容</h2>";
    echo "<ul>";
    echo "<li>添加主题到数据库（ID: 8）</li>";
    echo "<li>清除样式表缓存</li>";
    echo "<li>自动为当前用户应用主题</li>";
    echo "</ul>";
    echo "</div>";
    
    if ($all_files_exist) {
        echo "<div class='center'>";
        echo "<a href='?action=install' class='btn'>🚀 开始安装</a>";
        echo "<a href='index.php' class='btn' style='background: linear-gradient(135deg, #6b7280, #4b5563);'>取消</a>";
        echo "</div>";
    } else {
        echo "<div class='step'>";
        echo "<p class='error'>❌ 主题文件不完整，无法安装。请确保已上传以下文件到服务器：</p>";
        echo "<pre>public/styles/AstronautStyle/theme.css
public/styles/AstronautStyle/DomTT.css</pre>";
        echo "</div>";
    }
}

// 显示错误
if (!empty($errors)) {
    echo "<div class='step'>";
    echo "<h2 class='error'>⚠️ 安装遇到问题</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>{$error}</li>";
    }
    echo "</ul>";
    echo "<p><a href='check_theme.php' class='btn'>查看详细诊断</a></p>";
    echo "</div>";
}
?>

        <div class="step">
            <h2>💡 提示</h2>
            <ul>
                <li>安装后如果样式没有变化，请按 <strong>Ctrl+F5</strong> 强制刷新浏览器缓存</li>
                <li>可以访问 <a href="styles/AstronautStyle/preview.html" style="color: #00d4ff;">preview.html</a> 预览主题效果</li>
                <li>如有问题，请访问 <a href="check_theme.php" style="color: #00d4ff;">诊断工具</a></li>
            </ul>
        </div>
    </div>
</body>
</html>

