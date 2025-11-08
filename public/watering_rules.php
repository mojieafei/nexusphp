<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>浇水规则说明</title>
    <style>
        body { font-family: "Microsoft YaHei", Arial; padding: 20px; background: #1a1f3a; color: #fff; }
        .section { margin: 20px 0; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 10px; }
        .success { border-left: 4px solid #4ECDC4; }
        .info { border-left: 4px solid #667eea; }
        .warning { border-left: 4px solid #FFA500; }
        .btn { display: inline-block; padding: 10px 20px; background: #4ECDC4; color: #000; text-decoration: none; border-radius: 5px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; border: 1px solid rgba(255,255,255,0.2); }
        th { background: rgba(138, 43, 226, 0.3); }
        .highlight { color: #4ECDC4; font-weight: bold; }
    </style>
</head>
<body>
    <h1>💧 浇水功能完整规则说明</h1>
    
    <div class="section success">
        <h2>✅ 修复内容</h2>
        <p>刚才修复了两个问题：</p>
        <ol>
            <li><strong>浇水方向错误</strong> → 现在正确缩短时间（而不是延长）</li>
            <li><strong>限制规则优化</strong> → 移除了"每天只能给一个好友浇水一次"的限制</li>
        </ol>
    </div>
    
    <div class="section info">
        <h2>📋 浇水规则（修复后）</h2>
        
        <h3>🎯 基本规则</h3>
        <table>
            <tr>
                <th>规则</th>
                <th>说明</th>
            </tr>
            <tr>
                <td><span class="highlight">只能给好友浇水</span></td>
                <td>不能给自己的作物浇水</td>
            </tr>
            <tr>
                <td><span class="highlight">只能给"生长中"的作物浇水</span></td>
                <td>空地、已成熟、已枯萎的不能浇水</td>
            </tr>
            <tr>
                <td><span class="highlight">每块地最多被浇水3次</span></td>
                <td>达到3次后，任何人都不能再浇水</td>
            </tr>
            <tr>
                <td><span class="highlight">每人对每块地只能浇水1次</span></td>
                <td>你给某块地浇过水后，不能再次浇水（但可以给其他地浇水）</td>
            </tr>
        </table>
        
        <h3>💎 浇水效果</h3>
        <table>
            <tr>
                <th>效果</th>
                <th>数值</th>
            </tr>
            <tr>
                <td>缩短成长时间</td>
                <td><span class="highlight">10%</span> 剩余时间</td>
            </tr>
            <tr>
                <td>浇水者奖励</td>
                <td><span class="highlight">+5 星尘</span></td>
            </tr>
            <tr>
                <td>累计最大效果</td>
                <td>被浇水3次，约缩短 <span class="highlight">27%</span> 总时长</td>
            </tr>
        </table>
    </div>
    
    <div class="section warning">
        <h2>⚠️ 你遇到的情况</h2>
        <p>你点击浇水后提示 <strong>"无法浇水（可能已经成熟或已有3人浇水）"</strong></p>
        
        <p>这是因为：</p>
        <ul>
            <li>❌ <strong>旧限制（已移除）</strong>：每天只能给一个好友浇水一次</li>
            <li>✅ <strong>新规则（修复后）</strong>：你已经给 <strong>这块特定的地</strong> 浇过水了</li>
        </ul>
        
        <p style="margin-top: 20px; padding: 15px; background: rgba(78, 205, 196, 0.2); border-radius: 5px;">
            💡 <strong>现在的行为</strong>：
        </p>
        <ul>
            <li>你可以给 admin 的 <strong>第1块地</strong> 浇水 → ✅ 成功</li>
            <li>你再次给 admin 的 <strong>第1块地</strong> 浇水 → ❌ 提示"你已经给这块地浇过水了"</li>
            <li>你给 admin 的 <strong>第2块地</strong> 浇水 → ✅ 成功（如果admin有第2块地且在生长中）</li>
            <li>其他用户给 admin 的 <strong>第1块地</strong> 浇水 → ✅ 成功（最多3人）</li>
        </ul>
    </div>
    
    <div class="section info">
        <h2>🎮 使用场景示例</h2>
        
        <h3>场景1：好友互助</h3>
        <p><strong>玩家A</strong> 种了3块地：</p>
        <ul>
            <li>地1：月球（生长中，2小时成熟）</li>
            <li>地2：水星（生长中，4小时成熟）</li>
            <li>地3：金星（生长中，6小时成熟）</li>
        </ul>
        
        <p><strong>玩家B</strong> 访问 A 的农场：</p>
        <ul>
            <li>给地1浇水 → ✅ 成功，A的月球缩短到1.8小时，B获得5星尘</li>
            <li>给地2浇水 → ✅ 成功，A的水星缩短到3.6小时，B获得5星尘</li>
            <li>给地3浇水 → ✅ 成功，A的金星缩短到5.4小时，B获得5星尘</li>
        </ul>
        
        <p><strong>玩家C、D</strong> 也来浇水：</p>
        <ul>
            <li>C给地1浇水 → ✅ 成功（第2次浇水）</li>
            <li>D给地1浇水 → ✅ 成功（第3次浇水，达到上限）</li>
            <li>E给地1浇水 → ❌ 失败（已经被浇水3次了）</li>
        </ul>
        
        <h3>场景2：不能重复浇水</h3>
        <p><strong>玩家B</strong> 第二天再次访问 A 的农场：</p>
        <ul>
            <li>给地1浇水 → ❌ 失败（已经浇过了）</li>
            <li>给地1浇水 → ❌ 失败（已经3人浇水了）</li>
            <li>但如果A种了新作物在地4 → ✅ 可以给地4浇水</li>
        </ul>
    </div>
    
    <div class="section success">
        <h2>✨ 现在试试看</h2>
        <p>修复后的行为：</p>
        <ol>
            <li>你可以给好友的<strong>多块不同的地</strong>分别浇水</li>
            <li>但<strong>同一块地</strong>你只能浇水1次</li>
            <li>每块地最多被<strong>3个不同的人</strong>浇水</li>
            <li>浇水确实会<strong>缩短时间</strong>，不是延长！</li>
        </ol>
        
        <p style="margin-top: 20px;">如果admin有多块地在生长中，你应该能给其他地也浇水！</p>
    </div>
    
    <a href="stardust_farm.php" class="btn">🏡 我的农场</a>
    <a href="stardust_friends.php" class="btn">🤝 好友农场</a>
    <a href="index.php" class="btn">🏠 返回首页</a>
</body>
</html>

