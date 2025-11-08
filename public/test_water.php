<?php
require_once("../include/bittorrent.php");
require_once("../include/eloquent.php");
dbconn();
loggedinorreturn();

header('Content-Type: text/html; charset=utf-8');

// 获取当前用户的农场
$myFarm = \App\Models\StardustFarm::getOrCreateForUser($CURUSER['id']);
$myLands = $myFarm->lands()->where('status', 'growing')->get();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>浇水功能测试</title>
    <style>
        body { font-family: "Microsoft YaHei", Arial; padding: 20px; background: #1a1f3a; color: #fff; }
        .section { margin: 20px 0; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 10px; }
        .success { border-left: 4px solid #4ECDC4; }
        .info { border-left: 4px solid #667eea; }
        .btn { padding: 10px 20px; background: #4ECDC4; color: #000; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #FF6B6B; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; border: 1px solid rgba(255,255,255,0.2); text-align: left; }
        th { background: rgba(138, 43, 226, 0.3); }
        pre { background: #000; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>💧 浇水功能说明 & 测试</h1>
    
    <div class="section info">
        <h2>📋 浇水规则</h2>
        <ul>
            <li><strong>只能给好友浇水</strong> - 不能给自己的作物浇水</li>
            <li><strong>只能浇水"生长中"的作物</strong> - 空地和成熟的作物不能浇水</li>
            <li><strong>浇水效果</strong>：
                <ul>
                    <li>每次浇水可以缩短 <strong>10%</strong> 的成长时间</li>
                    <li>每块地最多被浇水 <strong>3次</strong></li>
                    <li>同一个人对同一块地只能浇水 <strong>1次</strong></li>
                    <li>浇水者获得少量星尘奖励</li>
                </ul>
            </li>
        </ul>
    </div>
    
    <div class="section success">
        <h2>🎮 如何浇水</h2>
        <ol>
            <li>点击右上角 <strong>"🤝 好友农场"</strong> 按钮</li>
            <li>进入好友列表，点击 <strong>"访问农场"</strong></li>
            <li>在好友农场中，找到"生长中"的作物</li>
            <li>点击 <strong>"💧 浇水"</strong> 按钮</li>
            <li>浇水成功后，作物成长时间会缩短</li>
        </ol>
    </div>
    
    <?php if ($myLands->count() > 0): ?>
    <div class="section info">
        <h2>🌱 你当前的种植情况</h2>
        <p>你有 <strong><?php echo $myLands->count(); ?></strong> 块地正在生长中：</p>
        <table>
            <tr>
                <th>地块ID</th>
                <th>作物</th>
                <th>种植时间</th>
                <th>成熟时间</th>
                <th>被浇水次数</th>
                <th>状态</th>
            </tr>
            <?php foreach ($myLands as $land): ?>
            <?php
                $crop = \App\Models\StardustCrop::find($land->crop_id);
                $land->updateStatus();
            ?>
            <tr>
                <td>#<?php echo $land->id; ?></td>
                <td><?php echo $crop->emoji . ' ' . $crop->name; ?></td>
                <td><?php echo $land->planted_at; ?></td>
                <td><?php echo $land->mature_at; ?></td>
                <td><?php echo $land->watered_times; ?> / 3</td>
                <td>
                    <?php if ($land->status === 'mature'): ?>
                        <span style="color: #4ECDC4;">✅ 已成熟</span>
                    <?php else: ?>
                        <span style="color: #FFA500;">⏰ 生长中</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <p style="margin-top: 20px; padding: 15px; background: rgba(255, 165, 0, 0.2); border-radius: 5px;">
            💡 <strong>提示</strong>：你不能给自己的作物浇水。如果想测试浇水功能，需要：
        </p>
        <ol style="margin-left: 40px;">
            <li>创建一个小号（测试账号）</li>
            <li>在小号上也种植作物</li>
            <li>用大号访问小号的农场，进行浇水</li>
            <li>或者等其他真实用户种植后互相浇水</li>
        </ol>
    </div>
    <?php else: ?>
    <div class="section info">
        <h2>🌱 你还没有种植作物</h2>
        <p>请先种植一些作物，然后：</p>
        <ol>
            <li>创建一个测试账号</li>
            <li>在测试账号上也种植作物</li>
            <li>用当前账号访问测试账号的农场</li>
            <li>为测试账号的作物浇水</li>
        </ol>
        <a href="stardust_farm.php" class="btn">🌱 去种植</a>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>🔗 相关链接</h2>
        <a href="stardust_farm.php" class="btn">🏡 我的农场</a>
        <a href="stardust_friends.php" class="btn">🤝 好友农场</a>
        <a href="index.php" class="btn">🏠 返回首页</a>
    </div>
    
    <div class="section info">
        <h2>💡 浇水的好处</h2>
        <ul>
            <li><strong>对被浇水者</strong>：作物成长时间缩短10%，最多可缩短30%（被浇3次）</li>
            <li><strong>对浇水者</strong>：获得少量星尘奖励 + 增进友谊</li>
            <li><strong>社交互动</strong>：促进玩家之间的互动，形成社区氛围</li>
        </ul>
        
        <p style="margin-top: 20px; padding: 15px; background: rgba(78, 205, 196, 0.2); border-radius: 5px;">
            📝 <strong>注意</strong>：浇水是一个社交功能，需要至少2个玩家才能体验。建议：
        </p>
        <ul style="margin-left: 40px;">
            <li>邀请朋友一起玩</li>
            <li>或者在站点公告中推广这个功能</li>
            <li>玩家之间互相帮助浇水，共同成长</li>
        </ul>
    </div>
</body>
</html>

