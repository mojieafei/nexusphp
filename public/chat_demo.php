<?php
require_once "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$nickname = $CURUSER["username"] ?? ("guest-" . substr(md5(getip() . microtime(true)), 0, 6));
// 允许通过 query 覆盖 ws host/port，便于 WSL/反向代理环境
$wsHostOverride = $_GET['ws_host'] ?? '';
$wsPortOverride = $_GET['ws_port'] ?? '';
$defaultPort = 2346;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>实时聊天室 Demo (Workerman)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        #log { border: 1px solid #ccc; height: 320px; overflow-y: auto; padding: 10px; background: #fafafa; }
        #input { width: 80%; padding: 8px; }
        #send { padding: 8px 12px; }
        .sys { color: #888; }
        .msg { margin: 4px 0; }
        .user { font-weight: bold; color: #0a7; }
        .time { color: #999; font-size: 12px; margin-left: 6px; }
    </style>
</head>
<body>
    <h2>实时聊天室 Demo</h2>
    <p>当前用户：<b><?php echo htmlspecialchars($nickname); ?></b> | 服务器：<?php echo htmlspecialchars($wsUrl); ?></p>
    <div id="log"></div>
    <div style="margin-top:10px;">
        <input id="input" type="text" placeholder="输入消息后回车发送" />
        <button id="send">发送</button>
    </div>

    <script>
        (function() {
            var hostOverride = "<?php echo htmlspecialchars($wsHostOverride, ENT_QUOTES); ?>";
            var portOverride = "<?php echo htmlspecialchars($wsPortOverride, ENT_QUOTES); ?>";
            var host = hostOverride || window.location.hostname;
            var port = portOverride || "<?php echo $defaultPort; ?>";
            var protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
            var wsUrl = protocol + host + ':' + port + "/?user=<?php echo rawurlencode($nickname); ?>";
            var log = document.getElementById('log');
            var input = document.getElementById('input');
            var sendBtn = document.getElementById('send');
            var ws;

            function appendLine(html, cls) {
                var div = document.createElement('div');
                div.className = cls || 'msg';
                div.innerHTML = html;
                log.appendChild(div);
                log.scrollTop = log.scrollHeight;
            }

            function connect() {
                ws = new WebSocket(wsUrl);
                ws.onopen = function() {
                    appendLine('<span class="sys">已连接服务器</span>', 'sys');
                };
                ws.onmessage = function(e) {
                    try {
                        var data = JSON.parse(e.data);
                        if (data.type === 'system') {
                            appendLine('<span class="sys">' + data.text + '</span>', 'sys');
                        } else if (data.type === 'message') {
                            appendLine(
                                '<span class="user">' + data.user + '</span>: ' +
                                '<span>' + data.text + '</span>' +
                                (data.time ? '<span class="time">' + data.time + '</span>' : '')
                            );
                        }
                    } catch (err) {
                        appendLine('<span class="sys">解析消息失败: ' + err + '</span>', 'sys');
                    }
                };
                ws.onclose = function() {
                    appendLine('<span class="sys">连接已断开，3秒后重连...</span>', 'sys');
                    setTimeout(connect, 3000);
                };
                ws.onerror = function() {
                    appendLine('<span class="sys">连接出错</span>', 'sys');
                };
            }

            function sendMsg() {
                var text = input.value.trim();
                if (!text || !ws || ws.readyState !== 1) return;
                ws.send(text);
                input.value = '';
            }

            sendBtn.addEventListener('click', sendMsg);
            input.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') sendMsg();
            });

            connect();
        })();
    </script>
</body>
</html>

