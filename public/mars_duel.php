<?php
require_once "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$nickname = $CURUSER["username"] ?? ("guest-" . substr(md5(getip() . microtime(true)), 0, 6));
$userId = intval($CURUSER["id"] ?? 0);

// 房间配置，可用站点设置 pvp.rooms（JSON 数组），否则默认两个房间
$ownerCardCount = intval($CURUSER['mars_owner_card'] ?? 0);
$tableIdParam = intval($_GET['table_id'] ?? 0);

// 可通过 query 覆盖 ws host/port，便于测试；端口为 0 时表示不拼端口
$wsHostOverride = $_GET['ws_host'] ?? get_setting('pvp.ws_host', 'localhost');
$wsPortSetting = get_setting('pvp.ws_port', null);
$wsPortOverride = array_key_exists('ws_port', $_GET) ? $_GET['ws_port'] : ($wsPortSetting === null ? '' : $wsPortSetting);
$defaultPort = 2346;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>火星幸运局 - PVP 幸运对战</title>
    <style>
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
            background:
                radial-gradient(ellipse at top, rgba(0, 212, 255, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at bottom right, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                linear-gradient(135deg, #0a0e1a 0%, #1a1845 30%, #2d1b4e 60%, #1a0f2e 100%);
            background-attachment: fixed;
            color: #e5e7eb;
            min-height: 100vh;
        }
        
        h2 {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.6);
            margin: 0 0 16px 0;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        p {
            margin: 0 0 20px 0;
            color: #9ca3af;
            font-size: 14px;
        }
        
        p b {
            color: #00d4ff;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
        }
        
        .layout {
            display: flex;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .left {
            flex: 2;
            background: rgba(10, 22, 40, 0.7);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.15);
            min-height: 520px;
        }
        
        .right {
            width: 360px;
            background: rgba(10, 22, 40, 0.7);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.15);
            min-height: 520px;
            max-height: 90vh;
            overflow: hidden;
        }
        
        .rooms {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        
        .room-card {
            background: rgba(10, 22, 40, 0.8);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 12px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.15);
            max-width: 320px;
            width: 100%;
        }
        
        .room-card h4 {
            margin: 0 0 6px 0;
            color: #00d4ff;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.5);
        }
        
        .small {
            color: #9ca3af;
            font-size: 12px;
            line-height: 1.6;
        }
        
        button {
            cursor: pointer;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.8), rgba(168, 85, 247, 0.8));
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 12px rgba(0, 212, 255, 0.3), 0 0 20px rgba(0, 212, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 212, 255, 0.4), 0 0 30px rgba(0, 212, 255, 0.3);
        }
        
        button:active:not(:disabled) {
            transform: translateY(0);
        }
        
        button.secondary {
            background: linear-gradient(135deg, rgba(70, 90, 100, 0.8), rgba(55, 71, 79, 0.8));
            box-shadow: 0 4px 12px rgba(70, 90, 100, 0.3), 0 0 20px rgba(70, 90, 100, 0.2);
        }
        
        button.secondary:hover:not(:disabled) {
            box-shadow: 0 6px 16px rgba(70, 90, 100, 0.4), 0 0 30px rgba(70, 90, 100, 0.3);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        #status {
            margin: 8px 0;
            color: #9ca3af;
            font-size: 13px;
            padding: 8px 12px;
            background: rgba(10, 22, 40, 0.6);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 8px;
            display: inline-block;
        }
        
        #status.connected {
            color: #00d4ff;
            border-color: rgba(0, 212, 255, 0.4);
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
        }
        
        #reconnect-btn {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.8), rgba(37, 99, 235, 0.8)) !important;
            color: #fff !important;
            font-size: 13px !important;
            padding: 8px 16px !important;
            border: 1px solid rgba(59, 130, 246, 0.5) !important;
            cursor: pointer;
            border-radius: 8px;
        }
        
        #reconnect-btn:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.9), rgba(37, 99, 235, 0.9)) !important;
            transform: translateY(-1px);
        }
        
        #seats {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .seat {
            flex: 1;
            background: rgba(10, 22, 40, 0.8);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 10px;
            padding: 16px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .seat.occupied {
            border-color: rgba(0, 212, 255, 0.5);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.2);
        }
        
        .seat.my-seat {
            border-color: rgba(251, 191, 36, 0.5);
            box-shadow: 0 0 20px rgba(251, 191, 36, 0.2);
            background: rgba(10, 22, 40, 0.9);
        }
        
        .seat .title {
            font-weight: 600;
            font-size: 16px;
            color: #00d4ff;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
            margin-bottom: 8px;
        }
        
        .seat .body {
            min-height: 24px;
            color: #9ca3af;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .seat .body.occupied {
            color: #00d4ff;
            font-weight: 500;
        }
        
        .seat .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9ca3af;
            margin-right: 6px;
        }
        
        .seat .status-indicator.ready {
            background: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
        }
        
        .seat .status-indicator.playing {
            background: #f59e0b;
            box-shadow: 0 0 8px rgba(245, 158, 11, 0.6);
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        #arena {
            background: rgba(10, 22, 40, 0.6);
            border: 1px dashed rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 20px;
            min-height: 360px;
        }
        
        #arena h4 {
            color: #00d4ff;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
            margin: 0 0 12px 0;
        }
        
        #duel-result {
            margin-top: 16px;
            background: rgba(10, 22, 40, 0.8);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 10px;
            padding: 16px;
            min-height: 140px;
            backdrop-filter: blur(10px);
        }
        
        #duel-result .dice-container {
            display: flex;
            gap: 12px;
            align-items: center;
            margin: 12px 0;
        }
        
        .dice {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(0, 212, 255, 0.4);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.6);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .dice.rolling {
            animation: rollDice 0.6s ease-in-out infinite;
        }
        
        @keyframes rollDice {
            0%, 100% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(90deg) scale(1.1); }
            50% { transform: rotate(180deg) scale(1); }
            75% { transform: rotate(270deg) scale(1.1); }
        }
        
        .dice.winner {
            border-color: rgba(251, 191, 36, 0.6);
            box-shadow: 0 0 20px rgba(251, 191, 36, 0.4);
            animation: winnerGlow 1s ease-in-out infinite;
        }
        
        @keyframes winnerGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(251, 191, 36, 0.4); }
            50% { box-shadow: 0 0 30px rgba(251, 191, 36, 0.6); }
        }
        
        .dice.loser {
            opacity: 0.5;
            filter: grayscale(0.5);
        }
        
        .result-winner {
            color: #fbbf24;
            font-weight: 600;
            font-size: 18px;
            text-shadow: 0 0 10px rgba(251, 191, 36, 0.6);
            margin-top: 12px;
            animation: winnerText 1s ease-in-out infinite;
        }
        
        @keyframes winnerText {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        #stats {
            margin-top: 16px;
            background: rgba(10, 22, 40, 0.6);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 10px;
            padding: 16px;
            backdrop-filter: blur(10px);
        }
        
        #stats > div:first-child {
            color: #00d4ff;
            font-weight: 600;
            margin-bottom: 8px;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
        }
        
        #history {
            margin-top: 12px;
            max-height: 180px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.6;
            padding: 8px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 6px;
        }
        
        #history div {
            padding: 4px 0;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
        }
        
        #history div:last-child {
            border-bottom: none;
        }
        
        #overlay {
            position: fixed;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
        }
        
        #overlay .bubble {
            background: linear-gradient(135deg, rgba(10, 22, 40, 0.98), rgba(30, 27, 75, 0.98));
            border: 2px solid rgba(0, 212, 255, 0.5);
            border-radius: 20px;
            padding: 40px 60px;
            font-size: 72px;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 30px rgba(0, 212, 255, 0.8);
            box-shadow: 0 0 40px rgba(0, 212, 255, 0.4);
            animation: countdownPulse 0.5s ease-in-out;
        }
        
        @keyframes countdownPulse {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        #chat-log {
            flex: 1;
            min-height: 200px;
            max-height: 400px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            background: rgba(10, 22, 40, 0.6);
            border-radius: 8px;
            padding: 12px;
            overflow-y: auto;
            overflow-x: hidden;
            backdrop-filter: blur(10px);
        }
        
        #chat-input {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        #chat-input input {
            flex: 1;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            background: rgba(10, 22, 40, 0.8);
            color: #e5e7eb;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        #chat-input input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }
        
        .msg {
            margin: 4px 0;
            padding: 4px 0;
        }
        
        .sys {
            color: #8aa0b8;
            font-style: italic;
        }
        
        .time {
            color: #6b7280;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .user {
            color: #00d4ff;
            font-weight: 600;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
        }
        
        #actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        #actions button {
            flex: 1;
        }
        
        #start {
            padding: 16px 24px !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.9), rgba(245, 158, 11, 0.9)) !important;
            color: #fff !important;
            text-shadow: 0 0 12px rgba(255, 255, 255, 0.8) !important;
            box-shadow: 
                0 6px 20px rgba(251, 191, 36, 0.4),
                0 0 30px rgba(251, 191, 36, 0.3),
                inset 0 0 15px rgba(255, 255, 255, 0.2) !important;
            border: 2px solid rgba(251, 191, 36, 0.6) !important;
            animation: startButtonPulse 2s ease-in-out infinite;
            position: relative;
            overflow: hidden;
        }
        
        #start:hover {
            transform: translateY(-3px) scale(1.02) !important;
            box-shadow: 
                0 8px 25px rgba(251, 191, 36, 0.5),
                0 0 40px rgba(251, 191, 36, 0.4),
                inset 0 0 20px rgba(255, 255, 255, 0.3) !important;
            text-shadow: 0 0 15px rgba(255, 255, 255, 1) !important;
        }
        
        #start:active {
            transform: translateY(-1px) scale(0.98) !important;
        }
        
        @keyframes startButtonPulse {
            0%, 100% {
                box-shadow: 
                    0 6px 20px rgba(251, 191, 36, 0.4),
                    0 0 30px rgba(251, 191, 36, 0.3),
                    inset 0 0 15px rgba(255, 255, 255, 0.2);
            }
            50% {
                box-shadow: 
                    0 6px 25px rgba(251, 191, 36, 0.5),
                    0 0 40px rgba(251, 191, 36, 0.4),
                    inset 0 0 18px rgba(255, 255, 255, 0.25);
            }
        }
        
        #cheers {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        #cheers button {
            flex: 1;
            min-width: 80px;
            font-size: 16px;
        }
        
        #roll-modal {
            position: fixed;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
        }
        
        #roll-modal .panel {
            background: linear-gradient(135deg, rgba(10, 22, 40, 0.98), rgba(30, 27, 75, 0.98));
            border: 2px solid rgba(0, 212, 255, 0.4);
            border-radius: 16px;
            padding: 24px;
            width: 360px;
            box-shadow: 0 0 30px rgba(0, 212, 255, 0.3);
            backdrop-filter: blur(15px);
        }
        
        #roll-modal h4 {
            margin: 0 0 16px 0;
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.6);
            font-size: 20px;
        }
        
        #roll-modal label {
            display: block;
            margin: 12px 0 6px 0;
            font-size: 13px;
            color: #9ca3af;
            font-weight: 500;
        }
        
        #roll-modal select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            background: rgba(10, 22, 40, 0.8);
            color: #e5e7eb;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        #roll-modal select:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }
        
        #roll-modal .actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        
        #roll-modal button {
            flex: 1;
        }
        
        #roll-countdown {
            margin-top: 12px;
            text-align: center;
            color: #f59e0b;
            font-weight: 600;
            font-size: 14px;
        }
        
        .leave-seat-btn {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.8), rgba(75, 85, 99, 0.8)) !important;
            color: #fff !important;
            font-size: 13px !important;
            padding: 8px 16px !important;
            border: 1px solid rgba(107, 114, 128, 0.5) !important;
        }
        
        .leave-seat-btn:hover {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.9), rgba(75, 85, 99, 0.9)) !important;
            transform: translateY(-1px);
        }
        
        .kick-player-btn {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(220, 38, 38, 0.8)) !important;
            color: #fff !important;
            font-size: 13px !important;
            padding: 8px 16px !important;
            border: 1px solid rgba(239, 68, 68, 0.5) !important;
        }
        
        .kick-player-btn:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.9)) !important;
            transform: translateY(-1px);
        }
        
        .take-seat-btn {
            width: 100%;
            margin-top: 8px;
        }
        
        /* 大骰子动画弹窗 */
        #dice-animation-modal {
            position: fixed;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            z-index: 10001;
        }
        
        .dice-animation-panel {
            background: linear-gradient(135deg, rgba(10, 22, 40, 0.98), rgba(30, 27, 75, 0.98));
            border: 2px solid rgba(0, 212, 255, 0.5);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 0 50px rgba(0, 212, 255, 0.4);
            backdrop-filter: blur(15px);
            min-width: 400px;
        }
        
        .dice-animation-title {
            color: #00d4ff;
            font-size: 24px;
            font-weight: 600;
            text-shadow: 0 0 15px rgba(0, 212, 255, 0.6);
            margin-bottom: 30px;
        }
        
        .dice-animation-container {
            display: flex;
            gap: 30px;
            justify-content: center;
            align-items: center;
            margin: 30px 0;
        }
        
        .dice-large {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.15);
            border: 3px solid rgba(0, 212, 255, 0.5);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.8);
            box-shadow: 0 0 30px rgba(0, 212, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .dice-large.rolling {
            animation: rollDiceLarge 0.4s ease-in-out infinite;
        }
        
        @keyframes rollDiceLarge {
            0%, 100% { 
                transform: rotate(0deg) scale(1) translateY(0);
                opacity: 1;
            }
            25% { 
                transform: rotate(90deg) scale(1.15) translateY(-10px);
                opacity: 0.8;
            }
            50% { 
                transform: rotate(180deg) scale(1) translateY(0);
                opacity: 1;
            }
            75% { 
                transform: rotate(270deg) scale(1.15) translateY(-10px);
                opacity: 0.8;
            }
        }
        
        .dice-large.result {
            animation: diceResultPop 0.6s ease-out;
            border-color: rgba(251, 191, 36, 0.6);
            box-shadow: 0 0 40px rgba(251, 191, 36, 0.5);
        }
        
        @keyframes diceResultPop {
            0% { 
                transform: scale(0.8);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% { 
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .dice-animation-result {
            margin-top: 20px;
            color: #fbbf24;
            font-size: 20px;
            font-weight: 600;
            text-shadow: 0 0 15px rgba(251, 191, 36, 0.6);
        }
        
        .dice-animation-sum {
            color: #00d4ff;
            font-size: 18px;
        }
        
        .dice-animation-sum span {
            color: #fbbf24;
            font-size: 24px;
            font-weight: bold;
            text-shadow: 0 0 15px rgba(251, 191, 36, 0.6);
        }
    </style>
</head>
<body>
    <div style="max-width: 1400px; margin: 0 auto;">
        <h2>🚀 火星赌局 · PVP 幸运对战</h2>
        <p style="margin: 6px 0 14px 0; color: #f87171; font-size: 13px;">温馨提示：本功能仅限娱乐，严禁赌博，严格遵守法律法规。</p>
        <div style="background: rgba(10, 22, 40, 0.6); border: 1px solid rgba(0, 212, 255, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 16px; backdrop-filter: blur(10px); box-shadow: 0 0 15px rgba(0, 212, 255, 0.15);">
            <p style="margin: 0; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <span style="color: #9ca3af;">当前用户：</span>
                <b><?php echo htmlspecialchars($nickname); ?></b>
                <span style="margin: 0 12px; color: rgba(0, 212, 255, 0.4);">|</span>
                <span style="color: #9ca3af;">
                    <span style="color: #fbbf24; text-shadow: 0 0 8px rgba(251, 191, 36, 0.4);">⭐</span>
                    我的火星老板卡：<b style="color: #fbbf24; text-shadow: 0 0 8px rgba(251, 191, 36, 0.4);"><?php echo $ownerCardCount; ?></b> 张
                </span>
                <span style="margin: 0 12px; color: rgba(0, 212, 255, 0.4);">|</span>
                <span style="color: #9ca3af;">魔力值：</span>
                <b id="current-bonus" style="color: #fbbf24; text-shadow: 0 0 8px rgba(251, 191, 36, 0.4);">
                    <?php echo number_format(floatval($CURUSER['seedbonus'] ?? 0), 1); ?>
                </b>
                <button id="refresh-bonus" style="padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(0, 212, 255, 0.4); background: rgba(0, 212, 255, 0.08); color: #00d4ff; cursor: pointer;">刷新余额</button>
            </p>
        </div>
        <div style="display: flex; gap: 12px; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap;">
            <div>
                <div id="status">未连接</div>
                <button id="reconnect-btn" style="display: none; margin-top: 8px; width: 100%;">重新连接</button>
            </div>
            <div id="rooms"></div>
            <div id="spectators-list" style="background: rgba(10, 22, 40, 0.8); border: 1px solid rgba(0, 212, 255, 0.3); border-radius: 12px; padding: 12px; backdrop-filter: blur(10px); box-shadow: 0 0 15px rgba(0, 212, 255, 0.15); max-width: 320px; width: 100%;">
                <h4 style="margin: 0 0 8px 0; color: #00d4ff; text-shadow: 0 0 8px rgba(0, 212, 255, 0.5); font-size: 16px;">👥 观众列表</h4>
                <div id="spectators-names" class="small" style="min-height: 20px;">暂无观众</div>
            </div>
        </div>
    <div class="layout">
        <div class="left">
            <div id="seats">
                <div class="seat" id="seat1">
                    <div class="title">玩家一</div>
                    <div class="body">空位</div>
                    <button class="take-seat-btn" data-seat="1" style="margin-top: 8px; width: 100%;">占位</button>
                </div>
                <div class="seat" id="seat2">
                    <div class="title">玩家二</div>
                    <div class="body">空位</div>
                    <button class="take-seat-btn" data-seat="2" style="margin-top: 8px; width: 100%;">占位</button>
                </div>
            </div>
            <div id="arena">
                <h4>对战内容区域</h4>
                <p>两名玩家就位后自动开局，掷 2 颗骰子比大小。支持观战与聊天。</p>
                <p id="spectators" class="small">观众：0</p>
                <div id="duel-result">
                    <div class="small">对局结果将在这里显示</div>
                </div>
                <div id="stats">
                    <div><b>胜负统计</b></div>
                    <div id="stat-p1" class="small">玩家一：0 胜</div>
                    <div id="stat-p2" class="small">玩家二：0 胜</div>
                    <div id="history">
                        <div class="small">对局记录将在这里显示</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="right">
            <div id="chat-log"></div>
            <div id="chat-input">
                <input type="text" id="chat-text" placeholder="输入消息，回车发送">
                <button id="chat-send">发送</button>
            </div>
            <div id="cheers">
                <button data-emoji="👏">👏 鼓掌</button>
                <button data-emoji="🔥">🔥 鼓励</button>
                <button data-emoji="😂">😂 乐了</button>
                <button data-emoji="👍">👍 赞一个</button>
            </div>
            <div id="actions">
                <button id="start">开始对局 / 再来一次</button>
            </div>
        </div>
    </div>
    </div>

    <div id="overlay"><div class="bubble" id="overlay-text">3</div></div>
    <div id="dice-animation-modal">
        <div class="dice-animation-panel">
            <div class="dice-animation-title" id="dice-animation-player">玩家投掷中</div>
            <div class="dice-animation-container">
                <div class="dice-large rolling" id="dice-large-1">?</div>
                <div class="dice-large rolling" id="dice-large-2">?</div>
            </div>
            <div class="dice-animation-result" id="dice-animation-result" style="display: none;">
                <div class="dice-animation-sum">总和：<span id="dice-animation-sum">0</span></div>
            </div>
        </div>
    </div>
    <div id="roll-modal">
        <div class="panel">
            <h4 id="roll-title">请掷骰</h4>
            <label>力度</label>
            <select id="roll-power">
                <option value="light">轻</option>
                <option value="medium" selected>中</option>
                <option value="heavy">重</option>
            </select>
            <label>咒语</label>
            <select id="roll-spell">
                <option value="lucky">幸运</option>
                <option value="crit">暴击</option>
                <option value="steady" selected>稳重</option>
            </select>
            <div class="actions">
                <button id="roll-confirm" style="width: 100%;">投掷</button>
            </div>
            <div class="small" id="roll-countdown">剩余 30 秒</div>
        </div>
    </div>

    <script>
    (function() {
        var rooms = [];
        var ownerCardCount = <?php echo json_encode($ownerCardCount); ?>;
        var currentBonus = <?php echo json_encode(floatval($CURUSER['seedbonus'] ?? 0)); ?>;
        var userId = <?php echo json_encode($userId); ?>;
        var hostOverride = "<?php echo htmlspecialchars($wsHostOverride, ENT_QUOTES); ?>";
        var portOverride = "<?php echo htmlspecialchars($wsPortOverride, ENT_QUOTES); ?>";
        var defaultPort = "<?php echo $defaultPort; ?>";
        var tableIdParam = <?php echo json_encode($tableIdParam); ?>;
        // 使用当前登录用户名；传输时再 encodeURIComponent，避免双重编码
        var user = <?php echo json_encode($nickname, JSON_UNESCAPED_UNICODE); ?>;

        var currentRoom = null;
        var currentRole = 'spectator';
        var currentSeat = null;
        var ws = null;
        var currentTableOwnerId = null; // 当前桌子的房主 ID
        var isTableOwner = false; // 当前用户是否是房主

        var roomsEl = document.getElementById('rooms');
        var statusEl = document.getElementById('status');
        var reconnectBtn = document.getElementById('reconnect-btn');
        var spectatorsListEl = document.getElementById('spectators-names');
        var currentBonusEl = document.getElementById('current-bonus');
        var refreshBonusBtn = document.getElementById('refresh-bonus');
        
        // 保存连接参数，用于重新连接
        var lastConnectParams = {
            room: null,
            role: null,
            seat: null,
            tableId: null,
            ownerId: null
        };
        var spectatorsList = [];
        var logEl = document.getElementById('chat-log');
        var inputEl = document.getElementById('chat-text');
        var sendBtn = document.getElementById('chat-send');
        var resultEl = document.getElementById('duel-result');
        var startBtn = document.getElementById('start');
        if (startBtn) startBtn.style.display = 'none'; // 观众默认不显示开始按钮

        function updateStartButtonVisibility() {
            if (!startBtn) return;
            if (currentSeat === 1 || currentSeat === 2) {
                startBtn.style.display = 'inline-block';
            } else {
                startBtn.style.display = 'none';
            }
        }

        function renderCurrentBonus(bonus) {
            currentBonus = bonus;
            if (currentBonusEl) {
                currentBonusEl.textContent = bonus.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1});
            }
        }

        function refreshBonus() {
            fetch('ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_current_bonus'
            }).then(function(res){return res.json();}).then(function(data){
                if (data && data.ret === 0 && data.data && typeof data.data.bonus !== 'undefined') {
                    renderCurrentBonus(parseFloat(data.data.bonus));
                }
            }).catch(function(err){
                console.error('refreshBonus error', err);
            });
        }
        var arenaInfoEl = document.createElement('div');
        arenaInfoEl.className = 'small';
        document.getElementById('arena').insertBefore(arenaInfoEl, document.getElementById('arena').firstChild);
        var statP1 = document.getElementById('stat-p1');
        var statP2 = document.getElementById('stat-p2');
        var historyEl = document.getElementById('history');
        var cheersEl = document.getElementById('cheers');
        var overlay = document.getElementById('overlay');
        var overlayText = document.getElementById('overlay-text');
        var rollModal = document.getElementById('roll-modal');
        var rollTitle = document.getElementById('roll-title');
        var diceAnimationModal = document.getElementById('dice-animation-modal');
        var diceAnimationPlayer = document.getElementById('dice-animation-player');
        var diceLarge1 = document.getElementById('dice-large-1');
        var diceLarge2 = document.getElementById('dice-large-2');
        var diceAnimationResult = document.getElementById('dice-animation-result');
        var diceAnimationSum = document.getElementById('dice-animation-sum');
        var rollPower = document.getElementById('roll-power');
        var rollSpell = document.getElementById('roll-spell');
        var rollConfirm = document.getElementById('roll-confirm');
        var rollCountdown = document.getElementById('roll-countdown');

        function updateArenaInfo(roomKey) {
            var r = rooms.find(function (x) { return x.key === roomKey; });
            if (!r) {
                arenaInfoEl.textContent = '桌面信息：-';
                return;
            }
            var ownerUntilText = r.owner_until ? new Date(r.owner_until).toLocaleString() : '-';
            arenaInfoEl.textContent = '桌面信息：下注额 ' + r.bet_amount + ' | 老板抽成 ' + r.owner_rake_percent + '% | 到期 ' + ownerUntilText;
        }

        function fetchRoomById(tableId) {
            roomsEl.innerHTML = '<div class="small">加载桌子信息...</div>';
            var form = new FormData();
            form.append('action', 'get_game_tables');
            fetch('ajax.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            }).then(function (res) { return res.json(); })
              .then(function (resp) {
                if (resp.ret === 0) {
                    var list = resp.data || [];
                    var found = list.find(function (x) { return parseInt(x.id, 10) === parseInt(tableId, 10); });
                    if (!found) {
                        roomsEl.innerHTML = '<div class="small" style="color:red;">未找到该桌子，请返回桌子列表</div>';
                        return;
                    }
                    rooms = [{
                        id: found.id,
                        key: 'mars-table-' + found.id,
                        name: found.name,
                        bet_amount: found.bet_amount,
                        owner_rake_percent: found.owner_rake_percent,
                        owner_id: found.owner_id,
                        owner_name: found.owner_name,
                        owner_until: found.owner_until,
                        is_expired: found.is_expired,
                    }];
                    var r = rooms[0];
                    var ownerUntilText = r.owner_until ? new Date(r.owner_until).toLocaleString() : '-';
                    roomsEl.innerHTML = '<div class="room-card">' +
                        '<h4>' + r.name + '</h4>' +
                        '<div class="small">频道：' + r.key + '</div>' +
                        '<div class="small">下注额：' + r.bet_amount + '</div>' +
                        '<div class="small">老板抽成：' + r.owner_rake_percent + '%</div>' +
                        '<div class="small">老板：' + (r.owner_name || '暂无老板') + '</div>' +
                        '<div class="small">到期：' + ownerUntilText + '</div>' +
                        '</div>';
                    // 保存房主信息
                    currentTableOwnerId = r.owner_id || null;
                    isTableOwner = (currentTableOwnerId && userId && currentTableOwnerId === userId);
                    updateArenaInfo(r.key);
                    // 默认以观众身份连接，传递 table_id 和 owner_id
                    connect(r.key, 'spectator', null, r.id, currentTableOwnerId);
                } else {
                    roomsEl.innerHTML = '<div class="small" style="color:red;">加载失败：' + (resp.msg || '未知错误') + '</div>';
                }
            }).catch(function (err) {
                roomsEl.innerHTML = '<div class="small" style="color:red;">加载失败：' + err + '</div>';
            });
        }

        function appendLine(html, cls) {
            var div = document.createElement('div');
            div.className = cls || 'msg';
            div.innerHTML = html;
            logEl.appendChild(div);
            logEl.scrollTop = logEl.scrollHeight;
        }

        function updateSpectatorsList(list) {
            if (!spectatorsListEl) return;
            var names = list || [];
            if (names.length === 0) {
                spectatorsListEl.innerHTML = '<span style="color: #6b7280;">暂无观众</span>';
            } else {
                spectatorsListEl.innerHTML = names.map(function(name) {
                    return '<div style="padding: 4px 0; color: #9ca3af;">' + name + '</div>';
                }).join('');
            }
        }

        function updateSeats(players, spectators) {
            var p1 = players && players.p1 ? players.p1 : '空位';
            var p2 = players && players.p2 ? players.p2 : '空位';
            var seat1El = document.getElementById('seat1');
            var seat2El = document.getElementById('seat2');
            var body1El = document.querySelector('#seat1 .body');
            var body2El = document.querySelector('#seat2 .body');
            
            // 更新座位内容
            if (p1 === '空位') {
                body1El.innerHTML = '<span class="status-indicator"></span>空位';
                body1El.className = 'body';
                seat1El.classList.remove('occupied', 'my-seat');
            } else {
                var p1Html = '<span class="status-indicator ready"></span>' + p1;
                if (p1 === user && totalBonusEarned > 0) {
                    p1Html += '<div style="margin-top: 6px; font-size: 12px; color: #fbbf24; text-shadow: 0 0 6px rgba(251, 191, 36, 0.4);">💰 累计获得：' + totalBonusEarned.toLocaleString() + ' 魔力值</div>';
                }
                body1El.innerHTML = p1Html;
                body1El.className = 'body occupied';
                seat1El.classList.add('occupied');
                if (p1 === user) {
                    seat1El.classList.add('my-seat');
                } else {
                    seat1El.classList.remove('my-seat');
                }
            }
            
            if (p2 === '空位') {
                body2El.innerHTML = '<span class="status-indicator"></span>空位';
                body2El.className = 'body';
                seat2El.classList.remove('occupied', 'my-seat');
            } else {
                var p2Html = '<span class="status-indicator ready"></span>' + p2;
                if (p2 === user && totalBonusEarned > 0) {
                    p2Html += '<div style="margin-top: 6px; font-size: 12px; color: #fbbf24; text-shadow: 0 0 6px rgba(251, 191, 36, 0.4);">💰 累计获得：' + totalBonusEarned.toLocaleString() + ' 魔力值</div>';
                }
                body2El.innerHTML = p2Html;
                body2El.className = 'body occupied';
                seat2El.classList.add('occupied');
                if (p2 === user) {
                    seat2El.classList.add('my-seat');
                } else {
                    seat2El.classList.remove('my-seat');
                }
            }
            
            document.getElementById('spectators').textContent = '观众：' + (spectators || 0);
            
            // 更新占位按钮状态
            var btn1 = document.querySelector('#seat1 .take-seat-btn');
            var btn2 = document.querySelector('#seat2 .take-seat-btn');
            var leaveBtn1 = document.querySelector('#seat1 .leave-seat-btn');
            var leaveBtn2 = document.querySelector('#seat2 .leave-seat-btn');
            
            // 如果没有"切换到观众"按钮，创建它
            if (!leaveBtn1) {
                leaveBtn1 = document.createElement('button');
                leaveBtn1.className = 'leave-seat-btn secondary';
                leaveBtn1.textContent = '切换到观众';
                leaveBtn1.style.cssText = 'margin-top: 8px; width: 100%;';
                leaveBtn1.setAttribute('data-seat', '1');
                seat1El.appendChild(leaveBtn1);
            }
            if (!leaveBtn2) {
                leaveBtn2 = document.createElement('button');
                leaveBtn2.className = 'leave-seat-btn secondary';
                leaveBtn2.textContent = '切换到观众';
                leaveBtn2.style.cssText = 'margin-top: 8px; width: 100%;';
                leaveBtn2.setAttribute('data-seat', '2');
                seat2El.appendChild(leaveBtn2);
            }
            
            // 创建踢人按钮（仅房主可见）
            var kickBtn1 = document.querySelector('#seat1 .kick-player-btn');
            var kickBtn2 = document.querySelector('#seat2 .kick-player-btn');
            if (!kickBtn1) {
                kickBtn1 = document.createElement('button');
                kickBtn1.className = 'kick-player-btn danger';
                kickBtn1.textContent = '踢到观众';
                kickBtn1.style.cssText = 'margin-top: 8px; width: 100%;';
                kickBtn1.setAttribute('data-seat', '1');
                seat1El.appendChild(kickBtn1);
            }
            if (!kickBtn2) {
                kickBtn2 = document.createElement('button');
                kickBtn2.className = 'kick-player-btn danger';
                kickBtn2.textContent = '踢到观众';
                kickBtn2.style.cssText = 'margin-top: 8px; width: 100%;';
                kickBtn2.setAttribute('data-seat', '2');
                seat2El.appendChild(kickBtn2);
            }
            
            if (btn1) {
                if (players && players.p1) {
                    btn1.disabled = true;
                    btn1.textContent = players.p1 === user ? '我的座位' : '已占用';
                    // 如果是自己的座位，显示"切换到观众"按钮
                    if (leaveBtn1) {
                        leaveBtn1.style.display = players.p1 === user ? 'block' : 'none';
                    }
                    // 如果是房主且不是自己的座位，显示"踢到观众"按钮
                    if (kickBtn1) {
                        kickBtn1.style.display = (isTableOwner && players.p1 !== user) ? 'block' : 'none';
                    }
                } else {
                    btn1.disabled = false;
                    btn1.textContent = '占位';
                    if (leaveBtn1) {
                        leaveBtn1.style.display = 'none';
                    }
                    if (kickBtn1) {
                        kickBtn1.style.display = 'none';
                    }
                }
            }
            if (btn2) {
                if (players && players.p2) {
                    btn2.disabled = true;
                    btn2.textContent = players.p2 === user ? '我的座位' : '已占用';
                    // 如果是自己的座位，显示"切换到观众"按钮
                    if (leaveBtn2) {
                        leaveBtn2.style.display = players.p2 === user ? 'block' : 'none';
                    }
                    // 如果是房主且不是自己的座位，显示"踢到观众"按钮
                    if (kickBtn2) {
                        kickBtn2.style.display = (isTableOwner && players.p2 !== user) ? 'block' : 'none';
                    }
                } else {
                    btn2.disabled = false;
                    btn2.textContent = '占位';
                    if (leaveBtn2) {
                        leaveBtn2.style.display = 'none';
                    }
                    if (kickBtn2) {
                        kickBtn2.style.display = 'none';
                    }
                }
            }
        }

        function renderResult(res) {
            if (!res) {
                resultEl.innerHTML = '<div class="small">对局结果将在这里显示</div>';
                return;
            }
            
            var p1Wins = res.p1_sum > res.p2_sum;
            var p2Wins = res.p2_sum > res.p1_sum;
            var isDraw = res.p1_sum === res.p2_sum;
            
            var dice1Class = p1Wins ? 'winner' : (p2Wins ? 'loser' : '');
            var dice2Class = p2Wins ? 'winner' : (p1Wins ? 'loser' : '');
            
            var winnerText = '';
            if (res.winner) {
                var bonus = res.winner_bonus || 0;
                winnerText = '<div class="result-winner">🏆 赢家：' + res.winner + '</div>';
                if (bonus > 0) {
                    winnerText += '<div style="margin-top: 12px; padding: 12px; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 8px; color: #fbbf24; font-size: 16px; font-weight: 600; text-align: center;">🎉 恭喜获得 ' + bonus.toLocaleString() + ' 魔力值！</div>';
                }
            } else {
                var p1Bonus = res.p1_bonus || 0;
                var p2Bonus = res.p2_bonus || 0;
                winnerText = '<div class="result-winner">🤝 平局</div>';
                if (p1Bonus > 0 || p2Bonus > 0) {
                    winnerText += '<div style="margin-top: 12px; padding: 12px; background: rgba(0, 212, 255, 0.1); border: 1px solid rgba(0, 212, 255, 0.3); border-radius: 8px; color: #00d4ff; font-size: 14px; text-align: center;">';
                    if (p1Bonus > 0) {
                        winnerText += '<div>' + res.p1 + ' 获得 ' + p1Bonus.toLocaleString() + ' 魔力值</div>';
                    }
                    if (p2Bonus > 0) {
                        winnerText += '<div>' + res.p2 + ' 获得 ' + p2Bonus.toLocaleString() + ' 魔力值</div>';
                    }
                    winnerText += '</div>';
                }
            }
            
            resultEl.innerHTML = '' +
                '<div style="margin-bottom: 12px; color: #9ca3af; font-size: 13px;">时间：' + (res.time || '') + '</div>' +
                '<div style="margin-bottom: 16px;">' +
                    '<div style="margin-bottom: 8px; color: #9ca3af; font-size: 13px;">' + res.p1 + '</div>' +
                    '<div class="dice-container">' +
                        '<div class="dice ' + dice1Class + '">' + res.p1_roll[0] + '</div>' +
                        '<span style="color: #00d4ff; font-size: 20px; margin: 0 8px;">+</span>' +
                        '<div class="dice ' + dice1Class + '">' + res.p1_roll[1] + '</div>' +
                        '<span style="color: #00d4ff; font-size: 18px; margin-left: 12px; font-weight: 600;">= ' + res.p1_sum + '</span>' +
                    '</div>' +
                '</div>' +
                '<div style="margin-bottom: 16px;">' +
                    '<div style="margin-bottom: 8px; color: #9ca3af; font-size: 13px;">' + res.p2 + '</div>' +
                    '<div class="dice-container">' +
                        '<div class="dice ' + dice2Class + '">' + res.p2_roll[0] + '</div>' +
                        '<span style="color: #00d4ff; font-size: 20px; margin: 0 8px;">+</span>' +
                        '<div class="dice ' + dice2Class + '">' + res.p2_roll[1] + '</div>' +
                        '<span style="color: #00d4ff; font-size: 18px; margin-left: 12px; font-weight: 600;">= ' + res.p2_sum + '</span>' +
                    '</div>' +
                '</div>' +
                winnerText;
        }

        function renderPartial(res, opts) {
            var showP1 = opts.showP1 || false;
            var showP2 = opts.showP2 || false;
            var rollingText = opts.text || '';
            var lines = [];
            if (rollingText) lines.push('<div><b>' + rollingText + '</b></div>');
            if (showP1) {
                lines.push('<div>' + res.p1 + ' 掷骰：' + res.p1_roll.join(' + ') + ' = ' + res.p1_sum + '</div>');
            } else {
                lines.push('<div>' + res.p1 + ' 掷骰：…</div>');
            }
            if (showP2) {
                lines.push('<div>' + res.p2 + ' 掷骰：' + res.p2_roll.join(' + ') + ' = ' + res.p2_sum + '</div>');
            } else {
                lines.push('<div>' + res.p2 + ' 掷骰：…</div>');
            }
            resultEl.innerHTML = lines.join('');
        }

        // 记录当前房间的玩家、胜负统计与历史
        var currentPlayers = {p1: null, p2: null};
        var winStats = {p1: 0, p2: 0};
        var history = [];
        var totalBonusEarned = 0; // 累计获得的魔力值
        var rollTimer = null;
        var rollDeadline = 0;
        var pendingTurn = null;
        var partialRolls = {p1: null, p2: null};

        function resetStats(players) {
            currentPlayers = {p1: players.p1 || null, p2: players.p2 || null};
            winStats = {p1: 0, p2: 0};
            history = [];
            totalBonusEarned = 0; // 重置累计魔力值
            statP1.textContent = '玩家一：0 胜';
            statP2.textContent = '玩家二：0 胜';
            historyEl.innerHTML = '<div class="small">对局记录将在这里显示</div>';
            updateSeats(players, null); // 更新座位显示
        }

        function updateHistory(res) {
            if (!res) return;
            var entry = '[' + (res.time || '') + '] ' +
                res.p1 + ' (' + res.p1_roll.join('+') + '=' + res.p1_sum + ') vs ' +
                res.p2 + ' (' + res.p2_roll.join('+') + '=' + res.p2_sum + ') => ' +
                (res.winner ? ('胜者 ' + res.winner) : '平局');
            history.unshift(entry);
            if (history.length > 20) history.pop();
            historyEl.innerHTML = history.map(function (h) {
                return '<div>' + h + '</div>';
            }).join('');
        }

        function renderPartialRolls() {
            var html = '<div style="margin-bottom: 16px; color: #00d4ff; font-weight: 600; text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);">🎲 对局进行中</div>';
            
            // 玩家一
            html += '<div style="margin-bottom: 16px;">';
            html += '<div style="margin-bottom: 8px; color: #9ca3af; font-size: 13px;">' + (currentPlayers.p1 || '玩家一') + '</div>';
            html += '<div class="dice-container">';
            if (partialRolls.p1) {
                html += '<div class="dice">' + partialRolls.p1.roll[0] + '</div>';
                html += '<span style="color: #00d4ff; font-size: 20px; margin: 0 8px;">+</span>';
                html += '<div class="dice">' + partialRolls.p1.roll[1] + '</div>';
                html += '<span style="color: #00d4ff; font-size: 18px; margin-left: 12px; font-weight: 600;">= ' + partialRolls.p1.sum + '</span>';
            } else {
                html += '<div class="dice rolling">?</div>';
                html += '<span style="color: #00d4ff; font-size: 20px; margin: 0 8px;">+</span>';
                html += '<div class="dice rolling">?</div>';
                html += '<span style="color: #9ca3af; font-size: 14px; margin-left: 12px;">等待中...</span>';
            }
            html += '</div></div>';
            
            // 玩家二
            html += '<div style="margin-bottom: 16px;">';
            html += '<div style="margin-bottom: 8px; color: #9ca3af; font-size: 13px;">' + (currentPlayers.p2 || '玩家二') + '</div>';
            html += '<div class="dice-container">';
            if (partialRolls.p2) {
                html += '<div class="dice">' + partialRolls.p2.roll[0] + '</div>';
                html += '<span style="color: #00d4ff; font-size: 20px; margin: 0 8px;">+</span>';
                html += '<div class="dice">' + partialRolls.p2.roll[1] + '</div>';
                html += '<span style="color: #00d4ff; font-size: 18px; margin-left: 12px; font-weight: 600;">= ' + partialRolls.p2.sum + '</span>';
            } else {
                html += '<div class="dice rolling">?</div>';
                html += '<span style="color: #00d4ff; font-size: 20px; margin: 0 8px;">+</span>';
                html += '<div class="dice rolling">?</div>';
                html += '<span style="color: #9ca3af; font-size: 14px; margin-left: 12px;">等待中...</span>';
            }
            html += '</div></div>';
            
            resultEl.innerHTML = html;
        }

        function showRollModal(turn, playerName, timeout) {
            pendingTurn = turn;
            rollTitle.textContent = '请掷骰 - ' + playerName + '（' + (turn === 'p1' ? '玩家一' : '玩家二') + '）';
            rollModal.style.display = 'flex';
            rollDeadline = Date.now() + (timeout || 30) * 1000;
            if (rollTimer) clearInterval(rollTimer);
            rollTimer = setInterval(function() {
                var left = Math.max(0, Math.ceil((rollDeadline - Date.now()) / 1000));
                rollCountdown.textContent = '剩余 ' + left + ' 秒';
                if (left <= 0) {
                    clearInterval(rollTimer);
                    rollTimer = null;
                    rollModal.style.display = 'none';
                }
            }, 500);
        }

        function hideRollModal() {
            pendingTurn = null;
            rollModal.style.display = 'none';
            if (rollTimer) {
                clearInterval(rollTimer);
                rollTimer = null;
            }
        }

        function showDiceAnimation(playerName, roll, sum) {
            if (!diceAnimationModal) return;
            
            // 设置玩家名字
            diceAnimationPlayer.textContent = playerName + ' 投掷中...';
            
            // 重置骰子状态
            diceLarge1.textContent = '?';
            diceLarge2.textContent = '?';
            diceLarge1.className = 'dice-large rolling';
            diceLarge2.className = 'dice-large rolling';
            diceAnimationResult.style.display = 'none';
            
            // 显示弹窗
            diceAnimationModal.style.display = 'flex';
            
            // 1.5秒后显示结果
            setTimeout(function() {
                if (!diceAnimationModal || diceAnimationModal.style.display !== 'flex') return;
                
                // 停止滚动动画，显示结果
                diceLarge1.className = 'dice-large result';
                diceLarge2.className = 'dice-large result';
                diceLarge1.textContent = roll[0];
                diceLarge2.textContent = roll[1];
                
                // 显示总和
                diceAnimationSum.textContent = sum;
                diceAnimationResult.style.display = 'block';
                diceAnimationPlayer.textContent = playerName + ' 投掷完成';
                
                // 2秒后自动关闭
                setTimeout(function() {
                    if (diceAnimationModal) {
                        diceAnimationModal.style.display = 'none';
                    }
                }, 2000);
            }, 1500);
        }

        function hideDiceAnimation() {
            if (diceAnimationModal) {
                diceAnimationModal.style.display = 'none';
            }
        }

        function updateWins(res) {
            if (!res) return;
            // 更新胜局统计
            if (res.winner) {
                if (res.winner === currentPlayers.p1) {
                    winStats.p1 += 1;
                } else if (res.winner === currentPlayers.p2) {
                    winStats.p2 += 1;
                }
            }
            statP1.textContent = '玩家一：' + winStats.p1 + ' 胜' + (currentPlayers.p1 ? '（' + currentPlayers.p1 + '）' : '');
            statP2.textContent = '玩家二：' + winStats.p2 + ' 胜' + (currentPlayers.p2 ? '（' + currentPlayers.p2 + '）' : '');
            
            // 更新累计获得的魔力值
            if (res.winner && res.winner === user && res.winner_bonus) {
                totalBonusEarned += res.winner_bonus;
            } else if (!res.winner) {
                // 平局
                if (res.p1 === user && res.p1_bonus) {
                    totalBonusEarned += res.p1_bonus;
                } else if (res.p2 === user && res.p2_bonus) {
                    totalBonusEarned += res.p2_bonus;
                }
            }
            // 更新座位显示
            updateSeats(currentPlayers, null);
        }

        function connect(room, role, seat, tableId, ownerId) {
            if (!room) return;
            if (ws && ws.readyState === 1) {
                ws.close();
            }
            currentRoom = room;
            currentRole = role;
            currentSeat = seat;
            
            // 保存连接参数
            lastConnectParams = {
                room: room,
                role: role,
                seat: seat,
                tableId: tableId,
                ownerId: ownerId
            };

            var host = hostOverride || window.location.hostname;
            var hasPortZeroOverride = (portOverride === '0' || portOverride === 0 || (typeof portOverride === 'string' && portOverride.trim() === '0'));
            var portRaw = hasPortZeroOverride ? '0' : ((portOverride !== null && portOverride !== undefined && portOverride !== '') ? portOverride : defaultPort);
            var portStr = (portRaw === null || portRaw === undefined) ? '' : portRaw.toString().trim();
            var protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
            // 规则：port 为空或 '0' 不拼；wss 的 443 不拼；ws 的 80 不拼
            var portPart = '';
            if (portStr !== '' && portStr !== '0') {
                if (!(protocol === 'wss://' && portStr === '443') && !(protocol === 'ws://' && portStr === '80')) {
                    portPart = ':' + portStr;
                }
            }
            console.debug('[WS] hostOverride=', hostOverride, 'portOverride=', portOverride, 'defaultPort=', defaultPort, 'resolved portStr=', portStr, 'protocol=', protocol, 'portPart=', portPart);
            var wsUrl = protocol + host + portPart + '/?room=' + encodeURIComponent(room) +
                '&role=' + encodeURIComponent(role) +
                (seat ? '&seat=' + encodeURIComponent(seat) : '') +
                '&user=' + encodeURIComponent(user) +
                (userId ? ('&uid=' + encodeURIComponent(userId)) : '') +
                (tableId ? ('&table_id=' + encodeURIComponent(tableId)) : '') +
                (ownerId ? ('&owner_id=' + encodeURIComponent(ownerId)) : '');

            ws = new WebSocket(wsUrl);
            statusEl.textContent = '连接中 ' + room + '...';
            statusEl.classList.remove('connected');

            ws.onopen = function() {
                appendLine('<span class="sys">已连接 ' + room + '（' + role + (seat ? ('#' + seat) : '') + '）</span>', 'sys');
                statusEl.textContent = '已连接：' + room;
                statusEl.classList.add('connected');
                // 隐藏重新连接按钮
                if (reconnectBtn) {
                    reconnectBtn.style.display = 'none';
                }
            };
            ws.onmessage = function(e) {
                try {
                    var data = JSON.parse(e.data);
                    if (data.type === 'system') {
                        appendLine('<span class="sys">' + data.text + '</span>', 'sys');
                    } else if (data.type === 'message') {
                        appendLine('<span class="user">' + data.user + '</span>: ' + data.text +
                            (data.time ? '<span class="time">' + data.time + '</span>' : ''));
                    } else if (data.type === 'cheer') {
                        appendLine('<span class="user">' + data.user + '</span> ' + (data.emoji || '👏') +
                            (data.time ? '<span class="time">' + data.time + '</span>' : ''));
                    } else if (data.type === 'seat_assigned') {
                        if (data.seat) {
                            currentSeat = parseInt(data.seat, 10) || null;
                            updateStartButtonVisibility();
                        }
                    } else if (data.type === 'room_update') {
                        updateSeats(data.players || {}, data.spectators || 0);
                        // 更新观众列表
                        if (data.spectators_list && Array.isArray(data.spectators_list)) {
                            updateSpectatorsList(data.spectators_list);
                        }
                        // 如果玩家换人则重置统计与历史
                        if (data.players) {
                            var changed = (data.players.p1 || null) !== currentPlayers.p1 || (data.players.p2 || null) !== currentPlayers.p2;
                            if (changed) {
                                resetStats(data.players);
                                partialRolls = {p1: null, p2: null};
                            }
                            // 推断当前座位（避免 auto 分配时客户端不知座位）
                            if (data.players.p1 === user) {
                                currentSeat = 1;
                                currentRole = 'player';
                            } else if (data.players.p2 === user) {
                                currentSeat = 2;
                                currentRole = 'player';
                            } else {
                                // 如果用户不在玩家列表中，说明被踢到观众席位
                                if (currentSeat === 1 || currentSeat === 2) {
                                    currentSeat = null;
                                    currentRole = 'spectator';
                                }
                            }
                            updateStartButtonVisibility();
                        }
                        // 确保不会自动开始游戏 - 只有在玩家主动点击"开始对局"按钮时才会开始
                        if (data.last_result) {
                            renderResult(data.last_result);
                            // 注意：这里不更新胜局统计，因为 last_result 是历史记录
                            // 胜局统计只在 duel_result 消息中更新
                            updateHistory(data.last_result);
                        }
                    } else if (data.type === 'roll_request') {
                        if ((currentSeat === 1 && data.turn === 'p1') || (currentSeat === 2 && data.turn === 'p2')) {
                            showRollModal(data.turn, data.player, data.timeout || 30);
                        }
                        resultEl.innerHTML = '<div><b>等待 ' + data.player + ' 投掷…</b></div>';
                    } else if (data.type === 'roll_done') {
                        if ((currentSeat === 1 && data.turn === 'p1') || (currentSeat === 2 && data.turn === 'p2')) {
                            hideRollModal();
                        }
                        appendLine('<span class="sys">' + data.player + ' 完成投掷' + (data.auto ? '（超时自动）' : '') + '</span>', 'sys');
                        if (data.roll && data.roll.length === 2) {
                            var sum = data.roll[0] + data.roll[1];
                            partialRolls[data.turn] = {player: data.player, roll: data.roll, sum: sum};
                            renderPartialRolls();
                            // 显示大骰子动画
                            showDiceAnimation(data.player, data.roll, sum);
                        }
                    } else if (data.type === 'countdown') {
                        overlay.style.display = 'flex';
                        overlayText.textContent = data.left;
                    } else if (data.type === 'duel_result') {
                        overlay.style.display = 'none';
                        partialRolls = {p1: null, p2: null};
                        renderResult(data);
                        updateWins(data);
                        updateHistory(data);
                refreshBonus();
                    }
                } catch (err) {
                    appendLine('<span class="sys">解析消息失败: ' + err + '</span>', 'sys');
                }
            };
            ws.onclose = function() {
                appendLine('<span class="sys">连接已断开</span>', 'sys');
                statusEl.textContent = '未连接';
                statusEl.classList.remove('connected');
                // 显示重新连接按钮（如果有保存的连接参数）
                if (reconnectBtn && lastConnectParams.room) {
                    reconnectBtn.style.display = 'block';
                }
            };
            ws.onerror = function() {
                appendLine('<span class="sys">连接出错</span>', 'sys');
            };
        }

        function sendMsg() {
            var text = inputEl.value.trim();
            if (!text || !ws || ws.readyState !== 1) return;
            ws.send(text);
            inputEl.value = '';
        }

        function sendRematch() {
            if (!ws || ws.readyState !== 1) return;
            ws.send(JSON.stringify({type: 'rematch'}));
        }

        function sendCheer(emoji) {
            if (!ws || ws.readyState !== 1) return;
            ws.send(JSON.stringify({type: 'cheer', emoji: emoji}));
        }

        function sendStart() {
            if (!ws || ws.readyState !== 1) return;
            ws.send(JSON.stringify({type: 'start'}));
        }

        function sendLeaveSeat() {
            if (!ws || ws.readyState !== 1) return;
            ws.send(JSON.stringify({type: 'leave_seat'}));
        }

        function sendKickPlayer(seat) {
            if (!ws || ws.readyState !== 1) return;
            if (!isTableOwner) {
                alert('只有房主可以踢人');
                return;
            }
            if (confirm('确定要将该玩家踢到观众席位吗？')) {
                ws.send(JSON.stringify({type: 'kick_player', seat: seat}));
            }
        }

        function reconnect() {
            if (lastConnectParams.room) {
                connect(
                    lastConnectParams.room,
                    lastConnectParams.role || 'spectator',
                    lastConnectParams.seat,
                    lastConnectParams.tableId,
                    lastConnectParams.ownerId
                );
            }
        }

        function sendRoll() {
            if (!ws || ws.readyState !== 1) return;
            if (!pendingTurn) return;
            var power = rollPower.value;
            var spell = rollSpell.value;
            ws.send(JSON.stringify({type: 'roll', turn: pendingTurn, power: power, spell: spell}));
            hideRollModal();
        }

        if (reconnectBtn) {
            reconnectBtn.addEventListener('click', reconnect);
        }
        if (refreshBonusBtn) {
            refreshBonusBtn.addEventListener('click', function() {
                refreshBonus();
            });
        }
        renderCurrentBonus(currentBonus);
        sendBtn.addEventListener('click', sendMsg);
        inputEl.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') sendMsg();
        });
        startBtn.addEventListener('click', sendStart);
        cheersEl.addEventListener('click', function(e) {
            if (e.target.tagName.toLowerCase() === 'button') {
                var emoji = e.target.getAttribute('data-emoji');
                sendCheer(emoji);
            }
        });
        rollConfirm.addEventListener('click', sendRoll);
        
        // 占位按钮点击事件
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('kick-player-btn')) {
                var seat = parseInt(e.target.getAttribute('data-seat'), 10);
                if (seat === 1 || seat === 2) {
                    sendKickPlayer(seat);
                }
                return;
            }
            if (e.target.classList.contains('leave-seat-btn')) {
                var seat = parseInt(e.target.getAttribute('data-seat'), 10);
                if (seat === 1 || seat === 2) {
                    sendLeaveSeat();
                }
                return;
            }
            if (e.target.classList.contains('take-seat-btn')) {
                if (e.target.disabled) {
                    return; // 按钮已禁用，不处理
                }
                var seat = parseInt(e.target.getAttribute('data-seat'), 10);
                if (seat === 1 || seat === 2) {
                    if (!currentRoom) {
                        alert('请先进入房间');
                        return;
                    }
                    // 检查座位是否已被占用
                    var seatBody = seat === 1 ? document.querySelector('#seat1 .body').textContent : document.querySelector('#seat2 .body').textContent;
                    if (seatBody !== '空位' && seatBody !== user) {
                        alert('该座位已被占用');
                        return;
                    }
                    // 重新连接为玩家角色
                    connect(currentRoom, 'player', seat);
                }
            }
        });

        if (tableIdParam > 0) {
            fetchRoomById(tableIdParam);
        } else {
            roomsEl.innerHTML = '<div class="small">请先从桌子列表进入：<a href="mars_tables.php" style="color:#5ad1ff;">前往桌子列表</a></div>';
        }
    })();
    </script>
</body>
</html>

