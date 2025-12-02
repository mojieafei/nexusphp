<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

$targetUserId = isset($_GET['uid']) ? intval($_GET['uid']) : $CURUSER['id'];
$isOwnFarm = ($targetUserId == $CURUSER['id']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>星尘农场 - 重建太阳系 - <?php echo $SITENAME; ?></title>
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    width: 100vw;
    height: 100vh;
    background: linear-gradient(180deg, #0a0e27 0%, #1a1f3a 50%, #2a2f4a 100%);
    font-family: "Microsoft YaHei", Arial, sans-serif;
    color: #fff;
    overflow: hidden;
}

/* 容器布局 */
.farm-container {
    display: flex;
    height: 100vh;
    width: 100vw;
}

/* 左侧农场主区域 */
.farm-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 20px;
    overflow-y: auto;
}

/* 顶部信息栏 */
.farm-header {
    background: rgba(0, 0, 0, 0.7);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    border: 2px solid rgba(138, 43, 226, 0.5);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.farm-info {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.farm-info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.farm-info-label {
    font-size: 14px;
    color: #aaa;
}

.farm-info-value {
    font-size: 24px;
    font-weight: bold;
    color: #ffd700;
}

.farm-info-value.stardust {
    color: #4ECDC4;
}

.farm-info-value.level {
    color: #FF6B6B;
}

.farm-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.3s;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(138, 43, 226, 0.8);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-success {
    background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
}

.btn-danger {
    background: linear-gradient(135deg, #FF6B6B 0%, #C44569 100%);
}

/* 农场地块区域 */
.farm-lands {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.land-plot {
    background: rgba(0, 0, 0, 0.6);
    border: 2px solid rgba(138, 43, 226, 0.5);
    border-radius: 10px;
    padding: 15px;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s;
}

.land-plot:hover {
    border-color: #8a2be2;
    box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
}

.land-plot.empty {
    cursor: pointer;
}

.land-plot.growing {
    border-color: #4ECDC4;
}

.land-plot.mature {
    border-color: #ffd700;
    animation: pulse 1.5s ease-in-out infinite;
}

.land-plot.withered {
    border-color: #666;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
    50% { box-shadow: 0 0 30px rgba(255, 215, 0, 0.9); }
}

.crop-emoji {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 72px;
}

.crop-name {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 5px;
}

.crop-status {
    font-size: 14px;
    color: #aaa;
    text-align: center;
}

.land-actions {
    margin-top: 10px;
    display: flex;
    gap: 5px;
}

.land-btn {
    padding: 5px 10px;
    font-size: 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    background: #667eea;
    color: white;
    transition: all 0.3s;
}

.land-btn:hover {
    background: #764ba2;
}

/* 右侧边栏 */
.farm-sidebar {
    width: 380px;
    background: rgba(0, 0, 0, 0.9);
    border-left: 3px solid rgba(138, 43, 226, 0.8);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar-tabs {
    display: flex;
    border-bottom: 2px solid rgba(138, 43, 226, 0.5);
}

.sidebar-tab {
    flex: 1;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    background: rgba(138, 43, 226, 0.2);
    border: none;
    color: white;
    font-size: 16px;
    transition: all 0.3s;
}

.sidebar-tab.active {
    background: rgba(138, 43, 226, 0.5);
    border-bottom: 3px solid #8a2be2;
}

.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.sidebar-section {
    display: none;
}

.sidebar-section.active {
    display: block;
}

/* 商店 */
.shop-item {
    background: rgba(138, 43, 226, 0.2);
    border: 2px solid rgba(138, 43, 226, 0.5);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
}

.shop-item:hover {
    border-color: #8a2be2;
    background: rgba(138, 43, 226, 0.3);
}

.shop-item-emoji {
    min-width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.shop-item-info {
    flex: 1;
}

.shop-item-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
}

.shop-item-details {
    font-size: 12px;
    color: #aaa;
    margin-bottom: 5px;
}

.shop-item-price {
    font-size: 16px;
    color: #ffd700;
    font-weight: bold;
}

.shop-item-btn {
    padding: 8px 15px;
    background: #4ECDC4;
    border: none;
    border-radius: 5px;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.shop-item-btn:hover {
    background: #44A08D;
    transform: scale(1.05);
}

.shop-item-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* 背包 */
.inventory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
}

.inventory-item {
    background: rgba(138, 43, 226, 0.2);
    border: 2px solid rgba(138, 43, 226, 0.5);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s;
    position: relative;
}

.inventory-item:hover {
    border-color: #8a2be2;
    transform: translateY(-5px);
}

.inventory-item-emoji {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 56px;
}

.crop-emoji .planet-icon,
.shop-item-emoji .planet-icon,
.inventory-item-emoji .planet-icon {
    filter: drop-shadow(0 0 14px rgba(138, 43, 226, 0.45));
}

.planet-icon {
    display: inline-block;
    border-radius: 50%;
    background: radial-gradient(circle at 30% 30%, #4e9af1 0%, #0a1f60 85%);
    position: relative;
    box-shadow: inset -12px -12px 20px rgba(0, 0, 0, 0.35), inset 6px 6px 14px rgba(255, 255, 255, 0.15);
    overflow: visible;
}

.planet-icon::before {
    content: '';
    position: absolute;
    top: 18%;
    left: 20%;
    width: 40%;
    height: 40%;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.18);
    filter: blur(2px);
    z-index: 1;
    pointer-events: none;
}

.planet-icon-lg {
    width: 72px;
    height: 72px;
}

.planet-icon-md {
    width: 56px;
    height: 56px;
}

.planet-icon-sm {
    width: 48px;
    height: 48px;
}

.planet-generic {
    background: radial-gradient(circle at 30% 30%, #7f8c8d 0%, #2c3e50 85%);
}

.planet-moon {
    background:
        radial-gradient(circle at 68% 32%, rgba(255, 255, 255, 0.14) 0 18%, transparent 56%),
        radial-gradient(circle at 42% 72%, rgba(0, 0, 0, 0.22) 0 20%, transparent 58%),
        radial-gradient(circle at 30% 30%, #f7f7f7 0%, #cfcfcf 60%, #8f8f8f 100%);
    box-shadow: inset -18px -18px 24px rgba(0, 0, 0, 0.4), inset 10px 10px 18px rgba(255, 255, 255, 0.18);
}

.planet-mercury {
    background:
        radial-gradient(circle at 32% 28%, rgba(255, 255, 255, 0.32) 0 18%, transparent 42%),
        radial-gradient(circle at 68% 62%, rgba(0, 0, 0, 0.35) 0 20%, transparent 48%),
        linear-gradient(120deg, #d6cec0 0%, #b39e87 38%, #7a5c48 62%, #37281f 82%, #0d0d0d 100%);
    background-blend-mode: screen, multiply, normal;
    box-shadow: inset -18px -18px 24px rgba(0, 0, 0, 0.55), inset 10px 10px 18px rgba(255, 255, 255, 0.18);
}

.planet-venus {
    background:
        radial-gradient(circle at 48% 28%, rgba(255, 233, 180, 0.28) 0 34%, transparent 70%),
        linear-gradient(150deg, #ffe1ad 0%, #f4b565 45%, #c97d31 100%),
        repeating-linear-gradient(28deg, rgba(255, 255, 255, 0.22) 0 12%, rgba(161, 90, 29, 0.16) 12% 24%);
    background-blend-mode: screen, multiply, normal;
    box-shadow: inset -20px -18px 28px rgba(130, 65, 10, 0.42), inset 10px 10px 18px rgba(255, 221, 181, 0.22);
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

.planet-mars {
    background:
        radial-gradient(circle at 30% 32%, rgba(255, 213, 160, 0.2) 0 28%, transparent 60%),
        linear-gradient(138deg, #e07b3c 0%, #b04a20 55%, #5d230e 100%);
}

.planet-jupiter {
    background:
        repeating-linear-gradient(160deg, #f8e7c3 0%, #f1d9ae 8%, #cf9d6a 8%, #cf9d6a 16%, #b67a45 16%, #b67a45 24%, #90613b 24%, #90613b 32%);
    background-size: 140% 140%;
    box-shadow: inset -18px -18px 28px rgba(0, 0, 0, 0.38), inset 10px 10px 20px rgba(255, 255, 255, 0.18);
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

.planet-neptune {
    background:
        radial-gradient(circle at 60% 28%, rgba(255, 255, 255, 0.18) 0 24%, transparent 60%),
        linear-gradient(140deg, #7bc4ff 0%, #2a68d5 55%, #0b226a 100%);
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

.planet-earth::after {
    content: '';
    position: absolute;
    inset: 7%;
    border-radius: 50%;
    background:
        /* 海洋高光层打底（略带渐变） */
        radial-gradient(circle at 45% 55%, rgba(135, 206, 250, 0.35) 0 40%, transparent 70%),
        radial-gradient(circle at 30% 35%, rgba(52, 152, 219, 0.25) 0 32%, transparent 65%),
        /* 北美 + 南美轮廓 */
        radial-gradient(circle at 26% 40%, rgba(39, 174, 96, 0.98) 0 14%, rgba(39, 174, 96, 0.98) 18%, rgba(39, 174, 96, 0) 22%),
        radial-gradient(circle at 28% 62%, rgba(39, 174, 96, 0.9) 0 12%, rgba(39, 174, 96, 0.15) 20%, transparent 28%),
        radial-gradient(circle at 32% 78%, rgba(46, 204, 113, 0.85) 0 10%, rgba(46, 204, 113, 0.12) 18%, transparent 26%),
        /* 非洲 + 欧洲 + 亚洲主体 */
        radial-gradient(circle at 60% 50%, rgba(33, 140, 58, 0.98) 0 18%, rgba(33, 140, 58, 0.4) 24%, transparent 32%),
        radial-gradient(circle at 70% 42%, rgba(39, 174, 96, 0.92) 0 16%, rgba(39, 174, 96, 0.2) 22%, transparent 30%),
        radial-gradient(circle at 63% 60%, rgba(210, 180, 140, 0.85) 0 12%, rgba(210, 180, 140, 0.2) 20%, transparent 28%),
        radial-gradient(circle at 76% 58%, rgba(184, 134, 88, 0.78) 0 10%, rgba(184, 134, 88, 0.15) 18%, transparent 26%),
        radial-gradient(circle at 82% 47%, rgba(52, 152, 219, 0.5) 0 9%, transparent 22%),
        radial-gradient(circle at 78% 70%, rgba(46, 204, 113, 0.7) 0 9%, rgba(46, 204, 113, 0.12) 18%, transparent 25%),
        /* 印度半岛与东南亚半岛 */
        radial-gradient(circle at 64% 64%, rgba(39, 174, 96, 0.9) 0 7%, rgba(39, 174, 96, 0.15) 14%, transparent 22%),
        radial-gradient(circle at 72% 66%, rgba(210, 180, 140, 0.6) 0 6%, transparent 16%),
        /* 云层 - 放顶部几层 */
        radial-gradient(ellipse at 46% 30%, rgba(255, 255, 255, 0.6) 0 20%, rgba(255, 255, 255, 0.25) 30%, transparent 50%),
        radial-gradient(ellipse at 62% 72%, rgba(255, 255, 255, 0.55) 0 18%, rgba(255, 255, 255, 0.2) 30%, transparent 52%),
        radial-gradient(ellipse at 34% 78%, rgba(255, 255, 255, 0.5) 0 16%, transparent 46%),
        /* 极地 */
        radial-gradient(circle at 50% 14%, rgba(255, 255, 255, 0.82) 0 11%, transparent 32%),
        radial-gradient(circle at 50% 86%, rgba(255, 255, 255, 0.78) 0 13%, transparent 35%);
    pointer-events: none;
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

.inventory-item-name {
    font-size: 14px;
    margin-bottom: 5px;
}

.inventory-item-count {
    font-size: 18px;
    font-weight: bold;
    color: #ffd700;
}

.craft-btn {
    width: 100%;
    margin-top: 10px;
    padding: 5px;
    background: #FF6B6B;
    border: none;
    border-radius: 5px;
    color: white;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.craft-btn:hover {
    background: #C44569;
}

.craft-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* 成就 */
.achievement-item {
    background: rgba(138, 43, 226, 0.2);
    border: 2px solid rgba(138, 43, 226, 0.5);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
}

.achievement-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
}

.achievement-icon {
    font-size: 36px;
}

.achievement-name {
    font-size: 16px;
    font-weight: bold;
}

.achievement-desc {
    font-size: 14px;
    color: #aaa;
    margin-bottom: 10px;
}

.achievement-reward {
    font-size: 14px;
    color: #ffd700;
}

.achievement-progress {
    margin-top: 10px;
    height: 8px;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 4px;
    overflow: hidden;
}

.achievement-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transition: width 0.3s;
}

/* 通知消息 */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.9);
    border: 2px solid #4ECDC4;
    border-radius: 10px;
    padding: 15px 20px;
    max-width: 350px;
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification.success { border-color: #4ECDC4; }
.notification.error { border-color: #FF6B6B; }
.notification.info { border-color: #667eea; }

/* Loading */
.loading {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid rgba(138, 43, 226, 0.3);
    border-top-color: #8a2be2;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* 模态框 */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9998;
    justify-content: center;
    align-items: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: #1a1f3a;
    border: 3px solid rgba(138, 43, 226, 0.8);
    border-radius: 20px;
    padding: 30px;
    width: min(90vw, 640px);
    max-height: 80vh;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.modal-title {
    font-size: 24px;
    font-weight: bold;
    color: #8a2be2;
    margin: 0;
}

.modal-close {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-size: 26px;
    cursor: pointer;
    color: #cbd3ff;
    background: rgba(10, 15, 40, 0.9);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.modal-close:hover {
    color: #ffffff;
    background: rgba(138, 43, 226, 0.35);
    transform: scale(1.05);
}

#seedsList {
    flex: 1;
    overflow-y: auto;
    padding-right: 8px;
}

/* 响应式 */
@media (max-width: 1200px) {
    .farm-container {
        flex-direction: column;
    }
    
    .farm-sidebar {
        width: 100%;
        height: 300px;
        border-left: none;
        border-top: 3px solid rgba(138, 43, 226, 0.8);
    }
}

@media (max-width: 768px) {
    .farm-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .farm-lands {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
    
    .shop-item {
        flex-direction: column;
        text-align: center;
    }
}
</style>
</head>
<body>

<div id="loadingScreen" class="loading">
    <div class="loading-spinner"></div>
</div>

<div class="farm-container" style="display: none;" id="farmContainer">
    <!-- 左侧主区域 -->
    <div class="farm-main">
        <!-- 顶部信息栏 -->
        <div class="farm-header">
            <div class="farm-info">
                <div class="farm-info-item">
                    <span class="farm-info-label">农场主</span>
                    <span class="farm-info-value" id="farmerName"><?php echo get_username($targetUserId); ?></span>
                </div>
                <div class="farm-info-item">
                    <span class="farm-info-label">⭐ 星尘</span>
                    <span class="farm-info-value stardust" id="stardustAmount">0</span>
                </div>
                <div class="farm-info-item">
                    <span class="farm-info-label">🏆 等级</span>
                    <span class="farm-info-value level" id="farmLevel">1</span>
                </div>
                <div class="farm-info-item">
                    <span class="farm-info-label">📊 经验</span>
                    <span class="farm-info-value" id="farmExp">0 / 100</span>
                </div>
                <div class="farm-info-item">
                    <span class="farm-info-label">🌱 土地</span>
                    <span class="farm-info-value" id="landCount">3</span>
                </div>
            </div>
            
            <!-- 购买土地按钮（仅自己的农场显示） -->
            <?php if ($isOwnFarm): ?>
            <div id="purchaseLandSection" style="margin: 15px 0; display: none;">
                <button id="purchaseLandBtn" class="btn" onclick="purchaseLand()" style="width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 16px;">
                    🏞️ 购买土地
                </button>
                <div id="purchaseLandInfo" style="font-size: 12px; color: #999; margin-top: 8px; text-align: center;">
                    <!-- 动态显示价格和等级要求 -->
                </div>
            </div>
            <?php endif; ?>
            
            <div class="farm-actions">
                <?php if ($isOwnFarm): ?>
                <button class="btn btn-success" onclick="location.href='stardust_friends.php'">🤝 好友农场</button>
                <button class="btn btn-warning" onclick="location.href='stardust_leaderboard.php'">🏆 排行榜</button>
                <button class="btn" onclick="location.href='index.php'">🏠 返回首页</button>
                <?php else: ?>
                <button class="btn btn-success" onclick="location.href='stardust_farm.php'">🏡 我的农场</button>
                <button class="btn" onclick="location.href='stardust_friends.php'">🤝 好友列表</button>
                <button class="btn btn-warning" onclick="location.href='stardust_leaderboard.php'">🏆 排行榜</button>
                <button class="btn" onclick="location.href='index.php'">🏠 返回首页</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- 农场地块 -->
        <div class="farm-lands" id="farmLands">
            <!-- 动态生成 -->
        </div>
    </div>

    <!-- 右侧边栏 -->
    <div class="farm-sidebar">
        <div class="sidebar-tabs">
            <button class="sidebar-tab active" onclick="showTab('inventory')">🎒 背包</button>
            <button class="sidebar-tab" onclick="showTab('planets')">🌍 行星</button>
            <button class="sidebar-tab" onclick="showTab('achievements')">🏆 成就</button>
            <button class="sidebar-tab" onclick="showTab('help')">❓ 说明</button>
        </div>
        <div class="sidebar-content">
            <!-- 背包 -->
            <div class="sidebar-section active" id="inventorySection">
                <h3 style="margin-bottom: 15px;">碎片仓库</h3>
                <div class="inventory-grid" id="fragmentsGrid">
                    <!-- 动态生成 -->
                </div>
            </div>

            <!-- 行星收藏 -->
            <div class="sidebar-section" id="planetsSection">
                <h3 style="margin-bottom: 15px;">行星收藏</h3>
                <div class="inventory-grid" id="planetsGrid">
                    <!-- 动态生成 -->
                </div>
            </div>

            <!-- 成就 -->
            <div class="sidebar-section" id="achievementsSection">
                <h3 style="margin-bottom: 15px;">成就列表</h3>
                <div id="achievementsList">
                    <!-- 动态生成 -->
                </div>
            </div>
            
            <!-- 游戏说明 -->
            <div class="sidebar-section" id="helpSection">
                <h3 style="margin-bottom: 15px; color: #FFD700;">📖 游戏说明</h3>
                <div style="color: #ddd; line-height: 1.8; font-size: 13px; max-height: calc(100vh - 200px); overflow-y: auto;">
                    
                    <div style="background: rgba(138, 43, 226, 0.2); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #8a2be2;">
                        <h4 style="color: #8a2be2; margin-bottom: 10px;">🎯 游戏目标</h4>
                        <p>重建被摧毁的太阳系！收集8大行星+太阳，完成伟大使命。</p>
                    </div>
                    
                    <div style="background: rgba(78, 205, 196, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #4ECDC4;">
                        <h4 style="color: #4ECDC4; margin-bottom: 10px;">💎 获取星尘</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• 流星游戏：100分 = 10星尘</li>
                            <li style="padding: 5px 0;">• 帮好友浇水：5星尘/次</li>
                            <li style="padding: 5px 0;">• 访问好友农场：10星尘/次（每天5次）</li>
                            <li style="padding: 5px 0;">• 完成成就：获得奖励星尘</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255, 215, 0, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #FFD700;">
                        <h4 style="color: #FFD700; margin-bottom: 10px;">🌱 种植流程</h4>
                        <ol style="list-style-position: inside; padding-left: 0;">
                            <li style="padding: 5px 0;"><strong>购买种子</strong>：用星尘购买行星种子</li>
                            <li style="padding: 5px 0;"><strong>种植</strong>：在空地上种植种子</li>
                            <li style="padding: 5px 0;"><strong>等待成熟</strong>：不同行星成熟时间不同
                                <ul style="list-style: none; padding-left: 15px; font-size: 12px; color: #aaa;">
                                    <li>🌙 月球：2小时</li>
                                    <li>☿️ 水星：4小时</li>
                                    <li>♀️ 金星：6小时</li>
                                    <li>🌍 地球：8小时</li>
                                    <li>♂️ 火星：12小时</li>
                                    <li>♃ 木星：24小时</li>
                                    <li>♄ 土星：36小时</li>
                                    <li>⛢ 天王星：48小时</li>
                                    <li>♆ 海王星：60小时</li>
                                    <li>☀️ 太阳：72小时</li>
                                </ul>
                            </li>
                            <li style="padding: 5px 0;"><strong>收获</strong>：成熟后收获1-2个碎片</li>
                        </ol>
                    </div>
                    
                    <div style="background: rgba(255, 107, 107, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #FF6B6B;">
                        <h4 style="color: #FF6B6B; margin-bottom: 10px;">💧 浇水机制</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• 可以给<strong>好友</strong>的作物浇水</li>
                            <li style="padding: 5px 0;">• 每次浇水：缩短<strong>10%</strong>生长时间</li>
                            <li style="padding: 5px 0;">• 每块地最多被<strong>3人</strong>浇水</li>
                            <li style="padding: 5px 0;">• 每人只能给同一块地浇水<strong>1次</strong></li>
                            <li style="padding: 5px 0;">• 浇水奖励：收获时产量增加<strong>10%</strong></li>
                            <li style="padding: 5px 0;">• 浇水者获得：<strong>5星尘</strong>奖励</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(138, 43, 226, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #8a2be2;">
                        <h4 style="color: #8a2be2; margin-bottom: 10px;">😈 偷取机制</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• 作物成熟后可以被偷取</li>
                            <li style="padding: 5px 0;">• 每块地只能被偷<strong>1次</strong></li>
                            <li style="padding: 5px 0;">• 偷取获得：<strong>1个碎片</strong></li>
                            <li style="padding: 5px 0;">• 枯萎的作物<strong>不能</strong>被偷</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(78, 205, 196, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #4ECDC4;">
                        <h4 style="color: #4ECDC4; margin-bottom: 10px;">🌍 合成行星</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• 收集<strong>9个同类碎片</strong></li>
                            <li style="padding: 5px 0;">• 点击"合成行星"按钮</li>
                            <li style="padding: 5px 0;">• 获得1个<strong>完整行星</strong></li>
                            <li style="padding: 5px 0;">• 完整行星不可分解</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255, 215, 0, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #FFD700;">
                        <h4 style="color: #FFD700; margin-bottom: 10px;">🏞️ 购买土地</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• 初始赠送：<strong>3块地</strong></li>
                            <li style="padding: 5px 0;">• 等级要求：<strong>Power User</strong>以上</li>
                            <li style="padding: 5px 0;">• 价格递增：
                                <ul style="list-style: none; padding-left: 15px; font-size: 12px; color: #aaa;">
                                    <li>第4块：500⭐</li>
                                    <li>第5块：700⭐</li>
                                    <li>第6块：900⭐</li>
                                    <li>第7块起：每块+200⭐</li>
                                    <li>最多：12块地</li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255, 107, 107, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #FF6B6B;">
                        <h4 style="color: #FF6B6B; margin-bottom: 10px;">⚡ 魔力值加成</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• 每合成1个完整行星</li>
                            <li style="padding: 5px 0;">• 魔力值获取速度提升<strong>2%</strong></li>
                            <li style="padding: 5px 0;">• 最多10个行星 = <strong>20%</strong>加成</li>
                            <li style="padding: 5px 0;">• 在"我的魔力值"页面查看详情</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(138, 43, 226, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #8a2be2;">
                        <h4 style="color: #8a2be2; margin-bottom: 10px;">🏆 排行榜</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• <strong>财富榜</strong>：比拼星尘数量</li>
                            <li style="padding: 5px 0;">• <strong>等级榜</strong>：比拼农场等级</li>
                            <li style="padding: 5px 0;">• <strong>碎片榜</strong>：比拼碎片总数</li>
                            <li style="padding: 5px 0;">• <strong>行星榜</strong>：比拼完整行星数</li>
                            <li style="padding: 5px 0;">• 点击导航栏"🏆农场排行榜"查看</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(78, 205, 196, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #4ECDC4;">
                        <h4 style="color: #4ECDC4; margin-bottom: 10px;">💡 进阶技巧</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">1. 优先种植<strong>短周期</strong>作物（月球、水星）</li>
                            <li style="padding: 5px 0;">2. 多访问好友农场获得<strong>星尘</strong></li>
                            <li style="padding: 5px 0;">3. 请好友帮忙<strong>浇水</strong>加速成长</li>
                            <li style="padding: 5px 0;">4. 及时收获，避免<strong>枯萎</strong></li>
                            <li style="padding: 5px 0;">5. 合成行星获得<strong>魔力值加成</strong></li>
                            <li style="padding: 5px 0;">6. 达到Veteran User后尽快<strong>扩地</strong></li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(255, 215, 0, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 3px solid #FFD700;">
                        <h4 style="color: #FFD700; margin-bottom: 10px;">🎮 快捷入口</h4>
                        <ul style="list-style: none; padding-left: 0;">
                            <li style="padding: 5px 0;">• <a href="meteor_game.php" target="_blank" style="color: #8a2be2; text-decoration: underline;">流星游戏</a> - 获取星尘</li>
                            <li style="padding: 5px 0;">• <a href="stardust_friends.php" style="color: #4ECDC4; text-decoration: underline;">好友农场</a> - 访问好友</li>
                            <li style="padding: 5px 0;">• <a href="stardust_leaderboard.php" target="_blank" style="color: #FFD700; text-decoration: underline;">排行榜</a> - 查看排名</li>
                            <li style="padding: 5px 0;">• <a href="mybonus.php" data-pjax-ignore="1" style="color: #FF6B6B; text-decoration: underline;">魔力值</a> - 查看加成</li>
                        </ul>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
                        <p>🌟 祝你早日重建太阳系！🌟</p>
                        <p style="margin-top: 10px;">有问题请联系站务</p>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 种植模态框 -->
<div class="modal" id="plantModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">选择种子</h2>
            <span class="modal-close" onclick="closePlantModal()">&times;</span>
        </div>
        <div id="seedsList">
            <!-- 动态生成 -->
        </div>
    </div>
</div>

<script>
// 全局变量
let farmData = null;
let selectedLandId = null;
const isOwnFarm = <?php echo $isOwnFarm ? 'true' : 'false'; ?>;
const targetUserId = <?php echo $targetUserId; ?>;
const currentUserId = <?php echo $CURUSER['id']; ?>;

const PLANET_CLASS_MAP = {
    'moon': 'planet-moon',
    'mercury': 'planet-mercury',
    'venus': 'planet-venus',
    'earth': 'planet-earth',
    'mars': 'planet-mars',
    'jupiter': 'planet-jupiter',
    'saturn': 'planet-saturn',
    'uranus': 'planet-uranus',
    'neptune': 'planet-neptune',
    'sun': 'planet-sun'
};

function getPlanetClassName(crop) {
    if (!crop) return 'planet-generic';
    const keySource = (crop.name_en || crop.name || '').toString().toLowerCase();
    return PLANET_CLASS_MAP[keySource] || 'planet-generic';
}

function renderPlanetIcon(crop, size = 'lg') {
    const sizeClass = size === 'sm' ? 'planet-icon-sm' : (size === 'md' ? 'planet-icon-md' : 'planet-icon-lg');
    const className = getPlanetClassName(crop);
    return `<span class="planet-icon ${sizeClass} ${className}" title="${crop ? crop.name : '天体'}"></span>`;
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    loadFarmData();
});

// 加载农场数据
function loadFarmData() {
    console.log('开始加载农场数据，用户ID:', targetUserId);
    callAPI('getStardustFarm', { user_id: targetUserId })
        .then(data => {
            console.log('农场数据加载成功:', data);
            farmData = data;
            renderFarm();
            document.getElementById('loadingScreen').style.display = 'none';
            document.getElementById('farmContainer').style.display = 'flex';
        })
        .catch(error => {
            console.error('加载失败:', error);
            document.getElementById('loadingScreen').innerHTML = '<div style="color: red; padding: 20px;"><h2>加载失败</h2><p>' + error + '</p><p>请按F12打开控制台查看详细错误，或联系管理员</p><button onclick="location.reload()" style="padding: 10px 20px; margin-top: 10px;">重新加载</button></div>';
            showNotification('加载失败: ' + error, 'error');
        });
}

// 渲染农场
function renderFarm() {
    // 更新头部信息
    document.getElementById('stardustAmount').textContent = farmData.farm.stardust;
    document.getElementById('farmLevel').textContent = farmData.farm.level;
    document.getElementById('farmExp').textContent = farmData.farm.experience + ' / ' + (farmData.farm.level * 100);
    document.getElementById('landCount').textContent = farmData.farm.land_slots;

    // 更新购买土地按钮
    updatePurchaseLandButton();

    // 渲染土地
    renderLands();
    
    // 渲染背包
    renderInventory();
    
    // 加载成就列表
    loadAchievements();

    if (!isOwnFarm) {
        recordVisit();
    }
}

// 加载成就列表
function loadAchievements() {
    if (!isOwnFarm) return; // 只在自己的农场显示成就
    
    callAPI('checkStardustAchievements', {})
        .then(unlocked => {
            if (Array.isArray(unlocked) && unlocked.length > 0) {
                unlocked.forEach(item => {
                    const rewardText = item.reward ? `，奖励 ${item.reward}⭐` : '';
                    showNotification(`🎉 成就达成：${item.name}${rewardText}`, 'success');
                });
            }
        })
        .catch(error => {
            console.warn('检查成就失败:', error);
        })
        .finally(() => {
            callAPI('getStardustAchievements', { user_id: targetUserId })
                .then(data => {
                    renderAchievements(data);
                })
                .catch(error => {
                    console.error('加载成就失败:', error);
                });
        });
}

// 记录访问
function recordVisit() {
    callAPI('visitStardustFarm', { target_user_id: targetUserId })
        .then(data => {
            if (data && data.message) {
                console.log('访问好友成功:', data.message);
            }
        })
        .catch(error => {
            if (error && error.indexOf && error.indexOf('访问次数已用完') === -1) {
                console.warn('记录访问失败:', error);
            }
        });
}

// 渲染成就列表
function renderAchievements(achievements) {
    const list = document.getElementById('achievementsList');
    
    if (!achievements || achievements.length === 0) {
        list.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">暂无成就数据</p>';
        return;
    }
    
    list.innerHTML = achievements.map(ach => {
        const isCompleted = ach.is_completed;
        const target = ach.target || 100;
        const rawProgress = ach.progress ?? 0;
        const progressValue = isCompleted ? target : rawProgress;
        const progressPercent = target > 0 ? Math.min((progressValue / target) * 100, 100) : 100;
        
        const progressSection = (`
                    <div style="margin: 10px 0;">
                        <div style="background: rgba(0,0,0,0.3); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #4ECDC4, #44A08D); height: 100%; width: ${progressPercent}%; transition: width 0.3s;"></div>
                        </div>
                        <div style="font-size: 11px; color: #999; margin-top: 4px; text-align: right;">${progressValue} / ${target}</div>
                    </div>
                `);

        return `
            <div class="achievement-item ${isCompleted ? 'completed' : 'locked'}" style="
                background: ${isCompleted ? 'rgba(78, 205, 196, 0.2)' : 'rgba(100, 100, 100, 0.1)'};
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 12px;
                border-left: 4px solid ${isCompleted ? '#4ECDC4' : '#555'};
                opacity: ${isCompleted ? '1' : '0.6'};
                transition: all 0.3s;
            ">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                    <span style="font-size: 32px; ${isCompleted ? '' : 'filter: grayscale(100%);'}">${ach.icon}</span>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="font-size: 16px; font-weight: bold; color: ${isCompleted ? '#4ECDC4' : '#aaa'};">${ach.name}</span>
                            ${isCompleted ? '<span style="color: #FFD700; font-size: 12px;">✓ 已完成</span>' : '<span style="color: #999; font-size: 12px;">🔒 未完成</span>'}
                        </div>
                        <div style="font-size: 13px; color: ${isCompleted ? '#ddd' : '#999'};">${ach.description}</div>
                    </div>
                </div>
                
                ${(!isCompleted ? progressSection : '')}
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                    <span style="font-size: 14px; color: ${isCompleted ? '#FFD700' : '#666'};">
                        <strong>${ach.reward_stardust}⭐</strong> 星尘
                    </span>
                    ${ach.is_repeatable ? '<span style="font-size: 11px; color: #8a2be2; background: rgba(138,43,226,0.2); padding: 2px 8px; border-radius: 10px;">可重复</span>' : ''}
                    ${isCompleted && ach.completed_times > 1 ? `<span style="font-size: 11px; color: #4ECDC4;">已完成 ${ach.completed_times} 次</span>` : ''}
                </div>
                ${isCompleted ? `
                    <div style="margin-top: 8px; font-size: 11px; color: #94a3b8; text-align: right;">
                        ${progressValue} / ${target}
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

// 更新购买土地按钮状态
function updatePurchaseLandButton() {
    const section = document.getElementById('purchaseLandSection');
    if (!section) return; // 如果不是自己的农场，没有这个元素
    
    const btn = document.getElementById('purchaseLandBtn');
    const info = document.getElementById('purchaseLandInfo');
    const canPurchase = farmData.can_purchase_land;
    const nextPrice = farmData.next_land_price;
    const currentStardust = farmData.farm.stardust;
    const requiredClassName = farmData.required_class_name || 'Veteran User';
    const currentLands = farmData.farm.land_slots;
    
    // 如果已经达到上限（12块地），隐藏按钮
    if (currentLands >= 12) {
        section.style.display = 'none';
        return;
    }
    
    section.style.display = 'block';
    
    // 如果下一块地免费（前3块）
    if (nextPrice === 0) {
        section.style.display = 'none'; // 免费的不显示购买按钮
        return;
    }
    
    // 检查等级限制
    if (!canPurchase) {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = `🔒 需要 ${requiredClassName} 等级`;
        info.innerHTML = `<span style="color: #f39c12;">⚠️ 购买土地需要达到 ${requiredClassName} 等级</span>`;
        return;
    }
    
    // 检查星尘是否足够
    if (currentStardust < nextPrice) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = `💰 星尘不足（需要 ${nextPrice}⭐）`;
        info.innerHTML = `<span style="color: #e74c3c;">还需 ${nextPrice - currentStardust}⭐ 星尘</span>`;
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.innerHTML = `🏞️ 购买土地（${nextPrice}⭐）`;
        info.innerHTML = `<span style="color: #2ecc71;">✓ 可购买第 ${currentLands + 1} 块土地</span>`;
    }
}

// 渲染土地
function renderLands() {
    const container = document.getElementById('farmLands');
    container.innerHTML = '';

    farmData.lands.forEach(land => {
        const div = document.createElement('div');
        div.className = 'land-plot ' + land.status;
        div.setAttribute('data-land-id', land.id);

        if (land.status === 'empty') {
            div.innerHTML = `
                <div class="crop-emoji">🌱</div>
                <div class="crop-name">空地</div>
                ${isOwnFarm ? '<button class="btn land-btn" onclick="openPlantModal(' + land.id + ')">种植</button>' : '<div class="crop-status">空闲中</div>'}
            `;
        } else {
            const crop = farmData.crops.find(c => c.id === land.crop_id);
            if (!crop) return;

            div.innerHTML = `
                <div class="crop-emoji">${renderPlanetIcon(crop, 'lg')}</div>
                <div class="crop-name">${crop.name}</div>
                <div class="crop-status">${getStatusText(land)}</div>
                ${renderLandActions(land)}
            `;
        }

        container.appendChild(div);
    });
}

// 获取状态文本
function getStatusText(land) {
    if (land.status === 'growing') {
        const remaining = getRemainingTime(land.mature_at);
        return `成熟倒计时: ${remaining}`;
    } else if (land.status === 'mature') {
        return '✨ 可以收获了！';
    } else if (land.status === 'withered') {
        return '⚠️ 已枯萎';
    }
    return '';
}

// 计算剩余时间
function getRemainingTime(matureAt) {
    const now = new Date();
    const mature = new Date(matureAt);
    const diff = mature - now;
    
    if (diff <= 0) return '0分钟';
    
    const hours = Math.floor(diff / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    
    if (hours > 0) {
        return `${hours}小时${minutes}分钟`;
    } else {
        return `${minutes}分钟`;
    }
}

// 渲染土地操作按钮
function renderLandActions(land) {
    if (!isOwnFarm && !['mature', 'growing'].includes(land.status)) {
        return '';
    }

    let actions = '<div class="land-actions">';
    
    if (isOwnFarm) {
        if (land.status === 'mature' || land.status === 'withered') {
            actions += `<button class="land-btn btn-success" onclick="harvest(${land.id})">收获</button>`;
        }
    } else {
        // 访问好友农场
        if (land.status === 'growing') {
            actions += `<button class="land-btn" onclick="waterLand(${land.id})">💧 浇水</button>`;
        }
        if (land.status === 'mature' && land.can_be_stolen) {
            actions += `<button class="land-btn btn-danger" onclick="stealFragment(${land.id})">🦹 偷碎片</button>`;
        }
    }
    
    actions += '</div>';
    return actions;
}

// 打开种植模态框
function openPlantModal(landId) {
    selectedLandId = landId;
    const modal = document.getElementById('plantModal');
    const seedsList = document.getElementById('seedsList');
    
    seedsList.innerHTML = '';
    farmData.crops.forEach(crop => {
        const canAfford = farmData.farm.stardust >= crop.seed_price;
        const levelOk = farmData.farm.level >= crop.level_required;
        const disabled = !canAfford || !levelOk;
        
        const div = document.createElement('div');
        div.className = 'shop-item';
        div.innerHTML = `
            <div class="shop-item-emoji">${renderPlanetIcon(crop, 'md')}</div>
            <div class="shop-item-info">
                <div class="shop-item-name">${crop.name}</div>
                <div class="shop-item-details">
                    成熟时间: ${formatDuration(crop.grow_duration)}<br>
                    产出: ${crop.fragment_min}-${crop.fragment_max}个碎片<br>
                    需要等级: ${crop.level_required}
                </div>
                <div class="shop-item-price">💰 ${crop.seed_price} 星尘</div>
            </div>
            <button class="shop-item-btn" onclick="plantCrop(${crop.id})" ${disabled ? 'disabled' : ''}>
                ${disabled ? (levelOk ? '星尘不足' : '等级不足') : '种植'}
            </button>
        `;
        seedsList.appendChild(div);
    });
    
    modal.classList.add('active');
}

// 关闭模态框
function closePlantModal() {
    document.getElementById('plantModal').classList.remove('active');
}

// 种植
function plantCrop(cropId) {
    callAPI('plantStardustCrop', { land_id: selectedLandId, crop_id: cropId })
        .then(data => {
            showNotification(data.message, 'success');
            closePlantModal();
            loadFarmData();
        })
        .catch(error => {
            showNotification(error, 'error');
        });
}

// 收获
function harvest(landId) {
    callAPI('harvestStardustCrop', { land_id: landId })
        .then(data => {
            showNotification(data.message, 'success');
            loadFarmData();
        })
        .catch(error => {
            showNotification(error, 'error');
        });
}

// 浇水
function waterLand(landId) {
    callAPI('waterStardustLand', { target_user_id: targetUserId, land_id: landId })
        .then(data => {
            showNotification(data.message, 'success');
            loadFarmData();
        })
        .catch(error => {
            showNotification(error, 'error');
        });
}

// 偷碎片
function stealFragment(landId) {
    if (!confirm('确定要偷取碎片吗？')) return;
    
    callAPI('stealStardustFragment', { target_user_id: targetUserId, land_id: landId })
        .then(data => {
            showNotification(data.message, 'success');
            loadFarmData();
        })
        .catch(error => {
            showNotification(error, 'error');
        });
}

// 渲染背包
function renderInventory() {
    const fragmentsGrid = document.getElementById('fragmentsGrid');
    const planetsGrid = document.getElementById('planetsGrid');
    
    fragmentsGrid.innerHTML = '';
    planetsGrid.innerHTML = '';

    // 渲染碎片
    Object.values(farmData.fragments).forEach(item => {
        const crop = farmData.crops.find(c => c.id == item.item_id);
        if (!crop) return;
        
        const div = document.createElement('div');
        div.className = 'inventory-item';
        div.innerHTML = `
            <div class="inventory-item-emoji">${renderPlanetIcon(crop, 'md')}</div>
            <div class="inventory-item-name">${crop.name}碎片</div>
            <div class="inventory-item-count">${item.quantity}/9</div>
            ${isOwnFarm && item.quantity >= 9 ? `<button class="craft-btn" onclick="craftPlanet(${crop.id})">合成行星</button>` : ''}
        `;
        fragmentsGrid.appendChild(div);
    });

    if (fragmentsGrid.children.length === 0) {
        fragmentsGrid.innerHTML = '<p style="color: #aaa; text-align: center; grid-column: 1/-1;">暂无碎片</p>';
    }

    // 渲染行星
    Object.values(farmData.planets).forEach(item => {
        const crop = farmData.crops.find(c => c.id == item.item_id);
        if (!crop) return;
        
        const div = document.createElement('div');
        div.className = 'inventory-item';
        div.innerHTML = `
            <div class="inventory-item-emoji">${renderPlanetIcon(crop, 'md')}</div>
            <div class="inventory-item-name">${crop.name}</div>
            <div class="inventory-item-count">×${item.quantity}</div>
        `;
        planetsGrid.appendChild(div);
    });

    if (planetsGrid.children.length === 0) {
        planetsGrid.innerHTML = '<p style="color: #aaa; text-align: center; grid-column: 1/-1;">暂无完整行星</p>';
    }
}

// 合成行星
function craftPlanet(cropId) {
    if (!confirm('消耗9个碎片合成1个完整行星？')) return;
    
    callAPI('craftStardustPlanet', { crop_id: cropId })
        .then(data => {
            showNotification(data.message, 'success');
            loadFarmData();
        })
        .catch(error => {
            showNotification(error, 'error');
        });
}

// 购买土地
function purchaseLand() {
    const nextPrice = farmData.next_land_price;
    const currentLands = farmData.farm.land_slots;
    
    if (!confirm(`确定花费 ${nextPrice}⭐ 星尘购买第 ${currentLands + 1} 块土地吗？`)) return;
    
    callAPI('purchaseStardustLand', {})
        .then(data => {
            showNotification(data.message || '购买成功！', 'success');
            loadFarmData();
        })
        .catch(error => {
            showNotification(error, 'error');
        });
}

// 切换侧边栏标签
function showTab(tabName) {
    // 更新按钮状态
    document.querySelectorAll('.sidebar-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // 更新内容
    document.querySelectorAll('.sidebar-section').forEach(section => {
        section.classList.remove('active');
    });
    document.getElementById(tabName + 'Section').classList.add('active');
}

// 显示商店
function showShop() {
    alert('商店功能开发中...');
}

// 格式化时长
function formatDuration(minutes) {
    if (minutes < 60) {
        return minutes + '分钟';
    } else if (minutes < 1440) {
        const hours = Math.floor(minutes / 60);
        return hours + '小时';
    } else {
        const days = Math.floor(minutes / 1440);
        const hours = Math.floor((minutes % 1440) / 60);
        return hours > 0 ? days + '天' + hours + '小时' : days + '天';
    }
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
        // NexusPHP AJAX 返回格式: { ret: 0, msg: "OK", data: {...} }
        if (data.ret === 0) {
            return data.data;
        } else {
            throw data.msg || '操作失败';
        }
    });
}

// 显示通知
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// 定时刷新
setInterval(() => {
    if (farmData) {
        loadFarmData();
    }
}, 60000); // 每分钟刷新一次
</script>
</body>
</html>

