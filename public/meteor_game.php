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
    <title>接流星小游戏 - <?php echo $SITENAME; ?></title>
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
    background: linear-gradient(180deg, #0a0e27 0%, #1a1f3a 50%, #2a2f4a 100%);
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
    border: 2px solid rgba(138, 43, 226, 0.5);
    border-radius: 10px;
    box-shadow: 0 0 30px rgba(138, 43, 226, 0.3);
    background: rgba(0, 0, 0, 0.3);
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
    text-shadow: 0 0 10px rgba(138, 43, 226, 0.8);
    z-index: 10;
}

.game-info > div {
    background: rgba(0, 0, 0, 0.6);
    padding: 10px 20px;
    border-radius: 10px;
    border: 2px solid rgba(138, 43, 226, 0.5);
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
    border: 2px solid rgba(138, 43, 226, 0.5);
    border-radius: 12px;
    color: #fff;
    font-size: 16px;
    box-shadow: 0 0 15px rgba(138, 43, 226, 0.3);
    flex-wrap: wrap;
}

.control-panel label {
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 8px;
}

.control-panel input[type="range"] {
    flex: 1;
    accent-color: #8a2be2;
}

.control-panel span {
    min-width: 72px;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.game-start-screen {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.95);
    padding: 40px 30px;
    border-radius: 20px;
    border: 3px solid rgba(138, 43, 226, 0.8);
    color: #fff;
    text-align: center;
    z-index: 100;
    min-width: 450px;
    max-width: 90vw;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 0 50px rgba(138, 43, 226, 0.5);
}

.game-start-screen h2 {
    font-size: 42px;
    margin-bottom: 20px;
    color: #8a2be2;
    text-shadow: 0 0 20px rgba(138, 43, 226, 0.8);
}

.game-start-screen .game-desc {
    font-size: 16px;
    color: #e5e7eb;
    margin-bottom: 30px;
    line-height: 1.8;
}

.game-start-screen .game-rules {
    background: rgba(138, 43, 226, 0.2);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    text-align: left;
}

.game-start-screen .game-rules h3 {
    color: #ffd700;
    font-size: 18px;
    margin-bottom: 15px;
}

.game-start-screen .game-rules ul {
    list-style: none;
    padding: 0;
}

.game-start-screen .game-rules li {
    padding: 8px 0;
    color: #e5e7eb;
}

.game-start-screen .game-rules li::before {
    content: '⭐ ';
    margin-right: 8px;
}

.game-start-screen button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 18px 50px;
    font-size: 22px;
    font-weight: bold;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
}

.game-start-screen button:hover {
    transform: scale(1.05);
    box-shadow: 0 0 30px rgba(138, 43, 226, 0.8);
}

.game-over-screen {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.95);
    padding: 40px;
    border-radius: 20px;
    border: 3px solid rgba(138, 43, 226, 0.8);
    color: #fff;
    text-align: center;
    display: none;
    z-index: 100;
    min-width: 400px;
    box-shadow: 0 0 50px rgba(138, 43, 226, 0.5);
}

.game-over-screen h2 {
    font-size: 36px;
    margin-bottom: 20px;
    color: #8a2be2;
    text-shadow: 0 0 20px rgba(138, 43, 226, 0.8);
}

.game-over-screen .final-score {
    font-size: 48px;
    color: #ffd700;
    margin: 20px 0;
    text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
}

.game-over-screen button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px 40px;
    font-size: 20px;
    border-radius: 10px;
    cursor: pointer;
    margin: 10px;
    transition: all 0.3s;
}

.game-over-screen button:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(138, 43, 226, 0.8);
}

/* 右侧排行榜区 */
.leaderboard {
    width: 380px;
    height: 100vh;
    padding: 30px 20px;
    background: rgba(0, 0, 0, 0.9);
    border-left: 3px solid rgba(138, 43, 226, 0.8);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.leaderboard h3 {
    color: #8a2be2;
    text-align: center;
    font-size: 32px;
    margin-bottom: 25px;
    text-shadow: 0 0 20px rgba(138, 43, 226, 1);
}

.leaderboard-tabs {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 25px;
    flex-shrink: 0;
}

.leaderboard-tabs button {
    background: rgba(138, 43, 226, 0.3);
    color: white;
    border: 2px solid rgba(138, 43, 226, 0.5);
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.leaderboard-tabs button.active {
    background: rgba(138, 43, 226, 0.8);
    border-color: #8a2be2;
    box-shadow: 0 0 15px rgba(138, 43, 226, 0.6);
}

.leaderboard-table {
    width: 100%;
    color: #fff;
    border-collapse: collapse;
    flex: 1;
}

.leaderboard-table th {
    background: rgba(138, 43, 226, 0.5);
    padding: 12px 10px;
    text-align: left;
    border-bottom: 2px solid rgba(138, 43, 226, 0.8);
    font-size: 14px;
    position: sticky;
    top: 0;
    z-index: 10;
}

.leaderboard-table td {
    padding: 10px;
    border-bottom: 1px solid rgba(138, 43, 226, 0.3);
    font-size: 13px;
}

.leaderboard-table tr:hover {
    background: rgba(138, 43, 226, 0.2);
}

.rank-medal {
    font-size: 18px;
    font-weight: bold;
}

.rank-1 { color: #ffd700; }
.rank-2 { color: #c0c0c0; }
.rank-3 { color: #cd7f32; }

/* 响应式布局：小屏幕改为上下布局 */
@media (max-width: 1200px) {
    body {
        flex-direction: column;
    }
    
    .game-container {
        height: 65vh;
        width: 100vw;
    }
    
    .leaderboard {
        width: 100vw;
        height: 35vh;
        border-left: none;
        border-top: 3px solid rgba(138, 43, 226, 0.8);
    }
    
    .game-info {
        font-size: 18px;
    }

    .control-panel {
        position: static;
        width: 100%;
        margin-top: 16px;
    }
}

@media (max-width: 768px) {
    .game-info {
        font-size: 14px;
        top: 10px;
        left: 10px;
        right: 10px;
    }
    
    .game-info > div {
        padding: 6px 10px;
        font-size: 12px;
    }
    
    .leaderboard h3 {
        font-size: 24px;
        margin-bottom: 15px;
    }
    
    .leaderboard-table th,
    .leaderboard-table td {
        padding: 8px 5px;
        font-size: 12px;
    }
    
    .game-start-screen {
        min-width: 85vw;
        max-width: 95vw;
        max-height: 90vh;
        padding: 20px 15px;
    }
    
    .game-start-screen h2 {
        font-size: 28px;
        margin-bottom: 15px;
    }
    
    .game-start-screen .game-desc {
        font-size: 13px;
        margin-bottom: 15px;
    }
    
    .game-start-screen .game-desc > div {
        padding: 12px !important;
    }
    
    .game-start-screen .game-rules {
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .game-start-screen .game-rules h3 {
        font-size: 15px;
        margin-bottom: 10px;
    }
    
    .game-start-screen .game-rules li {
        font-size: 12px;
        padding: 4px 0;
        line-height: 1.5;
    }
    
    .game-start-screen button {
        padding: 12px 30px;
        font-size: 16px;
    }
    
    #submitLimitInfo {
        font-size: 12px !important;
        padding: 10px !important;
    }
    
    .game-over-screen {
        min-width: 90%;
        padding: 20px;
    }
    
    .leaderboard h3 {
        font-size: 20px;
        margin-bottom: 10px;
    }
    
    .leaderboard-tabs button {
        padding: 8px 20px;
        font-size: 14px;
    }

    .control-panel {
        gap: 8px;
        padding: 10px 12px;
        font-size: 14px;
    }
    
    .control-panel span {
        min-width: 0;
    }
}
</style>
</head>
<body>

<div class="game-container">
    <div class="game-info" style="display: none;">
        <div>得分: <span id="scoreDisplay">0</span></div>
        <div>连击: <span id="comboDisplay">0</span></div>
        <div>时间: <span id="timerDisplay">60</span>秒</div>
        <div>操作: <span id="inputCounter">0</span></div>
    </div>
    <canvas id="gameCanvas" width="800" height="600"></canvas>
    <div class="control-panel">
        <label for="touchSensitivity">触控/键盘灵敏度</label>
        <input type="range" id="touchSensitivity" min="0" max="100" value="50" step="1">
        <span id="touchSensitivityValue">中等</span>
    </div>
    
    <!-- 开始游戏界面 -->
    <div class="game-start-screen" id="gameStartScreen">
        <h2>🌠 星际救援计划 🌠</h2>
        <div class="game-desc" style="margin-bottom: 25px;">
            <div style="background: rgba(138, 43, 226, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 3px solid #8a2be2;">
                <p style="margin: 0 0 10px 0; color: #ffd700; font-weight: bold;">📡 紧急任务简报</p>
                <p style="margin: 0; line-height: 1.8; font-size: 14px;">
                    公元2525年，一场神秘的时空乱流席卷太阳系，导致各大行星脱离轨道，向地球坠落！
                    更糟糕的是，时空裂缝中涌出了大量宇宙怪物！作为星际救援队的成员，你需要驾驶重力捕获器，
                    在60秒内尽可能多地捕获失控的行星，同时避开危险的宇宙怪物。记住：捕获行星得分，捕获怪物扣分！
                </p>
            </div>
            <div id="submitLimitInfo" style="background: rgba(255, 215, 0, 0.15); padding: 12px; border-radius: 8px; border: 1px solid rgba(255, 215, 0, 0.3); text-align: center; font-size: 14px;">
                <span style="color: #ffd700;">⏳ 正在加载今日提交信息...</span>
            </div>
        </div>
        <div class="game-rules">
            <h3>🎮 操作指南</h3>
            <ul>
                <li>⏱️ 任务时限：60秒</li>
                <li>🎯 控制方式：键盘 ← → 或 A D 键移动重力捕获器</li>
                <li>✅ 行星加分：🌙月球(+10) 🌍地球(+15) 🔴火星(+20) 🪐木星(+30) 🪐土星(+35) ☀️太阳(+50)</li>
                <li>❌ 怪物扣分：👾外星虫(-15) ☄️陨石怪(-25) 🕳️黑洞(-50)</li>
                <li>⚡ 连击奖励：连续捕获行星获得额外积分(+10%)</li>
                <li>⚠️ 注意：漏掉行星或捕获怪物都会中断连击</li>
            </ul>
        </div>
        <div style="background: rgba(78, 205, 196, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 3px solid #4ECDC4;">
            <h3 style="color: #4ECDC4; font-size: 16px; margin-bottom: 10px;">⭐ 星尘奖励与用途</h3>
            <ul style="list-style: none; padding: 0; font-size: 14px; line-height: 1.8; color: #e5e7eb;">
                <li style="padding: 5px 0;">💎 <strong>获得星尘</strong>：每得100分 = 10星尘（正分才有奖励）</li>
                <li style="padding: 5px 0;">🌱 <strong>种植天体</strong>：星尘可用于购买行星种子，在星尘农场中种植</li>
                <li style="padding: 5px 0;">🌍 <strong>重建太阳系</strong>：收集9个碎片合成完整行星，集齐9大天体重建太阳系！</li>
                <li style="padding: 5px 0;">🎯 <strong>游戏入口</strong>：右上角绿色按钮 → <a href="stardust_farm.php" style="color: #4ECDC4; text-decoration: underline;">星尘农场</a></li>
            </ul>
        </div>
        <button onclick="startGame()">🚀 开始救援任务</button>
    </div>
    
    <div class="game-over-screen" id="gameOverScreen">
        <h2>游戏结束！</h2>
        <div class="final-score" id="finalScore">0</div>
        <div>最高连击: <span id="finalCombo">0</span></div>
        <div id="stardustReward" style="margin: 15px 0; padding: 12px; background: rgba(78, 205, 196, 0.2); border-radius: 8px; border: 1px solid #4ECDC4;">
            <span style="color: #4ECDC4; font-size: 18px; font-weight: bold;">⭐ 获得星尘: <span id="stardustAmount">0</span></span>
            <div style="font-size: 12px; color: #aaa; margin-top: 5px;">可在<a href="stardust_farm.php" style="color: #4ECDC4;">星尘农场</a>中种植行星种子</div>
        </div>
        <div id="submitStatus" style="margin: 20px 0; color: #ffd700;"></div>
        <button onclick="restartGame()">再玩一次</button>
        <button onclick="location.href='stardust_farm.php'" style="background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);">🌍 进入星尘农场</button>
        <button onclick="location.href='index.php'">返回首页</button>
    </div>
</div>

<div class="leaderboard">
    <h3>🏆 排行榜 🏆</h3>
    <div class="leaderboard-tabs">
        <button class="active" onclick="loadLeaderboard('today', this)">今日</button>
        <button onclick="loadLeaderboard('7days', this)">近7天</button>
        <button onclick="loadLeaderboard('alltime', this)">总榜</button>
    </div>
    <table class="leaderboard-table">
        <thead>
            <tr>
                <th>排名</th>
                <th>玩家</th>
                <th>分数</th>
                <th>最高连击</th>
                <th>时间</th>
            </tr>
        </thead>
        <tbody id="leaderboardBody">
            <tr><td colspan="5" style="text-align:center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<script>
// MD5 函数（用于Token生成）
function md5(string) {
    function md5_RotateLeft(lValue, iShiftBits) {
        return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
    }
    function md5_AddUnsigned(lX, lY) {
        var lX4, lY4, lX8, lY8, lResult;
        lX8 = (lX & 0x80000000);
        lY8 = (lY & 0x80000000);
        lX4 = (lX & 0x40000000);
        lY4 = (lY & 0x40000000);
        lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
        if (lX4 & lY4) return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
        if (lX4 | lY4) {
            if (lResult & 0x40000000) return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
            else return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
        } else {
            return (lResult ^ lX8 ^ lY8);
        }
    }
    function md5_F(x, y, z) { return (x & y) | ((~x) & z); }
    function md5_G(x, y, z) { return (x & z) | (y & (~z)); }
    function md5_H(x, y, z) { return (x ^ y ^ z); }
    function md5_I(x, y, z) { return (y ^ (x | (~z))); }
    function md5_FF(a, b, c, d, x, s, ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_F(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_GG(a, b, c, d, x, s, ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_G(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_HH(a, b, c, d, x, s, ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_H(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_II(a, b, c, d, x, s, ac) {
        a = md5_AddUnsigned(a, md5_AddUnsigned(md5_AddUnsigned(md5_I(b, c, d), x), ac));
        return md5_AddUnsigned(md5_RotateLeft(a, s), b);
    }
    function md5_ConvertToWordArray(string) {
        var lWordCount, lMessageLength = string.length, lNumberOfWords_temp1 = lMessageLength + 8,
            lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64,
            lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16, lWordArray = Array(lNumberOfWords - 1),
            lBytePosition = 0, lByteCount = 0;
        while (lByteCount < lMessageLength) {
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount) << lBytePosition));
            lByteCount++;
        }
        lWordCount = (lByteCount - (lByteCount % 4)) / 4;
        lBytePosition = (lByteCount % 4) * 8;
        lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
        lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
        lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
        return lWordArray;
    }
    function md5_WordToHex(lValue) {
        var WordToHexValue = "", WordToHexValue_temp = "", lByte, lCount;
        for (lCount = 0; lCount <= 3; lCount++) {
            lByte = (lValue >>> (lCount * 8)) & 255;
            WordToHexValue_temp = "0" + lByte.toString(16);
            WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length - 2, 2);
        }
        return WordToHexValue;
    }
    var x = Array(), k, AA, BB, CC, DD, a, b, c, d, S11 = 7, S12 = 12, S13 = 17, S14 = 22, S21 = 5, S22 = 9, S23 = 14, S24 = 20,
        S31 = 4, S32 = 11, S33 = 16, S34 = 23, S41 = 6, S42 = 10, S43 = 15, S44 = 21;
    x = md5_ConvertToWordArray(string);
    a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
    for (k = 0; k < x.length; k += 16) {
        AA = a; BB = b; CC = c; DD = d;
        a = md5_FF(a, b, c, d, x[k + 0], S11, 0xD76AA478); d = md5_FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
        c = md5_FF(c, d, a, b, x[k + 2], S13, 0x242070DB); b = md5_FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
        a = md5_FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF); d = md5_FF(d, a, b, c, x[k + 5], S12, 0x4787C62A);
        c = md5_FF(c, d, a, b, x[k + 6], S13, 0xA8304613); b = md5_FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
        a = md5_FF(a, b, c, d, x[k + 8], S11, 0x698098D8); d = md5_FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
        c = md5_FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1); b = md5_FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
        a = md5_FF(a, b, c, d, x[k + 12], S11, 0x6B901122); d = md5_FF(d, a, b, c, x[k + 13], S12, 0xFD987193);
        c = md5_FF(c, d, a, b, x[k + 14], S13, 0xA679438E); b = md5_FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
        a = md5_GG(a, b, c, d, x[k + 1], S21, 0xF61E2562); d = md5_GG(d, a, b, c, x[k + 6], S22, 0xC040B340);
        c = md5_GG(c, d, a, b, x[k + 11], S23, 0x265E5A51); b = md5_GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
        a = md5_GG(a, b, c, d, x[k + 5], S21, 0xD62F105D); d = md5_GG(d, a, b, c, x[k + 10], S22, 0x2441453);
        c = md5_GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681); b = md5_GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
        a = md5_GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6); d = md5_GG(d, a, b, c, x[k + 14], S22, 0xC33707D6);
        c = md5_GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87); b = md5_GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
        a = md5_GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905); d = md5_GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8);
        c = md5_GG(c, d, a, b, x[k + 7], S23, 0x676F02D9); b = md5_GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
        a = md5_HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942); d = md5_HH(d, a, b, c, x[k + 8], S32, 0x8771F681);
        c = md5_HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122); b = md5_HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
        a = md5_HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44); d = md5_HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9);
        c = md5_HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60); b = md5_HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
        a = md5_HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6); d = md5_HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA);
        c = md5_HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085); b = md5_HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
        a = md5_HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039); d = md5_HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5);
        c = md5_HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8); b = md5_HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
        a = md5_II(a, b, c, d, x[k + 0], S41, 0xF4292244); d = md5_II(d, a, b, c, x[k + 7], S42, 0x432AFF97);
        c = md5_II(c, d, a, b, x[k + 14], S43, 0xAB9423A7); b = md5_II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
        a = md5_II(a, b, c, d, x[k + 12], S41, 0x655B59C3); d = md5_II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92);
        c = md5_II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D); b = md5_II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
        a = md5_II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F); d = md5_II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0);
        c = md5_II(c, d, a, b, x[k + 6], S43, 0xA3014314); b = md5_II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
        a = md5_II(a, b, c, d, x[k + 4], S41, 0xF7537E82); d = md5_II(d, a, b, c, x[k + 11], S42, 0xBD3AF235);
        c = md5_II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB); b = md5_II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
        a = md5_AddUnsigned(a, AA); b = md5_AddUnsigned(b, BB); c = md5_AddUnsigned(c, CC); d = md5_AddUnsigned(d, DD);
    }
    return (md5_WordToHex(a) + md5_WordToHex(b) + md5_WordToHex(c) + md5_WordToHex(d)).toLowerCase();
}

const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

const TOUCH_SENSITIVITY_MIN = 0.1;
const TOUCH_SENSITIVITY_MAX = 1.5;
const TOUCH_SLIDER_MIN = 0;
const TOUCH_SLIDER_MAX = 100;
const TOUCH_SENSITIVITY_STORAGE_KEY = 'meteor_game_touch_sensitivity';
const DEFAULT_TOUCH_SENSITIVITY = 0.5;

let touchSensitivity = DEFAULT_TOUCH_SENSITIVITY;
const sensitivitySlider = document.getElementById('touchSensitivity');
const sensitivityValueEl = document.getElementById('touchSensitivityValue');

const TELEMETRY_MAX_EVENTS = 400;
const TELEMETRY_MAX_INPUTS = 600;
const FLAG_REASON_LABELS = {
    high_score_low_combo: '高分但连击偏低',
    input_count_too_low: '操作次数过少',
    event_count_too_low: '捕获事件过少',
    bad_hit_ratio_high: '负面捕获占比过高',
};

const telemetry = {
    version: 2,
    startedAt: null,
    endedAt: null,
    events: [],
    inputs: [],
    summary: {
        goodCatch: 0,
        badCatch: 0,
        miss: 0,
    },
};

function resetTelemetry() {
    telemetry.startedAt = null;
    telemetry.endedAt = null;
    telemetry.events = [];
    telemetry.inputs = [];
    telemetry.summary = {
        goodCatch: 0,
        badCatch: 0,
        miss: 0,
    };
}

function pushTelemetryEvent(payload) {
    if (telemetry.startedAt === null) {
        return;
    }
    const event = {
        ...payload,
        t: payload.t !== undefined ? payload.t : (Date.now() - telemetry.startedAt),
    };
    if (telemetry.events.length >= TELEMETRY_MAX_EVENTS) {
        telemetry.events.shift();
    }
    telemetry.events.push(event);
}

function recordInputEvent(action, detail = {}) {
    if (!gameState.gameStarted || telemetry.startedAt === null) {
        return;
    }
    const inputRecord = {
        type: action,
        key: detail.key ?? null,
        delta: detail.delta ?? null,
        t: Date.now() - telemetry.startedAt,
    };
    if (telemetry.inputs.length >= TELEMETRY_MAX_INPUTS) {
        telemetry.inputs.shift();
    }
    telemetry.inputs.push(inputRecord);
}

resetTelemetry();

// 游戏状态
let gameState = {
    score: 0,
    combo: 0,
    maxCombo: 0,
    timeLeft: 60,
    gameOver: false,
    gameStarted: false,
    meteors: [],
    player: {
        x: canvas.width / 2,
        y: canvas.height - 60,
        width: 120,
        height: 30,
        speed: 8,
        baseSpeed: 8
    },
    keys: {},
    lastMeteorTime: 0,
    meteorInterval: 800
};

// 游戏循环和定时器变量
let animationId;
let timerInterval;
let lastTouchInputTs = 0;

// 太阳系行星类型（正分）
const METEOR_TYPES = [
    { name: '月球', emoji: '🌙', score: 10, size: 20, speed: 3, chance: 0.30, type: 'good' },  // 月球 - 最常见
    { name: '地球', emoji: '🌍', score: 15, size: 22, speed: 3.5, chance: 0.23, type: 'good' },  // 地球
    { name: '火星', emoji: '🔴', score: 20, size: 18, speed: 4, chance: 0.18, type: 'good' },  // 火星 - 快速
    { name: '木星', emoji: '🪐', score: 30, size: 28, speed: 2.5, chance: 0.10, type: 'good' }, // 木星 - 大号
    { name: '土星', emoji: '🪐', score: 35, size: 26, speed: 2.8, chance: 0.04, type: 'good' }, // 土星 - 稀有
    { name: '太阳', emoji: '☀️', score: 50, size: 30, speed: 2, chance: 0.02, type: 'good' },  // 太阳 - 超稀有
    // 宇宙怪物（负分）
    { name: '外星虫', emoji: '👾', score: -15, size: 22, speed: 3.8, chance: 0.08, type: 'bad' }, // 普通怪物
    { name: '陨石怪', emoji: '☄️', score: -25, size: 24, speed: 4.2, chance: 0.04, type: 'bad' }, // 快速怪物
    { name: '黑洞', emoji: '🕳️', score: -50, size: 28, speed: 2.5, chance: 0.01, type: 'bad' }  // 超级危险
];

// 创建流星
function createMeteor() {
    const rand = Math.random();
    let cumulativeChance = 0;
    let meteorType = METEOR_TYPES[0];
    
    for (let type of METEOR_TYPES) {
        cumulativeChance += type.chance;
        if (rand < cumulativeChance) {
            meteorType = type;
            break;
        }
    }
    
    return {
        x: Math.random() * (canvas.width - meteorType.size * 2) + meteorType.size,
        y: -meteorType.size,
        ...meteorType,
        caught: false
    };
}

// 绘制玩家飞船
function drawPlayer() {
    const p = gameState.player;
    ctx.save();
    
    // 飞船主体渐变色
    const gradient = ctx.createLinearGradient(p.x - p.width/2, p.y, p.x + p.width/2, p.y + p.height);
    gradient.addColorStop(0, '#667eea');
    gradient.addColorStop(1, '#764ba2');
    
    ctx.shadowBlur = 20;
    ctx.shadowColor = '#00d4ff';
    
    // 绘制飞船主体（三角形 + 两翼）
    ctx.fillStyle = gradient;
    ctx.beginPath();
    
    // 中央驾驶舱（三角形）
    ctx.moveTo(p.x, p.y); // 顶部中心
    ctx.lineTo(p.x - 25, p.y + p.height); // 左下
    ctx.lineTo(p.x + 25, p.y + p.height); // 右下
    ctx.closePath();
    ctx.fill();
    
    // 左翼
    ctx.beginPath();
    ctx.moveTo(p.x - 25, p.y + 10);
    ctx.lineTo(p.x - p.width/2, p.y + p.height - 5);
    ctx.lineTo(p.x - 25, p.y + p.height);
    ctx.closePath();
    ctx.fillStyle = '#8a2be2';
    ctx.fill();
    
    // 右翼
    ctx.beginPath();
    ctx.moveTo(p.x + 25, p.y + 10);
    ctx.lineTo(p.x + p.width/2, p.y + p.height - 5);
    ctx.lineTo(p.x + 25, p.y + p.height);
    ctx.closePath();
    ctx.fill();
    
    // 绘制飞船边框
    ctx.strokeStyle = '#00d4ff';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    ctx.lineTo(p.x - 25, p.y + p.height);
    ctx.lineTo(p.x - p.width/2, p.y + p.height - 5);
    ctx.moveTo(p.x - 25, p.y + p.height);
    ctx.lineTo(p.x + 25, p.y + p.height);
    ctx.moveTo(p.x + 25, p.y + p.height);
    ctx.lineTo(p.x + p.width/2, p.y + p.height - 5);
    ctx.lineTo(p.x + 25, p.y + 10);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    
    // 绘制驾驶舱窗口（移除发光效果）
    ctx.fillStyle = '#00d4ff';
    ctx.shadowBlur = 0;
    ctx.beginPath();
    ctx.arc(p.x, p.y + 15, 4, 0, Math.PI * 2);
    ctx.fill();
    
    // 绘制推进器火焰（简化版，用计数器代替sin计算）
    if (!gameState.flameCounter) gameState.flameCounter = 0;
    const flameIntensity = (gameState.flameCounter++ % 20 < 10) ? 0.8 : 0.6;
    
    ctx.shadowBlur = 0;
    ctx.fillStyle = `rgba(255, 107, 53, ${flameIntensity})`;
    
    // 左推进器（简化为矩形）
    ctx.fillRect(p.x - p.width/2 + 8, p.y + p.height - 2, 6, 8);
    
    // 右推进器（简化为矩形）
    ctx.fillRect(p.x + p.width/2 - 14, p.y + p.height - 2, 6, 8);
    
    ctx.restore();
}

// 绘制行星/怪物
function drawMeteor(meteor) {
    if (meteor.caught) return;
    
    ctx.save();
    
    // 如果是怪物，添加微弱的红色提示（无光晕）
    if (meteor.type === 'bad') {
        const warningAlpha = Math.sin(Date.now() * 0.008) * 0.1 + 0.2;
        ctx.strokeStyle = `rgba(255, 100, 100, ${warningAlpha})`;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.arc(meteor.x, meteor.y, meteor.size * 1.2, 0, Math.PI * 2);
        ctx.stroke();
    }
    
    // 绘制 emoji（移除光晕效果，更清晰）
    ctx.font = meteor.size * 2 + 'px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.shadowBlur = 0; // 完全移除光晕
    
    ctx.fillText(meteor.emoji, meteor.x, meteor.y);
    
    ctx.restore();
}

// 碰撞检测
function checkCollision(meteor) {
    const p = gameState.player;
    return meteor.y + meteor.size >= p.y &&
           meteor.y + meteor.size <= p.y + p.height &&
           meteor.x >= p.x - p.width/2 &&
           meteor.x <= p.x + p.width/2;
}

// 更新游戏
function updateGame(currentTime) {
    if (gameState.gameOver) return;
    
    // 生成新流星
    if (currentTime - gameState.lastMeteorTime > gameState.meteorInterval) {
        gameState.meteors.push(createMeteor());
        gameState.lastMeteorTime = currentTime;
        // 随着时间推移，流星生成速度加快
        gameState.meteorInterval = Math.max(400, 800 - (60 - gameState.timeLeft) * 10);
    }
    
    // 更新流星位置
    gameState.meteors.forEach((meteor, index) => {
        if (!meteor.caught) {
            meteor.y += meteor.speed;
            
            // 碰撞检测
            if (checkCollision(meteor)) {
                meteor.caught = true;

                const comboBefore = gameState.combo;
                let deltaScore = 0;

                if (meteor.type === 'bad') {
                    deltaScore = Number(meteor.score.toFixed(2));
                    gameState.score = Number((gameState.score + deltaScore).toFixed(2));
                    gameState.combo = 0;
                    telemetry.summary.badCatch++;
                    pushTelemetryEvent({
                        type: 'catch_bad',
                        meteor_name: meteor.name,
                        meteor_type: meteor.type,
                        base_score: meteor.score,
                        delta_score: deltaScore,
                        combo_before: comboBefore,
                        combo_after: gameState.combo,
                    });

                    // 强烈的屏幕震动效果
                    let shakeCount = 0;
                    const shakeInterval = setInterval(() => {
                        if (shakeCount < 8) {
                            const intensity = 15 - shakeCount * 1.5; // 震动逐渐减弱
                            canvas.style.transform = 'translate(' + 
                                (Math.random() * intensity - intensity/2) + 'px, ' + 
                                (Math.random() * intensity - intensity/2) + 'px) rotate(' + 
                                (Math.random() * 4 - 2) + 'deg)';
                            shakeCount++;
                        } else {
                            clearInterval(shakeInterval);
                            canvas.style.transform = 'translate(0, 0) rotate(0deg)';
                        }
                    }, 50);
                } else {
                    const comboMultiplier = 1 + comboBefore * 0.1;
                    deltaScore = Number((meteor.score * comboMultiplier).toFixed(2));
                    gameState.score = Number((gameState.score + deltaScore).toFixed(2));
                    gameState.combo++;
                    gameState.maxCombo = Math.max(gameState.maxCombo, gameState.combo);
                    telemetry.summary.goodCatch++;
                    pushTelemetryEvent({
                        type: 'catch_good',
                        meteor_name: meteor.name,
                        meteor_type: meteor.type,
                        base_score: meteor.score,
                        delta_score: deltaScore,
                        combo_before: comboBefore,
                        combo_after: gameState.combo,
                    });
                }
                
                // 移除已捕获的对象
                gameState.meteors.splice(index, 1);
            } else if (meteor.y > canvas.height) {
                // 掉落 - 只有行星掉落才清零连击，怪物掉落没影响
                if (meteor.type === 'good') {
                    const comboBefore = gameState.combo;
                    gameState.combo = 0;
                    telemetry.summary.miss++;
                    pushTelemetryEvent({
                        type: 'miss_good',
                        meteor_name: meteor.name,
                        meteor_type: meteor.type,
                        base_score: meteor.score,
                        delta_score: 0,
                        combo_before: comboBefore,
                        combo_after: gameState.combo,
                    });
                }
                gameState.meteors.splice(index, 1);
            }
        }
    });
    
    // 更新玩家位置
    const keyboardSpeed = gameState.player.baseSpeed * (touchSensitivity / DEFAULT_TOUCH_SENSITIVITY);
    if (gameState.keys['ArrowLeft'] || gameState.keys['a'] || gameState.keys['A']) {
        gameState.player.x = Math.max(gameState.player.width/2, gameState.player.x - keyboardSpeed);
    }
    if (gameState.keys['ArrowRight'] || gameState.keys['d'] || gameState.keys['D']) {
        gameState.player.x = Math.min(canvas.width - gameState.player.width/2, gameState.player.x + keyboardSpeed);
    }
}

// 渲染游戏
function renderGame() {
    // 清空画布（使用纯色填充，不用半透明拖尾效果）
    ctx.fillStyle = '#0a0e27';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // 绘制静态星空背景（减少到30个星星，降低计算量）
    if (!gameState.stars) {
        // 初始化时生成固定星星位置
        gameState.stars = [];
        for (let i = 0; i < 30; i++) {
            gameState.stars.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                alpha: Math.random() * 0.5 + 0.3
            });
        }
    }
    
    // 绘制静态星星（不再动态计算位置）
    ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
    gameState.stars.forEach(star => {
        ctx.fillRect(star.x, star.y, 1.5, 1.5);
    });
    
    // 绘制所有流星
    gameState.meteors.forEach(meteor => drawMeteor(meteor));
    
    // 绘制玩家
    drawPlayer();
    
    // 更新UI（降低更新频率）
    if (!gameState.uiUpdateCounter) gameState.uiUpdateCounter = 0;
    if (gameState.uiUpdateCounter++ % 3 === 0) {
        document.getElementById('scoreDisplay').textContent = Math.floor(gameState.score);
        document.getElementById('comboDisplay').textContent = gameState.combo;
        document.getElementById('inputCounter').textContent = telemetry.inputs.length;
    }
}

// 开始游戏
function startGame() {
    if (animationId) {
        cancelAnimationFrame(animationId);
    }
    if (timerInterval) {
        clearInterval(timerInterval);
    }
    lastTouchInputTs = 0;
    resetTelemetry();
    telemetry.startedAt = Date.now();
    telemetry.version = 2;
    
    // 隐藏开始界面
    document.getElementById('gameStartScreen').style.display = 'none';
    
    // 显示游戏信息
    document.querySelector('.game-info').style.display = 'flex';
    
    // 重置游戏状态
    gameState.score = 0;
    gameState.combo = 0;
    gameState.maxCombo = 0;
    gameState.timeLeft = 60;
    gameState.gameOver = false;
    gameState.gameStarted = true;
    gameState.meteors = [];
    gameState.lastMeteorTime = 0;
    gameState.player.x = canvas.width / 2;
    gameState.player.speed = gameState.player.baseSpeed;
    
    document.getElementById('scoreDisplay').textContent = '0';
    document.getElementById('comboDisplay').textContent = '0';
    document.getElementById('timerDisplay').textContent = gameState.timeLeft;
    document.getElementById('inputCounter').textContent = '0';
    document.getElementById('submitStatus').textContent = '';
    document.getElementById('stardustAmount').textContent = '0';
    
    // 启动游戏循环
    animationId = requestAnimationFrame(gameLoop);
    
    // 启动倒计时
    timerInterval = setInterval(() => {
        if (!gameState.gameOver && gameState.gameStarted) {
            gameState.timeLeft--;
            document.getElementById('timerDisplay').textContent = gameState.timeLeft;
            
            if (gameState.timeLeft <= 0) {
                endGame();
            }
        }
    }, 1000);
}

// 游戏循环
function gameLoop(currentTime) {
    if (gameState.gameStarted && !gameState.gameOver) {
        updateGame(currentTime);
    }
    renderGame();
    
    if (!gameState.gameOver) {
        animationId = requestAnimationFrame(gameLoop);
    }
}

// 结束游戏
function endGame() {
    gameState.gameOver = true;
    gameState.gameStarted = false;
    clearInterval(timerInterval);
    cancelAnimationFrame(animationId);
    telemetry.endedAt = Date.now();
    
    document.getElementById('finalScore').textContent = Math.floor(gameState.score);
    document.getElementById('finalCombo').textContent = gameState.maxCombo;
    document.getElementById('gameOverScreen').style.display = 'block';
    
    // 提交分数
    submitScore();
}

// 提交分数
function submitScore() {
    const userId = <?php echo $CURUSER['id']; ?>;
    const score = Math.floor(gameState.score);
    const comboMax = gameState.maxCombo;
    const duration = 60 - gameState.timeLeft;
    const endTimestamp = telemetry.endedAt ?? Date.now();
    if (!telemetry.endedAt) {
        telemetry.endedAt = endTimestamp;
    }
    const telemetryPayload = {
        version: telemetry.version,
        startedAt: telemetry.startedAt,
        endedAt: endTimestamp,
        events: telemetry.events.slice(),
        inputs: telemetry.inputs.slice(),
        summary: { ...telemetry.summary },
        client: {
            ua: navigator.userAgent,
            platform: navigator.platform ?? null,
        },
    };
    
    // 生成Token（与后端保持一致）
    const tokenString = userId.toString() + score.toString() + comboMax.toString() + duration.toString() + '<?php echo date("Y-m-d"); ?>' + '<?php echo $userPasshash; ?>';
    const gameToken = md5(tokenString);
    
    const formData = new FormData();
    formData.append('action', 'meteor_game_submit');
    formData.append('user_id', userId);
    formData.append('score', score);
    formData.append('combo_max', comboMax);
    formData.append('duration', duration);
    formData.append('game_token', gameToken);
    formData.append('telemetry', JSON.stringify(telemetryPayload));
    
    console.log('提交分数:', {
        user_id: userId,
        score: score,
        combo_max: comboMax,
        duration: duration,
        token: gameToken,
        telemetry: telemetryPayload
    });
    
    fetch('ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            const data = JSON.parse(text);
            const statusEl = document.getElementById('submitStatus');
            if (data.success) {
                // 显示星尘奖励
                const stardustAmount = data.stardust_reward || 0;
                document.getElementById('stardustAmount').textContent = stardustAmount;
                
                // 显示提交状态和剩余次数
                let message = '✓ 分数已提交到排行榜！';
                if (stardustAmount > 0) {
                    message += ` 获得 ${stardustAmount} ⭐星尘！`;
                }
                if (data.remaining_submits !== undefined) {
                    message += ` (今日剩余提交次数: ${data.remaining_submits})`;
                }
                if (data.flagged) {
                    message += '（数据已标记等待审核，星尘奖励已暂缓发放，排行榜暂不展示此成绩）';
                    if (Array.isArray(data.flag_reasons) && data.flag_reasons.length) {
                        const readableReasons = data.flag_reasons.map(reason => FLAG_REASON_LABELS[reason] || reason);
                        message += ` 原因: ${readableReasons.join('、')}`;
                    }
                    statusEl.style.color = '#FFD166';
                } else {
                    statusEl.style.color = '#4ECDC4';
                }
                statusEl.textContent = message;
                loadLeaderboard('today');
            } else {
                statusEl.textContent = '❌ ' + data.message;
                statusEl.style.color = '#FF6B6B';
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            document.getElementById('submitStatus').textContent = '❌ 提交失败: 服务器响应错误';
            document.getElementById('submitStatus').style.color = '#FF6B6B';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        document.getElementById('submitStatus').textContent = '❌ 提交失败: ' + error.message;
        document.getElementById('submitStatus').style.color = '#FF6B6B';
    });
}

// 重新开始游戏
function restartGame() {
    document.getElementById('gameOverScreen').style.display = 'none';
    gameState.gameOver = false;
    gameState.gameStarted = false;
    startGame();
}

// 加载排行榜
function loadLeaderboard(type, clickedButton) {
    console.log('加载排行榜:', type);
    
    // 更新按钮状态
    document.querySelectorAll('.leaderboard-tabs button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // 如果是点击按钮触发，高亮该按钮；否则高亮默认的"今日"按钮
    if (clickedButton) {
        clickedButton.classList.add('active');
    } else {
        // 初始加载时，默认高亮第一个按钮（今日）
        const defaultBtn = document.querySelector('.leaderboard-tabs button');
        if (defaultBtn) defaultBtn.classList.add('active');
    }
    
    const tbody = document.getElementById('leaderboardBody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">加载中...</td></tr>';
    
    fetch('ajax.php?action=meteor_game_leaderboard&type=' + type)
        .then(response => {
            console.log('排行榜响应状态:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('排行榜响应内容:', text);
            try {
                const data = JSON.parse(text);
                console.log('排行榜数据:', data);
                
                if (data.success) {
                    if (data.data && data.data.length > 0) {
                        tbody.innerHTML = data.data.map(record => {
                            let rankDisplay = record.rank;
                            if (record.rank === 1) rankDisplay = '<span class="rank-medal rank-1">🥇</span>';
                            else if (record.rank === 2) rankDisplay = '<span class="rank-medal rank-2">🥈</span>';
                            else if (record.rank === 3) rankDisplay = '<span class="rank-medal rank-3">🥉</span>';
                            
                            return `<tr>
                                <td>${rankDisplay}</td>
                                <td>${record.username}</td>
                                <td>${record.score}</td>
                                <td>${record.combo_max}</td>
                                <td>${record.created_at}</td>
                            </tr>`;
                        }).join('');
                        console.log('排行榜已更新，共', data.data.length, '条记录');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">暂无记录</td></tr>';
                        console.log('排行榜无记录');
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">加载失败: ' + data.message + '</td></tr>';
                    console.error('排行榜加载失败:', data.message);
                    if (data.trace) {
                        console.error('错误堆栈:', data.trace);
                    }
                }
            } catch (e) {
                console.error('JSON解析错误:', e);
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">数据解析失败</td></tr>';
            }
        })
        .catch(error => {
            console.error('请求错误:', error);
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">加载失败: ' + error.message + '</td></tr>';
        });
}

// 键盘事件
document.addEventListener('keydown', (e) => {
    gameState.keys[e.key] = true;
    if (!e.repeat && ['ArrowLeft', 'ArrowRight', 'a', 'A', 'd', 'D'].includes(e.key)) {
        recordInputEvent('keydown', { key: e.key });
    }
});

document.addEventListener('keyup', (e) => {
    gameState.keys[e.key] = false;
    if (['ArrowLeft', 'ArrowRight', 'a', 'A', 'd', 'D'].includes(e.key)) {
        recordInputEvent('keyup', { key: e.key });
    }
});

// 触摸事件（移动端支持）
let touchX = 0;
canvas.addEventListener('touchstart', (e) => {
    touchX = e.touches[0].clientX;
    recordInputEvent('touchstart', { key: 'touch', delta: 0 });
});

canvas.addEventListener('touchmove', (e) => {
    e.preventDefault();
    const newTouchX = e.touches[0].clientX;
    const deltaX = newTouchX - touchX;
    gameState.player.x += deltaX * touchSensitivity;
    gameState.player.x = Math.max(gameState.player.width/2, Math.min(canvas.width - gameState.player.width/2, gameState.player.x));
    touchX = newTouchX;
    const now = Date.now();
    if (now - lastTouchInputTs > 120) {
        recordInputEvent('touchmove', { key: 'touch', delta: Math.round(deltaX * touchSensitivity) });
        lastTouchInputTs = now;
    }
});

canvas.addEventListener('touchend', () => {
    recordInputEvent('touchend', { key: 'touch', delta: 0 });
});

// 加载今日剩余提交次数
function loadRemainingSubmits() {
    fetch('ajax.php?action=meteor_game_remaining')
        .then(response => response.json())
        .then(data => {
            const infoEl = document.getElementById('submitLimitInfo');
            if (data.success) {
                const remaining = data.remaining_submits;
                const today = data.today_submits;
                const maxSubmits = data.max_submits ?? 5;
                
                if (remaining > 0) {
                    infoEl.innerHTML = `<span style="color: #4ECDC4;">✨ 今日剩余提交次数: <strong>${remaining}/${maxSubmits}</strong> | 每次得分可获得星尘奖励（100积分=1✨星尘）</span>`;
                } else {
                    infoEl.innerHTML = `<span style="color: #FF6B6B;">⚠️ 今日提交次数已用完 (${today}/${maxSubmits})，明天再来吧！游戏仍可继续玩，但不计入排行榜。</span>`;
                }
            } else {
                infoEl.innerHTML = `<span style="color: #FF6B6B;">❌ 加载失败: ${data.message}</span>`;
            }
        })
        .catch(error => {
            console.error('加载剩余次数失败:', error);
            document.getElementById('submitLimitInfo').innerHTML = `<span style="color: #FF6B6B;">❌ 加载失败</span>`;
        });
}

// 页面加载完成后的初始化
// 只渲染背景，不启动游戏
requestAnimationFrame(gameLoop);
// 加载排行榜
loadLeaderboard('today');
// 加载剩余提交次数
loadRemainingSubmits();

function sliderValueToSensitivity(sliderValue) {
    const clamped = Math.min(Math.max(sliderValue, TOUCH_SLIDER_MIN), TOUCH_SLIDER_MAX);
    const ratio = (clamped - TOUCH_SLIDER_MIN) / (TOUCH_SLIDER_MAX - TOUCH_SLIDER_MIN);
    return Number((TOUCH_SENSITIVITY_MIN + ratio * (TOUCH_SENSITIVITY_MAX - TOUCH_SENSITIVITY_MIN)).toFixed(3));
}

function sensitivityToSliderValue(sensitivity) {
    const clamped = Math.min(Math.max(sensitivity, TOUCH_SENSITIVITY_MIN), TOUCH_SENSITIVITY_MAX);
    const ratio = (clamped - TOUCH_SENSITIVITY_MIN) / (TOUCH_SENSITIVITY_MAX - TOUCH_SENSITIVITY_MIN);
    return Math.round(TOUCH_SLIDER_MIN + ratio * (TOUCH_SLIDER_MAX - TOUCH_SLIDER_MIN));
}

function getSensitivityLabel(value) {
    let description = '中等';
    if (value <= TOUCH_SENSITIVITY_MIN + 0.1) {
        description = '极慢';
    } else if (value <= TOUCH_SENSITIVITY_MIN + 0.25) {
        description = '较慢';
    } else if (value >= TOUCH_SENSITIVITY_MAX - 0.2) {
        description = '极快';
    } else if (value >= TOUCH_SENSITIVITY_MAX - 0.45) {
        description = '较快';
    }
    return `${description} (${value.toFixed(2)}x)`;
}

function applyTouchSensitivity(newValue, persist = false) {
    touchSensitivity = Number(newValue.toFixed(3));
    if (sensitivityValueEl) {
        sensitivityValueEl.textContent = getSensitivityLabel(touchSensitivity);
    }
    if (persist && typeof window !== 'undefined' && window.localStorage) {
        try {
            localStorage.setItem(TOUCH_SENSITIVITY_STORAGE_KEY, touchSensitivity.toString());
        } catch (err) {
            console.warn('无法保存灵敏度设置:', err);
        }
    }
}

(function initTouchSensitivity() {
    let initialSensitivity = DEFAULT_TOUCH_SENSITIVITY;
    if (typeof window !== 'undefined' && window.localStorage) {
        const stored = localStorage.getItem(TOUCH_SENSITIVITY_STORAGE_KEY);
        if (stored) {
            const parsed = parseFloat(stored);
            if (!Number.isNaN(parsed)) {
                initialSensitivity = Math.min(Math.max(parsed, TOUCH_SENSITIVITY_MIN), TOUCH_SENSITIVITY_MAX);
            }
        }
    }
    if (sensitivitySlider) {
        sensitivitySlider.value = sensitivityToSliderValue(initialSensitivity).toString();
    }
    applyTouchSensitivity(initialSensitivity);
})();

if (sensitivitySlider) {
    sensitivitySlider.addEventListener('input', (event) => {
        const sliderValue = Number(event.target.value);
        const newSensitivity = sliderValueToSensitivity(sliderValue);
        applyTouchSensitivity(newSensitivity, true);
    });
}
</script>
</body>
</html>


