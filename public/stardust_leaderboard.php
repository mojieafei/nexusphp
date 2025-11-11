<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

stdhead("星尘农场 - 排行榜");
?>

<style>
.leaderboard-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 16px;
}

.leaderboard-header {
    text-align: center;
    color: white;
    margin-bottom: 30px;
}

.leaderboard-header h1 {
    font-size: 48px;
    margin-bottom: 10px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.leaderboard-tabs {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-size: 16px;
    cursor: pointer;
    border-radius: 25px;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
}

.tab-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.tab-btn.active {
    background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    border-color: #4ECDC4;
    box-shadow: 0 4px 15px rgba(78, 205, 196, 0.4);
}

.leaderboard-board {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    display: none;
}

.leaderboard-board.active {
    display: block;
}

.rank-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    margin: 10px 0;
    background: linear-gradient(90deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.05) 100%);
    border-radius: 15px;
    transition: all 0.3s;
    border-left: 4px solid transparent;
}

.rank-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.rank-item.top1 {
    background: linear-gradient(90deg, rgba(255, 215, 0, 0.2) 0%, rgba(255, 215, 0, 0.05) 100%);
    border-left-color: #FFD700;
}

.rank-item.top2 {
    background: linear-gradient(90deg, rgba(192, 192, 192, 0.2) 0%, rgba(192, 192, 192, 0.05) 100%);
    border-left-color: #C0C0C0;
}

.rank-item.top3 {
    background: linear-gradient(90deg, rgba(205, 127, 50, 0.2) 0%, rgba(205, 127, 50, 0.05) 100%);
    border-left-color: #CD7F32;
}

.rank-number {
    font-size: 24px;
    font-weight: bold;
    width: 60px;
    text-align: center;
}

.rank-item.top1 .rank-number { color: #FFD700; }
.rank-item.top2 .rank-number { color: #C0C0C0; }
.rank-item.top3 .rank-number { color: #CD7F32; }

.rank-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 15px;
}

.rank-info {
    flex: 1;
}

.rank-name {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.rank-detail {
    font-size: 14px;
    color: #666;
}

.rank-value {
    font-size: 24px;
    font-weight: bold;
    color: #667eea;
    margin-right: 10px;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #666;
    font-size: 18px;
}

.back-btn {
    display: inline-block;
    padding: 12px 30px;
    background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    color: white;
    text-decoration: none;
    border-radius: 25px;
    margin: 20px 0;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(78, 205, 196, 0.3);
}

.back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(78, 205, 196, 0.4);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}
</style>

<div class="leaderboard-container">
    <div class="leaderboard-header">
        <h1>🏆 星尘农场排行榜</h1>
        <p>谁是最强的星际农夫？</p>
    </div>

    <div class="leaderboard-tabs">
        <button class="tab-btn active" onclick="switchTab('wealth')">💰 财富榜</button>
        <button class="tab-btn" onclick="switchTab('level')">⭐ 等级榜</button>
        <button class="tab-btn" onclick="switchTab('fragments')">💎 碎片榜</button>
        <button class="tab-btn" onclick="switchTab('planets')">🌍 行星榜</button>
    </div>

    <div class="leaderboard-board active" id="board-wealth">
        <div class="loading">加载中...</div>
    </div>

    <div class="leaderboard-board" id="board-level">
        <div class="loading">加载中...</div>
    </div>

    <div class="leaderboard-board" id="board-fragments">
        <div class="loading">加载中...</div>
    </div>

    <div class="leaderboard-board" id="board-planets">
        <div class="loading">加载中...</div>
    </div>

    <div style="text-align: center;">
        <a href="stardust_farm.php" class="back-btn">🏡 返回农场</a>
        <a href="index.php" class="back-btn">🏠 返回首页</a>
    </div>
</div>

<script>
let currentTab = 'wealth';
let leaderboardData = {};

// 切换标签
function switchTab(type) {
    currentTab = type;
    
    // 更新按钮状态
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // 更新榜单显示
    document.querySelectorAll('.leaderboard-board').forEach(board => board.classList.remove('active'));
    document.getElementById('board-' + type).classList.add('active');
    
    // 加载数据
    if (!leaderboardData[type]) {
        loadLeaderboard(type);
    }
}

// 加载排行榜
function loadLeaderboard(type) {
    const board = document.getElementById('board-' + type);
    board.innerHTML = '<div class="loading">加载中...</div>';
    
    callAPI('getStardustLeaderboard', { type: type, limit: 100 })
        .then(data => {
            leaderboardData[type] = data;
            renderLeaderboard(type, data);
        })
        .catch(error => {
            board.innerHTML = '<div class="loading" style="color: red;">加载失败: ' + error + '</div>';
        });
}

// 渲染排行榜
function renderLeaderboard(type, data) {
    const board = document.getElementById('board-' + type);
    
    if (!data || data.length === 0) {
        board.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div>暂无数据</div>
                <div style="margin-top: 10px; color: #bbb;">成为第一个上榜的玩家吧！</div>
            </div>
        `;
        return;
    }
    
    let html = '';
    data.forEach((item, index) => {
        const rank = index + 1;
        const topClass = rank === 1 ? 'top1' : (rank === 2 ? 'top2' : (rank === 3 ? 'top3' : ''));
        const medal = rank === 1 ? '🥇' : (rank === 2 ? '🥈' : (rank === 3 ? '🥉' : rank));
        
        let valueText = '';
        let detailText = '';
        
        switch(type) {
            case 'wealth':
                valueText = item.stardust + ' ⭐';
                detailText = `等级 ${item.level} | ${item.land_slots} 块地`;
                break;
            case 'level':
                valueText = 'Lv.' + item.level;
                detailText = `${item.experience} 经验 | ${item.stardust} 星尘`;
                break;
            case 'fragments':
                valueText = item.total_fragments + ' 💎';
                detailText = `已收获 ${item.total_fragments} 个碎片`;
                break;
            case 'planets':
                valueText = item.total_planets + ' 🌍';
                detailText = `已合成 ${item.total_planets} 个完整行星`;
                break;
        }
        
        html += `
            <div class="rank-item ${topClass}">
                <div class="rank-number">${medal}</div>
                <div class="rank-avatar">${item.username ? item.username.charAt(0).toUpperCase() : '👤'}</div>
                <div class="rank-info">
                    <div class="rank-name">${item.username || '未知用户'}</div>
                    <div class="rank-detail">${detailText}</div>
                </div>
                <div class="rank-value">${valueText}</div>
            </div>
        `;
    });
    
    board.innerHTML = html;
}

// API 调用
function callAPI(action, params) {
    return fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=' + action + '&params=' + encodeURIComponent(JSON.stringify(params))
    })
    .then(response => response.json())
    .then(data => {
        if (data.ret === 0) {
            return data.data;
        } else {
            throw data.msg || '操作失败';
        }
    });
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    loadLeaderboard('wealth');
});
</script>

<?php
stdfoot();
?>

