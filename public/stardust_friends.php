<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

// 获取好友列表（使用站内现有的friends表）
$friendsQuery = "
    SELECT u.id, u.username, u.avatar, u.class, u.uploaded, u.downloaded,
           sf.stardust, sf.level, sf.land_slots,
           (SELECT COUNT(*) FROM stardust_lands WHERE farm_id = sf.id AND status = 'mature') as mature_lands
    FROM friends f
    INNER JOIN users u ON f.friendid = u.id
    LEFT JOIN stardust_farms sf ON u.id = sf.user_id
    WHERE f.userid = " . sqlesc($CURUSER['id']) . "
    ORDER BY sf.stardust DESC, u.username ASC
";
$friendsResult = sql_query($friendsQuery);
$friends = [];
while ($row = mysqli_fetch_assoc($friendsResult)) {
    $friends[] = $row;
}

// 如果没有好友，查询最近活跃的用户
$recentUsersQuery = "
    SELECT u.id, u.username, u.avatar, u.class,
           sf.stardust, sf.level, sf.land_slots,
           (SELECT COUNT(*) FROM stardust_lands WHERE farm_id = sf.id AND status = 'mature') as mature_lands
    FROM stardust_farms sf
    INNER JOIN users u ON sf.user_id = u.id
    WHERE u.id != " . sqlesc($CURUSER['id']) . "
    AND sf.stardust > 0
    ORDER BY sf.updated_at DESC
    LIMIT 20
";
$recentResult = sql_query($recentUsersQuery);
$recentUsers = [];
while ($row = mysqli_fetch_assoc($recentResult)) {
    $recentUsers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>好友农场 - <?php echo $SITENAME; ?></title>
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(180deg, #0a0e27 0%, #1a1f3a 50%, #2a2f4a 100%);
    font-family: "Microsoft YaHei", Arial, sans-serif;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.header {
    background: rgba(0, 0, 0, 0.7);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
    border: 2px solid rgba(138, 43, 226, 0.5);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    font-size: 28px;
    color: #8a2be2;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    color: white;
}

.btn-primary:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
}

.section {
    background: rgba(0, 0, 0, 0.6);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    border: 2px solid rgba(138, 43, 226, 0.5);
}

.section h2 {
    font-size: 22px;
    margin-bottom: 20px;
    color: #4ECDC4;
    display: flex;
    align-items: center;
    gap: 10px;
}

.friends-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.friend-card {
    background: rgba(138, 43, 226, 0.15);
    border: 2px solid rgba(138, 43, 226, 0.5);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    cursor: pointer;
}

.friend-card:hover {
    border-color: #8a2be2;
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(138, 43, 226, 0.4);
}

.friend-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.friend-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
}

.friend-info {
    flex: 1;
}

.friend-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
}

.friend-level {
    font-size: 12px;
    color: #aaa;
}

.friend-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 15px;
}

.stat-item {
    background: rgba(0, 0, 0, 0.3);
    padding: 8px;
    border-radius: 6px;
    text-align: center;
}

.stat-label {
    font-size: 11px;
    color: #aaa;
    margin-bottom: 3px;
}

.stat-value {
    font-size: 16px;
    font-weight: bold;
    color: #ffd700;
}

.stat-value.mature {
    color: #4ECDC4;
}

.friend-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    flex: 1;
    padding: 8px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
    transition: all 0.3s;
    text-align: center;
    text-decoration: none;
    display: block;
}

.action-btn.visit {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.action-btn.visit:hover {
    background: linear-gradient(135deg, #764ba2, #667eea);
    transform: scale(1.05);
}

.empty-message {
    text-align: center;
    padding: 40px;
    color: #aaa;
    font-size: 16px;
}

.empty-message a {
    color: #4ECDC4;
    text-decoration: underline;
}

@media (max-width: 768px) {
    .friends-grid {
        grid-template-columns: 1fr;
    }
    
    .header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🤝 好友农场</h1>
        <div style="display: flex; gap: 10px;">
            <a href="stardust_farm.php" class="btn btn-primary">🏡 我的农场</a>
            <a href="index.php" class="btn btn-primary">🏠 返回首页</a>
        </div>
    </div>

    <?php if (count($friends) > 0): ?>
    <div class="section">
        <h2>👥 我的好友 (<?php echo count($friends); ?>)</h2>
        <div class="friends-grid">
            <?php foreach ($friends as $friend): ?>
            <div class="friend-card" onclick="location.href='stardust_farm.php?uid=<?php echo $friend['id']; ?>'">
                <div class="friend-header">
                    <div class="friend-avatar">
                        <?php echo mb_substr($friend['username'], 0, 2, 'UTF-8'); ?>
                    </div>
                    <div class="friend-info">
                        <div class="friend-name"><?php echo htmlspecialchars($friend['username']); ?></div>
                        <div class="friend-level">Lv.<?php echo $friend['level'] ?? 1; ?> | <?php echo $friend['land_slots'] ?? 3; ?>块地</div>
                    </div>
                </div>
                
                <div class="friend-stats">
                    <div class="stat-item">
                        <div class="stat-label">⭐ 星尘</div>
                        <div class="stat-value"><?php echo number_format($friend['stardust'] ?? 0); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">✨ 成熟</div>
                        <div class="stat-value mature"><?php echo $friend['mature_lands'] ?? 0; ?>块</div>
                    </div>
                </div>
                
                <div class="friend-actions">
                    <a href="stardust_farm.php?uid=<?php echo $friend['id']; ?>" class="action-btn visit" onclick="event.stopPropagation()">
                        🚀 访问农场
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="section">
        <h2>👥 我的好友</h2>
        <div class="empty-message">
            暂无好友，去<a href="friends.php">添加好友</a>后再来访问吧！
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($recentUsers) > 0): ?>
    <div class="section">
        <h2>🌟 最近活跃的农场主</h2>
        <div class="friends-grid">
            <?php foreach ($recentUsers as $user): ?>
            <div class="friend-card" onclick="location.href='stardust_farm.php?uid=<?php echo $user['id']; ?>'">
                <div class="friend-header">
                    <div class="friend-avatar">
                        <?php echo mb_substr($user['username'], 0, 2, 'UTF-8'); ?>
                    </div>
                    <div class="friend-info">
                        <div class="friend-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="friend-level">Lv.<?php echo $user['level'] ?? 1; ?> | <?php echo $user['land_slots'] ?? 3; ?>块地</div>
                    </div>
                </div>
                
                <div class="friend-stats">
                    <div class="stat-item">
                        <div class="stat-label">⭐ 星尘</div>
                        <div class="stat-value"><?php echo number_format($user['stardust'] ?? 0); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">✨ 成熟</div>
                        <div class="stat-value mature"><?php echo $user['mature_lands'] ?? 0; ?>块</div>
                    </div>
                </div>
                
                <div class="friend-actions">
                    <a href="stardust_farm.php?uid=<?php echo $user['id']; ?>" class="action-btn visit" onclick="event.stopPropagation()">
                        🚀 访问农场
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>

