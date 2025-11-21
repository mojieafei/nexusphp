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
                <div>操作说明: 机械臂360度自动旋转 | 点击发射机械手 | 空格键快速发射</div>
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
    
    // 飞船（在屏幕中央）
    ship: {
        x: 0, // 将在初始化时设置到屏幕中心
        y: 0, // 将在初始化时设置到屏幕中心
        width: 80,
        height: 40,
        angle: 0, // 当前旋转角度（360度旋转）
        // 360度旋转相关
        rotationSpeed: 0.003 // 旋转速度（弧度/毫秒）
    },
    
    // 机械手
    claw: {
        extended: false,
        length: 0,
        maxLength: 400,
        extendSpeed: 0.5, // 像素/毫秒
        retractSpeed: 0.4, // 像素/毫秒
        angle: -Math.PI / 2,
        grabbing: false,
        grabbedItem: null
    },
    
    // 行星碎片
    fragments: [],
    fragmentSpawnTimer: 0,
    fragmentSpawnInterval: 2000,
    
    // 石头障碍物
    rocks: []
};

// 碎片类型（采用接流星游戏中的类型，质量梯度从月亮1.0到太阳8.0）
const FRAGMENT_TYPES = [
    // 正向碎片（加分）- 质量梯度合理递增
    { name: '月球', emoji: '🌙', score: 10, size: 20, weight: 1.0, chance: 0.30, type: 'good', color: '#C0C0C0' },
    { name: '地球', emoji: '🌍', score: 15, size: 22, weight: 2.0, chance: 0.23, type: 'good', color: '#4A90E2' },
    { name: '火星', emoji: '🔴', score: 20, size: 18, weight: 2.5, chance: 0.18, type: 'good', color: '#F44336' },
    { name: '木星', emoji: '🪐', score: 30, size: 28, weight: 5.0, chance: 0.10, type: 'good', color: '#9C27B0' },
    { name: '土星', emoji: '🪐', score: 35, size: 26, weight: 6.0, chance: 0.04, type: 'good', color: '#FF9800' },
    { name: '太阳', emoji: '☀️', score: 50, size: 30, weight: 8.0, chance: 0.02, type: 'good', color: '#FFD700' },
    // 负向碎片（扣分）
    { name: '外星虫', emoji: '👾', score: -15, size: 22, weight: 1.5, chance: 0.08, type: 'bad', color: '#9C27B0' },
    { name: '陨石怪', emoji: '☄️', score: -25, size: 24, weight: 3.0, chance: 0.04, type: 'bad', color: '#795548' },
    { name: '黑洞', emoji: '🕳️', score: -50, size: 28, weight: 10.0, chance: 0.01, type: 'bad', color: '#000000' }
];

// 石头障碍物类型
const ROCK_TYPES = [
    { size: 20, chance: 0.40 },
    { size: 35, chance: 0.30 },
    { size: 50, chance: 0.20 },
    { size: 70, chance: 0.10 }
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
        x: 0, // 将在生成时设置
        y: 0, // 将在生成时设置
        ...fragmentType,
        id: Date.now() + Math.random()
    };
}

// 创建石头障碍物
function createRock() {
    const rand = Math.random();
    let cumulativeChance = 0;
    let rockType = ROCK_TYPES[0];
    
    for (let type of ROCK_TYPES) {
        cumulativeChance += type.chance;
        if (rand < cumulativeChance) {
            rockType = type;
            break;
        }
    }
    
    return {
        x: 0, // 将在生成时设置
        y: 0, // 将在生成时设置
        size: rockType.size,
        id: Date.now() + Math.random() + 1000000 // 确保ID不冲突
    };
}

// 检查位置是否与现有物体碰撞
function checkPositionCollision(x, y, size, excludeId = null) {
    const minDistance = 80; // 物体间最小距离
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const minDistanceFromShip = 100; // 距离飞船中心的最小距离
    
    // 检查与飞船的距离
    if (Math.sqrt((x - centerX) ** 2 + (y - centerY) ** 2) < minDistanceFromShip) {
        return true;
    }
    
    // 检查与现有碎片的距离
    for (let fragment of gameState.fragments) {
        if (excludeId && fragment.id === excludeId) continue;
        const dx = x - fragment.x;
        const dy = y - fragment.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        if (distance < (size + fragment.size + minDistance)) {
            return true;
        }
    }
    
    // 检查与现有石头的距离
    for (let rock of gameState.rocks) {
        if (excludeId && rock.id === excludeId) continue;
        const dx = x - rock.x;
        const dy = y - rock.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        if (distance < (size + rock.size + minDistance)) {
            return true;
        }
    }
    
    return false;
}

// 生成随机位置（避免碰撞）
function generateRandomPosition(size) {
    const maxAttempts = 50;
    for (let i = 0; i < maxAttempts; i++) {
        const x = Math.random() * (canvas.width - size * 2) + size;
        const y = Math.random() * (canvas.height - size * 2) + size;
        if (!checkPositionCollision(x, y, size)) {
            return { x, y };
        }
    }
    // 如果找不到合适位置，返回随机位置
    return {
        x: Math.random() * (canvas.width - size * 2) + size,
        y: Math.random() * (canvas.height - size * 2) + size
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
    // 飞船位置在屏幕中央
    gameState.ship.x = canvas.width / 2;
    gameState.ship.y = canvas.height / 2;
    gameState.ship.angle = 0;
    gameState.claw.extended = false;
    gameState.claw.length = 0;
    gameState.claw.grabbing = false;
    gameState.claw.grabbedItem = null;
    gameState.claw.angle = 0;
    gameState.fragments = [];
    gameState.rocks = [];
    gameState.fragmentSpawnTimer = 0;
    
    // 初始生成20个固定碎片
    for (let i = 0; i < 20; i++) {
        const fragment = createFragment();
        const pos = generateRandomPosition(fragment.size);
        fragment.x = pos.x;
        fragment.y = pos.y;
        gameState.fragments.push(fragment);
    }
    
    // 随机生成一些石头障碍物（3-8个）
    const rockCount = 3 + Math.floor(Math.random() * 6);
    for (let i = 0; i < rockCount; i++) {
        const rock = createRock();
        const pos = generateRandomPosition(rock.size);
        rock.x = pos.x;
        rock.y = pos.y;
        gameState.rocks.push(rock);
    }
    
    updateUI();
}

// 绘制飞船
function drawShip() {
    const ship = gameState.ship;
    ctx.save();
    ctx.translate(ship.x, ship.y);
    ctx.rotate(ship.angle); // 飞船也会旋转，朝向当前角度
    
    // 飞船主体（圆形，表示飞船中心）
    ctx.fillStyle = '#4A90E2';
    ctx.shadowBlur = 20;
    ctx.shadowColor = '#64C8FF';
    ctx.beginPath();
    ctx.arc(0, 0, 30, 0, Math.PI * 2);
    ctx.fill();
    
    // 飞船中心装饰
    ctx.fillStyle = '#64C8FF';
    ctx.beginPath();
    ctx.arc(0, 0, 20, 0, Math.PI * 2);
    ctx.fill();
    
    // 指示方向的小三角形（指向机械手发射方向，转180度）
    ctx.fillStyle = '#FFD700';
    ctx.beginPath();
    ctx.moveTo(0, 25);  // 改为向下（与发射方向一致）
    ctx.lineTo(-8, 15);
    ctx.lineTo(8, 15);
    ctx.closePath();
    ctx.fill();
    
    // 机械手连接点（在中心）
    ctx.fillStyle = '#FF6B6B';
    ctx.beginPath();
    ctx.arc(0, 0, 5, 0, Math.PI * 2);
    ctx.fill();
    
    ctx.restore();
}

// 绘制机械手
function drawClaw() {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    if (claw.length === 0 && !claw.extended) return;
    
    ctx.save();
    // 从飞船中心开始绘制
    ctx.translate(ship.x, ship.y);
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

// 绘制石头障碍物
function drawRocks() {
    gameState.rocks.forEach(rock => {
        ctx.save();
        
        // 石头外观（灰色石头）
        ctx.fillStyle = '#666';
        ctx.shadowBlur = 5;
        ctx.shadowColor = '#333';
        ctx.beginPath();
        ctx.arc(rock.x, rock.y, rock.size, 0, Math.PI * 2);
        ctx.fill();
        
        // 石头纹理
        ctx.fillStyle = '#555';
        ctx.beginPath();
        ctx.arc(rock.x - rock.size * 0.3, rock.y - rock.size * 0.3, rock.size * 0.4, 0, Math.PI * 2);
        ctx.fill();
        
        ctx.beginPath();
        ctx.arc(rock.x + rock.size * 0.3, rock.y + rock.size * 0.3, rock.size * 0.3, 0, Math.PI * 2);
        ctx.fill();
        
        ctx.restore();
    });
}

// 绘制碎片
function drawFragments() {
    gameState.fragments.forEach(fragment => {
        ctx.save();
        
        // 碎片光晕
        if (fragment === gameState.claw.grabbedItem) {
            ctx.shadowBlur = 20;
            ctx.shadowColor = fragment.type === 'bad' ? '#FF0000' : '#64C8FF';
        } else {
            ctx.shadowBlur = 10;
            ctx.shadowColor = fragment.color;
        }
        
        // 如果是负分碎片，添加红色警告边框
        if (fragment.type === 'bad') {
            const warningAlpha = Math.sin(Date.now() * 0.008) * 0.1 + 0.2;
            ctx.strokeStyle = `rgba(255, 100, 100, ${warningAlpha})`;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(fragment.x, fragment.y, fragment.size * 1.2, 0, Math.PI * 2);
            ctx.stroke();
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

// 检查机械手是否碰到石头（阻挡）
function checkRockCollision(rock) {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    if (!claw.extended || claw.length === 0) return false;
    
    // 计算爪子当前位置（从飞船中心出发）
    const clawX = ship.x + Math.sin(claw.angle) * claw.length;
    const clawY = ship.y + Math.cos(claw.angle) * claw.length;
    
    // 检查爪子路径是否穿过石头（简化：检查爪子位置是否在石头内）
    const dx = clawX - rock.x;
    const dy = clawY - rock.y;
    const distance = Math.sqrt(dx * dx + dy * dy);
    
    return distance < rock.size + 8; // 8是爪子半径
}

// 检查碰撞（碎片）
function checkCollision(fragment) {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    if (!claw.extended || claw.length === 0) return false;
    
    // 计算爪子位置（从飞船中心出发）
    const clawX = ship.x + Math.sin(claw.angle) * claw.length;
    const clawY = ship.y + Math.cos(claw.angle) * claw.length;
    
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
    
    // 360度连续旋转（只在机械手未伸出时旋转）
    if (!claw.extended) {
        // 持续旋转
        ship.angle += ship.rotationSpeed * deltaTime;
        // 保持角度在0到2π之间
        if (ship.angle >= Math.PI * 2) {
            ship.angle -= Math.PI * 2;
        }
        if (ship.angle < 0) {
            ship.angle += Math.PI * 2;
        }
    }
    
    // 同步爪子角度
    claw.angle = ship.angle;
    
    // 更新机械手
    if (claw.extended) {
        // 检查是否在伸出阶段
        if (claw.length < claw.maxLength) {
            // 伸出阶段：增加长度（基于时间）
            claw.length += claw.extendSpeed * deltaTime;
            // 限制最大长度
            if (claw.length >= claw.maxLength) {
                claw.length = claw.maxLength;
            }
            
            // 检查碰撞（在伸出过程中持续检查，只要还没抓到就检查）
            if (!claw.grabbing) {
                // 检查是否碰到石头（如果碰到石头，停止伸出并开始收回）
                for (let rock of gameState.rocks) {
                    if (checkRockCollision(rock)) {
                        // 碰到石头，立即停止伸出并开始收回
                        claw.length = claw.maxLength;
                        break;
                    }
                }
                
                // 检查是否抓到碎片（如果还没碰到石头）
                if (!claw.grabbing) {
                    for (let fragment of gameState.fragments) {
                        if (checkCollision(fragment)) {
                            claw.grabbing = true;
                            claw.grabbedItem = fragment;
                            break;
                        }
                    }
                }
            }
        } else {
            // 已经到达或超过最大长度，开始收回阶段（无论是否抓到都要收回）
            if (claw.grabbing && claw.grabbedItem) {
                // 收回（带碎片，速度受重量影响，重量越大收回越慢）
                // 基础速度 / (1 + 重量*0.2)，这样重量越大速度越慢
                const weightFactor = 1 + (claw.grabbedItem.weight * 0.2);
                const retractSpeed = (claw.retractSpeed / weightFactor) * deltaTime;
                claw.length -= retractSpeed;
                
                // 更新碎片位置（从飞船中心计算）
                const clawX = ship.x + Math.sin(claw.angle) * claw.length;
                const clawY = ship.y + Math.cos(claw.angle) * claw.length;
                claw.grabbedItem.x = clawX;
                claw.grabbedItem.y = clawY;
            } else {
                // 收回（空手）- 最快速度，使用基础速度的1.5倍
                claw.length -= (claw.retractSpeed * 1.5) * deltaTime;
            }
            
            // 确保长度不会小于0
            if (claw.length < 0) {
                claw.length = 0;
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
    
    // 不再随机生成新碎片（每局固定20个）
    // 石头也不会再生成（固定数量）
    
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
    drawRocks(); // 先绘制石头（在碎片下方）
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
