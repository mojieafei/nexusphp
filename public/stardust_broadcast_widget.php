<!-- 星尘农场全站广播 -->
<div id="stardustBroadcastWidget" style="
    position: fixed;
    top: 80px;
    left: 20px;
    width: 350px;
    max-height: 400px;
    background: rgba(26, 31, 58, 0.95);
    border-radius: 15px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
    z-index: 9999999;
    overflow: hidden;
    transition: all 0.3s;
    display: none;
">
    <div style="
        padding: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
    " onclick="toggleStardustBroadcast()">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 20px;">📢</span>
            <span style="color: white; font-weight: bold;">星际动态</span>
        </div>
        <span id="broadcastToggle" style="color: white; font-size: 20px;">▼</span>
    </div>
    
    <div id="broadcastContent" style="
        max-height: 340px;
        overflow-y: auto;
        padding: 10px;
    ">
        <div id="broadcastList"></div>
        <div id="broadcastLoading" style="text-align: center; padding: 20px; color: #999;">
            加载中...
        </div>
    </div>
</div>

<!-- 浮动广播按钮 -->
<div id="stardustBroadcastBtn" style="
    position: fixed;
    top: 85px;
    left: 20px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(78, 205, 196, 0.4);
    z-index: 9999999;
    transition: all 0.3s;
    animation: pulse 2s infinite;
" onclick="showStardustBroadcast()">
    <span style="font-size: 28px;">📢</span>
    <div id="broadcastBadge" style="
        position: absolute;
        top: -5px;
        right: -5px;
        background: #FF6B6B;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(255, 107, 107, 0.5);
    ">0</div>
</div>

<style>
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

#stardustBroadcastBtn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(78, 205, 196, 0.6);
}

.broadcast-item {
    padding: 12px;
    margin: 8px 0;
    background: rgba(102, 126, 234, 0.1);
    border-left: 3px solid #4ECDC4;
    border-radius: 8px;
    transition: all 0.2s;
    animation: slideIn 0.3s ease-out;
}

.broadcast-item:hover {
    background: rgba(102, 126, 234, 0.2);
    transform: translateX(5px);
}

.broadcast-item.new {
    background: rgba(78, 205, 196, 0.2);
    border-left-color: #FFD700;
    animation: highlight 1s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes highlight {
    0%, 100% { background: rgba(78, 205, 196, 0.2); }
    50% { background: rgba(255, 215, 0, 0.3); }
}

.broadcast-time {
    font-size: 11px;
    color: #999;
    margin-top: 5px;
}

.broadcast-message {
    color: #e5e7eb;
    font-size: 14px;
    line-height: 1.5;
}

.broadcast-user {
    color: #4ECDC4;
    font-weight: bold;
}

#broadcastContent::-webkit-scrollbar {
    width: 6px;
}

#broadcastContent::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
    border-radius: 3px;
}

#broadcastContent::-webkit-scrollbar-thumb {
    background: rgba(78, 205, 196, 0.5);
    border-radius: 3px;
}

#broadcastContent::-webkit-scrollbar-thumb:hover {
    background: rgba(78, 205, 196, 0.7);
}
</style>

<script>
let broadcastWidget = {
    isOpen: false,
    lastBroadcastId: 0,
    lastReadId: 0,
    broadcasts: [],
    
    init() {
        const saved = localStorage.getItem('stardustBroadcastLastReadId')
        if (saved) {
            const parsed = parseInt(saved, 10)
            if (!isNaN(parsed)) {
                this.lastReadId = parsed
            }
        }
        this.loadBroadcasts();
        setInterval(() => this.loadBroadcasts(), 30000); // 每30秒刷新
    },
    
    loadBroadcasts() {
        fetch('ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=getStardustBroadcasts&params=' + encodeURIComponent(JSON.stringify({limit: 20}))
        })
        .then(res => res.json())
        .then(data => {
            if (data.ret === 0) {
                this.updateBroadcasts(data.data || []);
            } else {
                console.error('加载广播失败:', data.msg);
                this.updateBroadcasts([]);
            }
        })
        .catch(err => {
            console.error('加载广播失败:', err);
            this.updateBroadcasts([]);
        });
    },
    
    updateBroadcasts(newBroadcasts) {
        this.broadcasts = newBroadcasts;

        const sorted = [...newBroadcasts].sort((a, b) => (Number(a.id) || 0) - (Number(b.id) || 0))
        const newest = sorted.length ? Number(sorted[sorted.length - 1].id) || 0 : 0

        if (this.lastReadId === 0 && newest > 0) {
            this.lastReadId = newest
            localStorage.setItem('stardustBroadcastLastReadId', String(newest))
        }
        
        const maxId = newBroadcasts.reduce((max, item) => {
            const id = Number(item.id) || 0
            return id > max ? id : max
        }, this.lastReadId)

        if (this.isOpen) {
            this.markAllRead(maxId)
        }

        const unreadCount = this.isOpen
            ? 0
            : newBroadcasts.filter(item => (Number(item.id) || 0) > this.lastReadId).length

        const badge = document.getElementById('broadcastBadge')
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount
            badge.style.display = 'flex'
        } else {
            badge.style.display = 'none'
        }
        
        // 渲染列表
        this.renderBroadcasts();
    },

    markAllRead(maxId = null) {
        const currentMax = maxId !== null ? maxId : this.broadcasts.reduce((max, item) => {
            const id = Number(item.id) || 0
            return id > max ? id : max
        }, this.lastReadId)
        this.lastReadId = currentMax
        localStorage.setItem('stardustBroadcastLastReadId', String(currentMax))
        const badge = document.getElementById('broadcastBadge')
        badge.style.display = 'none'
    },
    
    renderBroadcasts() {
        const list = document.getElementById('broadcastList');
        const loading = document.getElementById('broadcastLoading');
        
        // 总是先隐藏加载提示
        loading.style.display = 'none';
        
        if (this.broadcasts.length === 0) {
            list.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">暂无动态</div>';
            return;
        }
        
        list.innerHTML = this.broadcasts.map((item, index) => {
            const isNew = index < 3 && !this.isOpen; // 最新3条标记为new
            const time = this.formatTime(item.created_at);
            const icon = this.getTypeIcon(item.type);
            const link = this.getItemLink(item);
            const content = `
                ${icon} <span class="broadcast-user">${item.username}</span> ${item.message}
            `;
            
            return `
                <div class="broadcast-item ${isNew ? 'new' : ''}">
                    <div class="broadcast-message">
                        ${link ? `<a href="${link}" target="_blank" style="color: inherit; text-decoration: underline;">${content}</a>` : content}
                    </div>
                    <div class="broadcast-time">${time}</div>
                </div>
            `;
        }).join('');
    },

    getItemLink(item) {
        const data = (item && typeof item.data === 'object' && item.data !== null) ? item.data : {}
        const safe = field => Object.prototype.hasOwnProperty.call(data, field) ? data[field] : null
        switch (item.type) {
            case 'fragment':
            case 'planet':
            case 'farm': {
                const userId = safe('user_id') || safe('target_user_id') || item.user_id || item.target_user_id
                return userId ? `stardust_farm.php?uid=${userId}` : null
            }
            case 'achievement':
                return 'stardust_farm.php'
            default:
                return null
        }
    },
    
    getTypeIcon(type) {
        const icons = {
            fragment: '💎',
            planet: '🌍',
            achievement: '🏆',
            milestone: '🎯'
        };
        return icons[type] || '📢';
    },
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return '刚刚';
        if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
        return Math.floor(diff / 86400) + '天前';
    }
};

function showStardustBroadcast() {
    const widget = document.getElementById('stardustBroadcastWidget');
    const btn = document.getElementById('stardustBroadcastBtn');
    const badge = document.getElementById('broadcastBadge');
    
    widget.style.display = 'block';
    btn.style.display = 'none';
    badge.style.display = 'none';
    broadcastWidget.isOpen = true;
    broadcastWidget.markAllRead();
    
    // 展开内容
    document.getElementById('broadcastContent').style.display = 'block';
    document.getElementById('broadcastToggle').textContent = '▼';
}

function hideStardustBroadcast() {
    const widget = document.getElementById('stardustBroadcastWidget');
    const btn = document.getElementById('stardustBroadcastBtn');
    
    widget.style.display = 'none';
    btn.style.display = 'flex';
    broadcastWidget.isOpen = false;
    broadcastWidget.markAllRead();
}

function toggleStardustBroadcast(event = null) {
    if (event) {
        event.stopPropagation();
    }
    const content = document.getElementById('broadcastContent');
    const toggle = document.getElementById('broadcastToggle');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        toggle.textContent = '▼';
    } else {
        content.style.display = 'none';
        toggle.textContent = '▶';
    }
}

// 点击外部关闭
document.addEventListener('click', function(e) {
    const widget = document.getElementById('stardustBroadcastWidget');
    const btn = document.getElementById('stardustBroadcastBtn');
    
    if (!widget.contains(e.target) && !btn.contains(e.target)) {
        if (broadcastWidget.isOpen && widget.style.display !== 'none') {
            hideStardustBroadcast();
        }
    }
});

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    broadcastWidget.init();
});
</script>

