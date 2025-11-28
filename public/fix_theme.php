<?php
/**
 * 主题加载问题快速修复工具
 * 诊断并修复主题加载错误的问题
 */

require "../include/bittorrent.php";
dbconn(true);
loggedinorreturn(true);

// 只允许管理员访问
if (get_user_class() < UC_ADMINISTRATOR) {
    die("需要管理员权限");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>主题修复工具</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #00ff00; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #00ff00; }
.ok { color: #00ff00; }
.error { color: #ff0000; }
.warning { color: #ffaa00; }
h2 { color: #00d4ff; }
pre { background: #000; padding: 10px; border-left: 3px solid #00d4ff; }
button { background: #00d4ff; color: #000; padding: 10px 20px; border: none; cursor: pointer; font-size: 14px; margin: 5px; }
button:hover { background: #00aaff; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #00ff00; padding: 8px; text-align: left; }
th { background: #002200; }
</style></head><body>";

echo "<h1>🔧 主题加载问题修复工具</h1>";

global $CURUSER, $Cache;

// 处理修复操作
$fixed = false;
$message = "";

if (isset($_GET['fix_user'])) {
    $userId = (int)$_GET['fix_user'];
    sql_query("UPDATE users SET stylesheet = 8 WHERE id = " . sqlesc($userId));
    $Cache->delete_value('user_'.$userId.'_content');
    $message = "✅ 已将用户 ID {$userId} 的主题设置为宇航员主题 (ID: 8)";
    $fixed = true;
}

if (isset($_GET['clear_cache'])) {
    $Cache->delete_value('stylesheet_content');
    $message = "✅ 已清除主题缓存";
    $fixed = true;
}

if (isset($_GET['fix_theme_uri'])) {
    $themeId = (int)$_GET['fix_theme_uri'];
    $newUri = 'styles/AstronautStyle/';
    sql_query("UPDATE stylesheets SET uri = " . sqlesc($newUri) . ", name = '宇航员 (Astronaut)' WHERE id = " . sqlesc($themeId));
    $Cache->delete_value('stylesheet_content');
    $message = "✅ 已修复主题 ID {$themeId} 的 URI 为 {$newUri}";
    $fixed = true;
}

if ($message) {
    echo "<div class='section'><p class='ok'>{$message}</p></div>";
}

// 步骤1：检查当前用户设置
echo "<div class='section'>";
echo "<h2>1️⃣ 当前用户设置</h2>";
echo "<p>用户ID: {$CURUSER['id']}</p>";
echo "<p>用户名: {$CURUSER['username']}</p>";
echo "<p>当前 stylesheet 值: <strong>{$CURUSER['stylesheet']}</strong></p>";

// 获取当前用户选择的主题信息
$userThemeId = $CURUSER['stylesheet'];
$userThemeRes = sql_query("SELECT * FROM stylesheets WHERE id = " . sqlesc($userThemeId));
$userTheme = mysql_fetch_assoc($userThemeRes);

if ($userTheme) {
    echo "<p>当前选择的主题:</p>";
    echo "<ul>";
    echo "<li>ID: {$userTheme['id']}</li>";
    echo "<li>名称: {$userTheme['name']}</li>";
    echo "<li>URI: <code>{$userTheme['uri']}</code></li>";
    echo "</ul>";
    
    if (stripos($userTheme['uri'], 'Nightfall') !== false) {
        echo "<p class='error'>❌ 发现问题！用户选择的主题 URI 指向了 Nightfall！</p>";
        echo "<p><button onclick='if(confirm(\"确定要将当前用户主题修复为宇航员主题吗？\")) window.location=\"?fix_user={$CURUSER['id']}\";'>🔧 修复当前用户主题</button></p>";
    } elseif ($userThemeId != 8) {
        echo "<p class='warning'>⚠️ 当前用户选择的不是宇航员主题 (ID: 8)</p>";
        echo "<p><button onclick='if(confirm(\"确定要将当前用户主题切换为宇航员主题吗？\")) window.location=\"?fix_user={$CURUSER['id']}\";'>🔧 切换为宇航员主题</button></p>";
    } else {
        echo "<p class='ok'>✅ 用户已选择宇航员主题</p>";
    }
} else {
    echo "<p class='error'>❌ 找不到 ID 为 {$userThemeId} 的主题记录！</p>";
}
echo "</div>";

// 步骤2：检查实际加载的主题
echo "<div class='section'>";
echo "<h2>2️⃣ 实际加载的主题</h2>";
$css_uri = get_css_uri();
$cssRow = get_css_row();

echo "<p>实际 CSS URI: <code>{$css_uri}</code></p>";

if ($cssRow) {
    echo "<p>实际加载的主题信息:</p>";
    echo "<ul>";
    echo "<li>ID: {$cssRow['id']}</li>";
    echo "<li>名称: {$cssRow['name']}</li>";
    echo "<li>URI: <code>{$cssRow['uri']}</code></li>";
    echo "</ul>";
    
    if (stripos($cssRow['uri'], 'Nightfall') !== false) {
        echo "<p class='error'>❌ 检测到加载的是 Nightfall 主题！这是问题的根源！</p>";
        echo "<p><button onclick='if(confirm(\"确定要修复主题 ID {$cssRow['id']} 的 URI 吗？\")) window.location=\"?fix_theme_uri={$cssRow['id']}\";'>🔧 修复主题 URI</button></p>";
    } elseif (stripos($cssRow['uri'], 'AstronautStyle') === false) {
        echo "<p class='warning'>⚠️ 当前加载的不是宇航员主题</p>";
    } else {
        echo "<p class='ok'>✅ 正在加载宇航员主题</p>";
    }
} else {
    echo "<p class='error'>❌ 无法获取主题信息</p>";
}
echo "</div>";

// 步骤3：所有主题列表
echo "<div class='section'>";
echo "<h2>3️⃣ 所有主题列表</h2>";
$styles = sql_query("SELECT * FROM stylesheets ORDER BY id");
echo "<table>";
echo "<tr><th>ID</th><th>名称</th><th>URI</th><th>状态</th></tr>";
$hasNightfall = false;
while ($style = mysql_fetch_assoc($styles)) {
    $isCurrent = ($style['id'] == $userThemeId) ? " ✅ 用户选择" : "";
    $isLoading = ($style['id'] == ($cssRow['id'] ?? 0)) ? " 🔄 正在加载" : "";
    $isNightfall = (stripos($style['uri'], 'Nightfall') !== false || stripos($style['name'], 'Nightfall') !== false);
    if ($isNightfall) {
        $hasNightfall = true;
        $style['name'] = "<span class='error'>{$style['name']} ⚠️ 问题主题</span>";
    }
    $highlight = ($isCurrent || $isLoading || $isNightfall) ? " style='background: #002200;'" : "";
    echo "<tr{$highlight}>";
    echo "<td>{$style['id']}</td>";
    echo "<td>{$style['name']}</td>";
    echo "<td><code>{$style['uri']}</code></td>";
    echo "<td>{$isCurrent}{$isLoading}</td>";
    echo "</tr>";
}
echo "</table>";

if ($hasNightfall) {
    echo "<p class='error'>❌ 发现数据库中有 Nightfall 主题记录！这可能导致主题加载错误。</p>";
}
echo "</div>";

// 步骤4：快速操作
echo "<div class='section'>";
echo "<h2>4️⃣ 快速操作</h2>";
echo "<p><button onclick='if(confirm(\"确定要清除主题缓存吗？\")) window.location=\"?clear_cache=1\";'>🗑️ 清除主题缓存</button></p>";
echo "<p><button onclick='if(confirm(\"确定要将当前用户主题切换为宇航员主题吗？\")) window.location=\"?fix_user={$CURUSER['id']}\";'>🚀 切换为宇航员主题</button></p>";
echo "</div>";

// 步骤5：修复建议
echo "<div class='section'>";
echo "<h2>5️⃣ 修复建议</h2>";
echo "<ol>";
if (stripos($cssRow['uri'] ?? '', 'Nightfall') !== false) {
    echo "<li class='error'>最紧急：主题 URI 指向了 Nightfall，需要立即修复</li>";
}
if ($userThemeId != 8) {
    echo "<li class='warning'>用户选择的主题不是宇航员主题 (当前: {$userThemeId}，应该是: 8)</li>";
}
echo "<li>清除浏览器缓存 (Ctrl+F5 强制刷新)</li>";
echo "<li>清除服务器端的主题缓存（点击上方清除缓存按钮）</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>

