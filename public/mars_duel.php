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
        
        #hidden-select-modal {
            position: fixed;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            z-index: 10001;
        }
        
        #hidden-select-modal .panel {
            background: linear-gradient(135deg, rgba(10, 22, 40, 0.98), rgba(30, 27, 75, 0.98));
            border: 2px solid rgba(251, 191, 36, 0.5);
            border-radius: 16px;
            padding: 24px;
            width: 420px;
            box-shadow: 0 0 40px rgba(251, 191, 36, 0.4);
            backdrop-filter: blur(15px);
        }
        
        #hidden-select-modal h4 {
            margin: 0 0 12px 0;
            color: #fbbf24;
            text-shadow: 0 0 10px rgba(251, 191, 36, 0.6);
            font-size: 20px;
            text-align: center;
        }
        
        #hidden-select-indexes button {
            width: 100%;
            padding: 12px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 8px;
            border: 2px solid rgba(0, 212, 255, 0.4);
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.2), rgba(168, 85, 247, 0.2));
            color: #00d4ff;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        #hidden-select-indexes button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 212, 255, 0.4);
            border-color: rgba(0, 212, 255, 0.6);
        }
        
        #hidden-select-indexes button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: rgba(107, 114, 128, 0.3);
            border-color: rgba(107, 114, 128, 0.5);
            color: #6b7280;
        }
        
        #hidden-select-indexes button.selected {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.4), rgba(245, 158, 11, 0.4));
            border-color: rgba(251, 191, 36, 0.6);
            color: #fbbf24;
            box-shadow: 0 0 15px rgba(251, 191, 36, 0.4);
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
        <h2 style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <span>🚀 火星赌局 · PVP 幸运对战</span>
            <span style="flex:1 1 auto"></span>
            <span>
                <a href="mars_tables.php" style="display:inline-block; padding:8px 12px; border-radius:8px; background:rgba(0,212,255,0.12); border:1px solid rgba(0,212,255,0.4); color:#00d4ff; text-decoration:none; margin-right:8px;">← 返回大厅</a>
                <a href="index.php" style="display:inline-block; padding:8px 12px; border-radius:8px; background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.5); color:#fbbf24; text-decoration:none;">🏠 返回主页</a>
            </span>
        </h2>
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
                <div id="hidden-dice-display" style="margin-bottom: 16px; padding: 12px; background: rgba(10, 22, 40, 0.6); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 8px; display: none;">
                    <div style="color: #fbbf24; font-weight: 600; margin-bottom: 8px; text-shadow: 0 0 8px rgba(251, 191, 36, 0.4);">🎲 暗骰</div>
                    <div id="hidden-dice-p1" style="margin-bottom: 8px; color: #9ca3af; font-size: 14px;"></div>
                    <div id="hidden-dice-p2" style="margin-bottom: 8px; color: #9ca3af; font-size: 14px;"></div>
                    <div id="pool-amount-display" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(251, 191, 36, 0.2); color: #fbbf24; font-size: 14px; font-weight: 600;">
                        💰 当前奖池：<span id="pool-amount-value" style="color: #00d4ff;">0</span> 魔力值
                    </div>
                </div>
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
    <div id="hidden-select-modal">
        <div class="panel">
            <h4 id="hidden-select-title">选择暗骰序号</h4>
            <div class="small" id="hidden-select-note" style="margin-bottom: 12px; color: #fbbf24;">请选择一枚暗骰序号（1-10），点数服务器保密</div>
            <div id="hidden-select-countdown" style="margin-bottom: 12px; color: #f59e0b; font-weight: 600; text-align: center; font-size: 16px;"></div>
            <div id="hidden-select-indexes" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 12px;"></div>
            <div id="hidden-select-status" class="small" style="text-align: center; color: #9ca3af; margin-top: 8px;"></div>
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
        var hiddenDiceDisplay = document.getElementById('hidden-dice-display');
        var hiddenDiceP1 = document.getElementById('hidden-dice-p1');
        var hiddenDiceP2 = document.getElementById('hidden-dice-p2');
        var poolAmountValue = document.getElementById('pool-amount-value');
        var currentPoolAmount = 0; // 当前奖池金额
        var myHiddenDiceIndex = null; // 自己选择的暗骰序号
        var opponentHiddenDiceIndex = null; // 对手选择的暗骰序号
        var currentDiceLog = {p1: [], p2: []}; // 当前对局的骰子记录（包括暗骰和明骰）
        var hiddenSelectContainer = document.getElementById('hidden-select');
        var hiddenIndexesEl = document.getElementById('hidden-indexes');
        var decisionContainer = document.getElementById('decision-box');
        var btnCall = document.getElementById('btn-call');
        var btnFold = document.getElementById('btn-fold');

        function updateStartButtonVisibility() {
            if (!startBtn) return;
            if (currentSeat === 1 || currentSeat === 2) {
                startBtn.style.display = 'inline-block';
            } else {
                startBtn.style.display = 'none';
            }
        }

        // 暗骰选择 UI（弹窗版本）
        function showHiddenSelectModal(data) {
            if (!hiddenSelectModal || !hiddenSelectIndexes) return;
            var availableIndexes = data.available_indexes || range(1, 10);
            var selectedIndexes = data.selected_indexes || {};
            var starter = data.starter || 'p1';
            var timeout = data.starter_timeout || 5;
            var isStarter = (currentSeat === 1 && starter === 'p1') || (currentSeat === 2 && starter === 'p2');
            
            hiddenSelectModal.style.display = 'flex';
            hiddenSelected = false;
            
            // 生成序号按钮
            var html = '';
            for (var i = 1; i <= 10; i++) {
                var p1Idx = (selectedIndexes && selectedIndexes.p1) ? selectedIndexes.p1 : null;
                var p2Idx = (selectedIndexes && selectedIndexes.p2) ? selectedIndexes.p2 : null;
                var isMySelection = false;
                var isOtherSelection = false;
                
                // 检查选择状态
                if (p1Idx === i) {
                    isMySelection = (currentSeat === 1);
                    isOtherSelection = (currentSeat !== 1);
                } else if (p2Idx === i) {
                    isMySelection = (currentSeat === 2);
                    isOtherSelection = (currentSeat !== 2);
                }
                
                var disabled = isOtherSelection || hiddenSelected;
                var className = isMySelection ? 'selected' : '';
                
                html += '<button class="' + className + '" data-idx="' + i + '" ' + 
                        (disabled ? 'disabled' : '') + '>' + i + '</button>';
            }
            hiddenSelectIndexes.innerHTML = html;
            
            // 如果是先手玩家，显示倒计时
            if (isStarter && !hiddenSelected) {
                hiddenSelectDeadline = Date.now() + timeout * 1000;
                if (hiddenSelectTimer) clearInterval(hiddenSelectTimer);
                hiddenSelectTimer = setInterval(function() {
                    var left = Math.max(0, Math.ceil((hiddenSelectDeadline - Date.now()) / 1000));
                    if (hiddenSelectCountdown) {
                        hiddenSelectCountdown.textContent = '⏰ 剩余时间：' + left + ' 秒（超时将自动随机选择）';
                        hiddenSelectCountdown.style.display = 'block';
                    }
                    if (left <= 0) {
                        clearInterval(hiddenSelectTimer);
                        hiddenSelectTimer = null;
                        if (hiddenSelectCountdown) {
                            hiddenSelectCountdown.textContent = '⏰ 时间到，自动选择中...';
                        }
                    }
                }, 200);
            } else {
                if (hiddenSelectCountdown) {
                    hiddenSelectCountdown.style.display = 'none';
                }
            }
            
            // 更新状态提示
            if (hiddenSelectStatus) {
                if (hiddenSelected) {
                    hiddenSelectStatus.textContent = '✓ 您已选择暗骰，等待对方选择...';
                } else if (selectedIndexes.length > 0) {
                    hiddenSelectStatus.textContent = '⚠ 对手已选择部分序号，请选择其他序号';
                } else {
                    hiddenSelectStatus.textContent = '';
                }
            }
        }
        
        function updateHiddenSelectModal(selectedIndexes, playerKey, isAuto) {
            if (!hiddenSelectModal || hiddenSelectModal.style.display !== 'flex') return;
            if (!hiddenSelectIndexes) return;
            
            // 更新按钮状态
            var buttons = hiddenSelectIndexes.querySelectorAll('button');
            buttons.forEach(function(btn) {
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                var isMySelection = false;
                var isOtherSelection = false;
                
                // 检查选择状态（selectedIndexes 可能是对象 {p1: 3, p2: 5} 或数组）
                var p1Idx = (selectedIndexes && typeof selectedIndexes === 'object' && selectedIndexes.p1) ? selectedIndexes.p1 : null;
                var p2Idx = (selectedIndexes && typeof selectedIndexes === 'object' && selectedIndexes.p2) ? selectedIndexes.p2 : null;
                
                if (p1Idx === idx) {
                    isMySelection = (currentSeat === 1);
                    isOtherSelection = (currentSeat !== 1);
                } else if (p2Idx === idx) {
                    isMySelection = (currentSeat === 2);
                    isOtherSelection = (currentSeat !== 2);
                }
                
                if (isMySelection) {
                    btn.classList.add('selected');
                    btn.disabled = true;
                } else if (isOtherSelection) {
                    btn.disabled = true;
                    btn.classList.remove('selected');
                } else if (hiddenSelected) {
                    btn.disabled = true;
                } else {
                    btn.disabled = false;
                }
            });
            
            // 更新状态提示
            if (hiddenSelectStatus) {
                if (hiddenSelected) {
                    hiddenSelectStatus.textContent = '✓ 您已选择暗骰，等待对方选择...';
                } else if (selectedIndexes && ((selectedIndexes.p1 !== null && selectedIndexes.p1 !== undefined) || (selectedIndexes.p2 !== null && selectedIndexes.p2 !== undefined))) {
                    hiddenSelectStatus.textContent = '⚠ 对手已选择部分序号，请选择其他序号';
                } else {
                    hiddenSelectStatus.textContent = '';
                }
            }
        }
        
        function hideHiddenSelectModal() {
            if (hiddenSelectModal) hiddenSelectModal.style.display = 'none';
            if (hiddenSelectTimer) {
                clearInterval(hiddenSelectTimer);
                hiddenSelectTimer = null;
            }
            if (hiddenSelectCountdown) {
                hiddenSelectCountdown.style.display = 'none';
            }
        }
        
        function sendHiddenSelect(idx) {
            if (!ws || ws.readyState !== 1) return;
            if (hiddenSelected) return;
            ws.send(JSON.stringify({type: 'hidden_select', index: idx}));
        }
        
        // 辅助函数：生成范围数组
        function range(start, end) {
            var arr = [];
            for (var i = start; i <= end; i++) {
                arr.push(i);
            }
            return arr;
        }
        
        // 更新暗骰显示（包含所有骰子和总和）
        function updateHiddenDiceDisplay() {
            if (!hiddenDiceDisplay || !hiddenDiceP1 || !hiddenDiceP2) return;
            
            var p1Name = currentPlayers.p1 || '玩家一';
            var p2Name = currentPlayers.p2 || '玩家二';
            
            // 确定p1和p2的暗骰序号
            var p1Index = null;
            var p2Index = null;
            
            if (currentSeat === 1) {
                // 我是p1
                p1Index = myHiddenDiceIndex;
                p2Index = opponentHiddenDiceIndex;
            } else if (currentSeat === 2) {
                // 我是p2
                p1Index = opponentHiddenDiceIndex;
                p2Index = myHiddenDiceIndex;
            } else {
                // 观众，显示双方
                p1Index = opponentHiddenDiceIndex; // p1的选择
                p2Index = myHiddenDiceIndex; // p2的选择（观众视角）
            }
            
            // 构建显示内容：暗骰 + 明骰 + 总和
            function buildDiceDisplay(playerName, hiddenIndex, diceLog) {
                var diceValues = [];
                var openSum = 0;
                
                // 添加暗骰（用?表示）
                if (hiddenIndex !== null) {
                    diceValues.push('<span style="color: #fbbf24;">?</span>');
                }
                
                // 添加明骰
                if (diceLog && Array.isArray(diceLog)) {
                    for (var i = 0; i < diceLog.length; i++) {
                        if (diceLog[i].type === 'open') {
                            var val = diceLog[i].val || 0;
                            openSum += val;
                            diceValues.push(val);
                        }
                    }
                }
                
                // 构建显示字符串
                var display = '<span style="color: #00d4ff;">' + playerName + '</span>：';
                
                if (diceValues.length === 0 && hiddenIndex === null) {
                    // 未选择暗骰，也没有明骰
                    display += '<span style="color: #6b7280;">未选择</span>';
                } else {
                    // 显示骰子数组和总和
                    if (diceValues.length > 0) {
                        display += '[' + diceValues.join(',') + ']';
                    }
                    
                    // 计算并显示总和
                    var sumDisplay = '';
                    if (hiddenIndex !== null) {
                        // 有暗骰，显示 ?+明骰总和
                        if (openSum > 0) {
                            sumDisplay = '<span style="color: #fbbf24;">?</span>+' + openSum;
                        } else {
                            sumDisplay = '<span style="color: #fbbf24;">?</span>';
                        }
                    } else {
                        // 只有明骰
                        sumDisplay = openSum.toString();
                    }
                    
                    display += ' = <span style="color: #fbbf24; font-size: 18px; font-weight: 600; text-shadow: 0 0 10px rgba(251, 191, 36, 0.6);">' + sumDisplay + '</span>';
                }
                
                return display;
            }
            
            // 更新p1显示
            hiddenDiceP1.innerHTML = buildDiceDisplay(p1Name, p1Index, currentDiceLog.p1);
            
            // 更新p2显示
            hiddenDiceP2.innerHTML = buildDiceDisplay(p2Name, p2Index, currentDiceLog.p2);
            
            // 如果至少有一方选择了，显示暗骰区域
            if (p1Index !== null || p2Index !== null || (currentDiceLog.p1 && currentDiceLog.p1.length > 0) || (currentDiceLog.p2 && currentDiceLog.p2.length > 0)) {
                hiddenDiceDisplay.style.display = 'block';
            } else {
                hiddenDiceDisplay.style.display = 'none';
            }
        }
        
        // 对局结束后，显示暗骰的真实点数
        function updateHiddenDiceDisplayWithResult(diceLog) {
            if (!hiddenDiceDisplay || !hiddenDiceP1 || !hiddenDiceP2 || !diceLog) return;
            
            var p1Name = currentPlayers.p1 || '玩家一';
            var p2Name = currentPlayers.p2 || '玩家二';
            
            // 从dice_log中提取暗骰信息
            var p1Hidden = null;
            var p2Hidden = null;
            
            if (diceLog.p1 && Array.isArray(diceLog.p1)) {
                for (var i = 0; i < diceLog.p1.length; i++) {
                    if (diceLog.p1[i].type === 'hidden') {
                        p1Hidden = diceLog.p1[i];
                        break;
                    }
                }
            }
            
            if (diceLog.p2 && Array.isArray(diceLog.p2)) {
                for (var i = 0; i < diceLog.p2.length; i++) {
                    if (diceLog.p2[i].type === 'hidden') {
                        p2Hidden = diceLog.p2[i];
                        break;
                    }
                }
            }
            
            // 更新显示（显示真实点数）
            if (p1Hidden) {
                hiddenDiceP1.innerHTML = '<span style="color: #00d4ff;">' + p1Name + '</span>：暗骰 #' + (p1Hidden.idx || '?') + ' = <span style="color: #fbbf24; font-size: 18px; font-weight: 600;">' + (p1Hidden.val || '?') + '</span>';
            } else {
                hiddenDiceP1.innerHTML = '<span style="color: #00d4ff;">' + p1Name + '</span>：未选择';
            }
            
            if (p2Hidden) {
                hiddenDiceP2.innerHTML = '<span style="color: #00d4ff;">' + p2Name + '</span>：暗骰 #' + (p2Hidden.idx || '?') + ' = <span style="color: #fbbf24; font-size: 18px; font-weight: 600;">' + (p2Hidden.val || '?') + '</span>';
            } else {
                hiddenDiceP2.innerHTML = '<span style="color: #00d4ff;">' + p2Name + '</span>：未选择';
            }
            
            // 显示暗骰区域
            if (p1Hidden || p2Hidden) {
                hiddenDiceDisplay.style.display = 'block';
            }
        }

        // 跟注/弃权
        var decisionTimer = null;
        var decisionDeadline = 0;
        function showDecision(timeout) {
            if (decisionContainer) decisionContainer.style.display = 'block';
            if (btnCall) btnCall.disabled = false;
            if (btnFold) btnFold.disabled = false;
            decisionDeadline = Date.now() + (timeout || 15) * 1000;
            if (decisionTimer) clearInterval(decisionTimer);
            decisionTimer = setInterval(function() {
                var left = Math.max(0, Math.ceil((decisionDeadline - Date.now()) / 1000));
                decisionContainer.querySelector('.small').textContent = '选择跟注或弃权（剩余 ' + left + ' 秒）';
                if (left <= 0) {
                    clearInterval(decisionTimer);
                    decisionTimer = null;
                    if (btnCall) { btnCall.disabled = true; btnFold.disabled = true; }
                }
            }, 500);
        }
        function hideDecision() {
            if (decisionTimer) { clearInterval(decisionTimer); decisionTimer = null; }
            if (decisionContainer) decisionContainer.style.display = 'none';
            if (btnCall) btnCall.disabled = true;
            if (btnFold) btnFold.disabled = true;
        }
        function sendDecision(choice) {
            if (!ws || ws.readyState !== 1) return;
            ws.send(JSON.stringify({type: 'decision', choice: choice}));
            if (btnCall) btnCall.disabled = true;
            if (btnFold) btnFold.disabled = true;
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
        var hiddenSelectModal = document.getElementById('hidden-select-modal');
        var hiddenSelectIndexes = document.getElementById('hidden-select-indexes');
        var hiddenSelectCountdown = document.getElementById('hidden-select-countdown');
        var hiddenSelectStatus = document.getElementById('hidden-select-status');
        var hiddenSelectTimer = null;
        var hiddenSelectDeadline = 0;
        // 新增：暗骰选择与跟注/弃权
        // 若页面已有容器则复用，否则创建
        if (!hiddenSelectContainer) {
            hiddenSelectContainer = document.createElement('div');
            hiddenSelectContainer.id = 'hidden-select';
            hiddenSelectContainer.style.display = 'none';
            hiddenSelectContainer.style.margin = '12px 0';
            hiddenSelectContainer.innerHTML = '<div class="small" id="hidden-note" style="margin-bottom:6px;color:#fbbf24;">请选择暗骰序号</div><div id="hidden-indexes" style="display:flex;gap:6px;flex-wrap:wrap;"></div>';
            document.getElementById('arena').insertBefore(hiddenSelectContainer, document.getElementById('arena').firstChild);
            hiddenIndexesEl = hiddenSelectContainer.querySelector('#hidden-indexes');
        }
        if (!decisionContainer) {
            decisionContainer = document.createElement('div');
            decisionContainer.id = 'decision-box';
            decisionContainer.style.display = 'none';
            decisionContainer.style.margin = '12px 0';
            decisionContainer.innerHTML = '<div class="small" style="margin-bottom:6px;color:#fbbf24;">选择跟注或弃权</div><button id="btn-call" class="btn" style="margin-right:8px;">跟注</button><button id="btn-fold" class="btn secondary">弃权</button>';
            document.getElementById('arena').insertBefore(decisionContainer, document.getElementById('arena').firstChild);
            btnCall = decisionContainer.querySelector('#btn-call');
            btnFold = decisionContainer.querySelector('#btn-fold');
        }
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

        function updateSeats(players, spectators, roomState) {
            roomState = roomState || {status: 'idle'};
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
            
            // 创建召唤AI和踢掉AI按钮
            var summonAiBtn1 = document.querySelector('#seat1 .summon-ai-btn');
            var summonAiBtn2 = document.querySelector('#seat2 .summon-ai-btn');
            var kickAiBtn1 = document.querySelector('#seat1 .kick-ai-btn');
            var kickAiBtn2 = document.querySelector('#seat2 .kick-ai-btn');
            
            if (!summonAiBtn1) {
                summonAiBtn1 = document.createElement('button');
                summonAiBtn1.className = 'summon-ai-btn';
                summonAiBtn1.textContent = '🤖 召唤AI';
                summonAiBtn1.style.cssText = 'margin-top: 8px; width: 100%; background: linear-gradient(135deg, rgba(139, 92, 246, 0.8), rgba(124, 58, 237, 0.8)) !important; color: #fff !important; font-size: 13px !important; padding: 8px 16px !important; border: 1px solid rgba(139, 92, 246, 0.5) !important;';
                summonAiBtn1.setAttribute('data-seat', '1');
                seat1El.appendChild(summonAiBtn1);
            }
            if (!summonAiBtn2) {
                summonAiBtn2 = document.createElement('button');
                summonAiBtn2.className = 'summon-ai-btn';
                summonAiBtn2.textContent = '🤖 召唤AI';
                summonAiBtn2.style.cssText = 'margin-top: 8px; width: 100%; background: linear-gradient(135deg, rgba(139, 92, 246, 0.8), rgba(124, 58, 237, 0.8)) !important; color: #fff !important; font-size: 13px !important; padding: 8px 16px !important; border: 1px solid rgba(139, 92, 246, 0.5) !important;';
                summonAiBtn2.setAttribute('data-seat', '2');
                seat2El.appendChild(summonAiBtn2);
            }
            if (!kickAiBtn1) {
                kickAiBtn1 = document.createElement('button');
                kickAiBtn1.className = 'kick-ai-btn';
                kickAiBtn1.textContent = '❌ 踢掉AI';
                kickAiBtn1.style.cssText = 'margin-top: 8px; width: 100%; background: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(220, 38, 38, 0.8)) !important; color: #fff !important; font-size: 13px !important; padding: 8px 16px !important; border: 1px solid rgba(239, 68, 68, 0.5) !important;';
                kickAiBtn1.setAttribute('data-seat', '1');
                seat1El.appendChild(kickAiBtn1);
            }
            if (!kickAiBtn2) {
                kickAiBtn2 = document.createElement('button');
                kickAiBtn2.className = 'kick-ai-btn';
                kickAiBtn2.textContent = '❌ 踢掉AI';
                kickAiBtn2.style.cssText = 'margin-top: 8px; width: 100%; background: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(220, 38, 38, 0.8)) !important; color: #fff !important; font-size: 13px !important; padding: 8px 16px !important; border: 1px solid rgba(239, 68, 68, 0.5) !important;';
                kickAiBtn2.setAttribute('data-seat', '2');
                seat2El.appendChild(kickAiBtn2);
            }
            
            // 显示/隐藏AI按钮
            var isP1Ai = (p1 === 'AI玩家');
            var isP2Ai = (p2 === 'AI玩家');
            var isP1Me = (p1 === user);
            var isP2Me = (p2 === user);
            var currentStatus = (roomState && roomState.status) || 'idle';
            var isGameIdle = (currentStatus === 'idle'); // 只有idle状态才能踢掉AI
            
            // 座位1的AI按钮
            if (summonAiBtn1) {
                // 如果座位1是空的，且座位2是当前用户，且游戏处于idle状态，显示召唤AI按钮
                summonAiBtn1.style.display = (p1 === '空位' && isP2Me && isGameIdle) ? 'block' : 'none';
            }
            if (kickAiBtn1) {
                // 如果座位1是AI，且座位2是当前用户，且游戏处于idle状态，显示踢掉AI按钮
                kickAiBtn1.style.display = (isP1Ai && isP2Me && isGameIdle) ? 'block' : 'none';
            }
            
            // 座位2的AI按钮
            if (summonAiBtn2) {
                // 如果座位2是空的，且座位1是当前用户，且游戏处于idle状态，显示召唤AI按钮
                summonAiBtn2.style.display = (p2 === '空位' && isP1Me && isGameIdle) ? 'block' : 'none';
            }
            if (kickAiBtn2) {
                // 如果座位2是AI，且座位1是当前用户，且游戏处于idle状态，显示踢掉AI按钮
                kickAiBtn2.style.display = (isP2Ai && isP1Me && isGameIdle) ? 'block' : 'none';
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

            // 兼容新格式（dice_log/sum1/sum2/fold）
            var sum1 = res.sum1 || res.p1_sum || 0;
            var sum2 = res.sum2 || res.p2_sum || 0;
            var p1Wins = sum1 > sum2;
            var p2Wins = sum2 > sum1;
            var isDraw = sum1 === sum2;
            var foldInfo = res.fold ? '<div class="small" style="color:#f87171;">有玩家弃权，本局提前结算</div>' : '';

            function renderDiceList(list) {
                if (!Array.isArray(list) || list.length === 0) return '<div class="small">无</div>';
                return list.map(function (d) {
                    var label = d.type === 'hidden' ? '暗' : '明';
                    var val = d.val || 0;
                    var idx = d.idx ? ('#' + d.idx) : '';
                    return '<span class="badge" style="margin-right:6px; margin-bottom:4px; display:inline-block; padding:4px 8px; border-radius:6px; background:rgba(0,212,255,0.1); border:1px solid rgba(0,212,255,0.3); color:#e5e7eb;">' + label + idx + '：' + val + '</span>';
                }).join('');
            }

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
                    if (p1Bonus > 0) winnerText += '<div>' + res.p1 + ' 获得 ' + p1Bonus.toLocaleString() + ' 魔力值</div>';
                    if (p2Bonus > 0) winnerText += '<div>' + res.p2 + ' 获得 ' + p2Bonus.toLocaleString() + ' 魔力值</div>';
                    winnerText += '</div>';
                }
            }

            var diceLogP1 = renderDiceList(res.dice_log && res.dice_log.p1);
            var diceLogP2 = renderDiceList(res.dice_log && res.dice_log.p2);

            resultEl.innerHTML = '' +
                '<div style="margin-bottom: 12px; color: #9ca3af; font-size: 13px;">时间：' + (res.time || '') + '</div>' +
                foldInfo +
                '<div style="margin-bottom: 16px;">' +
                    '<div style="margin-bottom: 8px; color: #9ca3af; font-size: 13px;">' + res.p1 + '</div>' +
                    '<div style="margin-bottom:6px;">' + diceLogP1 + '</div>' +
                    '<div class="dice-container">' +
                        '<span style="color: #00d4ff; font-size: 18px; font-weight: 600;">合计：' + sum1 + '</span>' +
                    '</div>' +
                '</div>' +
                '<div style="margin-bottom: 16px;">' +
                    '<div style="margin-bottom: 8px; color: #9ca3af; font-size: 13px;">' + res.p2 + '</div>' +
                    '<div style="margin-bottom:6px;">' + diceLogP2 + '</div>' +
                    '<div class="dice-container">' +
                        '<span style="color: #00d4ff; font-size: 18px; font-weight: 600;">合计：' + sum2 + '</span>' +
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
        var hiddenSelected = false; // 是否已选暗骰
        var rollTimer = null;
        var rollDeadline = 0;
        var pendingTurn = null;
        var partialRolls = {p1: null, p2: null};

        function resetStats(players) {
            currentPlayers = {p1: players.p1 || null, p2: players.p2 || null};
            winStats = {p1: 0, p2: 0};
            currentDiceLog = {p1: [], p2: []}; // 重置骰子记录
            history = [];
            totalBonusEarned = 0; // 重置累计魔力值
            statP1.textContent = '玩家一：0 胜';
            statP2.textContent = '玩家二：0 胜';
            historyEl.innerHTML = '<div class="small">对局记录将在这里显示</div>';
            updateSeats(players, null); // 更新座位显示
            // 重置暗骰显示
            myHiddenDiceIndex = null;
            opponentHiddenDiceIndex = null;
            if (hiddenDiceDisplay) {
                hiddenDiceDisplay.style.display = 'none';
            }
        }

        function updateHistory(res) {
            if (!res) return;
            var p1list = (res.dice_log && res.dice_log.p1 || []).map(function(d){return d.val;}).join(',');
            var p2list = (res.dice_log && res.dice_log.p2 || []).map(function(d){return d.val;}).join(',');
            var entry = '[' + (res.time || '') + '] ' +
                res.p1 + ' [' + p1list + ']=' + (res.sum1 || res.p1_sum || 0) + ' vs ' +
                res.p2 + ' [' + p2list + ']=' + (res.sum2 || res.p2_sum || 0) + ' => ' +
                (res.winner ? ('胜者 ' + res.winner) : '平局') +
                (res.fold ? '（弃权）' : '');
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
                html += '<span style="color: #00d4ff; font-size: 18px; margin-left: 12px; font-weight: 600;">点数：' + partialRolls.p1.sum + '</span>';
            } else {
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
                html += '<span style="color: #00d4ff; font-size: 18px; margin-left: 12px; font-weight: 600;">点数：' + partialRolls.p2.sum + '</span>';
            } else {
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
            
            // 重置骰子状态（只显示一个骰子）
            diceLarge1.textContent = '?';
            diceLarge1.className = 'dice-large rolling';
            diceLarge2.style.display = 'none'; // 隐藏第二个骰子
            diceAnimationResult.style.display = 'none';
            
            // 显示弹窗
            diceAnimationModal.style.display = 'flex';
            
            // 1.5秒后显示结果
            setTimeout(function() {
                if (!diceAnimationModal || diceAnimationModal.style.display !== 'flex') return;
                
                // 停止滚动动画，显示结果
                diceLarge1.className = 'dice-large result';
                diceLarge1.textContent = roll[0];
                
                // 显示点数（单个骰子，点数就是骰子值）
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
                // 连接成功后立即请求同步状态（用于断线重连）
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({type: 'sync_state'}));
                }
            };
            ws.onmessage = function(e) {
                try {
                    var data = JSON.parse(e.data);
                    console.log('收到消息:', data.type, data);
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
                            currentRole = 'player';
                            updateStartButtonVisibility();
                            if (data.reconnected) {
                                appendLine('<span class="sys">已恢复到座位 ' + currentSeat + '</span>', 'sys');
                            }
                        }
                    } else if (data.type === 'sync_state') {
                        // 处理断线重连的状态同步
                        console.log('收到状态同步:', data);
                        
                        // 首先检查并恢复座位（根据用户ID匹配）
                        if (userId && data.p1_id && parseInt(data.p1_id) === parseInt(userId)) {
                            // 当前用户是p1
                            currentSeat = 1;
                            currentRole = 'player';
                            updateStartButtonVisibility();
                        } else if (userId && data.p2_id && parseInt(data.p2_id) === parseInt(userId)) {
                            // 当前用户是p2
                            currentSeat = 2;
                            currentRole = 'player';
                            updateStartButtonVisibility();
                        }
                        
                        // 更新房间基本信息
                        if (data.players) {
                            currentPlayers.p1 = data.players.p1 || null;
                            currentPlayers.p2 = data.players.p2 || null;
                            updateSeats(data.players || {}, data.spectators || 0, {status: data.status || 'idle'});
                        }
                        if (data.spectators_list && Array.isArray(data.spectators_list)) {
                            updateSpectatorsList(data.spectators_list);
                        }
                        
                        // 如果在对局中，恢复游戏状态
                        if (data.status === 'selecting_hidden') {
                            // 恢复暗骰选择状态
                            if (data.available_indexes && data.selected_indexes) {
                                showHiddenSelectModal({
                                    available_indexes: data.available_indexes,
                                    selected_indexes: data.selected_indexes,
                                    starter: data.starter || 'p1',
                                    starter_timeout: data.starter_timeout || 5
                                });
                                // 恢复已选择的暗骰序号
                                var myPk = currentSeat === 1 ? 'p1' : (currentSeat === 2 ? 'p2' : null);
                                if (myPk && data.hidden_selected && data.hidden_selected[myPk] !== null) {
                                    myHiddenDiceIndex = data.hidden_selected[myPk];
                                }
                                var otherPk = currentSeat === 1 ? 'p2' : (currentSeat === 2 ? 'p1' : null);
                                if (otherPk && data.hidden_selected && data.hidden_selected[otherPk] !== null) {
                                    opponentHiddenDiceIndex = data.hidden_selected[otherPk];
                                }
                            }
                            // 恢复奖池
                            if (data.pool_amount !== undefined) {
                                currentPoolAmount = parseFloat(data.pool_amount || 0);
                                if (poolAmountValue) {
                                    poolAmountValue.textContent = currentPoolAmount.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1});
                                }
                                if (hiddenDiceDisplay && currentPoolAmount > 0) {
                                    hiddenDiceDisplay.style.display = 'block';
                                }
                            }
                        } else if (data.status === 'dueling' || data.status === 'playing' || data.status === 'decision') {
                            // 恢复对局状态
                            // 恢复骰子记录
                            if (data.dice_log) {
                                currentDiceLog = {
                                    p1: (data.dice_log.p1 || []).slice(),
                                    p2: (data.dice_log.p2 || []).slice()
                                };
                                // 恢复暗骰序号
                                var myPk = currentSeat === 1 ? 'p1' : (currentSeat === 2 ? 'p2' : null);
                                var otherPk = currentSeat === 1 ? 'p2' : (currentSeat === 2 ? 'p1' : null);
                                // 从dice_log中提取暗骰序号（服务器端使用 'idx' 字段）
                                if (myPk && data.dice_log[myPk]) {
                                    for (var i = 0; i < data.dice_log[myPk].length; i++) {
                                        if (data.dice_log[myPk][i].type === 'hidden') {
                                            myHiddenDiceIndex = data.dice_log[myPk][i].idx || data.dice_log[myPk][i].hidden_index || null;
                                            break;
                                        }
                                    }
                                }
                                if (otherPk && data.dice_log[otherPk]) {
                                    for (var i = 0; i < data.dice_log[otherPk].length; i++) {
                                        if (data.dice_log[otherPk][i].type === 'hidden') {
                                            opponentHiddenDiceIndex = data.dice_log[otherPk][i].idx || data.dice_log[otherPk][i].hidden_index || null;
                                            break;
                                        }
                                    }
                                }
                                updateHiddenDiceDisplay();
                            }
                            // 恢复奖池
                            if (data.pool_amount !== undefined) {
                                currentPoolAmount = parseFloat(data.pool_amount || 0);
                                if (poolAmountValue) {
                                    poolAmountValue.textContent = currentPoolAmount.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1});
                                }
                                if (hiddenDiceDisplay && currentPoolAmount > 0) {
                                    hiddenDiceDisplay.style.display = 'block';
                                }
                            }
                            // 如果正在决策阶段，显示决策按钮
                            if (data.status === 'decision') {
                                var myPk = currentSeat === 1 ? 'p1' : (currentSeat === 2 ? 'p2' : null);
                                if (myPk && data.decisions && data.decisions[myPk] === null) {
                                    // 如果还没有决策，显示决策按钮
                                    showDecision();
                                }
                            }
                            // 如果正在投掷阶段，根据当前回合显示提示
                            // 注意：如果轮到当前玩家，服务器会单独发送 roll_request 消息，这里不需要处理
                            // 这里只处理非当前玩家的情况
                            if (data.status === 'playing' && data.turn) {
                                var myPk = currentSeat === 1 ? 'p1' : (currentSeat === 2 ? 'p2' : null);
                                if (myPk !== data.turn) {
                                    // 不是轮到我，显示等待提示
                                    appendLine('<span class="sys">等待 ' + (data.turn === 'p1' ? (currentPlayers.p1 || '玩家一') : (currentPlayers.p2 || '玩家二')) + ' 投掷骰子</span>', 'sys');
                                }
                            }
                        } else if (data.status === 'idle') {
                            // 空闲状态，重置所有游戏相关变量
                            currentDiceLog = {p1: [], p2: []};
                            currentPoolAmount = 0;
                            myHiddenDiceIndex = null;
                            opponentHiddenDiceIndex = null;
                            if (poolAmountValue) {
                                poolAmountValue.textContent = '0';
                            }
                            if (hiddenDiceDisplay) {
                                hiddenDiceDisplay.style.display = 'none';
                            }
                            hideDecision();
                            hideHiddenSelectModal();
                        }
                        
                        // 更新最后结果（如果有）
                        if (data.last_result) {
                            // 不自动显示结果，但保存状态
                            // 如果需要，可以在这里调用 renderResult(data.last_result)
                        }
                    } else if (data.type === 'room_update') {
                        // 保存当前游戏状态
                        var roomState = {status: data.status || 'idle'};
                        updateSeats(data.players || {}, data.spectators || 0, roomState);
                        // 更新观众列表
                        if (data.spectators_list && Array.isArray(data.spectators_list)) {
                            updateSpectatorsList(data.spectators_list);
                        }
                        // 更新玩家信息（用于暗骰显示）
                        if (data.players) {
                            currentPlayers.p1 = data.players.p1 || null;
                            currentPlayers.p2 = data.players.p2 || null;
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
                        if (data.roll && data.roll.length >= 1) {
                            var sum = data.roll[0]; // 单个骰子，总和就是骰子值
                            partialRolls[data.turn] = {player: data.player, roll: data.roll, sum: sum};
                            renderPartialRolls();
                            // 显示大骰子动画
                            showDiceAnimation(data.player, data.roll, sum);
                            
                            // 更新骰子记录（明骰）
                            if (!currentDiceLog[data.turn]) {
                                currentDiceLog[data.turn] = [];
                            }
                            currentDiceLog[data.turn].push({
                                type: 'open',
                                val: sum,
                                roll: data.roll
                            });
                            
                            // 更新暗骰显示（包含所有骰子和总和）
                            updateHiddenDiceDisplay();
                        }
                    } else if (data.type === 'countdown') {
                        overlay.style.display = 'flex';
                        overlayText.textContent = data.left;
                    } else if (data.type === 'hidden_pool_generated') {
                        hiddenSelected = false;
                        myHiddenDiceIndex = null;
                        opponentHiddenDiceIndex = null;
                        currentDiceLog = {p1: [], p2: []}; // 重置骰子记录
                        currentPoolAmount = 0; // 重置奖池
                        if (poolAmountValue) {
                            poolAmountValue.textContent = '0';
                        }
                        hideDecision();
                        showHiddenSelectModal(data);
                        updateHiddenDiceDisplay();
                    } else if (data.type === 'hidden_select_ok') {
                        hiddenSelected = true;
                        myHiddenDiceIndex = data.index || null;
                        hideHiddenSelectModal();
                        if (hiddenSelectTimer) {
                            clearInterval(hiddenSelectTimer);
                            hiddenSelectTimer = null;
                        }
                        // 注意：暗骰的值在服务器端，客户端不知道，所以这里不添加到 currentDiceLog
                        // 暗骰会在 updateHiddenDiceDisplay 中用 ? 显示
                        updateHiddenDiceDisplay();
                    } else if (data.type === 'hidden_select_update') {
                        // 实时更新已选择的序号
                        updateHiddenSelectModal(data.selected_indexes, data.player_key, data.auto);
                        if (data.auto && data.player_key === ((currentSeat === 1) ? 'p1' : (currentSeat === 2) ? 'p2' : null)) {
                            // 如果是自动选择，显示提示
                            appendLine('<span class="sys">系统已为您自动选择暗骰序号</span>', 'sys');
                        }
                        // 更新暗骰显示
                        if (data.selected_indexes) {
                            // 根据当前座位更新暗骰序号
                            if (currentSeat === 1) {
                                // 我是p1
                                if (data.selected_indexes.p1 !== undefined && data.selected_indexes.p1 !== null) {
                                    myHiddenDiceIndex = data.selected_indexes.p1;
                                }
                                if (data.selected_indexes.p2 !== undefined && data.selected_indexes.p2 !== null) {
                                    opponentHiddenDiceIndex = data.selected_indexes.p2;
                                }
                            } else if (currentSeat === 2) {
                                // 我是p2
                                if (data.selected_indexes.p1 !== undefined && data.selected_indexes.p1 !== null) {
                                    opponentHiddenDiceIndex = data.selected_indexes.p1;
                                }
                                if (data.selected_indexes.p2 !== undefined && data.selected_indexes.p2 !== null) {
                                    myHiddenDiceIndex = data.selected_indexes.p2;
                                }
                            } else {
                                // 观众，显示双方
                                if (data.selected_indexes.p1 !== undefined && data.selected_indexes.p1 !== null) {
                                    opponentHiddenDiceIndex = data.selected_indexes.p1;
                                }
                                if (data.selected_indexes.p2 !== undefined && data.selected_indexes.p2 !== null) {
                                    myHiddenDiceIndex = data.selected_indexes.p2;
                                }
                            }
                        }
                        updateHiddenDiceDisplay();
                    } else if (data.type === 'decision_request') {
                        showDecision();
                    } else if (data.type === 'pool_update') {
                        // 更新奖池显示
                        console.log('收到奖池更新:', data);
                        currentPoolAmount = parseFloat(data.pool_amount || 0);
                        if (poolAmountValue) {
                            poolAmountValue.textContent = currentPoolAmount.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1});
                        }
                        // 确保暗骰显示区域可见
                        if (hiddenDiceDisplay && currentPoolAmount > 0) {
                            hiddenDiceDisplay.style.display = 'block';
                        }
                    } else if (data.type === 'duel_result') {
                        overlay.style.display = 'none';
                        partialRolls = {p1: null, p2: null};
                        hideDecision();
                        hideHiddenSelectModal();
                        renderResult(data);
                        updateWins(data);
                        updateHistory(data);
                        refreshBonus();
                        // 对局结束后，可以显示暗骰的真实点数（从dice_log中获取）
                        if (data.dice_log) {
                            updateHiddenDiceDisplayWithResult(data.dice_log);
                        }
                        // 重置骰子记录和奖池，准备下一局
                        currentDiceLog = {p1: [], p2: []};
                        currentPoolAmount = 0;
                        if (poolAmountValue) {
                            poolAmountValue.textContent = '0';
                        }
                    } else if (data.type === 'error') {
                        appendLine('<span class="sys" style="color: #f87171;">错误：' + data.text + '</span>', 'sys');
                        // 如果是余额不足的错误，显示弹窗
                        if (data.text && data.text.indexOf('余额不足') !== -1) {
                            var modal = document.createElement('div');
                            modal.style.cssText = 'position: fixed; left: 0; top: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 10002; display: flex; align-items: center; justify-content: center;';
                            modal.innerHTML = '<div style="background: linear-gradient(135deg, rgba(10, 22, 40, 0.98), rgba(30, 27, 75, 0.98)); border: 2px solid rgba(239, 68, 68, 0.5); border-radius: 16px; padding: 24px; width: 420px; box-shadow: 0 0 40px rgba(239, 68, 68, 0.4); backdrop-filter: blur(15px);">' +
                                '<h4 style="margin: 0 0 16px 0; color: #ef4444; text-shadow: 0 0 10px rgba(239, 68, 68, 0.6); font-size: 20px; text-align: center;">余额不足</h4>' +
                                '<div style="color: #e5e7eb; margin-bottom: 20px; line-height: 1.6;">' +
                                '<p style="margin: 8px 0; color: #f87171;">' + data.text + '</p>' +
                                '<p style="margin: 8px 0; color: #9ca3af; font-size: 13px;">如果对方余额足够，对方可以开始游戏。</p>' +
                                '</div>' +
                                '<button onclick="this.parentElement.parentElement.remove()" style="width: 100%; padding: 12px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(220, 38, 38, 0.8)); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">知道了</button>' +
                                '</div>';
                            document.body.appendChild(modal);
                        }
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
            
            // 检查余额是否足够（需要支持开局+四次跟注）
            // 先获取当前桌子的下注金额
            var betAmount = 0;
            if (rooms && rooms.length > 0) {
                betAmount = rooms[0].bet_amount || 0;
            }
            
            if (betAmount > 0) {
                // 需要5倍下注金额：开局1次 + 跟注4次
                var requiredAmount = betAmount * 5;
                
                // 检查当前余额
                if (currentBonus < requiredAmount) {
                    // 余额不足，显示弹窗提示
                    var modal = document.createElement('div');
                    modal.style.cssText = 'position: fixed; left: 0; top: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 10002; display: flex; align-items: center; justify-content: center;';
                    modal.innerHTML = '<div style="background: linear-gradient(135deg, rgba(10, 22, 40, 0.98), rgba(30, 27, 75, 0.98)); border: 2px solid rgba(239, 68, 68, 0.5); border-radius: 16px; padding: 24px; width: 420px; box-shadow: 0 0 40px rgba(239, 68, 68, 0.4); backdrop-filter: blur(15px);">' +
                        '<h4 style="margin: 0 0 16px 0; color: #ef4444; text-shadow: 0 0 10px rgba(239, 68, 68, 0.6); font-size: 20px; text-align: center;">余额不足</h4>' +
                        '<div style="color: #e5e7eb; margin-bottom: 20px; line-height: 1.6;">' +
                        '<p style="margin: 8px 0;">开始对局需要足够的余额支持：</p>' +
                        '<ul style="margin: 8px 0; padding-left: 20px;">' +
                        '<li>开局扣款：' + betAmount.toLocaleString() + ' 魔力值</li>' +
                        '<li>四次跟注：' + (betAmount * 4).toLocaleString() + ' 魔力值</li>' +
                        '<li style="color: #fbbf24; font-weight: 600;">总计需要：' + requiredAmount.toLocaleString() + ' 魔力值</li>' +
                        '</ul>' +
                        '<p style="margin: 8px 0; color: #f87171;">当前余额：' + currentBonus.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' 魔力值</p>' +
                        '<p style="margin: 8px 0; color: #9ca3af; font-size: 13px;">余额不足，无法开始对局。如果对方余额足够，对方可以开始游戏。</p>' +
                        '</div>' +
                        '<button onclick="this.parentElement.parentElement.remove()" style="width: 100%; padding: 12px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(220, 38, 38, 0.8)); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">知道了</button>' +
                        '</div>';
                    document.body.appendChild(modal);
                    return;
                }
            }
            
            // 余额足够，发送开始消息
            ws.send(JSON.stringify({type: 'start'}));
        }

        function sendLeaveSeat() {
            if (!ws || ws.readyState !== 1) return;
            ws.send(JSON.stringify({type: 'leave_seat'}));
        }

        function sendSwitchSeat(seat) {
            if (ws && ws.readyState === 1) {
                ws.send(JSON.stringify({type: 'switch_seat', seat: seat}));
            } else {
                // 如果未连接，直接按玩家身份连接
                connect(currentRoom, 'player', seat, lastConnectParams.tableId, lastConnectParams.ownerId);
            }
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
        // 暗骰序号按钮点击（弹窗版本）
        if (hiddenSelectIndexes) {
            hiddenSelectIndexes.addEventListener('click', function (e) {
                if (e.target.tagName === 'BUTTON' && !e.target.disabled) {
                    var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                    sendHiddenSelect(idx);
                }
            });
        }
        // 跟注/弃权按钮
        if (btnCall) btnCall.addEventListener('click', function () { sendDecision('call'); hideDecision(); });
        if (btnFold) btnFold.addEventListener('click', function () { sendDecision('fold'); hideDecision(); });

        // 占位按钮点击事件
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('summon-ai-btn')) {
                if (!ws || ws.readyState !== 1) return;
                ws.send(JSON.stringify({type: 'summon_ai'}));
                return;
            }
            if (e.target.classList.contains('kick-ai-btn')) {
                if (!ws || ws.readyState !== 1) return;
                ws.send(JSON.stringify({type: 'kick_ai'}));
                return;
            }
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
                    // 检查座位是否已被占用（前端快速判断，最终以后端为准）
                    var seatBody = seat === 1 ? document.querySelector('#seat1 .body').textContent : document.querySelector('#seat2 .body').textContent;
                    if (seatBody !== '空位' && seatBody !== user) {
                        alert('该座位已被占用');
                        return;
                    }
                    // 不断开重连，改为发送切换座位消息
                    sendSwitchSeat(seat);
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

