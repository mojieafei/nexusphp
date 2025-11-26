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
    display: block;
    margin: 0;
    padding: 0;
}

.game-info {
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    display: none; /* 初始隐藏，游戏开始后显示 */
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

/* control-panel 已移除 */

.game-start-screen {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.95);
    padding: 40px 30px;
    border-radius: 20px;
    border: 3px solid rgba(100, 200, 255, 0.8);
    color: #fff;
    text-align: center;
    z-index: 100;
    min-width: 500px;
    max-width: 90vw;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 0 50px rgba(100, 200, 255, 0.5);
}

.game-start-screen h2 {
    font-size: 42px;
    margin-bottom: 20px;
    color: #64c8ff;
    text-shadow: 0 0 20px rgba(100, 200, 255, 0.8);
}

.game-start-screen .game-desc {
    font-size: 16px;
    color: #e5e7eb;
    margin-bottom: 30px;
    line-height: 1.8;
}

.game-start-screen .game-rules {
    background: rgba(100, 200, 255, 0.2);
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
    font-size: 14px;
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
    box-shadow: 0 0 20px rgba(100, 200, 255, 0.5);
}

.game-start-screen button:hover {
    transform: scale(1.05);
    box-shadow: 0 0 30px rgba(100, 200, 255, 0.8);
}

.difficulty-select {
    margin: 20px 0;
    text-align: left;
}

.difficulty-select h3 {
    margin-bottom: 10px;
    font-size: 18px;
    color: #ffd700;
}

.difficulty-buttons {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.difficulty-btn {
    padding: 10px 0;
    border-radius: 10px;
    border: 2px solid rgba(100, 200, 255, 0.4);
    background: rgba(0, 0, 0, 0.4);
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s ease;
}

.difficulty-btn.active {
    border-color: #ffd700;
    background: rgba(255, 215, 0, 0.25);
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.4);
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

.btn-return {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    font-size: 18px;
    font-weight: bold;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4);
}

.btn-return:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.6);
    background: linear-gradient(135deg, #5a6268 0%, #3d4146 100%);
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

/* ========== 农场行星CSS样式（保留作为参考，实际使用Canvas绘制） ========== */
.planet-icon {
    display: inline-block;
    border-radius: 50%;
    position: relative;
    box-shadow: inset -12px -12px 20px rgba(0, 0, 0, 0.35), inset 6px 6px 14px rgba(255, 255, 255, 0.15);
    overflow: visible;
}

.planet-moon {
    background:
        radial-gradient(circle at 68% 32%, rgba(255, 255, 255, 0.14) 0 18%, transparent 56%),
        radial-gradient(circle at 42% 72%, rgba(0, 0, 0, 0.22) 0 20%, transparent 58%),
        radial-gradient(circle at 30% 30%, #f7f7f7 0%, #cfcfcf 60%, #8f8f8f 100%);
    box-shadow: inset -18px -18px 24px rgba(0, 0, 0, 0.4), inset 10px 10px 18px rgba(255, 255, 255, 0.18);
}

.planet-moon::after {
    content: '';
    position: absolute;
    inset: 18%;
    border-radius: 50%;
    background:
        radial-gradient(circle at 30% 30%, rgba(0, 0, 0, 0.32) 0 16%, rgba(255, 255, 255, 0.18) 22%, transparent 48%),
        radial-gradient(circle at 60% 38%, rgba(0, 0, 0, 0.28) 0 10%, rgba(255, 255, 255, 0.16) 16%, transparent 34%),
        radial-gradient(circle at 48% 72%, rgba(0, 0, 0, 0.24) 0 14%, rgba(255, 255, 255, 0.18) 22%, transparent 44%),
        radial-gradient(circle at 68% 66%, rgba(0, 0, 0, 0.2) 0 8%, rgba(255, 255, 255, 0.18) 14%, transparent 36%),
        radial-gradient(circle at 40% 52%, rgba(255, 255, 255, 0.12) 0 6%, transparent 24%);
    opacity: 0.88;
    mix-blend-mode: soft-light;
    pointer-events: none;
}

.planet-mercury {
    background:
        radial-gradient(circle at 32% 28%, rgba(255, 255, 255, 0.32) 0 18%, transparent 42%),
        radial-gradient(circle at 68% 62%, rgba(0, 0, 0, 0.35) 0 20%, transparent 48%),
        linear-gradient(120deg, #d6cec0 0%, #b39e87 38%, #7a5c48 62%, #37281f 82%, #0d0d0d 100%);
    background-blend-mode: screen, multiply, normal;
    box-shadow: inset -18px -18px 24px rgba(0, 0, 0, 0.55), inset 10px 10px 18px rgba(255, 255, 255, 0.18);
}

.planet-mercury::after {
    content: '';
    position: absolute;
    inset: 12%;
    border-radius: 50%;
    background:
        radial-gradient(ellipse at 22% 38%, rgba(0, 0, 0, 0.38) 0 16%, transparent 44%),
        radial-gradient(ellipse at 32% 68%, rgba(0, 0, 0, 0.32) 0 14%, transparent 46%),
        radial-gradient(circle at 62% 34%, rgba(255, 255, 255, 0.24) 0 14%, transparent 40%),
        radial-gradient(circle at 76% 68%, rgba(0, 0, 0, 0.28) 0 12%, transparent 42%),
        linear-gradient(115deg, transparent 0 42%, rgba(0, 0, 0, 0.65) 70%, rgba(0, 0, 0, 0.85) 100%);
    opacity: 0.8;
    mix-blend-mode: soft-light;
    pointer-events: none;
}

.planet-venus {
    background:
        radial-gradient(circle at 48% 28%, rgba(255, 233, 180, 0.28) 0 34%, transparent 70%),
        linear-gradient(150deg, #ffe1ad 0%, #f4b565 45%, #c97d31 100%),
        repeating-linear-gradient(28deg, rgba(255, 255, 255, 0.22) 0 12%, rgba(161, 90, 29, 0.16) 12% 24%);
    background-blend-mode: screen, multiply, normal;
    box-shadow: inset -20px -18px 28px rgba(130, 65, 10, 0.42), inset 10px 10px 18px rgba(255, 221, 181, 0.22);
}

.planet-venus::after {
    content: '';
    position: absolute;
    inset: 8%;
    border-radius: 50%;
    background:
        conic-gradient(from 200deg, rgba(255, 255, 255, 0.2) 0 18%, transparent 18% 32%, rgba(255, 255, 255, 0.18) 32% 48%, transparent 48% 68%, rgba(255, 255, 255, 0.16) 68% 84%, transparent 84% 100%);
    filter: blur(1.2px);
    opacity: 0.8;
    mix-blend-mode: lighten;
    pointer-events: none;
}

.planet-earth {
    background:
        radial-gradient(circle at 35% 30%, #4a90e2 0%, #2e7cd6 25%, #1e5fa8 50%, #0f3f86 75%, #051b43 100%);
    box-shadow: 
        inset -14px -14px 20px rgba(0, 0, 0, 0.4),
        inset 8px 8px 20px rgba(255, 255, 255, 0.2),
        0 0 25px rgba(78, 205, 196, 0.3),
        0 0 40px rgba(52, 152, 219, 0.2);
    filter: drop-shadow(0 0 15px rgba(78, 205, 196, 0.4));
    overflow: visible;
}

.planet-earth::before {
    content: '';
    position: absolute;
    inset: -3%;
    border-radius: 50%;
    background: 
        radial-gradient(circle at 35% 30%, transparent 85%, rgba(135, 206, 250, 0.25) 90%, rgba(78, 205, 196, 0.15) 95%, transparent 100%);
    pointer-events: none;
    z-index: -1;
}

.planet-earth::after {
    content: '';
    position: absolute;
    inset: 7%;
    border-radius: 50%;
    background:
        radial-gradient(circle at 45% 55%, rgba(135, 206, 250, 0.35) 0 40%, transparent 70%),
        radial-gradient(circle at 30% 35%, rgba(52, 152, 219, 0.25) 0 32%, transparent 65%),
        radial-gradient(circle at 26% 40%, rgba(39, 174, 96, 0.98) 0 14%, rgba(39, 174, 96, 0.98) 18%, rgba(39, 174, 96, 0) 22%),
        radial-gradient(circle at 28% 62%, rgba(39, 174, 96, 0.9) 0 12%, rgba(39, 174, 96, 0.15) 20%, transparent 28%),
        radial-gradient(circle at 32% 78%, rgba(46, 204, 113, 0.85) 0 10%, rgba(46, 204, 113, 0.12) 18%, transparent 26%),
        radial-gradient(circle at 60% 50%, rgba(33, 140, 58, 0.98) 0 18%, rgba(33, 140, 58, 0.4) 24%, transparent 32%),
        radial-gradient(circle at 70% 42%, rgba(39, 174, 96, 0.92) 0 16%, rgba(39, 174, 96, 0.2) 22%, transparent 30%),
        radial-gradient(circle at 63% 60%, rgba(210, 180, 140, 0.85) 0 12%, rgba(210, 180, 140, 0.2) 20%, transparent 28%),
        radial-gradient(circle at 76% 58%, rgba(184, 134, 88, 0.78) 0 10%, rgba(184, 134, 88, 0.15) 18%, transparent 26%),
        radial-gradient(circle at 82% 47%, rgba(52, 152, 219, 0.5) 0 9%, transparent 22%),
        radial-gradient(circle at 78% 70%, rgba(46, 204, 113, 0.7) 0 9%, rgba(46, 204, 113, 0.12) 18%, transparent 25%),
        radial-gradient(circle at 64% 64%, rgba(39, 174, 96, 0.9) 0 7%, rgba(39, 174, 96, 0.15) 14%, transparent 22%),
        radial-gradient(circle at 72% 66%, rgba(210, 180, 140, 0.6) 0 6%, transparent 16%),
        radial-gradient(ellipse at 46% 30%, rgba(255, 255, 255, 0.6) 0 20%, rgba(255, 255, 255, 0.25) 30%, transparent 50%),
        radial-gradient(ellipse at 62% 72%, rgba(255, 255, 255, 0.55) 0 18%, rgba(255, 255, 255, 0.2) 30%, transparent 52%),
        radial-gradient(ellipse at 34% 78%, rgba(255, 255, 255, 0.5) 0 16%, transparent 46%),
        radial-gradient(circle at 50% 14%, rgba(255, 255, 255, 0.82) 0 11%, transparent 32%),
        radial-gradient(circle at 50% 86%, rgba(255, 255, 255, 0.78) 0 13%, transparent 35%);
    pointer-events: none;
}

.planet-mars {
    background:
        radial-gradient(circle at 30% 32%, rgba(255, 213, 160, 0.2) 0 28%, transparent 60%),
        linear-gradient(138deg, #e07b3c 0%, #b04a20 55%, #5d230e 100%);
}

.planet-mars::after {
    content: '';
    position: absolute;
    inset: 12%;
    border-radius: 50%;
    background:
        radial-gradient(circle at 65% 38%, rgba(142, 68, 173, 0.2) 0 18%, transparent 52%),
        radial-gradient(circle at 36% 68%, rgba(44, 62, 80, 0.28) 0 18%, transparent 55%),
        radial-gradient(circle at 32% 32%, rgba(255, 255, 255, 0.18) 0 14%, transparent 48%),
        radial-gradient(circle at 72% 68%, rgba(142, 68, 173, 0.16) 0 16%, transparent 52%),
        radial-gradient(circle at 52% 20%, rgba(254, 255, 255, 0.35) 0 10%, transparent 42%);
    mix-blend-mode: overlay;
    opacity: 0.8;
    pointer-events: none;
}

.planet-jupiter {
    background:
        repeating-linear-gradient(160deg, #f8e7c3 0%, #f1d9ae 8%, #cf9d6a 8%, #cf9d6a 16%, #b67a45 16%, #b67a45 24%, #90613b 24%, #90613b 32%);
    background-size: 140% 140%;
    box-shadow: inset -18px -18px 28px rgba(0, 0, 0, 0.38), inset 10px 10px 20px rgba(255, 255, 255, 0.18);
}

.planet-jupiter::after {
    content: '';
    position: absolute;
    inset: 14%;
    border-radius: 50%;
    background:
        radial-gradient(ellipse at 62% 64%, rgba(231, 76, 60, 0.65) 0 22%, rgba(211, 84, 0, 0.38) 30%, transparent 58%),
        linear-gradient(160deg, rgba(255, 255, 255, 0.13) 0 18%, transparent 18% 38%, rgba(255, 255, 255, 0.09) 38% 58%, transparent 58% 80%, rgba(255, 255, 255, 0.1) 80% 100%);
    mix-blend-mode: lighten;
    opacity: 0.85;
    pointer-events: none;
}

.planet-saturn {
    background:
        radial-gradient(circle at 42% 26%, rgba(255, 240, 199, 0.22) 0 30%, transparent 70%),
        linear-gradient(150deg, #ffe3aa 0%, #e0ba6e 48%, #b67d32 100%),
        repeating-linear-gradient(20deg, rgba(255, 255, 255, 0.2) 0 12%, rgba(164, 107, 35, 0.18) 12% 24%);
    background-blend-mode: screen, multiply, normal;
    box-shadow: inset -16px -16px 22px rgba(0, 0, 0, 0.32), inset 8px 8px 16px rgba(255, 255, 255, 0.12);
}

.planet-saturn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 120%;
    height: 42%;
    border: 4px solid rgba(255, 222, 173, 0.7);
    border-radius: 50%;
    transform: translate(-50%, -50%) rotate(-18deg);
    box-shadow: 0 0 12px rgba(255, 222, 173, 0.4);
    z-index: 0;
    pointer-events: none;
}

.planet-uranus {
    background:
        radial-gradient(circle at 46% 24%, rgba(255, 255, 255, 0.22) 0 28%, transparent 66%),
        linear-gradient(135deg, #c6f9ff 0%, #6bc8dd 45%, #1d6a8c 100%);
}

.planet-uranus::after {
    content: '';
    position: absolute;
    inset: 18%;
    border-radius: 50%;
    background:
        linear-gradient(150deg, rgba(255, 255, 255, 0.16) 0 45%, transparent 55% 100%),
        linear-gradient(30deg, rgba(46, 204, 206, 0.16) 0 38%, transparent 58% 100%),
        radial-gradient(circle at 68% 58%, rgba(255, 255, 255, 0.18) 0 12%, transparent 46%);
    mix-blend-mode: screen;
    opacity: 0.72;
    pointer-events: none;
}

.planet-neptune {
    background:
        radial-gradient(circle at 60% 28%, rgba(255, 255, 255, 0.18) 0 24%, transparent 60%),
        linear-gradient(140deg, #7bc4ff 0%, #2a68d5 55%, #0b226a 100%);
}

.planet-neptune::after {
    content: '';
    position: absolute;
    inset: 15%;
    border-radius: 50%;
    background:
        radial-gradient(circle at 66% 58%, rgba(52, 152, 219, 0.75) 0 20%, rgba(52, 152, 219, 0.35) 32%, transparent 58%),
        radial-gradient(circle at 28% 42%, rgba(255, 255, 255, 0.15) 0 18%, transparent 55%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.1) 0 30%, transparent 60% 100%);
    mix-blend-mode: screen;
    opacity: 0.88;
    pointer-events: none;
}

.planet-sun {
    background: 
        radial-gradient(circle at 50% 50%, #ffffff 0%, #fff6a1 15%, #ffd700 30%, #ffb347 50%, #ff8c42 70%, #ff6b6b 85%, #d63031 100%);
    box-shadow: 
        0 0 40px rgba(255, 255, 0, 0.6),
        0 0 60px rgba(255, 204, 0, 0.5),
        0 0 80px rgba(255, 153, 0, 0.4),
        0 0 100px rgba(255, 102, 0, 0.3),
        inset -12px -12px 24px rgba(255, 94, 0, 0.35),
        inset 8px 8px 20px rgba(255, 255, 200, 0.2);
    filter: drop-shadow(0 0 20px rgba(255, 204, 0, 0.6));
    overflow: visible;
}

.planet-sun::after {
    content: '';
    position: absolute;
    inset: 4%;
    border-radius: 50%;
    background:
        radial-gradient(ellipse at 75% 35%, rgba(255, 255, 150, 0.5) 0 20%, transparent 50%),
        radial-gradient(ellipse at 25% 65%, rgba(255, 200, 100, 0.4) 0 18%, transparent 48%),
        radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.4) 0 15%, transparent 45%),
        radial-gradient(circle at 30% 40%, rgba(255, 255, 255, 0.35) 0 20%, transparent 55%),
        radial-gradient(circle at 70% 60%, rgba(255, 180, 0, 0.4) 0 18%, transparent 50%),
        radial-gradient(circle at 65% 60%, rgba(255, 94, 0, 0.35) 0 22%, transparent 55%);
    box-shadow:
        0 0 30px rgba(255, 255, 0, 0.5),
        0 0 50px rgba(255, 204, 0, 0.4),
        0 0 70px rgba(255, 153, 0, 0.3);
    mix-blend-mode: screen;
    opacity: 0.95;
    pointer-events: none;
}

/* 石头和负分碎片样式 */
.fragment-rock {
    background: #666;
    box-shadow: inset -8px -8px 12px rgba(0, 0, 0, 0.5), inset 4px 4px 8px rgba(255, 255, 255, 0.1);
}

.fragment-rock::before {
    content: '';
    position: absolute;
    top: 20%;
    left: 20%;
    width: 40%;
    height: 40%;
    border-radius: 50%;
    background: #555;
}

.fragment-rock::after {
    content: '🪨';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 60%;
    opacity: 0.7;
}

.fragment-bad {
    background: transparent;
    font-size: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255, 100, 100, 0.5);
    animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(255, 100, 100, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(255, 100, 100, 0); }
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
            <div>难度: <span id="difficultyDisplay">1</span></div>
        </div>
        
        <canvas id="gameCanvas"></canvas>
        
        <!-- 游戏开始界面 -->
        <div class="game-start-screen" id="gameStartScreen">
            <h2>🌌 拯救太阳系计划 🌌</h2>
            <div class="game-desc" style="margin-bottom: 25px;">
                <div style="background: rgba(100, 200, 255, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 3px solid #64c8ff;">
                    <p style="margin: 0 0 10px 0; color: #ffd700; font-weight: bold;">🚀 紧急任务简报</p>
                    <p style="margin: 0; line-height: 1.8; font-size: 14px;">
                        公元3025年，一场未知的宇宙灾难摧毁了太阳系！各大行星被炸成碎片，散落在宇宙空间中。
                        更可怕的是，邪恶的宇宙生物趁机入侵，威胁着整个银河系的安全！
                        作为最后的希望，你驾驶着"星尘号"宇宙飞船，装备了先进的机械抓取臂。
                        你的任务是：在60秒内尽可能多地收集行星碎片，重建太阳系，同时避开危险的宇宙生物！
                        记住：收集行星碎片得分，抓到宇宙生物扣分！时间紧迫，太阳系的命运掌握在你手中！
                    </p>
            </div>
                <div id="submitLimitInfo" style="background: rgba(255, 215, 0, 0.15); padding: 12px; border-radius: 8px; border: 1px solid rgba(255, 215, 0, 0.3); text-align: center; font-size: 14px;">
                    <span style="color: #ffd700;">⏳ 正在加载今日提交信息...</span>
                </div>
            </div>
            <div class="difficulty-select">
                <h3>⚙️ 选择难度（Lv.1 - Lv.6）</h3>
                <div class="difficulty-buttons">
                    <button type="button" class="difficulty-btn active" data-level="1" onclick="selectDifficulty(1)">Lv.1</button>
                    <button type="button" class="difficulty-btn" data-level="2" onclick="selectDifficulty(2)">Lv.2</button>
                    <button type="button" class="difficulty-btn" data-level="3" onclick="selectDifficulty(3)">Lv.3</button>
                    <button type="button" class="difficulty-btn" data-level="4" onclick="selectDifficulty(4)">Lv.4</button>
                    <button type="button" class="difficulty-btn" data-level="5" onclick="selectDifficulty(5)">Lv.5</button>
                    <button type="button" class="difficulty-btn" data-level="6" onclick="selectDifficulty(6)">Lv.6</button>
                </div>
                <p id="difficultyHint" style="margin-top: 10px; font-size: 14px; color: #e5e7eb;">
                    难度越高：碎石数量 = (5-8)×难度，石头越大，需要更多子弹，所有得分倍率每级+0.2（Lv.6 = 2倍分值）。<br>
                    ⚠️ 碎石可抓取但得分极低（基础分数20%），建议用追踪弹炸碎获得更高分数（基础分数50%）。
                </p>
            </div>
            <div class="game-rules">
                <h3>🎮 操作指南</h3>
                <ul>
                    <li>⏱️ 任务时限：60秒</li>
                    <li>🔄 自动旋转：机械臂会360度自动旋转，瞄准目标</li>
                    <li>🎯 发射机械手：点击鼠标或按空格键发射机械手</li>
                    <li>⬅️ 收回机制：机械手碰到物体或达到最远距离后自动收回</li>
                    <li>🚀 追踪弹：抓取物体后再次按空格键即可无限发射追踪弹，可炸毁碎石和宇宙生物（正向天体不可被炸碎）</li>
                    <li>✅ 行星碎片加分：🌙月球(+10) 🌍地球(+15) 🔴火星(+20) 🪐木星(+30) 🪐土星(+35) ☀️太阳(+50)</li>
                    <li>❌ 宇宙生物扣分：👾外星虫(-30) ☄️陨石怪(-50) 🕳️黑洞(-100)</li>
                    <li>⚖️ 重量影响：碎片越重，收回速度越慢（太阳最重，月球最轻）</li>
                    <li>🪨 小行星：灰色石头可抓取但得分极低（基础分数20%），建议用追踪弹炸碎获得更高分数（基础分数50%）</li>
                    <li>🎯 关卡系统：达到目标分数后进入下一关，并获得时间奖励</li>
                    <li>⚠️ 注意：避开宇宙生物，优先收集高价值碎片！</li>
                </ul>
            </div>
            <div style="background: rgba(78, 205, 196, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 3px solid #4ECDC4;">
                <h3 style="color: #4ECDC4; font-size: 16px; margin-bottom: 10px;">⭐ 星尘奖励与用途</h3>
                <ul style="list-style: none; padding: 0; font-size: 14px; line-height: 1.8; color: #e5e7eb;">
                    <li style="padding: 5px 0;">💎 <strong>获得星尘</strong>：每得10分 = 1星尘（正分才有奖励）</li>
                    <li style="padding: 5px 0;">🌱 <strong>种植天体</strong>：星尘可用于购买行星种子，在星尘农场中种植</li>
                    <li style="padding: 5px 0;">🌍 <strong>重建太阳系</strong>：收集9个碎片合成完整行星，集齐9大天体重建太阳系！</li>
                    <li style="padding: 5px 0;">🎯 <strong>游戏入口</strong>：右上角绿色按钮 → <a href="stardust_farm.php" style="color: #4ECDC4; text-decoration: underline;">星尘农场</a></li>
                </ul>
            </div>
            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                <button class="btn-return" onclick="window.location.href='index.php'">
                    ← 返回主页
                </button>
                <button class="btn-start" onclick="startGame()">
                    🚀 开始拯救任务
                </button>
            </div>
        </div>
        
        <div class="game-over" id="gameOver">
            <h2>游戏结束</h2>
            <p>最终得分: <span id="finalScore">0</span></p>
            <p>抓取数量: <span id="finalCaught">0</span></p>
            <div id="submitStatus" style="margin: 15px 0; min-height: 24px; color: #64C8FF;"></div>
            <button class="btn-submit" onclick="resetGame()">返回主菜单</button>
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

let selectedDifficulty = 1;

function selectDifficulty(level) {
    level = Math.min(6, Math.max(1, level));
    selectedDifficulty = level;
    const buttons = document.querySelectorAll('.difficulty-btn');
    buttons.forEach(btn => {
        const btnLevel = Number(btn.dataset.level);
        if (!Number.isNaN(btnLevel)) {
            btn.classList.toggle('active', btnLevel === level);
        }
    });
    const hint = document.getElementById('difficultyHint');
    if (hint) {
        hint.textContent = `当前难度 Lv.${level}：碎石数量放大为 (5-8)×${level}，大石概率提升，所有得分倍率为 ${(1 + (level - 1) * 0.2).toFixed(1)} 倍。`;
    }
    const diffDisplay = document.getElementById('difficultyDisplay');
    if (diffDisplay) {
        diffDisplay.textContent = level;
    }
}

selectDifficulty(selectedDifficulty);

// 游戏状态
const gameState = {
    started: false,
    gameOver: false,
    score: 0,
    target: 500,
    timeLeft: 60,
    level: 1,
    caught: 0,
    maxPossibleScore: 0, // 本局理论最大分数（所有正向碎片最高分之和，考虑最大距离加成）
    difficulty: 1,
    scoreMultiplier: 1,
    
    // 追踪弹系统
    missiles: [], // 追踪弹数组
    
    // 已收集的天体类型（用于集齐10个天体的奖励）
    collectedPlanets: new Set(), // 使用Set存储已收集的planetClass
    
    // 飞船（在屏幕中央）
    ship: {
        x: 0, // 将在初始化时设置到屏幕中心
        y: 0, // 将在初始化时设置到屏幕中心
        width: 80,
        height: 40,
        radius: 32, // 飞船前端半径（从中心到机械手连接点的距离）
        angle: 0, // 初始角度：0度（向右），当前旋转角度（360度旋转），飞船前方指向右
        // 360度旋转相关
        rotationSpeed: 0.003 // 旋转速度（弧度/毫秒）
    },
    
    // 机械手
    claw: {
        extended: false,
        length: 0,
        maxLength: 400, // 初始值，会在初始化时根据画布大小动态计算
        extendSpeed: 0.5, // 像素/毫秒
        retractSpeed: 0.4, // 像素/毫秒
        angle: Math.PI / 2, // 初始角度：90度（向下），与飞船同步
        grabbing: false,
        grabbedItem: null,
        retracting: false, // 标记是否正在收回（一旦开始收回就持续收回）
        grabDistance: 0 // 记录抓取时的距离（用于得分加成）
    },
    
    // 行星碎片（包括普通碎片和石头）
    fragments: [],
    fragmentSpawnTimer: 0,
    fragmentSpawnInterval: 2000
};

// 碎片类型（基于真实行星大小比例，地球=1.0）
// 真实直径比例：月球0.27, 地球1.0, 火星0.53, 木星11.2, 土星9.4, 太阳109（太大，缩小）
// 地球基准size=24，质量基于size的平方（体积相关）
const EARTH_BASE_SIZE = 24; // 地球基准大小

// 计算质量函数：基于size的平方（与体积相关，质量 ∝ size³，简化用size²）
// 质量越大，回收速度越慢
function calculateMass(size) {
    // 质量 = (size / 基准size)²，地球的质量 = 1.0
    const baseSize = EARTH_BASE_SIZE;
    return Math.pow(size / baseSize, 2);
}

// 计算得分函数：平衡抓取难度（size小=难抓）和时间成本（质量大=费时）
// 公式：得分 = 基础分 * (难度系数 + 时间补偿)
// 难度系数：反比于size（小的难抓给高分）
// 时间补偿：正比于质量（大的费时给补偿，但不如难度系数重要）
function calculateScore(size, mass, baseScore = 15) {
    const sizeRatio = size / EARTH_BASE_SIZE; // 相对于地球的大小比例
    
    // 难度系数：小的难抓，给更高分（反比关系，用平方根平滑）
    const difficultyFactor = Math.pow(1 / sizeRatio, 0.6); // 0.6次方让曲线更平滑
    
    // 时间补偿：大的费时，给一定补偿（但权重较低）
    const timeCompensation = 1 + (mass - 1) * 0.3; // 质量大于1时给补偿，但权重只有0.3
    
    // 最终得分 = 基础分 * 难度系数 * 时间补偿 * 2（分数翻倍）
    const finalScore = Math.round(baseScore * difficultyFactor * timeCompensation * 2);
    
    // 确保最小得分不为0
    return Math.max(1, finalScore);
}

// 计算距离加成：距离越远，加成越高（因为越远越难掌握角度）
// 公式：加成系数 = 1 + (距离 / 最大距离) * 加成倍率
function calculateDistanceBonus(grabDistance, maxLength) {
    if (maxLength <= 0) return 1.0;
    
    // 距离比例（0到1之间）
    const distanceRatio = Math.min(grabDistance / maxLength, 1.0);
    
    // 最大加成倍率：最远距离可以获得最多50%的额外分数
    const MAX_BONUS_RATIO = 0.5;
    
    // 距离加成系数：1.0（无加成）到 1.5（最远50%加成）
    const bonusMultiplier = 1.0 + (distanceRatio * MAX_BONUS_RATIO);
    
    return bonusMultiplier;
}

const FRAGMENT_TYPES = [
    // 正向碎片（加分）- 按真实比例，质量和得分自动计算，使用CSS绘制
    // 8大行星 + 太阳 + 月亮 = 10个天体
    { name: '月球', planetClass: 'moon', size: Math.round(EARTH_BASE_SIZE * 0.27), chance: 0.22, type: 'good', color: '#C0C0C0' },
    { name: '水星', planetClass: 'mercury', size: Math.round(EARTH_BASE_SIZE * 0.38), chance: 0.15, type: 'good', color: '#8C7853' },
    { name: '金星', planetClass: 'venus', size: Math.round(EARTH_BASE_SIZE * 0.95), chance: 0.18, type: 'good', color: '#FFC649' },
    { name: '地球', planetClass: 'earth', size: EARTH_BASE_SIZE, chance: 0.17, type: 'good', color: '#4A90E2' },
    { name: '火星', planetClass: 'mars', size: Math.round(EARTH_BASE_SIZE * 0.53), chance: 0.13, type: 'good', color: '#F44336' },
    { name: '木星', planetClass: 'jupiter', size: Math.round(EARTH_BASE_SIZE * 3.0), chance: 0.07, type: 'good', color: '#9C27B0' }, // 真实11.2太大，缩小到3倍
    { name: '土星', planetClass: 'saturn', size: Math.round(EARTH_BASE_SIZE * 2.5), chance: 0.03, type: 'good', color: '#FF9800' }, // 真实9.4太大，缩小到2.5倍
    { name: '天王星', planetClass: 'uranus', size: Math.round(EARTH_BASE_SIZE * 1.8), chance: 0.04, type: 'good', color: '#4FD0E7' }, // 真实4.0太大，缩小到1.8倍
    { name: '海王星', planetClass: 'neptune', size: Math.round(EARTH_BASE_SIZE * 1.8), chance: 0.04, type: 'good', color: '#4166F5' }, // 真实3.9太大，缩小到1.8倍
    { name: '太阳', planetClass: 'sun', size: Math.round(EARTH_BASE_SIZE * 3.5), chance: 0.01, type: 'good', color: '#FFD700' }, // 真实109太大，缩小到3.5倍
    // 负向碎片（扣分）- 保持emoji显示（分数已翻倍）
    { name: '外星虫', emoji: '👾', score: -30, size: EARTH_BASE_SIZE, chance: 0.08, type: 'bad', color: '#9C27B0' },
    { name: '陨石怪', emoji: '☄️', score: -50, size: EARTH_BASE_SIZE, chance: 0.04, type: 'bad', color: '#795548' },
    { name: '黑洞', emoji: '🕳️', score: -100, size: EARTH_BASE_SIZE, chance: 0.01, type: 'bad', color: '#000000' }
];

// 为每个正向碎片类型自动计算质量和得分
FRAGMENT_TYPES.forEach(fragment => {
    if (fragment.type === 'good') {
        fragment.weight = calculateMass(fragment.size);
        fragment.score = calculateScore(fragment.size, fragment.weight);
    } else {
        // 负向碎片的质量也计算（用于回收速度）
        fragment.weight = calculateMass(fragment.size);
    }
});

// 石头类型（高密度天体，可以被抓取但质量很大，回收慢）
const ROCK_TYPES = [
    { size: 20, chance: 0.40 },
    { size: 35, chance: 0.30 },
    { size: 50, chance: 0.20 },
    { size: 70, chance: 0.10 }
];

// 计算石头质量（高密度，质量比普通天体高很多）
function calculateRockMass(size) {
    // 高密度系数：石头的密度是普通天体的4倍
    const DENSITY_MULTIPLIER = 4.0;
    const baseMass = calculateMass(size);
    return baseMass * DENSITY_MULTIPLIER;
}

// 计算石头得分（基于大小，碎石得分较低）
function calculateRockScore(size) {
    // 碎石基础分数降低（原来15，现在改为8）
    const baseScore = 8;
    const sizeFactor = size / EARTH_BASE_SIZE;
    // 得分 = 基础分 * 大小因子 * 2（分数翻倍）
    return Math.round(baseScore * (0.5 + sizeFactor * 0.5) * 2);
}

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

// 创建石头（高密度天体）
function createRock(difficultyLevel = 1) {
    const bias = Math.max(0, Math.min(1, (difficultyLevel - 1) / 5));
    const weighted = [];
    let totalWeight = 0;
    
    ROCK_TYPES.forEach((type, index) => {
        const levelFactor = ROCK_TYPES.length > 1 ? index / (ROCK_TYPES.length - 1) : 0;
        const weight = type.chance * (1 + bias * levelFactor);
        weighted.push({ type, weight });
        totalWeight += weight;
    });
    
    let rand = Math.random() * totalWeight;
    let rockType = ROCK_TYPES[ROCK_TYPES.length - 1];
    for (let entry of weighted) {
        if (rand < entry.weight) {
            rockType = entry.type;
            break;
        }
        rand -= entry.weight;
    }
    
    // 高难度时，石头尺寸增大（难度越高，石头越大）
    // 难度1=原尺寸，难度6=原尺寸*1.3倍（每级+0.06）
    const sizeMultiplier = 1 + (difficultyLevel - 1) * 0.06;
    const size = Math.round(rockType.size * sizeMultiplier);
    const mass = calculateRockMass(size);
    const score = calculateRockScore(size);
    
    // 根据石头大小和难度线性计算所需子弹数
    // 基础：最小石头(size 20)需要1发，最大石头(size 70)需要6发
    // 难度加成：难度越高，所需子弹数增加（难度1=1.0倍，难度6=2.0倍，每级+0.2）
    const minSize = 20;
    const maxSize = 70;
    const minHits = 1;
    const maxHits = 6;
    const baseHits = Math.max(1, Math.ceil(minHits + (size - minSize) / (maxSize - minSize) * (maxHits - minHits)));
    // 难度加成：难度1=1.0倍，难度6=2.0倍（每级+0.2）
    const difficultyHitsMultiplier = 1 + (difficultyLevel - 1) * 0.2;
    const hitsRequired = Math.max(1, Math.ceil(baseHits * difficultyHitsMultiplier));
    
    return {
        x: 0, // 将在生成时设置
        y: 0, // 将在生成时设置
        name: '小行星',
        emoji: '🪨',
        size: size,
        score: score,
        weight: mass,
        type: 'rock', // 石头类型
        color: '#666',
        chance: 0, // 不参与碎片随机生成
        id: Date.now() + Math.random() + 1000000, // 确保ID不冲突
        hitsRequired: hitsRequired, // 所需子弹数
        currentHits: 0 // 当前受到的伤害（子弹数）
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
    
    // 检查与现有碎片/石头的距离（石头现在也作为fragments的一部分）
    for (let fragment of gameState.fragments) {
        if (excludeId && fragment.id === excludeId) continue;
        const dx = x - fragment.x;
        const dy = y - fragment.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        if (distance < (size + fragment.size + minDistance)) {
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
    gameState.difficulty = selectedDifficulty;
    gameState.scoreMultiplier = 1 + (gameState.difficulty - 1) * 0.2;
    
    // 重置追踪弹
    gameState.missiles = [];
    
    // 重置已收集的天体
    gameState.collectedPlanets = new Set();
    gameState.collectionComplete = false;
    
    // 根据画布大小动态计算机械手最大长度
    // 策略：从中心点到画布边缘的距离，确保可以到达画布任何角落
    // 计算方法：使用画布对角线的一半，这样可以从中心到达画布的任何边缘
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    // 计算从中心到最近角落的距离（这是最大可到达距离）
    const distToCorner = Math.sqrt(centerX * centerX + centerY * centerY);
    // 使用对角线长度的85%，留一点安全边距
    gameState.claw.maxLength = distToCorner * 0.85;
    // 确保最大长度在合理范围内（至少300，最多600）
    gameState.claw.maxLength = Math.max(300, Math.min(600, gameState.claw.maxLength));
    
    // 飞船位置在屏幕中央
    gameState.ship.x = canvas.width / 2;
    gameState.ship.y = canvas.height / 2;
    gameState.ship.angle = 0; // 初始0度（向右），飞船前方指向右
    gameState.claw.extended = false;
    gameState.claw.length = 0;
    gameState.claw.grabbing = false;
    gameState.claw.grabbedItem = null;
    gameState.claw.retracting = false; // 重置收回状态
    gameState.claw.angle = 0; // 与飞船同步
    gameState.fragments = [];
    gameState.fragmentSpawnTimer = 0;
    
    // 获取所有正向碎片类型（太阳系天体）
    const celestialBodies = FRAGMENT_TYPES.filter(f => f.type === 'good');
    
    // 确保每种太阳系天体只生成一个（总共10个）
    celestialBodies.forEach((bodyType, index) => {
        // 创建一个新的碎片对象，确保包含所有属性（包括质量等）
        const fragment = {
            x: 0, // 将在生成时设置
            y: 0, // 将在生成时设置
            name: bodyType.name,
            size: bodyType.size,
            score: bodyType.score, // 确保包含得分（已翻倍）
            weight: bodyType.weight, // 确保包含质量
            chance: bodyType.chance,
            type: bodyType.type,
            color: bodyType.color,
            planetClass: bodyType.planetClass, // 确保包含planetClass用于CSS绘制
            id: Date.now() + Math.random() + index * 1000, // 确保唯一ID
            hitsRequired: 1, // 天体默认1发即可摧毁
            currentHits: 0
        };
        const pos = generateRandomPosition(fragment.size);
        fragment.x = pos.x;
        fragment.y = pos.y;
        gameState.fragments.push(fragment);
    });
    
    // 生成扣分天体（1-3个，大小等于地球）
    const negativeTypes = FRAGMENT_TYPES.filter(f => f.type === 'bad');
    const negativeCount = 1 + Math.floor(Math.random() * 3);
    for (let i = 0; i < negativeCount; i++) {
        const type = negativeTypes[Math.floor(Math.random() * negativeTypes.length)];
        const fragment = {
            id: Date.now() + Math.random() + i * 300,
            name: type.name,
            type: type.type,
            emoji: type.emoji,
            score: type.score,
            size: EARTH_BASE_SIZE,
            weight: calculateMass(EARTH_BASE_SIZE),
            color: type.color,
            hitsRequired: 1, // 负分碎片默认1发即可摧毁
            currentHits: 0
        };
        const pos = generateRandomPosition(fragment.size);
        fragment.x = pos.x;
        fragment.y = pos.y;
        gameState.fragments.push(fragment);
    }
    
    // 随机生成一些石头（高密度天体，数量 = (5-8) * 难度）
    const baseRockCount = 5 + Math.floor(Math.random() * 4);
    const rockCount = baseRockCount * gameState.difficulty;
    for (let i = 0; i < rockCount; i++) {
        const rock = createRock(gameState.difficulty);
        const pos = generateRandomPosition(rock.size);
        rock.x = pos.x;
        rock.y = pos.y;
        gameState.fragments.push(rock); // 石头也作为fragments的一部分
    }
    
    // 计算本局理论最大分数（所有正向碎片的基础分数之和 * 最大距离加成1.5）
    let maxBaseScore = 0;
    gameState.fragments.forEach(fragment => {
        // 只计算正向碎片（天体+石头），不考虑负分碎片
        if (fragment.type === 'good' || fragment.type === 'rock') {
            maxBaseScore += Math.abs(fragment.score || 0); // 确保是正数
        }
    });
    
    // 理论最大分数 = 所有正向碎片基础分数之和 * 最大距离加成（1.5倍）
    gameState.maxPossibleScore = Math.ceil(maxBaseScore * 1.5 * gameState.scoreMultiplier);
    
    updateUI();
}

// 绘制飞船
function drawShip() {
    const ship = gameState.ship;
    ctx.save();
    ctx.translate(ship.x, ship.y);
    ctx.rotate(ship.angle); // 飞船也会旋转，朝向当前角度
    
    // 外层光晕效果
    ctx.shadowBlur = 30;
    ctx.shadowColor = '#64C8FF';
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;
    
    // 飞船主体 - 六边形核心舱（更大更醒目）
    // 调整六边形方向，使其一个边朝向飞船前方（当前角度方向）
    ctx.fillStyle = '#1a237e';
    ctx.beginPath();
    const centerSize = 28;
    for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i - Math.PI / 6 + Math.PI / 2; // +90度偏移，使一个边朝右（前方）
        const x = Math.cos(angle) * centerSize;
        const y = Math.sin(angle) * centerSize;
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    }
    ctx.closePath();
    ctx.fill();
    
    // 内层核心（发光效果）
    ctx.fillStyle = '#283593';
    ctx.beginPath();
    ctx.arc(0, 0, 22, 0, Math.PI * 2);
    ctx.fill();
    
    // 中央能量核心
    const coreGradient = ctx.createRadialGradient(0, 0, 0, 0, 0, 18);
    coreGradient.addColorStop(0, '#64C8FF');
    coreGradient.addColorStop(0.6, '#2196F3');
    coreGradient.addColorStop(1, '#1565C0');
    ctx.fillStyle = coreGradient;
    ctx.beginPath();
    ctx.arc(0, 0, 18, 0, Math.PI * 2);
    ctx.fill();
    
    // 能量核心闪烁效果
    ctx.fillStyle = 'rgba(100, 200, 255, 0.6)';
    ctx.beginPath();
    ctx.arc(0, 0, 12, 0, Math.PI * 2);
    ctx.fill();
    
    // 两侧推进器/机翼（上方和下方）
    ctx.shadowBlur = 15;
    ctx.shadowColor = '#64C8FF';
    ctx.fillStyle = '#3949ab';
    
    // 上方推进器（原本左侧，现在旋转90度后在上方）
    ctx.save();
    ctx.translate(0, -35);
    ctx.beginPath();
    ctx.moveTo(-12, 0);
    ctx.lineTo(-8, 8);
    ctx.lineTo(8, 8);
    ctx.lineTo(12, 0);
    ctx.lineTo(8, -4);
    ctx.lineTo(-8, -4);
    ctx.closePath();
    ctx.fill();
    
    // 推进器喷射口
    ctx.fillStyle = '#5c6bc0';
    ctx.fillRect(-6, 6, 12, 2);
    ctx.restore();
    
    // 下方推进器（原本右侧，现在旋转90度后在下方）
    ctx.save();
    ctx.translate(0, 35);
    ctx.beginPath();
    ctx.moveTo(-12, 0);
    ctx.lineTo(-8, -8);
    ctx.lineTo(8, -8);
    ctx.lineTo(12, 0);
    ctx.lineTo(8, 4);
    ctx.lineTo(-8, 4);
    ctx.closePath();
    ctx.fill();
    
    // 推进器喷射口
    ctx.fillStyle = '#5c6bc0';
    ctx.fillRect(-6, -8, 12, 2);
    ctx.restore();
    
    // 后方天线/传感器（原本顶部，现在旋转90度后在后方/左侧）
    ctx.strokeStyle = '#64C8FF';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(-28, 0);
    ctx.lineTo(-35, 0);
    ctx.stroke();
    
    ctx.fillStyle = '#FFD700';
    ctx.beginPath();
    ctx.arc(-35, 0, 3, 0, Math.PI * 2);
    ctx.fill();
    
    // 机械手连接点（在飞船正前方）
    const shipFront = ship.radius; // 飞船前端位置
    ctx.shadowBlur = 10;
    ctx.shadowColor = '#FF6B6B';
    ctx.fillStyle = '#FF6B6B';
    ctx.beginPath();
    ctx.arc(shipFront, 0, 6, 0, Math.PI * 2); // 在飞船前方
    ctx.fill();
    
    // 连接点内部
    ctx.fillStyle = '#FF0000';
    ctx.beginPath();
    ctx.arc(shipFront, 0, 3, 0, Math.PI * 2);
    ctx.fill();
    
    // 连接点高光
    ctx.fillStyle = '#FFAAAA';
    ctx.beginPath();
    ctx.arc(shipFront - 1, -1, 2, 0, Math.PI * 2);
    ctx.fill();
    
    ctx.restore();
}

// 绘制机械手
function drawClaw() {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    ctx.save();
    // 从飞船中心开始绘制
    ctx.translate(ship.x, ship.y);
    ctx.rotate(claw.angle);
    
    // 飞船前端位置（机械手连接点）
    const shipFront = ship.radius;
    
    // 如果爪子完全收回且未伸出，显示一个小的预览爪子标识
    if (claw.length === 0 && !claw.extended) {
        const previewX = shipFront;
        const previewY = 0;
        
        // 绘制小的预览夹子（半透明，表示待命状态）
        ctx.globalAlpha = 0.7;
        ctx.shadowBlur = 8;
        ctx.shadowColor = 'rgba(100, 200, 255, 0.5)';
        
        // 小的夹子主体
        ctx.fillStyle = '#888';
    ctx.beginPath();
        ctx.roundRect(previewX - 5, previewY - 4, 10, 8, 2);
        ctx.fill();
        
        // 三个小的预览夹爪（闭合状态）
        ctx.fillStyle = '#666';
        for (let i = -1; i <= 1; i++) {
            ctx.beginPath();
            ctx.moveTo(previewX, previewY);
            ctx.lineTo(previewX - 2, previewY + 6 + i * 2);
            ctx.lineTo(previewX + 2, previewY + 6 + i * 2);
            ctx.closePath();
            ctx.fill();
        }
        
        // 连接点高光
        ctx.fillStyle = '#AAA';
        ctx.beginPath();
        ctx.arc(previewX, previewY, 2, 0, Math.PI * 2);
        ctx.fill();
        
        ctx.globalAlpha = 1.0;
        ctx.restore();
        return;
    }
    
    // 绘制绳索/机械臂（沿角度方向，从飞船前端开始）
    // 绳索渐变效果
    const cableGradient = ctx.createLinearGradient(shipFront, 0, shipFront + claw.length, 0);
    cableGradient.addColorStop(0, '#aaa');
    cableGradient.addColorStop(0.5, '#888');
    cableGradient.addColorStop(1, '#666');
    
    ctx.strokeStyle = cableGradient;
    ctx.lineWidth = 4;
    ctx.lineCap = 'round';
    ctx.shadowBlur = 5;
    ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
    ctx.beginPath();
    ctx.moveTo(shipFront, 0); // 从飞船前端开始
    ctx.lineTo(shipFront + claw.length, 0); // 到爪子位置
    ctx.stroke();
    
    // 绳索细节（分段效果）
    ctx.strokeStyle = '#555';
    ctx.lineWidth = 2;
    ctx.shadowBlur = 0;
    for (let i = shipFront + 20; i < shipFront + claw.length; i += 30) {
        ctx.beginPath();
        ctx.moveTo(i, -2);
        ctx.lineTo(i, 2);
        ctx.stroke();
    }
    
    // 绘制机械夹子
    if (claw.extended || claw.length > 0) {
        const clawX = shipFront + claw.length; // 从飞船前端加上爪子长度
        const clawY = 0;
        
        ctx.shadowBlur = 12;
        ctx.shadowColor = claw.grabbing ? 'rgba(255, 0, 0, 0.8)' : 'rgba(0, 0, 0, 0.6)';
        
        // 夹子主体（连接块）
        ctx.fillStyle = claw.grabbing ? '#FF4444' : '#666';
        ctx.beginPath();
        ctx.roundRect(clawX - 8, clawY - 6, 16, 12, 3);
        ctx.fill();
        
        // 夹子主体高光
        ctx.fillStyle = claw.grabbing ? '#FF8888' : '#999';
        ctx.beginPath();
        ctx.roundRect(clawX - 7, clawY - 5, 14, 6, 2);
        ctx.fill();
        
        // 绘制三个夹爪
        const clawColor = claw.grabbing ? '#FF0000' : '#555';
        const clawOpenAngle = claw.grabbing ? 0.3 : 0.6; // 抓取时角度变小（闭合）
        
            for (let i = -1; i <= 1; i++) {
            ctx.save();
            ctx.translate(clawX, clawY);
            const angle = i * clawOpenAngle; // 三个夹爪分开的角度
            ctx.rotate(angle);
            
            // 夹爪形状（类似钩子）
            ctx.fillStyle = clawColor;
                ctx.beginPath();
            
            if (i === 0) {
                // 中间夹爪（向下）
                ctx.moveTo(0, 0);
                ctx.lineTo(-3, 12);
                ctx.lineTo(3, 12);
                ctx.closePath();
                // 夹爪尖端
                ctx.moveTo(-2, 12);
                ctx.lineTo(-1, 16);
                ctx.lineTo(1, 16);
                ctx.lineTo(2, 12);
                ctx.closePath();
            } else {
                // 两侧夹爪（斜向）
                ctx.moveTo(0, 0);
                ctx.lineTo(-4, 10);
                ctx.lineTo(4, 10);
                ctx.closePath();
                // 夹爪尖端
                ctx.moveTo(-2, 10);
                ctx.lineTo(-1, 14);
                ctx.lineTo(1, 14);
                ctx.lineTo(2, 10);
                ctx.closePath();
            }
            
                ctx.fill();
            
            // 夹爪高光
            ctx.fillStyle = claw.grabbing ? '#FF6666' : '#777';
            ctx.beginPath();
            if (i === 0) {
                ctx.moveTo(-1, 2);
                ctx.lineTo(-1, 10);
                ctx.lineTo(1, 10);
                ctx.lineTo(1, 2);
                ctx.closePath();
            } else {
                ctx.moveTo(-1, 1);
                ctx.lineTo(-1, 8);
                ctx.lineTo(1, 8);
                ctx.lineTo(1, 1);
                ctx.closePath();
            }
            ctx.fill();
            
            ctx.restore();
        }
        
        // 夹子中心装饰点
        ctx.fillStyle = claw.grabbing ? '#FFAAAA' : '#AAA';
        ctx.beginPath();
        ctx.arc(clawX, clawY, 3, 0, Math.PI * 2);
        ctx.fill();
    }
    
    ctx.restore();
}

// Canvas roundRect polyfill（如果浏览器不支持）
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x, y, width, height, radius) {
        if (width < 2 * radius) radius = width / 2;
        if (height < 2 * radius) radius = height / 2;
        this.beginPath();
        this.moveTo(x + radius, y);
        this.arcTo(x + width, y, x + width, y + height, radius);
        this.arcTo(x + width, y + height, x, y + height, radius);
        this.arcTo(x, y + height, x, y, radius);
        this.arcTo(x, y, x + width, y, radius);
        this.closePath();
    };
}

// 绘制行星（使用Canvas模拟CSS样式）
function drawPlanet(ctx, x, y, radius, planetClass) {
        ctx.save();
    ctx.translate(x, y);
    
    const r = radius;
    
    switch(planetClass) {
        case 'moon':
            // 月球 - 按照CSS样式精确还原
            // 背景层 - 灰色渐变
            let moonBase = ctx.createRadialGradient(-r * 0.3, -r * 0.3, 0, 0, 0, r);
            moonBase.addColorStop(0, '#f7f7f7');
            moonBase.addColorStop(0.6, '#cfcfcf');
            moonBase.addColorStop(1, '#8f8f8f');
            ctx.fillStyle = moonBase;
        ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
        ctx.fill();
        
            // 高光层
            let moonHighlight = ctx.createRadialGradient(r * 0.36, r * 0.04, 0, 0, 0, r);
            moonHighlight.addColorStop(0, 'rgba(255, 255, 255, 0.14)');
            moonHighlight.addColorStop(0.18, 'transparent');
            ctx.fillStyle = moonHighlight;
        ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
        ctx.fill();
        
            // 阴影层
            let moonShadow = ctx.createRadialGradient(-r * 0.16, -r * 0.14, 0, 0, 0, r);
            moonShadow.addColorStop(0, 'rgba(0, 0, 0, 0.22)');
            moonShadow.addColorStop(0.2, 'transparent');
            ctx.fillStyle = moonShadow;
        ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
        ctx.fill();
        
            // 模拟inset阴影效果 - 使用覆盖层
            ctx.save();
            ctx.globalCompositeOperation = 'multiply';
            ctx.globalAlpha = 0.3;
            let moonInsetDark = ctx.createRadialGradient(r * 0.45, r * 0.45, 0, 0, 0, r * 1.5);
            moonInsetDark.addColorStop(0, 'transparent');
            moonInsetDark.addColorStop(0.6, 'rgba(0, 0, 0, 0.4)');
            ctx.fillStyle = moonInsetDark;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.globalCompositeOperation = 'screen';
            ctx.globalAlpha = 0.15;
            let moonInsetLight = ctx.createRadialGradient(-r * 0.35, -r * 0.35, 0, 0, 0, r * 1.2);
            moonInsetLight.addColorStop(0, 'rgba(255, 255, 255, 0.18)');
            moonInsetLight.addColorStop(0.5, 'transparent');
            ctx.fillStyle = moonInsetLight;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
        ctx.restore();

            // ::after伪元素效果 - 月球表面细节
        ctx.save();
            ctx.globalAlpha = 0.88;
            ctx.globalCompositeOperation = 'soft-light';
            let moonDetail1 = ctx.createRadialGradient(-r * 0.3, -r * 0.3, 0, 0, 0, r * 0.82);
            moonDetail1.addColorStop(0, 'rgba(0, 0, 0, 0.32)');
            moonDetail1.addColorStop(0.16, 'rgba(255, 255, 255, 0.18)');
            moonDetail1.addColorStop(0.22, 'transparent');
            ctx.fillStyle = moonDetail1;
            ctx.beginPath();
            ctx.arc(0, 0, r * 0.82, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            break;
            
        case 'mercury':
            // 水星
            let mercuryGrad1 = ctx.createRadialGradient(-r * 0.18, -r * 0.22, 0, 0, 0, r);
            mercuryGrad1.addColorStop(0, 'rgba(255, 255, 255, 0.32)');
            mercuryGrad1.addColorStop(0.18, 'transparent');
            ctx.fillStyle = mercuryGrad1;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            let mercuryGrad2 = ctx.createRadialGradient(r * 0.18, r * 0.12, 0, 0, 0, r);
            mercuryGrad2.addColorStop(0, 'rgba(0, 0, 0, 0.35)');
            mercuryGrad2.addColorStop(0.2, 'transparent');
            ctx.fillStyle = mercuryGrad2;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            let mercuryGrad3 = ctx.createLinearGradient(-r * 0.6, -r * 0.4, r * 0.6, r * 0.4);
            mercuryGrad3.addColorStop(0, '#d6cec0');
            mercuryGrad3.addColorStop(0.38, '#b39e87');
            mercuryGrad3.addColorStop(0.62, '#7a5c48');
            mercuryGrad3.addColorStop(0.82, '#37281f');
            mercuryGrad3.addColorStop(1, '#0d0d0d');
            ctx.fillStyle = mercuryGrad3;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            break;
            
        case 'venus':
            // 金星
            let venusGrad1 = ctx.createRadialGradient(r * 0.02, -r * 0.22, 0, 0, 0, r);
            venusGrad1.addColorStop(0, 'rgba(255, 233, 180, 0.28)');
            venusGrad1.addColorStop(0.34, 'transparent');
            ctx.fillStyle = venusGrad1;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            let venusGrad2 = ctx.createLinearGradient(-r * 0.7, -r * 0.5, r * 0.7, r * 0.5);
            venusGrad2.addColorStop(0, '#ffe1ad');
            venusGrad2.addColorStop(0.45, '#f4b565');
            venusGrad2.addColorStop(1, '#c97d31');
            ctx.fillStyle = venusGrad2;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            break;
            
        case 'earth':
            // 地球 - 按照CSS样式精确还原
            // 主体 - 蓝色海洋渐变（radial-gradient circle at 35% 30%）
            // CSS的35% 30%表示从左35%，从上30%（Canvas坐标系：x向右为正，y向下为正）
            // 所以35% = r * 0.35（向右），30% = -r * 0.30（向上，所以是负值）
            let earthOcean = ctx.createRadialGradient(r * 0.35, -r * 0.30, 0, 0, 0, r);
            earthOcean.addColorStop(0, '#4a90e2');
            earthOcean.addColorStop(0.25, '#2e7cd6');
            earthOcean.addColorStop(0.5, '#1e5fa8');
            earthOcean.addColorStop(0.75, '#0f3f86');
            earthOcean.addColorStop(1, '#051b43');
            ctx.fillStyle = earthOcean;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            // 外发光效果（box-shadow + filter drop-shadow）
            ctx.save();
            ctx.shadowBlur = 25;
            ctx.shadowColor = 'rgba(78, 205, 196, 0.3)';
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 0;
            ctx.fillStyle = earthOcean;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            ctx.save();
            ctx.shadowBlur = 40;
            ctx.shadowColor = 'rgba(52, 152, 219, 0.2)';
            ctx.fillStyle = earthOcean;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            // inset阴影效果
            ctx.save();
            ctx.globalCompositeOperation = 'multiply';
            ctx.globalAlpha = 0.25;
            let earthInsetDark = ctx.createRadialGradient(r * 0.4, r * 0.4, 0, 0, 0, r * 1.5);
            earthInsetDark.addColorStop(0, 'transparent');
            earthInsetDark.addColorStop(0.5, 'rgba(0, 0, 0, 0.4)');
            ctx.fillStyle = earthInsetDark;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            ctx.save();
            ctx.globalCompositeOperation = 'screen';
            ctx.globalAlpha = 0.15;
            let earthInsetLight = ctx.createRadialGradient(-r * 0.3, -r * 0.3, 0, 0, 0, r * 1.2);
            earthInsetLight.addColorStop(0, 'rgba(255, 255, 255, 0.2)');
            earthInsetLight.addColorStop(0.5, 'transparent');
            ctx.fillStyle = earthInsetLight;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            // ::before伪元素 - 外层光晕（inset -3%，渐变中心也在35% 30%）
            ctx.save();
            ctx.globalAlpha = 0.6;
            let earthHalo = ctx.createRadialGradient(r * 0.35, -r * 0.30, r * 0.85, 0, 0, r * 1.03);
            earthHalo.addColorStop(0, 'transparent');
            earthHalo.addColorStop(0.85, 'transparent');
            earthHalo.addColorStop(0.90, 'rgba(135, 206, 250, 0.25)');
            earthHalo.addColorStop(0.95, 'rgba(78, 205, 196, 0.15)');
            earthHalo.addColorStop(1, 'transparent');
            ctx.fillStyle = earthHalo;
            ctx.beginPath();
            ctx.arc(0, 0, r * 1.03, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            // ::after伪元素 - 详细的大陆、海洋、云层（inset 7%）
            ctx.save();
            ctx.globalAlpha = 1.0;
            const innerR = r * 0.93; // inset 7%
            
            // 海洋高光层
            let oceanHighlight1 = ctx.createRadialGradient(innerR * 0.05, innerR * 0.25, 0, 0, 0, innerR);
            oceanHighlight1.addColorStop(0, 'rgba(135, 206, 250, 0.35)');
            oceanHighlight1.addColorStop(0.4, 'transparent');
            ctx.fillStyle = oceanHighlight1;
            ctx.beginPath();
            ctx.arc(0, 0, innerR, 0, Math.PI * 2);
            ctx.fill();
            
            let oceanHighlight2 = ctx.createRadialGradient(-innerR * 0.2, -innerR * 0.15, 0, 0, 0, innerR);
            oceanHighlight2.addColorStop(0, 'rgba(52, 152, 219, 0.25)');
            oceanHighlight2.addColorStop(0.32, 'transparent');
            ctx.fillStyle = oceanHighlight2;
            ctx.beginPath();
            ctx.arc(0, 0, innerR, 0, Math.PI * 2);
            ctx.fill();
            
            // ========== 增加更多绿色大陆区域，让地球更明显 ==========
            
            // 大陆 - 北美（更大更明显）
            ctx.fillStyle = 'rgba(46, 204, 113, 0.95)'; // 更鲜艳的绿色
            ctx.beginPath();
            ctx.arc(-innerR * 0.24, -innerR * 0.10, innerR * 0.16, 0, Math.PI * 2);
            ctx.fill();
            // 北美延伸部分
            ctx.fillStyle = 'rgba(39, 174, 96, 0.92)';
            ctx.beginPath();
            ctx.arc(-innerR * 0.30, -innerR * 0.05, innerR * 0.12, 0, Math.PI * 2);
            ctx.fill();
            
            // 大陆 - 南美（更大）
            ctx.fillStyle = 'rgba(46, 204, 113, 0.92)';
            ctx.beginPath();
            ctx.arc(-innerR * 0.22, innerR * 0.15, innerR * 0.15, 0, Math.PI * 2);
            ctx.fill();
            // 南美延伸
            ctx.fillStyle = 'rgba(39, 174, 96, 0.88)';
            ctx.beginPath();
            ctx.arc(-innerR * 0.18, innerR * 0.25, innerR * 0.10, 0, Math.PI * 2);
            ctx.fill();
            
            // 大陆 - 非洲（更大更明显）
            ctx.fillStyle = 'rgba(33, 140, 58, 0.95)';
            ctx.beginPath();
            ctx.arc(innerR * 0.10, innerR * 0.00, innerR * 0.20, 0, Math.PI * 2);
            ctx.fill();
            // 非洲延伸
            ctx.fillStyle = 'rgba(39, 174, 96, 0.90)';
            ctx.beginPath();
            ctx.arc(innerR * 0.05, innerR * 0.12, innerR * 0.14, 0, Math.PI * 2);
            ctx.fill();
            
            // 大陆 - 欧洲+亚洲（更大区域）
            ctx.fillStyle = 'rgba(46, 204, 113, 0.93)';
            ctx.beginPath();
            ctx.arc(innerR * 0.22, -innerR * 0.05, innerR * 0.18, 0, Math.PI * 2);
            ctx.fill();
            // 亚洲中部
            ctx.fillStyle = 'rgba(39, 174, 96, 0.95)';
            ctx.beginPath();
            ctx.arc(innerR * 0.28, innerR * 0.08, innerR * 0.15, 0, Math.PI * 2);
            ctx.fill();
            // 东亚
            ctx.fillStyle = 'rgba(33, 140, 58, 0.90)';
            ctx.beginPath();
            ctx.arc(innerR * 0.35, innerR * 0.02, innerR * 0.12, 0, Math.PI * 2);
            ctx.fill();
            
            // 大陆 - 东南亚/澳洲区域（新增）
            ctx.fillStyle = 'rgba(46, 204, 113, 0.88)';
            ctx.beginPath();
            ctx.arc(innerR * 0.32, innerR * 0.20, innerR * 0.12, 0, Math.PI * 2);
            ctx.fill();
            
            // 大陆 - 中东/印度区域（新增，部分沙漠色+绿色）
            ctx.fillStyle = 'rgba(210, 180, 140, 0.75)'; // 沙漠色
            ctx.beginPath();
            ctx.arc(innerR * 0.20, innerR * 0.08, innerR * 0.10, 0, Math.PI * 2);
            ctx.fill();
            // 绿色覆盖部分
            ctx.fillStyle = 'rgba(39, 174, 96, 0.85)';
            ctx.beginPath();
            ctx.arc(innerR * 0.18, innerR * 0.10, innerR * 0.08, 0, Math.PI * 2);
            ctx.fill();
            
            // 大陆 - 南美洲北部延伸（新增）
            ctx.fillStyle = 'rgba(46, 204, 113, 0.90)';
            ctx.beginPath();
            ctx.arc(-innerR * 0.16, innerR * 0.05, innerR * 0.10, 0, Math.PI * 2);
            ctx.fill();
            
            // 云层（减少云层遮挡，让大陆更明显）
            ctx.globalAlpha = 0.4; // 降低云层不透明度
            ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
            ctx.beginPath();
            ctx.ellipse(innerR * -0.04, -innerR * 0.18, innerR * 0.18, innerR * 0.08, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.ellipse(innerR * 0.14, innerR * 0.20, innerR * 0.15, innerR * 0.07, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // 极地
            ctx.fillStyle = 'rgba(255, 255, 255, 0.82)';
            ctx.beginPath();
            ctx.arc(0, -innerR * 0.86, innerR * 0.11, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.fillStyle = 'rgba(255, 255, 255, 0.78)';
            ctx.beginPath();
            ctx.arc(0, innerR * 0.86, innerR * 0.13, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            break;
            
        case 'mars':
            // 火星
            let marsGrad1 = ctx.createRadialGradient(-r * 0.3, -r * 0.28, 0, 0, 0, r);
            marsGrad1.addColorStop(0, 'rgba(255, 213, 160, 0.2)');
            marsGrad1.addColorStop(0.28, 'transparent');
            ctx.fillStyle = marsGrad1;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            let marsGrad2 = ctx.createLinearGradient(-r * 0.7, -r * 0.5, r * 0.7, r * 0.5);
            marsGrad2.addColorStop(0, '#e07b3c');
            marsGrad2.addColorStop(0.55, '#b04a20');
            marsGrad2.addColorStop(1, '#5d230e');
            ctx.fillStyle = marsGrad2;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            break;
            
        case 'jupiter':
            // 木星 - 按照CSS样式精确还原
            // CSS: repeating-linear-gradient(160deg, #f8e7c3 0%, #f1d9ae 8%, #cf9d6a 8%, #cf9d6a 16%, #b67a45 16%, #b67a45 24%, #90613b 24%, #90613b 32%)
            // background-size: 140% 140%
            ctx.save();
            
            // 创建圆形裁剪区域
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.clip();
            
            // 基础底色
            ctx.fillStyle = '#f8e7c3';
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            // 绘制条纹 - 先旋转到160度
            ctx.save();
            ctx.rotate(Math.PI * 160 / 180); // 160度
            
            // 计算条纹尺寸（考虑140%的background-size）
            // 一个完整模式是32%（0%到32%），但background-size是140%，所以实际模式更小
            const patternSize = r * 2 * 0.32; // 32%的直径
            const scaledPatternSize = patternSize / 1.4; // 除以140%得到实际大小
            
            // 条纹颜色和位置（按CSS定义）
            const stripes = [
                { start: 0, end: 0.08, color1: '#f8e7c3', color2: '#f1d9ae' },
                { start: 0.08, end: 0.16, color1: '#cf9d6a', color2: '#cf9d6a' },
                { start: 0.16, end: 0.24, color1: '#b67a45', color2: '#b67a45' },
                { start: 0.24, end: 0.32, color1: '#90613b', color2: '#90613b' }
            ];
            
            // 绘制重复的条纹模式（覆盖整个圆形区域）
            const totalCoverage = r * 2 * 1.4; // 140%覆盖
            const startPos = -totalCoverage / 2;
            const numPatterns = Math.ceil(totalCoverage / scaledPatternSize) + 2;
            
            for (let p = -1; p <= numPatterns; p++) {
                const patternBaseY = startPos + p * scaledPatternSize;
                
                // 绘制这个模式中的每个条纹
                stripes.forEach(stripe => {
                    const y1 = patternBaseY + stripe.start * scaledPatternSize;
                    const y2 = patternBaseY + stripe.end * scaledPatternSize;
                    const height = y2 - y1;
                    
                    if (height > 0) {
                        let grad = ctx.createLinearGradient(0, y1, 0, y2);
                        grad.addColorStop(0, stripe.color1);
                        grad.addColorStop(1, stripe.color2);
                        ctx.fillStyle = grad;
                        ctx.fillRect(-r * 1.4, y1, r * 2.8, height);
                    }
                });
            }
            
            ctx.restore();
            ctx.restore();
            
            // inset阴影
        ctx.save();
            ctx.globalCompositeOperation = 'multiply';
            ctx.globalAlpha = 0.25;
            let jupiterInsetDark = ctx.createRadialGradient(r * 0.45, r * 0.45, 0, 0, 0, r * 1.5);
            jupiterInsetDark.addColorStop(0, 'transparent');
            jupiterInsetDark.addColorStop(0.6, 'rgba(0, 0, 0, 0.38)');
            ctx.fillStyle = jupiterInsetDark;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            ctx.save();
            ctx.globalCompositeOperation = 'screen';
            ctx.globalAlpha = 0.15;
            let jupiterInsetLight = ctx.createRadialGradient(-r * 0.35, -r * 0.35, 0, 0, 0, r * 1.2);
            jupiterInsetLight.addColorStop(0, 'rgba(255, 255, 255, 0.18)');
            jupiterInsetLight.addColorStop(0.5, 'transparent');
            ctx.fillStyle = jupiterInsetLight;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            // ::after伪元素 - 大红斑和细节（inset 14%）
            ctx.save();
            ctx.globalAlpha = 0.85;
            ctx.globalCompositeOperation = 'lighten';
            const jupiterInnerR = r * 0.86;
            // 大红斑
            let redSpot = ctx.createRadialGradient(jupiterInnerR * 0.12, jupiterInnerR * 0.14, 0, 0, 0, jupiterInnerR);
            redSpot.addColorStop(0, 'rgba(231, 76, 60, 0.65)');
            redSpot.addColorStop(0.22, 'rgba(211, 84, 0, 0.38)');
            redSpot.addColorStop(0.30, 'transparent');
            ctx.fillStyle = redSpot;
            ctx.beginPath();
            ctx.ellipse(jupiterInnerR * 0.62, jupiterInnerR * 0.64, jupiterInnerR * 0.22, jupiterInnerR * 0.18, 0.28, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            break;
            
        case 'saturn':
            // 土星 - 先绘制光环（在背后）
            ctx.strokeStyle = 'rgba(255, 222, 173, 0.7)';
            ctx.lineWidth = r * 0.12;
            ctx.beginPath();
            ctx.ellipse(0, 0, r * 1.2, r * 0.42, -0.3, 0, Math.PI * 2);
            ctx.stroke();
            
            // 再绘制土星主体（在前面）
            let saturnGrad1 = ctx.createRadialGradient(r * 0.12, -r * 0.24, 0, 0, 0, r);
            saturnGrad1.addColorStop(0, 'rgba(255, 240, 199, 0.22)');
            saturnGrad1.addColorStop(0.3, 'transparent');
            ctx.fillStyle = saturnGrad1;
        ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
        ctx.fill();
        
            let saturnGrad2 = ctx.createLinearGradient(-r * 0.7, -r * 0.5, r * 0.7, r * 0.5);
            saturnGrad2.addColorStop(0, '#ffe3aa');
            saturnGrad2.addColorStop(0.48, '#e0ba6e');
            saturnGrad2.addColorStop(1, '#b67d32');
            ctx.fillStyle = saturnGrad2;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            break;
            
        case 'uranus':
            // 天王星
            let uranusGrad1 = ctx.createRadialGradient(r * 0.04, -r * 0.24, 0, 0, 0, r);
            uranusGrad1.addColorStop(0, 'rgba(255, 255, 255, 0.22)');
            uranusGrad1.addColorStop(0.28, 'transparent');
            ctx.fillStyle = uranusGrad1;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            let uranusGrad2 = ctx.createLinearGradient(-r * 0.7, -r * 0.5, r * 0.7, r * 0.5);
            uranusGrad2.addColorStop(0, '#c6f9ff');
            uranusGrad2.addColorStop(0.45, '#6bc8dd');
            uranusGrad2.addColorStop(1, '#1d6a8c');
            ctx.fillStyle = uranusGrad2;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            break;
            
        case 'neptune':
            // 海王星
            let neptuneGrad1 = ctx.createRadialGradient(r * 0.10, -r * 0.22, 0, 0, 0, r);
            neptuneGrad1.addColorStop(0, 'rgba(255, 255, 255, 0.18)');
            neptuneGrad1.addColorStop(0.24, 'transparent');
            ctx.fillStyle = neptuneGrad1;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            let neptuneGrad2 = ctx.createLinearGradient(-r * 0.7, -r * 0.5, r * 0.7, r * 0.5);
            neptuneGrad2.addColorStop(0, '#7bc4ff');
            neptuneGrad2.addColorStop(0.55, '#2a68d5');
            neptuneGrad2.addColorStop(1, '#0b226a');
            ctx.fillStyle = neptuneGrad2;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            break;
            
        case 'sun':
            // 太阳 - 按照CSS样式精确还原
            // 主体渐变
            let sunGrad = ctx.createRadialGradient(0, 0, 0, 0, 0, r);
            sunGrad.addColorStop(0, '#ffffff');
            sunGrad.addColorStop(0.15, '#fff6a1');
            sunGrad.addColorStop(0.3, '#ffd700');
            sunGrad.addColorStop(0.5, '#ffb347');
            sunGrad.addColorStop(0.7, '#ff8c42');
            sunGrad.addColorStop(0.85, '#ff6b6b');
            sunGrad.addColorStop(1, '#d63031');
            
            // 多层外发光效果（box-shadow）
            ctx.save();
            ctx.shadowBlur = 100;
            ctx.shadowColor = 'rgba(255, 102, 0, 0.3)';
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 0;
            ctx.fillStyle = sunGrad;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
        ctx.restore();
            
            ctx.save();
            ctx.shadowBlur = 80;
            ctx.shadowColor = 'rgba(255, 153, 0, 0.4)';
            ctx.fillStyle = sunGrad;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            ctx.save();
            ctx.shadowBlur = 60;
            ctx.shadowColor = 'rgba(255, 204, 0, 0.5)';
            ctx.fillStyle = sunGrad;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            ctx.save();
            ctx.shadowBlur = 40;
            ctx.shadowColor = 'rgba(255, 255, 0, 0.6)';
            ctx.fillStyle = sunGrad;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            // 主体
            ctx.fillStyle = sunGrad;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            
            // inset阴影
            ctx.save();
            ctx.globalCompositeOperation = 'multiply';
            ctx.globalAlpha = 0.25;
            let sunInsetDark = ctx.createRadialGradient(r * 0.3, r * 0.3, 0, 0, 0, r * 1.2);
            sunInsetDark.addColorStop(0, 'transparent');
            sunInsetDark.addColorStop(0.6, 'rgba(255, 94, 0, 0.35)');
            ctx.fillStyle = sunInsetDark;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            ctx.save();
            ctx.globalCompositeOperation = 'screen';
            ctx.globalAlpha = 0.15;
            let sunInsetLight = ctx.createRadialGradient(-r * 0.3, -r * 0.3, 0, 0, 0, r * 1.0);
            sunInsetLight.addColorStop(0, 'rgba(255, 255, 200, 0.2)');
            sunInsetLight.addColorStop(0.5, 'transparent');
            ctx.fillStyle = sunInsetLight;
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            
            // ::after伪元素 - 表面细节（inset 4%）
            ctx.save();
            ctx.globalAlpha = 0.95;
            ctx.globalCompositeOperation = 'screen';
            const sunInnerR = r * 0.96;
            // 多个高光点
            let sunDetail1 = ctx.createRadialGradient(sunInnerR * 0.25, sunInnerR * -0.15, 0, 0, 0, sunInnerR);
            sunDetail1.addColorStop(0, 'rgba(255, 255, 150, 0.5)');
            sunDetail1.addColorStop(0.20, 'transparent');
            ctx.fillStyle = sunDetail1;
            ctx.beginPath();
            ctx.arc(0, 0, sunInnerR, 0, Math.PI * 2);
            ctx.fill();
            
            let sunDetail2 = ctx.createRadialGradient(sunInnerR * -0.25, sunInnerR * 0.15, 0, 0, 0, sunInnerR);
            sunDetail2.addColorStop(0, 'rgba(255, 200, 100, 0.4)');
            sunDetail2.addColorStop(0.18, 'transparent');
            ctx.fillStyle = sunDetail2;
            ctx.beginPath();
            ctx.arc(0, 0, sunInnerR, 0, Math.PI * 2);
            ctx.fill();
            
            let sunDetail3 = ctx.createRadialGradient(0, 0, 0, 0, 0, sunInnerR);
            sunDetail3.addColorStop(0, 'rgba(255, 255, 255, 0.4)');
            sunDetail3.addColorStop(0.15, 'transparent');
            ctx.fillStyle = sunDetail3;
            ctx.beginPath();
            ctx.arc(0, 0, sunInnerR, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
            break;
            
        default:
            // 默认灰色
            ctx.fillStyle = '#7f8c8d';
            ctx.beginPath();
            ctx.arc(0, 0, r, 0, Math.PI * 2);
            ctx.fill();
    }
    
    ctx.restore();
}

// 绘制碎片（包括普通碎片和石头）- 使用Canvas绘制
function drawFragments() {
    // 按大小从大到小排序，这样小物体（如地球）会绘制在上层，不会被大物体遮挡
    const sortedFragments = [...gameState.fragments].sort((a, b) => b.size - a.size);
    
    sortedFragments.forEach(fragment => {
        ctx.save();
        
        // 如果是石头类型，绘制石头外观
        if (fragment.type === 'rock') {
            // 石头外观（灰色石头）
            ctx.fillStyle = '#666';
            ctx.beginPath();
            ctx.arc(fragment.x, fragment.y, fragment.size, 0, Math.PI * 2);
            ctx.fill();
            
            // 石头纹理
            ctx.fillStyle = '#555';
            ctx.beginPath();
            ctx.arc(fragment.x - fragment.size * 0.3, fragment.y - fragment.size * 0.3, fragment.size * 0.4, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.beginPath();
            ctx.arc(fragment.x + fragment.size * 0.3, fragment.y + fragment.size * 0.3, fragment.size * 0.3, 0, Math.PI * 2);
            ctx.fill();
            
            // 绘制emoji
            ctx.fillStyle = '#999';
            ctx.font = fragment.size + 'px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(fragment.emoji, fragment.x, fragment.y);
            
            // 如果石头受到伤害，显示伤害指示器
            if (fragment.hitsRequired > 1 && fragment.currentHits > 0) {
                const hitsRequired = fragment.hitsRequired || 1;
                const currentHits = fragment.currentHits || 0;
                const damageRatio = currentHits / hitsRequired;
                
                // 绘制伤害进度条（在石头下方）
                const barWidth = fragment.size * 2;
                const barHeight = 4;
                const barX = fragment.x - barWidth / 2;
                const barY = fragment.y + fragment.size + 8;
                
                // 背景
                ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                ctx.fillRect(barX, barY, barWidth, barHeight);
                
                // 伤害进度（红色到黄色）
                const gradient = ctx.createLinearGradient(barX, barY, barX + barWidth, barY);
                gradient.addColorStop(0, '#FF4444');
                gradient.addColorStop(0.5, '#FFAA00');
                gradient.addColorStop(1, '#FFFF00');
                ctx.fillStyle = gradient;
                ctx.fillRect(barX, barY, barWidth * damageRatio, barHeight);
                
                // 边框
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
                ctx.lineWidth = 1;
                ctx.strokeRect(barX, barY, barWidth, barHeight);
                
                // 显示伤害数字（可选）
                ctx.fillStyle = '#FFFFFF';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(`${currentHits}/${hitsRequired}`, fragment.x, barY - 4);
            }
        } else if (fragment.planetClass) {
            // 使用Canvas绘制行星（模拟CSS样式效果）
            drawPlanet(ctx, fragment.x, fragment.y, fragment.size, fragment.planetClass);
        } else {
            // 负分碎片 - 使用emoji，添加红色警告边框
        if (fragment.type === 'bad') {
            const warningAlpha = Math.sin(Date.now() * 0.008) * 0.1 + 0.2;
            ctx.strokeStyle = `rgba(255, 100, 100, ${warningAlpha})`;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(fragment.x, fragment.y, fragment.size * 1.2, 0, Math.PI * 2);
            ctx.stroke();
        }
        
        // 绘制emoji
        ctx.font = fragment.size + 'px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(fragment.emoji, fragment.x, fragment.y);
        }
        
        ctx.restore();
    });
}

// 计算机械手当前位置（统一使用cos/sin计算）
function getClawPosition() {
    const ship = gameState.ship;
    const claw = gameState.claw;
    // canvas坐标系：0度向右，角度逆时针增加
    // 从飞船前端开始计算（飞船中心 + 前端半径 + 爪子长度）
    const shipFrontX = ship.x + Math.cos(claw.angle) * ship.radius;
    const shipFrontY = ship.y + Math.sin(claw.angle) * ship.radius;
    const clawX = shipFrontX + Math.cos(claw.angle) * claw.length;
    const clawY = shipFrontY + Math.sin(claw.angle) * claw.length;
    return { x: clawX, y: clawY };
}

// 检查机械手路径是否碰到石头（阻挡）- 检查整条路径
function checkRockCollision(rock) {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    if (!claw.extended || claw.length === 0) return false;
    
    // 飞船前端位置
    const shipFrontX = ship.x + Math.cos(claw.angle) * ship.radius;
    const shipFrontY = ship.y + Math.sin(claw.angle) * ship.radius;
    
    // 检查整条路径（从飞船前端到爪子当前位置）
    // 简化：检查路径上的多个点
    const checkPoints = 10; // 检查10个点
    for (let i = 1; i <= checkPoints; i++) {
        const checkLength = (claw.length / checkPoints) * i;
        const checkX = shipFrontX + Math.cos(claw.angle) * checkLength;
        const checkY = shipFrontY + Math.sin(claw.angle) * checkLength;
        
        const dx = checkX - rock.x;
        const dy = checkY - rock.y;
    const distance = Math.sqrt(dx * dx + dy * dy);
    
        if (distance < rock.size + 8) { // 8是爪子半径
            return true;
        }
}

    return false;
}

// 检查碰撞（碎片）- 检查整个路径，不只是末端
function checkCollision(fragment) {
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    if (!claw.extended || claw.length === 0) return false;
    
    // 飞船前端位置
    const shipFrontX = ship.x + Math.cos(claw.angle) * ship.radius;
    const shipFrontY = ship.y + Math.sin(claw.angle) * ship.radius;
    
    // 检查整条路径（从飞船前端到爪子当前位置）
    // 简化：检查路径上的多个点，确保不会错过碎片
    const checkPoints = 15; // 检查15个点，更密集
    for (let i = 1; i <= checkPoints; i++) {
        const checkLength = (claw.length / checkPoints) * i;
        const checkX = shipFrontX + Math.cos(claw.angle) * checkLength;
        const checkY = shipFrontY + Math.sin(claw.angle) * checkLength;
        
        const dx = checkX - fragment.x;
        const dy = checkY - fragment.y;
    const distance = Math.sqrt(dx * dx + dy * dy);
    
        if (distance < fragment.size + 8) { // 8是爪子半径
            return true;
        }
    }
    
    return false;
}

// 发射追踪弹
function fireMissile() {
    if (!gameState.claw.grabbing || !gameState.claw.grabbedItem) {
        return;
    }
    
    const ship = gameState.ship;
    const target = gameState.claw.grabbedItem;
    
    // 正向的10大天体不能被子弹打碎（只能抓取）
    if (target.type === 'good' && target.planetClass) {
        return; // 不发射追踪弹
    }
    
    // 从飞船左右两端各发射一发追踪弹
    const leftOffsetX = Math.cos(ship.angle - Math.PI / 2) * 25; // 左侧偏移
    const leftOffsetY = Math.sin(ship.angle - Math.PI / 2) * 25;
    const rightOffsetX = Math.cos(ship.angle + Math.PI / 2) * 25; // 右侧偏移
    const rightOffsetY = Math.sin(ship.angle + Math.PI / 2) * 25;
    
    // 左侧追踪弹
    gameState.missiles.push({
        x: ship.x + leftOffsetX,
        y: ship.y + leftOffsetY,
        target: target,
        speed: 0.8, // 像素/毫秒
        radius: 4,
        active: true
    });
    
    // 右侧追踪弹
    gameState.missiles.push({
        x: ship.x + rightOffsetX,
        y: ship.y + rightOffsetY,
        target: target,
        speed: 0.8,
        radius: 4,
        active: true
    });
    
}

// 更新游戏
function updateGame(deltaTime) {
    if (!gameState.started || gameState.gameOver) return;
    
    const ship = gameState.ship;
    const claw = gameState.claw;
    
    // 更新追踪弹
    gameState.missiles = gameState.missiles.filter(missile => {
        if (!missile.active || !missile.target) {
            return false;
        }
        
        // 计算到目标的向量
        const dx = missile.target.x - missile.x;
        const dy = missile.target.y - missile.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        // 如果目标已被移除或距离为0，移除追踪弹
        if (distance < missile.target.size + missile.radius) {
            // 命中目标，对物体造成伤害
            const targetFragment = gameState.fragments.find(f => f.id === missile.target.id);
            if (targetFragment) {
                // 正向的10大天体不能被子弹打碎（只能抓取）
                if (targetFragment.type === 'good' && targetFragment.planetClass) {
                    // 正向天体不受伤害，追踪弹直接消失
                    return false;
                }
                
                // 初始化生命值系统（如果还没有）
                if (targetFragment.hitsRequired === undefined) {
                    // 非石头物体默认1发即可摧毁
                    targetFragment.hitsRequired = 1;
                    targetFragment.currentHits = 0;
                }
                
                // 增加受到的伤害
                targetFragment.currentHits = (targetFragment.currentHits || 0) + 1;
                
                // 检查是否达到所需子弹数
                if (targetFragment.currentHits >= targetFragment.hitsRequired) {
                    // 物体被摧毁
                    // 如果是石头，获得分值（炸碎得分更高，为原来的50%）
                    if (targetFragment.type === 'rock' && targetFragment.score) {
                        const difficultyMultiplier = gameState.scoreMultiplier || 1;
                        // 炸碎碎石得分（为原来基础分数的50%，比抓取的20%高）
                        const rockScore = Math.round(targetFragment.score * 0.5 * difficultyMultiplier);
                        gameState.score += rockScore;
                        
                        // 前端分数限制：不能超过理论最大分数
                        if (gameState.score > gameState.maxPossibleScore) {
                            gameState.score = gameState.maxPossibleScore;
                        }
                        gameState.caught++;
                    }
                    
                    // 如果这个物体是被抓到的物体，释放机械臂并标记为收回状态
                    if (claw.grabbedItem && claw.grabbedItem.id === missile.target.id) {
                        claw.grabbedItem = null;
                        claw.grabbing = false;
                        // 确保机械臂开始收回（如果是伸出状态，标记为收回）
                        if (claw.extended && !claw.retracting) {
                            claw.retracting = true;
                        }
                    }
                    
                    // 移除碎片
                    gameState.fragments = gameState.fragments.filter(f => f.id !== missile.target.id);
                } else {
                    // 物体还未被摧毁，但同步更新被抓物体的状态
                    if (claw.grabbedItem && claw.grabbedItem.id === missile.target.id) {
                        claw.grabbedItem.currentHits = targetFragment.currentHits;
                    }
                }
            }
            return false; // 追踪弹命中后移除
        }
        
        // 检查目标是否已经被移除（例如被其他追踪弹炸毁）
        const targetStillExists = gameState.fragments.find(f => f.id === missile.target.id);
        if (!targetStillExists) {
            return false; // 目标已不存在，移除追踪弹
        }
        
        // 追踪目标
        const angle = Math.atan2(dy, dx);
        missile.x += Math.cos(angle) * missile.speed * deltaTime;
        missile.y += Math.sin(angle) * missile.speed * deltaTime;
        
        return true;
    });
    
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
        // 判断当前状态：是否应该收回
        // 收回条件：1. 正在收回状态  2. 达到最大长度  3. 抓到碎片
        const shouldRetract = claw.retracting || claw.length >= claw.maxLength || claw.grabbing;
        
        if (!shouldRetract) {
            // ========== 伸出阶段 ==========
            // 只有在长度小于最大值且没抓到东西时才伸出
            // 增加长度
            claw.length += claw.extendSpeed * deltaTime;
            
            // 限制最大长度
            if (claw.length >= claw.maxLength) {
                claw.length = claw.maxLength;
                // 达到最大长度，标记为收回状态
                claw.retracting = true;
            }
            
            // 检查碰撞：检查所有物体（包括碎石，碎石可抓但得分极低）
            if (!claw.retracting && claw.length < claw.maxLength) {
                // 检查碎片（包括碎石）
                    for (let fragment of gameState.fragments) {
                        if (checkCollision(fragment)) {
                            // 抓到物体，标记并停止伸出
                            // 保持当前长度，从物体位置直接收回
                            claw.grabbing = true;
                            claw.grabbedItem = fragment;
                            claw.retracting = true; // 标记为收回状态
                            // 记录抓取时的距离（用于得分加成）
                            claw.grabDistance = claw.length;
                            // 更新物体位置到爪子当前位置
                            const pos = getClawPosition();
                            claw.grabbedItem.x = pos.x;
                            claw.grabbedItem.y = pos.y;
                            break;
                    }
                }
            }
        } else {
            // ========== 收回阶段 ==========
            // 确保收回状态已标记
            claw.retracting = true;
            
            // 确保长度不超过最大值
            if (claw.length > claw.maxLength) {
                claw.length = claw.maxLength;
            }
            
            if (claw.grabbing && claw.grabbedItem) {
                // 收回（带碎片，从物体位置直接收回，速度受质量影响）
                // 质量公式：mass = (size/基准size)²，质量越大，回收越慢
                // 回收速度 = 基础速度 / (1 + 质量系数 * 质量)
                // 质量系数控制回收速度的减缓程度
                const MASS_FACTOR = 0.15; // 质量系数，可调整
                const mass = claw.grabbedItem.weight || calculateMass(claw.grabbedItem.size);
                const speedMultiplier = 1 / (1 + MASS_FACTOR * mass);
                const retractSpeed = claw.retractSpeed * speedMultiplier * deltaTime;
                claw.length -= retractSpeed;
                
                // 更新碎片位置跟随爪子（从当前位置开始收回）
                const pos = getClawPosition();
                claw.grabbedItem.x = pos.x;
                claw.grabbedItem.y = pos.y;
            } else {
                // 收回（空手）- 最快速度
                claw.length -= (claw.retractSpeed * 1.5) * deltaTime;
            }
            
            // 确保长度不小于0
            if (claw.length < 0) {
                claw.length = 0;
            }
            
            // 检查是否回到飞船
            if (claw.length <= 0) {
                claw.length = 0;
                
                // 如果抓到了碎片，处理得分
                if (claw.grabbing && claw.grabbedItem) {
                    // 计算基础分数
                    let baseScore = claw.grabbedItem.score;
                    
                    // 如果是碎石，抓取时得分极低（只有基础分数的20%）
                    if (claw.grabbedItem.type === 'rock') {
                        baseScore = Math.round(baseScore * 0.2); // 碎石抓取得分极低
                    }
                    
                    // 应用距离加成：距离越远，加成越高
                    const distanceBonus = calculateDistanceBonus(claw.grabDistance, claw.maxLength);
                    const difficultyMultiplier = gameState.scoreMultiplier || 1;
                    const finalScore = Math.round(baseScore * distanceBonus * difficultyMultiplier);
                    
                    // 获得分数（应用距离加成后）
                    gameState.score += finalScore;
                    
                    // 如果是天体（good类型且有planetClass），记录已收集
                    if (claw.grabbedItem.type === 'good' && claw.grabbedItem.planetClass) {
                        gameState.collectedPlanets.add(claw.grabbedItem.planetClass);
                        
                        // 检查是否集齐10个天体
                        if (gameState.collectedPlanets.size === 10 && !gameState.collectionComplete) {
                            gameState.collectionComplete = true;
                            // 额外增加1000分
                            const bonusScore = Math.round(1000 * gameState.scoreMultiplier);
                            gameState.score += bonusScore;
                            // 显示集齐特效
                            showCollectionCompleteEffect();
                        }
                    }
                    
                    // 前端分数限制：不能超过理论最大分数
                    if (gameState.score > gameState.maxPossibleScore) {
                        gameState.score = gameState.maxPossibleScore;
                    }
                    
                    if (gameState.score < 0) {
                        gameState.score = 0;
                    }
                    gameState.caught++;
                    
                    // 移除碎片
                    // 移除碎片
                    gameState.fragments = gameState.fragments.filter(f => f.id !== claw.grabbedItem.id);
                    
                    // 检查是否达到目标
                    if (gameState.score >= gameState.target) {
                        gameState.level++;
                        gameState.target = 500 + (gameState.level - 1) * 300;
                        gameState.timeLeft += 10;
                        if (gameState.timeLeft > 120) {
                            gameState.timeLeft = 120;
                        }
                    }
                    
                    claw.grabbedItem = null;
                }
                
                // 重置机械手状态
                claw.grabbing = false;
                claw.retracting = false; // 重置收回状态
                claw.extended = false;
                claw.grabDistance = 0; // 重置抓取距离
            }
        }
    }
    
    // 不再随机生成新碎片（每局固定20个）
    // 石头也不会再生成（固定数量）
    
    // 更新时间（只在游戏进行中才减少）
    if (gameState.started && !gameState.gameOver) {
    gameState.timeLeft -= deltaTime / 1000;
    if (gameState.timeLeft <= 0) {
        gameState.timeLeft = 0;
        endGame();
            return; // 游戏结束后不再更新
        }
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
}

// 绘制追踪弹
function drawMissiles() {
    gameState.missiles.forEach(missile => {
        if (!missile.active) return;
        
        ctx.save();
        
        // 追踪弹主体（发光的橙色/红色）
        const gradient = ctx.createRadialGradient(missile.x, missile.y, 0, missile.x, missile.y, missile.radius * 2);
        gradient.addColorStop(0, '#FFFFFF');
        gradient.addColorStop(0.3, '#FFD700');
        gradient.addColorStop(0.6, '#FF6B6B');
        gradient.addColorStop(1, '#FF0000');
        ctx.fillStyle = gradient;
        
        // 发光效果
        ctx.shadowBlur = 15;
        ctx.shadowColor = '#FF6B6B';
        
        ctx.beginPath();
        ctx.arc(missile.x, missile.y, missile.radius, 0, Math.PI * 2);
        ctx.fill();
        
        // 追踪弹核心（白色亮点）
        ctx.fillStyle = '#FFFFFF';
        ctx.beginPath();
        ctx.arc(missile.x, missile.y, missile.radius * 0.5, 0, Math.PI * 2);
        ctx.fill();
        
        // 尾迹效果
        ctx.globalAlpha = 0.6;
        ctx.fillStyle = '#FFD700';
        ctx.beginPath();
        ctx.arc(missile.x - Math.cos(Math.atan2(missile.target.y - missile.y, missile.target.x - missile.x)) * missile.radius * 2, 
                missile.y - Math.sin(Math.atan2(missile.target.y - missile.y, missile.target.x - missile.x)) * missile.radius * 2, 
                missile.radius * 0.7, 0, Math.PI * 2);
        ctx.fill();
        
        ctx.restore();
    });
}

// 集齐10个天体的特效显示
let collectionEffectTimer = 0;
function showCollectionCompleteEffect() {
    collectionEffectTimer = 3000; // 特效持续3秒
}

// 绘制集齐特效
function drawCollectionEffect() {
    if (collectionEffectTimer <= 0) return;
    
    const progress = 1 - (collectionEffectTimer / 3000); // 0到1的进度
    const alpha = Math.sin(progress * Math.PI); // 正弦波，开始和结束时透明，中间最明显
    
    ctx.save();
    ctx.globalAlpha = alpha;
    
    // 全屏闪光效果
    const gradient = ctx.createRadialGradient(canvas.width / 2, canvas.height / 2, 0, canvas.width / 2, canvas.height / 2, Math.max(canvas.width, canvas.height) * 0.8);
    gradient.addColorStop(0, 'rgba(255, 215, 0, 0.8)'); // 金色中心
    gradient.addColorStop(0.3, 'rgba(255, 255, 0, 0.6)'); // 黄色
    gradient.addColorStop(0.6, 'rgba(255, 200, 100, 0.4)'); // 橙黄色
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)'); // 透明边缘
    
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // 绘制文字提示
    ctx.globalAlpha = alpha * 0.9;
    ctx.fillStyle = '#FFD700';
    ctx.strokeStyle = '#FF8C00';
    ctx.lineWidth = 4;
    ctx.font = 'bold 72px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    
    const text = '🌟 集齐太阳系！+1000分 🌟';
    const x = canvas.width / 2;
    const y = canvas.height / 2 - 50;
    
    // 文字描边
    ctx.strokeText(text, x, y);
    // 文字填充
    ctx.fillText(text, x, y);
    
    // 绘制闪烁星星
    for (let i = 0; i < 20; i++) {
        const angle = (i / 20) * Math.PI * 2 + progress * Math.PI * 2;
        const radius = 150 + Math.sin(progress * Math.PI * 4 + i) * 50;
        const starX = x + Math.cos(angle) * radius;
        const starY = y + Math.sin(angle) * radius;
        
        ctx.globalAlpha = alpha * 0.8;
        ctx.fillStyle = '#FFD700';
        ctx.beginPath();
        ctx.arc(starX, starY, 8, 0, Math.PI * 2);
        ctx.fill();
        
        // 星星光芒
        ctx.strokeStyle = '#FFD700';
        ctx.lineWidth = 2;
        for (let j = 0; j < 8; j++) {
            const rayAngle = (j / 8) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(starX, starY);
            ctx.lineTo(starX + Math.cos(rayAngle) * 15, starY + Math.sin(rayAngle) * 15);
            ctx.stroke();
        }
    }
    
    ctx.restore();
}

// 绘制
function draw() {
    drawBackground();
    drawFragments(); // 绘制所有碎片（包括普通碎片和石头）
    drawMissiles(); // 绘制追踪弹
    drawClaw();
    drawShip();
    drawCollectionEffect(); // 绘制集齐特效
}

// 游戏循环
let lastTime = 0;
let paused = false;
function gameLoop(currentTime) {
    if (!lastTime) lastTime = currentTime;
    const deltaTime = Math.min(currentTime - lastTime, 100); // 限制最大deltaTime，防止异常大的时间跳跃
    lastTime = currentTime;
    
    if (!paused && gameState.started) {
    updateGame(deltaTime);
    }
    
    // 更新集齐特效计时器
    if (collectionEffectTimer > 0) {
        collectionEffectTimer -= deltaTime;
        if (collectionEffectTimer < 0) {
            collectionEffectTimer = 0;
        }
    }
    
    draw();
    
    requestAnimationFrame(gameLoop);
}

// 页面可见性改变时暂停/恢复
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        paused = true;
    } else {
        paused = false;
        lastTime = performance.now(); // 重置时间基准，避免时间跳跃
    }
});

// 更新UI
function updateUI() {
    document.getElementById('score').textContent = Math.max(0, gameState.score);
    document.getElementById('target').textContent = gameState.target;
    document.getElementById('time').textContent = Math.max(0, Math.ceil(gameState.timeLeft));
    document.getElementById('level').textContent = gameState.level;
    const diffDisplay = document.getElementById('difficultyDisplay');
    if (diffDisplay) {
        diffDisplay.textContent = gameState.difficulty || 1;
    }
}

// 自动提交分数
async function submitScore() {
    const statusEl = document.getElementById('submitStatus');
    if (!statusEl) return;
    
    statusEl.textContent = '正在提交分数...';
    statusEl.style.color = '#64C8FF';
    
    try {
        const userId = <?php echo $CURUSER['id']; ?>;
        let score = Math.max(0, Math.floor(gameState.score));
        const caught = gameState.caught;
        const level = gameState.level;
        
        // 前端验证：分数不能超过理论最大分数
        if (score > gameState.maxPossibleScore) {
            score = gameState.maxPossibleScore;
            gameState.score = score; // 同步更新游戏状态
        }
        
        // 生成Token（与后端保持一致）：用户ID + 分数 + 抓取数量 + 关卡 + 理论最大分数 + 日期 + 密码哈希
        const tokenString = userId.toString() + score.toString() + caught.toString() + level.toString() + gameState.maxPossibleScore.toString() + '<?php echo date("Y-m-d"); ?>' + '<?php echo $userPasshash; ?>';
        const gameToken = md5(tokenString);
        
        const response = await fetch('ajax.php?action=space_miner_submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                score: score,
                caught: caught,
                level: level,
                max_possible_score: gameState.maxPossibleScore, // 传递理论最大分数用于后端验证
                token: gameToken
            })
        });
        
        const data = await response.json();
        if (data.success) {
            // 显示提交成功信息
            let message = '✓ 分数已自动提交到排行榜！';
            if (data.stardust_reward && data.stardust_reward > 0) {
                message += ` 获得 ${data.stardust_reward} ⭐星尘！`;
            }
            if (data.remaining_submits !== undefined) {
                message += ` (今日剩余提交次数: ${data.remaining_submits})`;
            }
            statusEl.textContent = message;
            statusEl.style.color = '#4ECDC4';
            
            // 自动刷新排行榜
            loadLeaderboard('today');
            loadRemainingSubmits();
        } else {
            statusEl.textContent = '✗ 提交失败: ' + data.message;
            statusEl.style.color = '#FF6B6B';
        }
    } catch (error) {
        statusEl.textContent = '✗ 提交错误: ' + error.message;
        statusEl.style.color = '#FF6B6B';
    }
}

// 结束游戏
function endGame() {
    if (gameState.gameOver) return; // 防止重复调用
    
    gameState.gameOver = true;
    gameState.started = false;
    
    // 停止机械手
    gameState.claw.extended = false;
    gameState.claw.retracting = false;
    
    // 确保分数不为负数
    const finalScore = Math.max(0, gameState.score);
    document.getElementById('finalScore').textContent = finalScore;
    document.getElementById('finalCaught').textContent = gameState.caught;
    
    // 显示游戏结束界面
    document.getElementById('gameOver').style.display = 'block';
    
    // 自动提交分数
    submitScore();
}

// 点击/触摸发射控制
canvas.addEventListener('click', () => {
    if (!gameState.started || gameState.gameOver) return;
    // 只有在机械手完全收回后才能再次发射
    if (!gameState.claw.extended && gameState.claw.length === 0) {
        gameState.claw.extended = true;
    }
});

// 空格键控制：发射机械臂 或 发射追踪弹
document.addEventListener('keydown', (e) => {
    if (e.code === 'Space' && gameState.started && !gameState.gameOver) {
        e.preventDefault();
        
        // 如果已经抓到物体，发射追踪弹
        if (gameState.claw.grabbing && gameState.claw.grabbedItem) {
            fireMissile();
        } 
        // 否则，如果机械手完全收回，发射机械手
        else if (!gameState.claw.extended && gameState.claw.length === 0) {
        gameState.claw.extended = true;
        }
    }
});

// 开始游戏函数（从开始界面调用）
function startGame() {
    // 隐藏开始界面
    const startScreen = document.getElementById('gameStartScreen');
    if (startScreen) {
        startScreen.style.display = 'none';
    }
    
    // 显示游戏信息
    const gameInfo = document.querySelector('.game-info');
    if (gameInfo) {
        gameInfo.style.display = 'flex';
    }
    
    // 确保游戏状态完全重置
    gameState.gameOver = false;
    gameState.started = false;
    gameState.score = 0;
    gameState.target = 500;
    gameState.timeLeft = 60;
    gameState.level = 1;
    gameState.caught = 0;
    gameState.maxPossibleScore = 0; // 重置理论最大分数
    
    // 重置追踪弹
    gameState.missiles = [];
    
    // 重置飞船
    gameState.ship.angle = 0; // 重置角度
    
    // 重置机械手
    gameState.claw.extended = false;
    gameState.claw.length = 0;
    gameState.claw.grabbing = false;
    gameState.claw.grabbedItem = null;
    gameState.claw.retracting = false;
    gameState.claw.grabDistance = 0;
    gameState.claw.angle = 0; // 重置角度
    
    // 清空碎片
    gameState.fragments = [];
    gameState.fragmentSpawnTimer = 0;
    
    // 清空提交状态
    const statusEl = document.getElementById('submitStatus');
    if (statusEl) {
        statusEl.textContent = '';
    }
    
    // 确保游戏结束界面隐藏
    const gameOverEl = document.getElementById('gameOver');
    if (gameOverEl) {
        gameOverEl.style.display = 'none';
    }
    
    // 重新初始化画布大小（防止窗口变化后尺寸不对）
    resizeCanvas();
    
    // 初始化游戏（这会设置飞船位置、生成碎片等）
    initGame();
    
    // 重置时间基准
    lastTime = performance.now();
    paused = false;
}

// 重置游戏
function resetGame() {
    // 隐藏游戏结束界面，显示开始界面
    document.getElementById('gameOver').style.display = 'none';
    document.getElementById('gameStartScreen').style.display = 'block';
    selectDifficulty(selectedDifficulty);
    
    // 隐藏游戏信息
    const gameInfo = document.querySelector('.game-info');
    if (gameInfo) {
        gameInfo.style.display = 'none';
    }
    
    // 完全重置游戏状态
    gameState.gameOver = false;
    gameState.started = false;
    gameState.score = 0;
    gameState.target = 500;
    gameState.timeLeft = 60;
    gameState.level = 1;
    gameState.caught = 0;
    gameState.maxPossibleScore = 0; // 重置理论最大分数
    
    // 重置追踪弹
    gameState.missiles = [];
    
    // 重置已收集的天体
    gameState.collectedPlanets = new Set();
    gameState.collectionComplete = false;
    
    // 重置集齐特效
    collectionEffectTimer = 0;
    
    // 重置飞船
    gameState.ship.angle = 0; // 重置角度
    gameState.ship.x = 0; // 将在initGame中重新设置
    gameState.ship.y = 0; // 将在initGame中重新设置
    
    // 重置机械手
    gameState.claw.extended = false;
    gameState.claw.length = 0;
    gameState.claw.grabbing = false;
    gameState.claw.grabbedItem = null;
    gameState.claw.retracting = false;
    gameState.claw.grabDistance = 0; // 重置抓取距离
    gameState.claw.angle = 0; // 重置角度
    
    // 清空碎片（包括石头）
    gameState.fragments = [];
    gameState.fragmentSpawnTimer = 0;
    
    // 清空提交状态
    const statusEl = document.getElementById('submitStatus');
    if (statusEl) {
        statusEl.textContent = '';
    }
    
    // 更新UI
    updateUI();
    
    // 重置时间基准
    lastTime = performance.now();
    paused = false;
    
    // 确保游戏循环继续运行（重新绘制一次，清除画布）
    draw();
}

// 提交分数功能已改为自动提交，在 endGame() 中调用

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
