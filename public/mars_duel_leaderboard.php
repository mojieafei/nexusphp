<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

stdhead("火星幸运局 - 排行榜");
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
    color: white;
    margin-bottom: 30px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
}

.leaderboard-header h1 {
    font-size: 48px;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.leaderboard-header p {
    margin: 0;
    font-size: 18px;
    color: rgba(255, 255, 255, 0.8);
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
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    border-color: #FF6B6B;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
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
    display: grid;
    grid-template-columns: 30% 40% 30%;
    align-items: center;
    padding: 18px 24px;
    margin: 10px 0;
    background: linear-gradient(90deg, rgba(255, 107, 107, 0.12) 0%, rgba(238, 90, 111, 0.06) 100%);
    border-radius: 16px;
    transition: all 0.3s;
    border-left: 4px solid transparent;
    column-gap: 16px;
}

.rank-item:hover {
    transform: translateX(4px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
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

.rank-col {
    display: flex;
    align-items: center;
    gap: 14px;
}

.rank-col--left {
    justify-content: flex-start;
}

.rank-col--middle {
    flex-direction: column;
    justify-content: center;
    text-align: center;
    gap: 6px;
}

.rank-col--right {
    justify-content: flex-end;
    text-align: right;
}

.rank-number {
    font-size: 28px;
    font-weight: bold;
}

.rank-item.top1 .rank-number { color: #FFD700; }
.rank-item.top2 .rank-number { color: #C0C0C0; }
.rank-item.top3 .rank-number { color: #CD7F32; }

.rank-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.rank-name {
    font-size: 20px;
    font-weight: bold;
    color: #1f2a4d;
}

.rank-detail {
    font-size: 13px;
    color: #5d6a85;
}

.rank-value {
    font-size: 24px;
    font-weight: bold;
    color: #FF6B6B;
}

.rank-col--right .rank-value {
    width: 100%;
}

.loading {
    text-align: center;
    padding: 60px 20px;
    color: #999;
    font-size: 18px;
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

.back-btn {
    display: inline-block;
    padding: 12px 30px;
    background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
    color: white;
    text-decoration: none;
    border-radius: 25px;
    margin: 20px 10px;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
}

.back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
}
</style>

<div class="leaderboard-container">
    <div class="leaderboard-header">
        <h1>🎲 火星幸运局排行榜</h1>
        <p>谁是最幸运的赌徒？</p>
    </div>

    <div class="leaderboard-tabs">
        <button class="tab-btn active" data-type="bet" onclick="switchTab('bet', event)">💰 总下注榜</button>
        <button class="tab-btn" data-type="win" onclick="switchTab('win', event)">🏆 总获胜榜</button>
        <button class="tab-btn" data-type="profit" onclick="switchTab('profit', event)">💎 净收益榜</button>
        <button class="tab-btn" data-type="rate" onclick="switchTab('rate', event)">📊 胜率榜</button>
    </div>

    <div class="leaderboard-board active" id="board-bet">
        <div class="loading">加载中...</div>
    </div>

    <div class="leaderboard-board" id="board-win">
        <div class="loading">加载中...</div>
    </div>

    <div class="leaderboard-board" id="board-profit">
        <div class="loading">加载中...</div>
    </div>

    <div class="leaderboard-board" id="board-rate">
        <div class="loading">加载中...</div>
    </div>

    <div style="text-align: center;">
        <a href="index.php" class="back-btn">🏠 返回首页</a>
    </div>
</div>

<script>
let currentTab = 'bet';
let leaderboardData = {};

// 初始化页面
function initLeaderboardPage() {
    currentTab = 'bet';
    leaderboardData = {};
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const defaultBtn = document.querySelector('.tab-btn[data-type="bet"]');
    if (defaultBtn) {
        defaultBtn.classList.add('active');
    }
    document.querySelectorAll('.leaderboard-board').forEach(board => board.classList.remove('active'));
    const defaultBoard = document.getElementById('board-bet');
    if (defaultBoard) {
        defaultBoard.classList.add('active');
    }
    loadLeaderboard('bet');
}

// 切换标签
function switchTab(type, evt) {
    if (evt && typeof evt.preventDefault === 'function') {
        evt.preventDefault();
    }
    currentTab = type;
    
    // 更新按钮状态
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = (evt && evt.currentTarget) || document.querySelector('.tab-btn[data-type="' + type + '"]');
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
    
    // 更新榜单显示
    document.querySelectorAll('.leaderboard-board').forEach(board => board.classList.remove('active'));
    const targetBoard = document.getElementById('board-' + type);
    if (targetBoard) {
        targetBoard.classList.add('active');
    }
    
    // 加载数据
    if (!leaderboardData[type]) {
        loadLeaderboard(type);
    } else {
        renderLeaderboard(type, leaderboardData[type]);
    }
}

// 加载排行榜
function loadLeaderboard(type) {
    const board = document.getElementById('board-' + type);
    if (!board) return;
    
    board.innerHTML = '<div class="loading">加载中...</div>';
    
    fetch('ajax.php?action=get_mars_duel_leaderboard&type=' + type)
        .then(response => response.json())
        .then(data => {
            if (data.ret === 0 && data.data) {
                leaderboardData[type] = data.data;
                renderLeaderboard(type, data.data);
            } else {
                board.innerHTML = '<div class="empty-state"><div class="empty-state-icon">😔</div><div>暂无数据</div></div>';
            }
        })
        .catch(error => {
            console.error('加载排行榜失败:', error);
            board.innerHTML = '<div class="empty-state"><div class="empty-state-icon">❌</div><div>加载失败，请刷新重试</div></div>';
        });
}

// 渲染排行榜
function renderLeaderboard(type, data) {
    const board = document.getElementById('board-' + type);
    if (!board || !data || data.length === 0) {
        board.innerHTML = '<div class="empty-state"><div class="empty-state-icon">😔</div><div>暂无数据</div></div>';
        return;
    }
    
    let html = '';
    data.forEach((item, index) => {
        const rank = index + 1;
        const topClass = rank === 1 ? 'top1' : (rank === 2 ? 'top2' : (rank === 3 ? 'top3' : ''));
        const medal = rank === 1 ? '🥇' : (rank === 2 ? '🥈' : (rank === 3 ? '🥉' : rank));
        
        let valueText = '';
        let detailText = '';
        
        if (type === 'bet') {
            valueText = formatNumber(item.total_bet_amount) + ' 魔力值';
            detailText = `下注次数: ${item.total_bets}`;
        } else if (type === 'win') {
            valueText = formatNumber(item.total_win_amount) + ' 魔力值';
            detailText = `获胜次数: ${item.total_wins}`;
        } else if (type === 'profit') {
            const profit = item.total_win_amount - item.total_bet_amount;
            valueText = (profit >= 0 ? '+' : '') + formatNumber(profit) + ' 魔力值';
            detailText = `下注: ${formatNumber(item.total_bet_amount)} | 获胜: ${formatNumber(item.total_win_amount)}`;
        } else if (type === 'rate') {
            const rate = item.total_bets > 0 ? ((item.total_wins / item.total_bets) * 100).toFixed(1) : 0;
            valueText = rate + '%';
            detailText = `获胜: ${item.total_wins} / 下注: ${item.total_bets}`;
        }
        
        html += `
            <div class="rank-item ${topClass}">
                <div class="rank-col rank-col--left">
                    <div class="rank-number">${medal}</div>
                    <div class="rank-avatar">${item.username ? item.username.charAt(0).toUpperCase() : '👤'}</div>
                </div>
                <div class="rank-col rank-col--middle">
                    <div class="rank-name">${escapeHtml(item.username || '未知用户')}</div>
                    <div class="rank-detail">${detailText}</div>
                </div>
                <div class="rank-col rank-col--right">
                    <div class="rank-value">${valueText}</div>
                </div>
            </div>
        `;
    });
    
    board.innerHTML = html;
}

// 格式化数字
function formatNumber(num) {
    if (num >= 1000000000) {
        return (num / 1000000000).toFixed(2) + 'B';
    } else if (num >= 1000000) {
        return (num / 1000000).toFixed(2) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(2) + 'K';
    }
    return num.toString();
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 页面加载完成后初始化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLeaderboardPage);
} else {
    initLeaderboardPage();
}
</script>

<?php
stdfoot();
?>

