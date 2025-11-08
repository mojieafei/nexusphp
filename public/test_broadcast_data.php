<?php
require_once("../include/bittorrent.php");
dbconn();

// 查看广播数据
$sql = "SELECT * FROM stardust_broadcasts ORDER BY id DESC LIMIT 10";
$result = sql_query($sql);

echo "<h1>星尘农场广播数据检查</h1>";
echo "<pre>";

echo "=== 最近10条广播 ===\n\n";
while ($row = mysql_fetch_assoc($result)) {
    echo "ID: {$row['id']}\n";
    echo "用户: {$row['username']} (ID: {$row['user_id']})\n";
    echo "类型: {$row['type']}\n";
    echo "消息: {$row['message']}\n";
    echo "数据: {$row['data']}\n";
    echo "时间: {$row['created_at']}\n";
    echo "---\n\n";
}

// 清空旧数据选项
echo "\n\n";
if (isset($_GET['clear']) && $_GET['clear'] == 'yes') {
    sql_query("TRUNCATE TABLE stardust_broadcasts");
    echo "✅ 已清空广播表！\n";
    echo "<a href='test_broadcast_data.php'>返回查看</a>\n";
} else {
    echo "<a href='test_broadcast_data.php?clear=yes'>点击清空所有广播数据</a>\n";
}

echo "</pre>";
?>

