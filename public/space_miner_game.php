<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

// 获取用户密码哈希用于Token生成
$user = \App\Models\User::find($CURUSER['id']);
$userPasshash = $user ? $user->passhash : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>宇宙碎片抓取 - <?php echo $SITENAME; ?></title>
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    overflow: hidden;
    width: 100vw;
    height: 100vh;
    background: linear-gradient(180deg, #0a0a1e 0%, #1a1a3e 50%, #2a2a5e 100%);
    display: flex;
}

/* 左侧游戏区 */
.game-container {
    position: relative;
    flex: 1;
    height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

#gameCanvas {
    border: 2px solid rgba(100, 200, 255, 0.5);
    border-radius: 10px;
    box-shadow: 0 0 30px rgba(100, 200, 255, 0.3);
    background: rgba(0, 0, 20, 0.5);
    max-width: 90%;
    max-height: 85%;
}

.game-info {
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #fff;
    font-size: 24px;
    font-weight: bold;
    text-shadow: 0 0 10px rgba(100, 200, 255, 0.8);
    z-index: 10;
}

.game-info > div {
    background: rgba(0, 0, 0, 0.6);
    padding: 10px 20px;
    border-radius: 10px;
    border: 2px solid rgba(100, 200, 255, 0.5);
}

.control-panel {
    position: absolute;
    left: 20px;
    right: 20px;
    bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: rgba(0, 0, 0, 0.6);
    border: 2px solid rgba(100, 200, 255, 0.5);
    border-radius: 12px;
    color: #fff;
    font-size: 16px;
    box-shadow: 0 0 15px rgba(100, 200, 255, 0.3);
    flex-wrap: wrap;
}

.btn-start {
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.btn-start:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.game-over {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.9);
    border: 3px solid rgba(100, 200, 255, 0.8);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    color: #fff;
    z-index: 1000;
    display: none;
}

.game-over h2 {
    font-size: 36px;
    margin-bottom: 20px;
    color: #64c8ff;
}

.game-over p {
    font-size: 24px;
    margin: 10px 0;
}

.btn-submit {
    margin-top: 20px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* 右侧排行榜 */
.leaderboard-panel {
    width: 350px;
    height: 100vh;
    background: rgba(0, 0, 0, 0.8);
    border-left: 2px solid rgba(100, 200, 255, 0.5);
    padding: 20px;
    overflow-y: auto;
    color: #fff;
}

.leaderboard-panel h3 {
    font-size: 24px;
    margin-bottom: 15px;
    color: #64c8ff;
    text-align: center;
    border-bottom: 2px solid rgba(100, 200, 255, 0.5);
    padding-bottom: 10px;
}

.tab-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
}

.tab-btn {
    flex: 1;
    padding: 8px;
    background: rgba(100, 200, 255, 0.2);
    border: 1px solid rgba(100, 200, 255, 0.5);
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    transition: all 0.3s;
}

.tab-btn.active {
    background: rgba(100, 200, 255, 0.5);
    border-color: #64c8ff;
}

.tab-btn:hover {
    background: rgba(100, 200, 255, 0.3);
}

.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.leaderboard-table th,
.leaderboard-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid rgba(100, 200, 255, 0.2);
}

.leaderboard-table th {
    background: rgba(100, 200, 255, 0.2);
    font-weight: bold;
}

.leaderboard-table tr:hover {
    background: rgba(100, 200, 255, 0.1);
}

.rank-1 { color: #ffd700; }
.rank-2 { color: #c0c0c0; }
.rank-3 { color: #cd7f32; }

#submitLimitInfo {
    margin-top: 15px;
    padding: 10px;
    background: rgba(100, 200, 255, 0.1);
    border-radius: 6px;
    font-size: 13px;
    text-align: center;
}

@media (max-width: 1200px) {
    .leaderboard-panel {
        display: none;
    }
}
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-info">
            <div>得分: <span id="score">0</span></div>
            <div>目标: <span id="target">500</span></div>
            <div>时间: <span id="time">60</span>s</div>
            <div>关卡: <span id="level">1</span></div>
        </div>
        
        <canvas id="gameCanvas"></canvas>
        
        <div class="control-panel">
            <button class="btn-start" id="startBtn">开始游戏</button>
            <div style="flex: 1;">
                <div>操作说明: 机械手自动摆动 | 点击发射机械手 | 空格键快速发射</div>
                <div id="submitLimitInfo">加载中...</div>
            </div>
        </div>
        
        <div class="game-over" id="gameOver">
            <h2>游戏结束</h2>
            <p>最终得分: <span id="finalScore">0</span></p>
            <p>抓取数量: <span id="finalCaught">0</span></p>
            <button class="btn-submit" id="submitBtn">提交分数</button>
            <button class="btn-submit" onclick="location.reload()" style="margin-left: 10px;">重新开始</button>
        </div>
    </div>
    
    <div class="leaderboard-panel">
        <h3>🏆 排行榜</h3>
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="loadLeaderboard('today', this)">今日</button>
            <button class="tab-btn" onclick="loadLeaderboard('7days', this)">7天</button>
            <button class="tab-btn" onclick="loadLeaderboard('alltime', this)">总榜</button>
        </div>
        <table class="leaderboard-table">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>用户</th>
                    <th>得分</th>
                    <th>抓取</th>
                </tr>
            </thead>
            <tbody id="leaderboardBody">
                <tr><td colspan="4" style="text-align:center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>

    <script>
const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

// 设置画布大小
function resizeCanvas() {
    const container = document.querySelector('.game-container');
    const maxWidth = container.clientWidth - 40;
    const maxHeight = container.clientHeight - 200;
    const aspectRatio = 16 / 10;
    
    let width = Math.min(maxWidth, 1200);
    let height = width / aspectRatio;
    
    if (height > maxHeight) {
        height = maxHeight;
        width = height * aspectRatio;
    }
    
    canvas.width = width;
    canvas.height = height;
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

// 游戏状态
const gameState = {
    started: false,
    gameOver: false,
    score: 0,
    target: 500,
    timeLeft: 60,
    level: 1,
    caught: 0,
    
    // 飞船（在顶部中心）
    ship: {
        x: 0, // 将在初始化时设置
        y: 50,
        width: 80,
        height: 40,
        angle: -Math.PI / 2, // 初始角度向下
        angleSpeed: 0.02,
        // 自动摆动相关
        swingSpeed: 0.008, // 摆动速度（弧度/毫秒）
        swingDirection: 1, // 1为向右，-1为向左
        swingAngle: Math.PI * 165 / 180, // 最大摆动角度（左右各82.5度，总共165度）
        currentSwingAngle: 0 // 当前摆动角度（从0到swingAngle，再到-swingAngle）
    },
    
    // 机械手
    claw: {
        extended: false,
        length: 0,
        maxLength: 400,
        extendSpeed: 8,
        retractSpeed: 6,
        angle: -Math.PI / 2,
        grabbing: false,
        grabbedItem: null
    },
    
    // 行星碎片
    fragments: [],
    fragmentSpawnTimer: 0,
    fragmentSpawnInterval: 2000
};

// 碎片类型
const FRAGMENT_TYPES = [
    { name: '小碎片', emoji: '🟤', score: 10, size: 15, weight: 1, chance: 0.30, color: '#8B4513' },
    { name: '中碎片', emoji: '🟠', score: 25, size: 25, weight: 2, chance: 0.25, color: '#FF8C00' },
    { name: '大碎片', emoji: '🟡', score: 50, size: 35, weight: 3, chance: 0.20, color: '#FFD700' },
    { name: '金星碎片', emoji: '🟨', score: 100, size: 30, weight: 2, chance: 0.10, color: '#FFC107' },
    { name: '火星碎片', emoji: '🔴', score: 150, size: 40, weight: 4, chance: 0.08, color: '#F44336' },
    { name: '木星碎片', emoji: '🪐', score: 200, size: 45, weight: 5, chance: 0.05, color: '#9C27B0' },
    { name: '太阳碎片', emoji: '☀️', score: 300, size: 50, weight: 6, chance: 0.02, color: '#FF9800' }
];

// 创建碎片
function createFragment() {
    const rand = Math.random();
    let cumulativeChance = 0;
    let fragmentType = FRAGMENT_TYPES[0];
    
    for (let type of FRAGMENT_TYPES) {
        cumulativeChance += type.chance;
        if (rand < cumulativeChance) {
            fragmentType = type;
            break;
        }
    }
    
    return {
        x: Math.random() * (canvas.width - fragmentType.size * 2) + fragmentType.size,
        y: Math.random() * (canvas.height - 300) + 150, // 避免在飞船附近生成
        ...fragmentType,
        id: Date.now() + Math.random()
    };
}

// 初始化游戏
function initGame() {
    gameState.started = true;
    gameState.gameOver = false;
    gameState.score = 0;
    gameState.target = 500;
    gameState.timeLeft = 60;
    gameState.level = 1;
    gameState.caught = 0;
    gameState.ship.x = canvas.width / 2;
    gameState.ship.angle = -Math.PI / 2;
    gameState.ship.currentSwingAngle = 0;
    gameState.ship.swingDirection = 1;
    gameState.claw.extended = false;
    gameState.claw.length = 0;
    gameState.claw.grabbing = false;
    gameState.claw.grabbedItem = null;
    gameState.fragments = [];
    gameState.fragmentSpawnTimer = 0;
    
    // 初始生成一些碎片
    for (let i = 0; i < 8; i++) {
        gameState.fragments.push(createFragment());
    }
    
    updateUI();
}

// 绘制飞船
function drawShip() {
    const ship = gameState.ship;
    ctx.save();
    ctx.translate(ship.x, ship.y);
    
    // 飞船主体（矩形）
    ctx.fillStyle = '#4A90E2';
    ctx.shadowBlur = 15;
    ctx.shadowColor = '#64C8FF';
    ctx.fillRect(-ship.width / 2, 0, ship.width, ship.height);
    
    // 飞船装饰
    ctx.fillStyle = '#64C8FF';
    ctx.fillRect(-ship.width / 2 + 10, 5, 20, 10);
    ctx.fillRect(ship.width / 2 - 30, 5, 20, 10);
    
    // 机械手连接点
    ctx.fillStyle = '#FFD700';
    ctx.beginPath();
    ctx.arc(0, ship.height / 2, 5, 0, Math.PI * 2);
    ctx.fill();
    
    ctx.restore();
}

// 绘制机械手
function drawClaw() {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    if (claw.length === 0 && !claw.extended) return;
    
    ctx.save();
    ctx.translate(ship.x, ship.y + gameState.ship.height / 2);
    ctx.rotate(claw.angle);
    
    // 绘制绳索/机械臂
    ctx.strokeStyle = '#888';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.lineTo(0, claw.length);
    ctx.stroke();
    
    // 绘制爪子
    if (claw.extended || claw.length > 0) {
        const clawX = 0;
        const clawY = claw.length;
        
        ctx.fillStyle = claw.grabbing ? '#FF6B6B' : '#888';
        ctx.shadowBlur = 10;
        ctx.shadowColor = claw.grabbing ? '#FF0000' : '#444';
        
        // 爪子形状（简化版）
        ctx.beginPath();
        ctx.arc(clawX, clawY, 8, 0, Math.PI * 2);
        ctx.fill();
        
        // 爪子手指
        if (claw.grabbing) {
            ctx.fillStyle = '#FF0000';
            // 三个爪子手指
            for (let i = -1; i <= 1; i++) {
                ctx.beginPath();
                ctx.arc(clawX + i * 6, clawY + 8, 4, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }
    
    ctx.restore();
}

// 绘制碎片
function drawFragments() {
    gameState.fragments.forEach(fragment => {
        ctx.save();
        
        // 碎片光晕
        if (fragment === gameState.claw.grabbedItem) {
            ctx.shadowBlur = 20;
            ctx.shadowColor = '#64C8FF';
        } else {
            ctx.shadowBlur = 10;
            ctx.shadowColor = fragment.color;
        }
        
        // 绘制碎片（圆形）
        ctx.fillStyle = fragment.color;
        ctx.globalAlpha = 0.8;
        ctx.beginPath();
        ctx.arc(fragment.x, fragment.y, fragment.size, 0, Math.PI * 2);
        ctx.fill();
        
        // 绘制emoji
        ctx.globalAlpha = 1;
        ctx.font = fragment.size + 'px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowBlur = 0;
        ctx.fillText(fragment.emoji, fragment.x, fragment.y);
        
        ctx.restore();
    });
}

// 检查碰撞
function checkCollision(fragment) {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    if (!claw.extended || claw.length === 0) return false;
    
    // 计算爪子位置
    const shipY = ship.y + ship.height / 2;
    const clawX = ship.x + Math.sin(claw.angle) * claw.length;
    const clawY = shipY + Math.cos(claw.angle) * claw.length;
    
    // 距离检测
    const dx = clawX - fragment.x;
    const dy = clawY - fragment.y;
    const distance = Math.sqrt(dx * dx + dy * dy);
    
    return distance < fragment.size + 8;
}

// 更新游戏
function updateGame(deltaTime) {
    if (!gameState.started || gameState.gameOver) return;
    
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    // 自动摆动逻辑（只在机械手未伸出时摆动）
    if (!claw.extended) {
        // 更新摆动角度
        ship.currentSwingAngle += ship.swingSpeed * ship.swingDirection * deltaTime;
        
        // 到达边界时改变方向
        if (ship.currentSwingAngle >= ship.swingAngle) {
            ship.currentSwingAngle = ship.swingAngle;
            ship.swingDirection = -1;
        } else if (ship.currentSwingAngle <= -ship.swingAngle) {
            ship.currentSwingAngle = -ship.swingAngle;
            ship.swingDirection = 1;
        }
        
        // 计算最终角度（垂直向下 + 摆动角度）
        ship.angle = -Math.PI / 2 + ship.currentSwingAngle;
    } else {
        // 机械手伸出时，保持当前角度不变
    }
    
    // 同步爪子角度
    claw.angle = ship.angle;
    
    // 更新机械手
    if (claw.extended) {
        // 检查是否在伸出阶段
        const isExtending = claw.length < claw.maxLength;
        
        if (isExtending) {
            // 伸出阶段：增加长度
            claw.length += claw.extendSpeed;
            if (claw.length > claw.maxLength) {
                claw.length = claw.maxLength;
            }
            
            // 检查碰撞（在伸出过程中持续检查）
            for (let fragment of gameState.fragments) {
                if (checkCollision(fragment)) {
                    claw.grabbing = true;
                    claw.grabbedItem = fragment;
                    break;
                }
            }
        } else {
            // 已经到达最大长度，开始收回阶段
            if (claw.grabbing && claw.grabbedItem) {
                // 收回（带碎片）
                const retractSpeed = claw.retractSpeed / claw.grabbedItem.weight;
                claw.length -= retractSpeed;
                
                // 更新碎片位置（相对于飞船位置）
                const shipY = ship.y + ship.height / 2;
                const clawX = ship.x + Math.sin(claw.angle) * claw.length;
                const clawY = shipY + Math.cos(claw.angle) * claw.length;
                claw.grabbedItem.x = clawX;
                claw.grabbedItem.y = clawY;
            } else {
                // 收回（空手）
                claw.length -= claw.retractSpeed;
            }
            
            // 检查是否回到飞船
            if (claw.length <= 0) {
                claw.length = 0;
                
                if (claw.grabbing && claw.grabbedItem) {
                    // 获得分数
                    gameState.score += claw.grabbedItem.score;
                    gameState.caught++;
                    
                    // 移除碎片
                    gameState.fragments = gameState.fragments.filter(f => f.id !== claw.grabbedItem.id);
                    
                    // 检查是否达到目标
                    if (gameState.score >= gameState.target) {
                        gameState.level++;
                        gameState.target = 500 + (gameState.level - 1) * 300;
                        gameState.timeLeft += 10; // 奖励时间
                    }
                    
                    claw.grabbedItem = null;
                }
                
                claw.grabbing = false;
                claw.extended = false;
            }
        }
    }
    
    // 生成新碎片
    gameState.fragmentSpawnTimer += deltaTime;
    if (gameState.fragmentSpawnTimer >= gameState.fragmentSpawnInterval) {
        if (gameState.fragments.length < 12) {
            gameState.fragments.push(createFragment());
        }
        gameState.fragmentSpawnTimer = 0;
    }
    
    // 更新时间
    gameState.timeLeft -= deltaTime / 1000;
    if (gameState.timeLeft <= 0) {
        gameState.timeLeft = 0;
        endGame();
    }
    
    updateUI();
}

// 绘制背景（星空）
function drawBackground() {
    // 深色渐变背景
    const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, '#0a0a1e');
    gradient.addColorStop(1, '#2a2a5e');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // 绘制一些星星
    ctx.fillStyle = '#fff';
    for (let i = 0; i < 50; i++) {
        const x = (i * 37) % canvas.width;
        const y = (i * 71) % canvas.height;
        const size = Math.random() * 2;
        ctx.globalAlpha = Math.random() * 0.8 + 0.2;
        ctx.beginPath();
        ctx.arc(x, y, size, 0, Math.PI * 2);
        ctx.fill();
    }
    ctx.globalAlpha = 1;
}

// 绘制
function draw() {
    drawBackground();
    drawFragments();
    drawClaw();
    drawShip();
}

// 游戏循环
let lastTime = 0;
function gameLoop(currentTime) {
    if (!lastTime) lastTime = currentTime;
    const deltaTime = currentTime - lastTime;
    lastTime = currentTime;
    
    updateGame(deltaTime);
    draw();
    
    requestAnimationFrame(gameLoop);
}

// 更新UI
function updateUI() {
    document.getElementById('score').textContent = gameState.score;
    document.getElementById('target').textContent = gameState.target;
    document.getElementById('time').textContent = Math.ceil(gameState.timeLeft);
    document.getElementById('level').textContent = gameState.level;
}

// 结束游戏
function endGame() {
    gameState.gameOver = true;
    gameState.started = false;
    document.getElementById('finalScore').textContent = gameState.score;
    document.getElementById('finalCaught').textContent = gameState.caught;
    document.getElementById('gameOver').style.display = 'block';
}

// 点击/触摸发射控制
canvas.addEventListener('click', () => {
    if (!gameState.started || gameState.gameOver) return;
    if (!gameState.claw.extended) {
        gameState.claw.extended = true;
    }
});

// 空格键快速发射
document.addEventListener('keydown', (e) => {
    if (e.code === 'Space' && gameState.started && !gameState.gameOver && !gameState.claw.extended) {
        e.preventDefault();
        gameState.claw.extended = true;
    }
});

// 开始按钮
document.getElementById('startBtn').addEventListener('click', () => {
    initGame();
    document.getElementById('startBtn').disabled = true;
    document.getElementById('gameOver').style.display = 'none';
});

// 提交分数
document.getElementById('submitBtn').addEventListener('click', async () => {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '提交中...';
    
    try {
        const response = await fetch('ajax.php?action=space_miner_submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                score: gameState.score,
                caught: gameState.caught,
                level: gameState.level,
                token: '<?php echo md5($userPasshash . "space_miner_token"); ?>'
            })
        });
        
        const data = await response.json();
        if (data.success) {
            alert('分数提交成功！');
            loadLeaderboard('today');
            loadRemainingSubmits();
        } else {
            alert('提交失败: ' + data.message);
        }
    } catch (error) {
        alert('提交错误: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '提交分数';
    }
});

// 加载排行榜
function loadLeaderboard(type, eventElement) {
    // 更新标签
    if (eventElement) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        eventElement.classList.add('active');
    }
    
    fetch(`ajax.php?action=space_miner_leaderboard&type=${type}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('leaderboardBody');
            if (data.success && data.leaderboard) {
                if (data.leaderboard.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">暂无数据</td></tr>';
                    return;
                }
                tbody.innerHTML = data.leaderboard.map((item, index) => {
                    const rankClass = index === 0 ? 'rank-1' : index === 1 ? 'rank-2' : index === 2 ? 'rank-3' : '';
                    return `<tr class="${rankClass}">
                        <td>${index + 1}</td>
                        <td>${item.username || 'Unknown'}</td>
                        <td>${item.score}</td>
                        <td>${item.caught}</td>
                    </tr>`;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">加载失败</td></tr>';
            }
        })
        .catch(error => {
            document.getElementById('leaderboardBody').innerHTML = '<tr><td colspan="4" style="text-align:center;">加载失败</td></tr>';
        });
}

// 加载剩余提交次数
function loadRemainingSubmits() {
    fetch('ajax.php?action=space_miner_remaining')
        .then(response => response.json())
        .then(data => {
            const infoEl = document.getElementById('submitLimitInfo');
            if (data.success) {
                const remaining = data.remaining_submits || 0;
                const maxSubmits = data.max_submits || 5;
                if (remaining > 0) {
                    infoEl.innerHTML = `<span style="color: #64C8FF;">✨ 今日剩余提交次数: <strong>${remaining}/${maxSubmits}</strong></span>`;
                } else {
                    infoEl.innerHTML = `<span style="color: #FF6B6B;">⚠️ 今日提交次数已用完</span>`;
                }
            }
        })
        .catch(() => {});
}

// 启动游戏循环
requestAnimationFrame(gameLoop);

// 加载排行榜和剩余次数
loadLeaderboard('today');
loadRemainingSubmits();
    </script>
</body>
</html>
