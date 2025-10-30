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
        echo "<p class='ok'>✅ 缓存中包含宇航员主题</p>";
    } else {
        echo "<p class='error'>❌ 缓存中没有宇航员主题，需要清除缓存</p>";
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

// 清除缓存功能
if (isset($_GET['clear_cache'])) {
    $Cache->delete_value('stylesheet_content');
    echo "<div class='section'>";
    echo "<p class='ok'>✅ 缓存已清除！请刷新页面查看。</p>";
    echo "</div>";
}

// 快速修复按钮
if (!$theme) {
    echo "<div class='section'>";
    echo "<h2>⚡ 快速修复</h2>";
    if (isset($_GET['quick_fix'])) {
        $sql = "INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme')
ON DUPLICATE KEY UPDATE `name` = '宇航员 (Astronaut)'";
        sql_query($sql);
        $Cache->delete_value('stylesheet_content');
        echo "<p class='ok'>✅ 已自动插入主题记录并清除缓存！</p>";
        echo "<p><a href='check_theme.php' style='color: #00d4ff;'>重新检查</a></p>";
    } else {
        echo "<p><a href='?quick_fix=1' style='color: #00d4ff; font-size: 18px; text-decoration: none; border: 2px solid #00d4ff; padding: 10px 20px; display: inline-block;'>🔧 一键修复数据库</a></p>";
    }
    echo "</div>";
}

echo "</body></html>";
?>

