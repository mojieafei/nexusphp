<?php
require_once "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$nickname = $CURUSER["username"] ?? ("guest-" . substr(md5(getip() . microtime(true)), 0, 6));
$userId = intval($CURUSER["id"] ?? 0);
$ownerCardCount = intval($CURUSER["mars_owner_card"] ?? 0);

$wsHostDefault = get_setting('pvp.ws_host', 'localhost');
$wsPortDefault = get_setting('pvp.ws_port', 2346);
$wsHostOverride = $_GET['ws_host'] ?? $wsHostDefault;
$wsPortOverride = $_GET['ws_port'] ?? $wsPortDefault;
$defaultPort = $wsPortDefault;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>火星幸运局 - 房间列表</title>
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
        
        .rooms {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .room-card {
            background: rgba(10, 22, 40, 0.7);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 16px;
            width: 280px;
            backdrop-filter: blur(10px);
            box-shadow:
                0 0 15px rgba(0, 212, 255, 0.2),
                0 0 30px rgba(0, 212, 255, 0.1),
                inset 0 0 20px rgba(0, 212, 255, 0.05);
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        
        .room-card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 0 20px rgba(0, 212, 255, 0.3),
                0 0 40px rgba(0, 212, 255, 0.15),
                inset 0 0 20px rgba(0, 212, 255, 0.1);
        }
        
        .room-card h4 {
            margin: 0 0 12px 0;
            color: #00d4ff;
            font-size: 18px;
            font-weight: 600;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.5);
        }
        
        .small {
            color: #9ca3af;
            font-size: 13px;
            line-height: 1.6;
            margin: 4px 0;
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
            box-shadow:
                0 4px 12px rgba(0, 212, 255, 0.3),
                0 0 20px rgba(0, 212, 255, 0.2),
                inset 0 0 10px rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        
        button:hover {
            transform: translateY(-2px);
            box-shadow:
                0 6px 16px rgba(0, 212, 255, 0.4),
                0 0 30px rgba(0, 212, 255, 0.3),
                inset 0 0 12px rgba(255, 255, 255, 0.15);
            text-shadow: 0 0 12px rgba(255, 255, 255, 0.8);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button.secondary {
            background: linear-gradient(135deg, rgba(70, 90, 100, 0.8), rgba(55, 71, 79, 0.8));
            box-shadow:
                0 4px 12px rgba(70, 90, 100, 0.3),
                0 0 20px rgba(70, 90, 100, 0.2),
                inset 0 0 10px rgba(255, 255, 255, 0.1);
        }
        
        button.secondary:hover {
            box-shadow:
                0 6px 16px rgba(70, 90, 100, 0.4),
                0 0 30px rgba(70, 90, 100, 0.3),
                inset 0 0 12px rgba(255, 255, 255, 0.15);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        
        /* 弹窗样式 */
        #modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9998;
            align-items: center;
            justify-content: center;
        }
        
        #modal-backdrop.active {
            display: flex;
        }
        
        #modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(10, 22, 40, 0.98), rgba(30, 27, 75, 0.98));
            border: 1px solid rgba(0, 212, 255, 0.4);
            border-radius: 16px;
            padding: 24px;
            width: 400px;
            max-width: 90vw;
            max-height: 90vh;
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.6),
                0 0 20px rgba(0, 212, 255, 0.2),
                inset 0 0 20px rgba(0, 212, 255, 0.05);
            backdrop-filter: blur(15px);
            z-index: 9999;
            overflow-y: auto;
        }
        
        #modal h4 {
            margin: 0 0 16px 0;
            color: #00d4ff;
            font-size: 20px;
            font-weight: 600;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.6);
        }
        
        #modal .field {
            margin-bottom: 16px;
        }
        
        #modal label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: #9ca3af;
            font-weight: 500;
        }
        
        #modal input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            background: rgba(10, 22, 40, 0.8);
            color: #e5e7eb;
            font-size: 14px;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }
        
        #modal input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow:
                0 0 10px rgba(0, 212, 255, 0.3),
                inset 0 0 10px rgba(0, 212, 255, 0.1);
        }
        
        #modal .actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        #modal .actions button {
            flex: 1;
            min-width: 100px;
        }
        
        /* 加载和空状态样式 */
        .loading-state, .error-state, .empty-state {
            background: rgba(10, 22, 40, 0.6);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.15);
            color: #9ca3af;
            font-size: 14px;
        }
        
        .loading-state {
            color: #00d4ff;
            text-shadow: 0 0 8px rgba(0, 212, 255, 0.4);
        }
        
        .error-state {
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.15);
        }
        
        .empty-state {
            color: #9ca3af;
        }

        /* 大厅聊天 */
        #lobby-chat {
            margin-top: 20px;
            background: rgba(10, 22, 40, 0.7);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            padding: 12px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.15);
            max-width: 420px;
        }
        #lobby-status {
            color: #9ca3af;
            font-size: 12px;
            margin-bottom: 8px;
        }
        #lobby-status.connected {
            color: #00d4ff;
            text-shadow: 0 0 6px rgba(0, 212, 255, 0.5);
        }
        #lobby-log {
            max-height: 260px;
            overflow-y: auto;
            overflow-x: hidden;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(0, 212, 255, 0.15);
            border-radius: 8px;
            padding: 8px;
            font-size: 13px;
            margin-bottom: 8px;
        }
        #lobby-log .msg { margin: 4px 0; color: #e5e7eb; }
        #lobby-log .sys { color: #8aa0b8; font-style: italic; }
        #lobby-log .user { color: #00d4ff; font-weight: 600; }
        #lobby-log .time { color: #6b7280; font-size: 11px; margin-left: 6px; }
        #lobby-input {
            display: flex;
            gap: 8px;
        }
        #lobby-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }
        #lobby-text {
            flex: 1;
            border-radius: 8px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            background: rgba(10, 22, 40, 0.8);
            color: #e5e7eb;
            padding: 10px 12px;
        }
    </style>
</head>
<body>
    <div style="max-width: 1400px; margin: 0 auto;">
        <h2 style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <span>🚀 火星幸运局 · 桌子列表</span>
            <span style="flex:1 1 auto"></span>
            <span style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <a href="index.php" style="display:inline-block; padding:8px 12px; border-radius:8px; background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.5); color:#fbbf24; text-decoration:none;">🏠 返回主页</a>
            </span>
        </h2>
        <p style="margin: 6px 0 14px 0; color: #f87171; font-size: 13px;">温馨提示：本功能仅限娱乐，严禁赌博，严格遵守法律法规。</p>
        <div style="background: rgba(10, 22, 40, 0.65); border: 1px solid rgba(0, 212, 255, 0.3); border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 0 12px rgba(0, 212, 255, 0.12);">
            <div style="color:#00d4ff; font-weight:600; margin-bottom:8px;">🎮 对战说明</div>
            <ul style="margin:0; padding-left:18px; color:#cbd5e1; line-height:1.6; font-size:13px;">
                <li>玩法：双方各掷两枚骰子，比点数和；支持手动掷骰，超时系统自动掷。</li>
                <li>进入：默认观战，点击玩家一/二“占位”入座，房主可踢人回观众。</li>
                <li>开始：两席都有玩家后，任意一方点击“开始对局”，先点者先手。</li>
                <li>魔力值扣减：开局前实时按桌面“下注额”从双方账户扣款；余额不足无法开局。</li>
                <li>分成结算：结束后立即结算，赢家获得(总池-平台抽成-老板抽成)，房主获得老板抽成，平台抽固定比例。</li>
                <li>老板说明：持有“火星老板卡”可在无有效老板的桌子上点击“我要当老板”，有效期30天；成为老板后可在“设置”中调节下注额与抽成。</li>
                <li>其他：掉线可手动“重新连接”；房间右侧显示在线与聊天，大厅支持全局聊天与在线数。</li>
            </ul>
        </div>
        <div style="background: rgba(10, 22, 40, 0.6); border: 1px solid rgba(0, 212, 255, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 24px; backdrop-filter: blur(10px); box-shadow: 0 0 15px rgba(0, 212, 255, 0.15);">
            <p style="margin: 0;">
                <span style="color: #9ca3af;">当前用户：</span>
                <b><?php echo htmlspecialchars($nickname); ?></b>
                <span style="margin: 0 12px; color: rgba(0, 212, 255, 0.4);">|</span>
                <span id="owner-info" style="color: #9ca3af;">
                    <span style="color: #fbbf24; text-shadow: 0 0 8px rgba(251, 191, 36, 0.4);">⭐</span>
                    我的火星老板卡：<b style="color: #fbbf24; text-shadow: 0 0 8px rgba(251, 191, 36, 0.4);"><?php echo $ownerCardCount; ?></b> 张
                </span>
            </p>
        </div>
    <div style="display:flex; gap:16px; flex-wrap:wrap;">
        <div class="rooms" id="rooms" style="flex:1; min-width: 580px;"></div>
        <div id="lobby-chat">
            <div id="lobby-status">大厅未连接</div>
            <div id="lobby-actions">
                <button id="lobby-connect">连接大厅</button>
                <div id="lobby-count" class="small" style="flex:1; text-align:right; color:#9ca3af;">在线人数：-</div>
            </div>
            <div id="lobby-log"></div>
            <div id="lobby-input">
                <input type="text" id="lobby-text" placeholder="输入消息，回车发送" />
                <button id="lobby-send">发送</button>
            </div>
        </div>
    </div>
    </div>

    <div id="modal-backdrop"></div>
    <div id="modal">
        <h4 id="modal-title"></h4>
        <div id="modal-body"></div>
        <div class="actions">
            <button id="modal-cancel" class="secondary">取消</button>
            <button id="modal-ok">确定</button>
        </div>
    </div>

    <script>
    (function() {
        var ownerCardCount = <?php echo json_encode($ownerCardCount); ?>;
        var userId = <?php echo json_encode($userId); ?>;
        var nickname = <?php echo json_encode($nickname, JSON_UNESCAPED_UNICODE); ?>;
        var wsHostOverride = <?php echo json_encode($wsHostOverride); ?>;
        var wsPortOverride = <?php echo json_encode($wsPortOverride); ?>;
        var defaultPort = <?php echo json_encode($defaultPort); ?>;
        var rooms = [];
        var roomsEl = document.getElementById('rooms');
        var ownerInfoEl = document.getElementById('owner-info');
        var modalBackdrop = document.getElementById('modal-backdrop');
        var modal = document.getElementById('modal');
        var modalTitle = document.getElementById('modal-title');
        var modalBody = document.getElementById('modal-body');
        var modalOk = document.getElementById('modal-ok');
        var modalCancel = document.getElementById('modal-cancel');
        var lobbyStatus = document.getElementById('lobby-status');
        var lobbyLog = document.getElementById('lobby-log');
        var lobbyText = document.getElementById('lobby-text');
        var lobbySend = document.getElementById('lobby-send');
        var lobbyConnectBtn = document.getElementById('lobby-connect');
        var lobbyCountEl = document.getElementById('lobby-count');
        var lobbyWs = null;
        var lobbyUserCount = 0;
        var roomOnlineMap = {}; // room_key -> {players, spectators, spectators_list}
        
        // 点击背景关闭弹窗
        modalBackdrop.addEventListener('click', function(e) {
            if (e.target === modalBackdrop) {
                modalBackdrop.classList.remove('active');
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        function openFormDialog(options) {
            return new Promise(function (resolve, reject) {
                modalTitle.textContent = options.title || '提示';
                var fields = options.fields || [];
                modalBody.innerHTML = fields.map(function (f) {
                    var type = f.type || 'text';
                    var val = f.value != null ? f.value : '';
                    return '<div class="field">' +
                        '<label>' + (f.label || '') + '</label>' +
                        '<input data-name="' + f.name + '" type="' + type + '" placeholder="' + (f.placeholder || '') + '" value="' + val + '" />' +
                        '</div>';
                }).join('') || '<div class="small">' + (options.message || '') + '</div>';
                modalBackdrop.classList.add('active');
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                function close() {
                    modalBackdrop.classList.remove('active');
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                    modalOk.onclick = null;
                    modalCancel.onclick = null;
                }
                modalCancel.onclick = function () {
                    close();
                    reject('cancel');
                };
                modalOk.onclick = function () {
                    var data = {};
                    var inputs = modalBody.querySelectorAll('input');
                    inputs.forEach(function (inp) {
                        data[inp.getAttribute('data-name')] = inp.value;
                    });
                    close();
                    resolve(data);
                };
            });
        }

        function showMessage(msg) {
            return openFormDialog({ title: '提示', message: msg });
        }

        function refreshOwnerCardCount() {
            var form = new FormData();
            form.append('action', 'get_mars_owner_card_count');
            fetch('ajax.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            }).then(function (res) { return res.json(); })
              .then(function (resp) {
                if (resp.ret === 0) {
                    ownerCardCount = resp.data.count || 0;
                    ownerInfoEl.textContent = '我的火星老板卡：' + ownerCardCount + ' 张';
                } else {
                    ownerInfoEl.textContent = '我的火星老板卡：查询失败';
                }
            }).catch(function () {
                ownerInfoEl.textContent = '我的火星老板卡：查询失败';
            });
        }

        function renderRooms() {
            roomsEl.innerHTML = '';
            if (!rooms.length) {
                roomsEl.innerHTML = '<div class="loading-state">🔄 正在加载桌子列表...</div>';
                return;
            }
            var nowMs = Date.now();
            rooms.forEach(function (r) {
                var ownerIdNum = r.owner_id_raw ? parseInt(r.owner_id_raw, 10) : 0;
                var ownerUntilNum = r.owner_until ? parseInt(r.owner_until, 10) : 0;
                var expired = ownerUntilNum > 0 ? ownerUntilNum < nowMs : false;
                var nowOwnerActive = ownerIdNum > 1 && !expired;
                // ID=1 也视作普通老板
                var platformOwnerActive = (ownerIdNum === 1 && !expired);
                var canBind = (ownerIdNum === 0 || expired);
                var isMyActiveTable = !expired && (ownerIdNum === parseInt(userId, 10));
                var ownerText = r.owner_name_raw
                    ? r.owner_name_raw
                    : (platformOwnerActive ? '平台' : '暂无老板');
                var ownerUntilText = r.owner_until ? new Date(r.owner_until).toLocaleString() : '-';
                var onlineInfo = '';
                if (roomOnlineMap[r.key]) {
                    var info = roomOnlineMap[r.key];
                    var spectatorsCount = info.spectators || (info.spectators_list ? info.spectators_list.length : 0) || 0;
                    var playersCount = 0;
                    if (info.players) {
                        if (info.players.p1) playersCount++;
                        if (info.players.p2) playersCount++;
                    }
                    var totalOnline = playersCount + spectatorsCount;
                    onlineInfo = '<div class="small" style="margin-top:6px;">' +
                        '<span style="display:inline-block;padding:4px 8px;border-radius:8px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.4);color:#34d399;font-weight:600;box-shadow:0 0 10px rgba(16,185,129,0.25);">' +
                        '在线 ' + totalOnline + '：玩家' + playersCount + '，观众' + spectatorsCount +
                        '</span>' +
                        '</div>';
                }
                var div = document.createElement('div');
                div.className = 'room-card';
                div.innerHTML = '<h4>' + r.name + '</h4>' +
                    '<div class="small">频道：<span style="color:#7dd3fc;">' + r.key + '</span></div>' +
                    '<div class="small">下注额：<span style="color:#fbbf24;font-weight:600;">' + r.bet_amount + '</span></div>' +
                    '<div class="small">老板抽成：<span style="color:#c084fc;font-weight:600;">' + r.owner_rake_percent + '%</span></div>' +
                    '<div class="small">老板：<span style="color:#34d399;">' + ownerText + '</span></div>' +
                    '<div class="small">到期：<span style="color:#9ca3af;">' + ownerUntilText + '</span></div>' +
                    (onlineInfo || '') +
                    '<div style="margin-top:6px; display:flex; gap:6px; flex-wrap:wrap;">' +
                    '<button data-room="' + r.key + '" data-table="' + r.id + '" data-role="enter">进入房间</button>' +
                    (canBind ? ('<button data-table="' + r.id + '" data-role="bind">我要当老板</button>') : '') +
                    (isMyActiveTable ? ('<button data-table="' + r.id + '" data-role="config" class="secondary">设置</button>') : '') +
                    '</div>';
                roomsEl.appendChild(div);
            });
        }

        function fetchRooms() {
            roomsEl.innerHTML = '<div class="loading-state">🔄 加载桌子列表...</div>';
            var form = new FormData();
            form.append('action', 'get_game_tables');
            fetch('ajax.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            }).then(function (res) { return res.json(); })
              .then(function (resp) {
                if (resp.ret === 0) {
                    rooms = resp.data.map(function (item) {
                        return {
                            id: item.id,
                            key: 'mars-table-' + item.id,
                            name: item.name,
                            bet_amount: item.bet_amount,
                            owner_rake_percent: item.owner_rake_percent,
                            owner_id: item.owner_id,
                            owner_name: item.owner_name,
                            owner_id_raw: item.owner_id_raw,
                            owner_name_raw: item.owner_name_raw,
                            owner_until: item.owner_until,
                            is_expired: item.is_expired,
                        };
                    });
                    renderRooms();
                } else {
                    roomsEl.innerHTML = '<div class="error-state">❌ 加载失败：' + (resp.msg || '未知错误') + '</div>';
                }
            }).catch(function (err) {
                roomsEl.innerHTML = '<div class="error-state">❌ 加载失败：' + err + '</div>';
            });
        }

        function appendLobbyLine(html, cls) {
            var div = document.createElement('div');
            div.className = cls || 'msg';
            div.innerHTML = html;
            lobbyLog.appendChild(div);
            lobbyLog.scrollTop = lobbyLog.scrollHeight;
        }

        function updateLobbyCount() {
            if (lobbyCountEl) {
                lobbyCountEl.textContent = '在线人数：' + lobbyUserCount;
            }
        }

        function connectLobby() {
            var host = wsHostOverride || window.location.hostname;
            var port = wsPortOverride || defaultPort;
            // 强制使用 ws 协议（即便页面是 https），按需确保浏览器允许运行
            var protocol = 'ws://';
            // 端口为空或“0”不拼；wss 的 443、ws 的 80 也不拼
            var portStr = (port === null || port === undefined) ? '' : port.toString().trim();
            var portPart = '';
            if (portStr !== '' && portStr !== '0') {
                if (!(protocol === 'wss://' && portStr === '443') && !(protocol === 'ws://' && portStr === '80')) {
                    portPart = ':' + portStr;
                }
            }
            var wsUrl = protocol + host + portPart + '/?room=global&role=spectator&user=' + encodeURIComponent(nickname) + (userId ? ('&uid=' + encodeURIComponent(userId)) : '');
            lobbyWs = new WebSocket(wsUrl);
            lobbyStatus.textContent = '大厅连接中...';
            lobbyStatus.classList.remove('connected');

            lobbyWs.onopen = function() {
                lobbyStatus.textContent = '大厅已连接';
                lobbyStatus.classList.add('connected');
                lobbyUserCount = 0;
                updateLobbyCount();
                appendLobbyLine('<span class="sys">已加入大厅聊天</span>', 'sys');
                // 请求房间在线状态
                if (lobbyWs && lobbyWs.readyState === 1) {
                    lobbyWs.send(JSON.stringify({type: 'room_state_request'}));
                }
            };
            lobbyWs.onmessage = function(e) {
                try {
                    var data = JSON.parse(e.data);
                    if (data.type === 'system') {
                        appendLobbyLine('<span class="sys">' + data.text + '</span>', 'sys');
                        // 系统消息不再单独统计人数，依赖 room_update
                    } else if (data.type === 'message') {
                        appendLobbyLine('<span class="user">' + data.user + '</span>: ' + data.text +
                            (data.time ? '<span class="time">' + data.time + '</span>' : ''), 'msg');
                    } else if (data.type === 'room_state') {
                        if (Array.isArray(data.rooms)) {
                            data.rooms.forEach(function(r) {
                                if (r.room) {
                                    roomOnlineMap[r.room] = {
                                        players: r.players || {},
                                        spectators: r.spectators || 0,
                                        spectators_list: r.spectators_list || []
                                    };
                                }
                            });
                            renderRooms(); // 更新房间在线人数
                        }
                    } else if (data.type === 'room_update') {
                        // 使用 room_update 中的 spectators / spectators_list 作为大厅人数（global room）
                        if ((data.room || '') === 'global') {
                            if (typeof data.spectators === 'number') {
                                lobbyUserCount = data.spectators;
                            } else if (Array.isArray(data.spectators_list)) {
                                lobbyUserCount = data.spectators_list.length;
                            }
                            updateLobbyCount();
                        }
                        // 同时更新房间在线人数（如果是具体房间的 room_update）
                        if (data.room) {
                            roomOnlineMap[data.room] = roomOnlineMap[data.room] || {};
                            roomOnlineMap[data.room].players = data.players || {};
                            roomOnlineMap[data.room].spectators = data.spectators || 0;
                            roomOnlineMap[data.room].spectators_list = data.spectators_list || [];
                            renderRooms();
                        }
                    }
                } catch (err) {
                    appendLobbyLine('<span class="sys">解析消息失败: ' + err + '</span>', 'sys');
                }
            };
            lobbyWs.onclose = function() {
                lobbyStatus.textContent = '大厅未连接';
                lobbyStatus.classList.remove('connected');
                appendLobbyLine('<span class="sys">大厅连接已断开</span>', 'sys');
                lobbyUserCount = 0;
                updateLobbyCount();
            };
            lobbyWs.onerror = function() {
                appendLobbyLine('<span class="sys">大厅连接出错</span>', 'sys');
            };
        }

        function sendLobby() {
            var txt = (lobbyText.value || '').trim();
            if (!txt || !lobbyWs || lobbyWs.readyState !== 1) return;
            lobbyWs.send(txt);
            lobbyText.value = '';
        }

        if (lobbySend && lobbyText) {
            lobbySend.addEventListener('click', sendLobby);
            lobbyText.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') sendLobby();
            });
        }
        if (lobbyConnectBtn) {
            lobbyConnectBtn.addEventListener('click', function() {
                if (lobbyWs && lobbyWs.readyState === 1) {
                    lobbyWs.close();
                    return;
                }
                connectLobby();
            });
        }

        roomsEl.addEventListener('click', function(e) {
            if (e.target.tagName.toLowerCase() !== 'button') return;
            var role = e.target.getAttribute('data-role');
            var tableId = e.target.getAttribute('data-table');
            var roomKey = e.target.getAttribute('data-room');
            if (role === 'enter') {
                if (!roomKey || !tableId) return;
                // 带上 table_id 进入房间页面
                window.location.href = 'mars_duel.php?table_id=' + encodeURIComponent(tableId);
                return;
            }
            if (role === 'bind') {
                if (!tableId) return;
                if (ownerCardCount <= 0) {
                    showMessage('您没有火星老板卡，请先在魔力值商店购买');
                    return;
                }
                var form = new FormData();
                form.append('action', 'use_mars_owner_card');
                form.append('table_id', tableId);
                fetch('ajax.php', {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin'
                }).then(function (res) { return res.json(); })
                  .then(function (resp) {
                    if (resp.ret === 0) {
                        ownerCardCount = (resp.data && typeof resp.data.owner_card !== 'undefined') ? resp.data.owner_card : Math.max(0, ownerCardCount - 1);
                        ownerInfoEl.textContent = '我的火星老板卡：' + ownerCardCount + ' 张';
                        fetchRooms();
                        showMessage(resp.msg || '绑定成功');
                    } else {
                        showMessage(resp.msg || '绑定失败');
                    }
                }).catch(function (err) {
                    showMessage('请求失败：' + err);
                });
                return;
            }
            if (role === 'config') {
                if (!tableId) return;
                openFormDialog({
                    title: '设置桌子参数',
                    fields: [
                        { name: 'name', label: '桌子名称(<=64字符)', type: 'text', value: (rooms.find(function(x){return x.id==tableId;})||{}).name || '', placeholder: '桌子名称' },
                        { name: 'owner_rake_percent', label: '老板抽成(1-90%)', type: 'number', value: (rooms.find(function(x){return x.id==tableId;})||{}).owner_rake_percent || 10, placeholder: '1-90' },
                        { name: 'bet_amount', label: '下注额(正整数)', type: 'number', value: (rooms.find(function(x){return x.id==tableId;})||{}).bet_amount || 10000, placeholder: '如 10000' },
                    ]
                }).then(function (data) {
                    var newName = (data.name || '').trim();
                    var newRake = parseInt(data.owner_rake_percent || '0', 10);
                    var newBet = parseInt(data.bet_amount || '0', 10);
                    if (!newName) {
                        showMessage('桌子名称不能为空');
                        return;
                    }
                    if (newName.length > 64) {
                        showMessage('桌子名称长度需在64字符以内');
                        return;
                    }
                    if (!(newRake >= 1 && newRake <= 90)) {
                        showMessage('抽成需在1-90之间');
                        return;
                    }
                    if (!(newBet > 0)) {
                        showMessage('下注额必须大于0');
                        return;
                    }
                    var form2 = new FormData();
                    form2.append('action', 'update_game_table_settings');
                    form2.append('table_id', tableId);
                    form2.append('name', newName);
                    form2.append('owner_rake_percent', newRake);
                    form2.append('bet_amount', newBet);
                    fetch('ajax.php', {
                        method: 'POST',
                        body: form2,
                        credentials: 'same-origin'
                    }).then(function (res) { return res.json(); })
                      .then(function (resp) {
                        if (resp.ret === 0) {
                            showMessage('设置已保存');
                            fetchRooms();
                        } else {
                            showMessage(resp.msg || '保存失败');
                        }
                    }).catch(function (err) {
                        showMessage('请求失败：' + err);
                    });
                }).catch(function () {});
            }
        });

        refreshOwnerCardCount();
        fetchRooms();
        connectLobby();
    })();
    </script>
</body>
</html>

