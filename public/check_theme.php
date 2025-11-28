<?php
/**
 * 宇航员主题诊断工具
 * 访问此页面检查主题安装状态
 */

require "../include/bittorrent.php";
dbconn(true);
// 不需要加载语言文件
loggedinorreturn(true);

// 只允许管理员访问
if (get_user_class() < UC_ADMINISTRATOR) {
    die("需要管理员权限");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>宇航员主题诊断</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #00ff00; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #00ff00; }
.ok { color: #00ff00; }
.error { color: #ff0000; }
.warning { color: #ffaa00; }
h2 { color: #00d4ff; }
pre { background: #000; padding: 10px; border-left: 3px solid #00d4ff; }
</style></head><body>";

echo "<h1>🚀 宇航员主题诊断工具</h1>";

// 检查1：数据库中是否有记录
echo "<div class='section'>";
echo "<h2>1️⃣ 检查数据库记录</h2>";
$result = sql_query("SELECT * FROM stylesheets WHERE id = 8");
$theme = mysql_fetch_assoc($result);
if ($theme) {
    echo "<p class='ok'>✅ 数据库记录存在</p>";
    echo "<pre>";
    print_r($theme);
    echo "</pre>";
    
    // 检查 URI 是否正确
    if (stripos($theme['uri'], 'Nightfall') !== false) {
        echo "<p class='error' style='font-size: 16px; margin-top: 10px;'><strong>❌ 发现问题！数据库中 ID=8 的主题 URI 是 Nightfall！</strong></p>";
        echo "<p><a href='?fix_db=1' style='color: #00d4ff; font-size: 16px; border: 2px solid #00d4ff; padding: 10px 20px; display: inline-block; text-decoration: none;'>🔧 一键修复数据库 URI</a></p>";
    } elseif (stripos($theme['uri'], 'AstronautStyle') === false) {
        echo "<p class='warning' style='font-size: 16px; margin-top: 10px;'><strong>⚠️ URI 不是 AstronautStyle！</strong></p>";
        echo "<p>当前 URI: <code>{$theme['uri']}</code></p>";
        echo "<p><a href='?fix_db=1' style='color: #00d4ff; font-size: 16px; border: 2px solid #00d4ff; padding: 10px 20px; display: inline-block; text-decoration: none;'>🔧 修复为正确的 URI</a></p>";
    } else {
        echo "<p class='ok' style='font-size: 16px; margin-top: 10px;'><strong>✅ 数据库 URI 正确</strong></p>";
    }
} else {
    echo "<p class='error'>❌ 数据库中没有找到ID=8的主题</p>";
    echo "<p class='warning'>请执行以下SQL：</p>";
    echo "<pre>INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme');</pre>";
}
echo "</div>";

// 检查2：主题文件是否存在
echo "<div class='section'>";
echo "<h2>2️⃣ 检查主题文件</h2>";
$files = [
    'styles/AstronautStyle/theme.css' => '主题核心CSS',
    'styles/AstronautStyle/DomTT.css' => 'Tooltip CSS',
    'styles/AstronautStyle/index-enhance.css' => '首页增强CSS',
];
$allExists = true;
foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "<p class='ok'>✅ {$desc}: {$file}</p>";
    } else {
        echo "<p class='error'>❌ {$desc}: {$file} 不存在</p>";
        $allExists = false;
    }
}
echo "</div>";

// 检查3：当前用户的stylesheet设置
echo "<div class='section'>";
echo "<h2>3️⃣ 检查当前用户设置</h2>";
global $CURUSER;
echo "<p>当前用户ID: {$CURUSER['id']}</p>";
echo "<p>当前stylesheet值: {$CURUSER['stylesheet']}</p>";

if ($CURUSER['stylesheet'] == 8) {
    echo "<p class='ok'>✅ 已设置为宇航员主题</p>";
} else {
    echo "<p class='warning'>⚠️ 当前使用的是其他主题 (ID: {$CURUSER['stylesheet']})</p>";
    echo "<p>可用的主题列表：</p>";
    $styles = sql_query("SELECT * FROM stylesheets ORDER BY id");
    echo "<ul>";
    while ($style = mysql_fetch_assoc($styles)) {
        $current = ($style['id'] == $CURUSER['stylesheet']) ? " [当前]" : "";
        echo "<li>ID {$style['id']}: {$style['name']}{$current}</li>";
    }
    echo "</ul>";
}
echo "</div>";

// 检查4：缓存状态
echo "<div class='section'>";
echo "<h2>4️⃣ 检查缓存</h2>";
global $Cache;
$cached = $Cache->get_value('stylesheet_content');
if ($cached) {
    echo "<p class='warning'>⚠️ 发现样式表缓存</p>";
    if (isset($cached[8])) {
        echo "<p>缓存中 ID=8 的主题信息：</p>";
        echo "<pre>";
        print_r($cached[8]);
        echo "</pre>";
        
        // 检查缓存中的 URI 是否正确
        if (isset($cached[8]['uri'])) {
            if (stripos($cached[8]['uri'], 'Nightfall') !== false) {
                echo "<p class='error' style='font-size: 16px; margin-top: 10px;'><strong>❌ 缓存中的 URI 是 Nightfall！这是问题根源！</strong></p>";
                echo "<p><a href='?clear_cache=1' style='color: #ff0000; font-size: 16px; border: 2px solid #ff0000; padding: 10px 20px; display: inline-block; text-decoration: none;'>🗑️ 立即清除缓存</a></p>";
            } elseif (stripos($cached[8]['uri'], 'AstronautStyle') === false) {
                echo "<p class='warning' style='font-size: 16px; margin-top: 10px;'><strong>⚠️ 缓存中的 URI 不是 AstronautStyle</strong></p>";
                echo "<p>缓存 URI: <code>{$cached[8]['uri']}</code></p>";
                echo "<p><a href='?clear_cache=1' style='color: #00d4ff; font-size: 16px; border: 2px solid #00d4ff; padding: 10px 20px; display: inline-block; text-decoration: none;'>🗑️ 清除缓存</a></p>";
            } else {
                echo "<p class='ok' style='font-size: 16px; margin-top: 10px;'><strong>✅ 缓存中的 URI 正确</strong></p>";
            }
        }
    } else {
        echo "<p class='error'>❌ 缓存中没有宇航员主题，需要清除缓存</p>";
        echo "<p><a href='?clear_cache=1' style='color: #00d4ff;'>点击这里清除缓存</a></p>";
    }
} else {
    echo "<p class='ok'>✅ 没有缓存</p>";
}
echo "</div>";

// 检查5：CSS加载路径
echo "<div class='section'>";
echo "<h2>5️⃣ 检查CSS加载路径</h2>";
$css_uri = get_css_uri();
echo "<p>当前CSS URI: <code>{$css_uri}</code></p>";
echo "<p>完整路径:</p>";
echo "<ul>";
echo "<li>主题CSS: <code>{$css_uri}theme.css</code></li>";
echo "<li>Tooltip CSS: <code>{$css_uri}DomTT.css</code></li>";
echo "</ul>";

// 检查实际加载的主题
$cssRow = get_css_row();
if ($cssRow) {
    echo "<p style='margin-top: 15px; padding-top: 15px; border-top: 1px solid #00ff00;'>";
    echo "<strong>当前实际加载的主题：</strong><br>";
    echo "ID: {$cssRow['id']}<br>";
    echo "名称: {$cssRow['name']}<br>";
    echo "URI: {$cssRow['uri']}<br>";
    if (stripos($cssRow['uri'], 'Nightfall') !== false || stripos($cssRow['name'], 'Nightfall') !== false) {
        echo "<span class='error'>❌ 检测到加载的是 Nightfall 主题！</span>";
    } elseif (stripos($cssRow['uri'], 'AstronautStyle') !== false || stripos($cssRow['name'], '宇航员') !== false) {
        echo "<span class='ok'>✅ 正在加载宇航员主题</span>";
    }
    echo "</p>";
} else {
    echo "<p class='error'>❌ 无法获取当前主题信息</p>";
}
echo "</div>";

// 检查所有主题列表
echo "<div class='section'>";
echo "<h2>6️⃣ 所有主题列表</h2>";
$styles = sql_query("SELECT * FROM stylesheets ORDER BY id");
echo "<table border='1' cellpadding='5' cellspacing='0' style='border-color: #00ff00; width: 100%;'>";
echo "<tr style='background: #002200;'><th>ID</th><th>名称</th><th>URI</th><th>状态</th></tr>";
while ($style = mysql_fetch_assoc($styles)) {
    $isCurrent = ($style['id'] == ($CURUSER['stylesheet'] ?? 0)) ? " ✅ 当前用户选择" : "";
    $isLoading = ($style['id'] == ($cssRow['id'] ?? 0)) ? " 🔄 正在加载" : "";
    $highlight = "";
    if ($isCurrent || $isLoading) {
        $highlight = " style='background: #002200;'";
    }
    echo "<tr{$highlight}>";
    echo "<td>{$style['id']}</td>";
    echo "<td>{$style['name']}</td>";
    echo "<td><code>{$style['uri']}</code></td>";
    echo "<td>{$isCurrent}{$isLoading}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 修复建议
echo "<div class='section'>";
echo "<h2>🔧 修复建议</h2>";
$needFix = false;

if (!$theme) {
    echo "<p class='error'>1. 需要插入数据库记录（见上方SQL）</p>";
    $needFix = true;
}

if (!$allExists) {
    echo "<p class='error'>2. 需要上传主题文件到 styles/AstronautStyle/ 目录</p>";
    $needFix = true;
}

if ($theme && $allExists && $CURUSER['stylesheet'] != 8) {
    echo "<p class='warning'>3. 请访问用户设置 (usercp.php) 切换主题</p>";
    $needFix = true;
}

if ($cached && !isset($cached[8])) {
    echo "<p class='warning'>4. 需要清除缓存</p>";
    echo "<p><a href='?clear_cache=1' style='color: #00d4ff;'>点击这里清除缓存</a></p>";
    $needFix = true;
}

if (!$needFix && $CURUSER['stylesheet'] == 8) {
    echo "<p class='ok' style='font-size: 18px;'>✅ 一切正常！主题应该已经生效了。如果页面还是旧样式，请按 Ctrl+F5 强制刷新浏览器缓存。</p>";
}
echo "</div>";

// 处理修复操作
$fixed = false;
if (isset($_GET['fix_db'])) {
    $sql = "UPDATE `stylesheets` SET 
        `uri` = 'styles/AstronautStyle/',
        `name` = '宇航员 (Astronaut)',
        `designer` = 'NexusPHP Team',
        `comment` = '探索浩瀚星海 - Space Explorer Theme'
    WHERE `id` = 8";
    sql_query($sql);
    $Cache->delete_value('stylesheet_content');
    echo "<div class='section'>";
    echo "<p class='ok' style='font-size: 18px;'>✅ 数据库已修复！缓存已清除！请刷新页面查看。</p>";
    echo "</div>";
    $fixed = true;
}

// 清除缓存功能
if (isset($_GET['clear_cache'])) {
    $Cache->delete_value('stylesheet_content');
    // 同时清除用户缓存，确保重新加载
    if (isset($CURUSER)) {
        $Cache->delete_value('user_'.$CURUSER['id'].'_content');
    }
    echo "<div class='section'>";
    echo "<p class='ok' style='font-size: 18px;'>✅ 缓存已清除！请按 Ctrl+F5 强制刷新浏览器查看。</p>";
    echo "</div>";
    $fixed = true;
}

if ($fixed) {
    echo "<meta http-equiv='refresh' content='3;url=check_theme.php'>";
}

// 快速修复按钮
echo "<div class='section'>";
echo "<h2>⚡ 一键修复</h2>";
if (isset($_GET['quick_fix'])) {
    // 修复数据库
    $sql = "INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme')
ON DUPLICATE KEY UPDATE 
    `uri` = 'styles/AstronautStyle/',
    `name` = '宇航员 (Astronaut)',
    `designer` = 'NexusPHP Team',
    `comment` = '探索浩瀚星海 - Space Explorer Theme'";
    sql_query($sql);
    
    // 清除所有相关缓存
    $Cache->delete_value('stylesheet_content');
    if (isset($CURUSER)) {
        $Cache->delete_value('user_'.$CURUSER['id'].'_content');
    }
    
    echo "<p class='ok' style='font-size: 18px;'>✅ 已自动修复数据库并清除缓存！</p>";
    echo "<p>3秒后自动刷新...</p>";
    echo "<p><a href='check_theme.php' style='color: #00d4ff;'>立即刷新</a></p>";
    echo "<meta http-equiv='refresh' content='3;url=check_theme.php'>";
} else {
    echo "<p><strong>此操作将：</strong></p>";
    echo "<ul>";
    echo "<li>修复数据库中 ID=8 的主题 URI 为 <code>styles/AstronautStyle/</code></li>";
    echo "<li>清除主题缓存</li>";
    echo "<li>清除用户缓存</li>";
    echo "</ul>";
    echo "<p><a href='?quick_fix=1' style='color: #00d4ff; font-size: 18px; text-decoration: none; border: 2px solid #00d4ff; padding: 10px 20px; display: inline-block;'>🔧 一键修复所有问题</a></p>";
}
echo "</div>";

echo "</body></html>";
?>

