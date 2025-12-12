<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>火星幸运局 - 游戏说明</title>
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
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(10, 22, 40, 0.85);
            border: 1px solid rgba(0, 212, 255, 0.4);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 0 30px rgba(0, 212, 255, 0.3);
            backdrop-filter: blur(10px);
        }
        
        h1 {
            color: #00d4ff;
            text-shadow: 0 0 15px rgba(0, 212, 255, 0.6);
            margin: 0 0 24px 0;
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .section {
            margin-bottom: 24px;
        }
        
        .section-title {
            color: #00d4ff;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 12px;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .important-box {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            border: 2px solid rgba(239, 68, 68, 0.5);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
        }
        
        .important-box .section-title {
            color: #ef4444;
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.6);
        }
        
        ul {
            margin: 0;
            padding-left: 24px;
            line-height: 1.9;
            font-size: 15px;
        }
        
        li {
            margin-bottom: 10px;
            color: #e5e7eb;
        }
        
        .important-box li {
            color: #fca5a5;
        }
        
        strong {
            color: #00d4ff;
            font-weight: 700;
        }
        
        .important-box strong {
            color: #ef4444;
        }
        
        .highlight {
            color: #fbbf24;
            text-shadow: 0 0 8px rgba(251, 191, 36, 0.6);
            font-size: 16px;
            font-weight: 700;
        }
        
        .step {
            margin-bottom: 16px;
            padding: 14px;
            background: rgba(0, 212, 255, 0.08);
            border-left: 4px solid #00d4ff;
            border-radius: 8px;
        }
        
        .step-title {
            color: #00d4ff;
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 6px;
        }
        
        .step-content {
            color: #e5e7eb;
            font-size: 14px;
            line-height: 1.7;
        }
        
        .step-2 .step-title { color: #a855f7; }
        .step-2 { border-left-color: #a855f7; background: rgba(168, 85, 247, 0.08); }
        
        .step-3 .step-title { color: #fbbf24; }
        .step-3 { border-left-color: #fbbf24; background: rgba(251, 191, 36, 0.08); }
        
        .step-4 .step-title { color: #10b981; }
        .step-4 { border-left-color: #10b981; background: rgba(16, 185, 129, 0.08); }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span>🎮</span>
            <span>火星幸运局 - 游戏说明</span>
        </h1>
        
        <!-- 重要提示 -->
        <div class="section important-box">
            <div class="section-title">
                <span>⚠️</span>
                <span>重要提示</span>
            </div>
            <ul>
                <li><strong>余额要求</strong>：开局前需要 <span class="highlight">5倍下注金额</span> 的余额（开局1次 + 跟注4次），余额不足无法开局</li>
                <li><strong>扣款规则</strong>：<span class="highlight">开局时扣款</span>，<span class="highlight">每次跟注时也会扣款</span>，所有扣款都会加入当前局奖池</li>
                <li><strong>奖池说明</strong>：每局奖池独立，结算后重置；奖池金额 = 开局扣款 + 所有跟注扣款</li>
            </ul>
        </div>
        
        <!-- 游戏流程 -->
        <div class="section">
            <div class="section-title">游戏流程</div>
            
            <div class="step step-1">
                <div class="step-title">1. 暗骰选择</div>
                <div class="step-content">双方从1-10号暗骰池中各选择1枚暗骰，点数保密直到对局结束。先手玩家有5秒选择时间，超时自动随机选择。</div>
            </div>
            
            <div class="step step-2">
                <div class="step-title">2. 明骰投掷</div>
                <div class="step-content">双方轮流投掷1枚明骰（最多5次），每次投掷后显示点数并累加到总分。超时30秒自动投掷。</div>
            </div>
            
            <div class="step step-3">
                <div class="step-title">3. 跟注/弃权</div>
                <div class="step-content">每轮投掷后，双方选择"跟注"或"弃权"。<strong>跟注会扣除下注金额并加入奖池</strong>。超时15秒自动跟注。</div>
            </div>
            
            <div class="step step-4">
                <div class="step-title">4. 结算</div>
                <div class="step-content">当一方弃权或双方都投掷5次后结算。总点数高者获胜，获得奖池扣除抽成后的金额。平局则双方平分奖池。</div>
            </div>
        </div>
        
        <!-- 其他功能 -->
        <div class="section">
            <div class="section-title">💡 其他功能</div>
            <ul>
                <li><strong>进入房间</strong>：默认观战，点击"占位"按钮入座，房主可踢人回观众</li>
                <li><strong>开始对局</strong>：两席都有玩家后，任意一方点击"开始对局"，先点者先手</li>
                <li><strong>AI玩家</strong>：可以召唤AI玩家对战，AI会自动选择暗骰、投掷和决策</li>
                <li><strong>断线重连</strong>：刷新页面后会自动恢复到原座位，游戏状态同步恢复</li>
                <li><strong>老板功能</strong>：持有"火星老板卡"可成为桌子老板，有效期30天，可设置下注额与抽成</li>
                <li><strong>结算分成</strong>：赢家获得(总池-平台抽成-老板抽成)，房主获得老板抽成，平台抽固定比例</li>
            </ul>
        </div>
    </div>
</body>
</html>
