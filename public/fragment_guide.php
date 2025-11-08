<?php
require_once("../include/bittorrent.php");
require_once("../include/eloquent.php");
dbconn();
loggedinorreturn();

header('Content-Type: text/html; charset=utf-8');

// 获取作物数据
$crops = \App\Models\StardustCrop::where('is_active', true)->orderBy('sort_order')->get();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>碎片获取完全指南</title>
    <style>
        body { font-family: "Microsoft YaHei", Arial; padding: 20px; background: #1a1f3a; color: #fff; }
        .section { margin: 20px 0; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 10px; }
        .success { border-left: 4px solid #4ECDC4; }
        .info { border-left: 4px solid #667eea; }
        .warning { border-left: 4px solid #FFA500; }
        .danger { border-left: 4px solid #FF6B6B; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; border: 1px solid rgba(255,255,255,0.2); text-align: left; }
        th { background: rgba(138, 43, 226, 0.3); }
        .highlight { color: #4ECDC4; font-weight: bold; }
        .emoji { font-size: 24px; }
        .btn { display: inline-block; padding: 10px 20px; background: #4ECDC4; color: #000; text-decoration: none; border-radius: 5px; margin: 5px; }
        .formula { background: rgba(0,0,0,0.5); padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
    </style>
</head>
<body>
    <h1>💎 碎片获取完全指南</h1>
    
    <div class="section success">
        <h2>🌾 正常收获（自己的农场）</h2>
        
        <h3>📊 碎片数量计算公式</h3>
        <div class="formula">
            <strong>正常成熟：</strong><br>
            基础碎片 = random(作物最小产量, 作物最大产量)<br>
            浇水加成 = 基础碎片 × (浇水次数 × 10%)<br>
            <span class="highlight">最终碎片 = 基础碎片 + 浇水加成</span>
        </div>
        
        <div class="formula">
            <strong>已枯萎：</strong><br>
            <span class="highlight">最终碎片 = 作物最小产量（没有浇水加成）</span><br>
            经验减半
        </div>
        
        <h3>💧 浇水加成详解</h3>
        <table>
            <tr>
                <th>浇水次数</th>
                <th>产量加成</th>
                <th>示例（基础产量2）</th>
            </tr>
            <tr>
                <td>0次</td>
                <td>0%</td>
                <td>2个碎片</td>
            </tr>
            <tr>
                <td>1次</td>
                <td>+10%</td>
                <td>2 × 1.1 = <span class="highlight">3个碎片</span> (向上取整)</td>
            </tr>
            <tr>
                <td>2次</td>
                <td>+20%</td>
                <td>2 × 1.2 = <span class="highlight">3个碎片</span> (向上取整)</td>
            </tr>
            <tr>
                <td>3次（上限）</td>
                <td>+30%</td>
                <td>2 × 1.3 = <span class="highlight">3个碎片</span> (向上取整)</td>
            </tr>
        </table>
        
        <p style="margin-top: 15px; padding: 12px; background: rgba(78, 205, 196, 0.2); border-radius: 5px;">
            💡 <strong>小技巧</strong>：让好友帮你浇水3次，可以获得最高30%的产量加成！
        </p>
    </div>
    
    <div class="section danger">
        <h2>🦹 偷取碎片（好友的农场）</h2>
        
        <h3>📊 偷取规则</h3>
        <table>
            <tr>
                <th>条件</th>
                <th>说明</th>
            </tr>
            <tr>
                <td>可偷取状态</td>
                <td>作物必须<strong>已成熟</strong>且农场主<strong>超过24小时未收获</strong></td>
            </tr>
            <tr>
                <td>偷取数量</td>
                <td><span class="highlight">固定1个碎片</span>（不受浇水加成影响）</td>
            </tr>
            <tr>
                <td>每块地限制</td>
                <td>每块地<strong>只能被偷1次</strong></td>
            </tr>
            <tr>
                <td>每天限制</td>
                <td>每天只能偷同一个好友<strong>1次</strong></td>
            </tr>
            <tr>
                <td>偷后状态</td>
                <td>地块标记为"已偷"，其他人不能再偷</td>
            </tr>
        </table>
        
        <div class="formula">
            <strong>偷取碎片数量：</strong><br>
            <span class="highlight">固定 = 1个碎片</span><br>
            （无论作物产量多少，偷取都是1个）
        </div>
        
        <p style="margin-top: 15px; padding: 12px; background: rgba(255, 107, 107, 0.2); border-radius: 5px;">
            ⚠️ <strong>注意</strong>：偷取是惩罚机制，用于提醒玩家及时收获。建议好友之间提醒收获，而不是偷取！
        </p>
    </div>
    
    <div class="section info">
        <h2>📋 各作物碎片产量表</h2>
        <table>
            <tr>
                <th>作物</th>
                <th>基础产量</th>
                <th>无浇水</th>
                <th>浇水1次</th>
                <th>浇水2次</th>
                <th>浇水3次（最大）</th>
                <th>偷取</th>
            </tr>
            <?php foreach ($crops as $crop): ?>
            <tr>
                <td><span class="emoji"><?php echo $crop->emoji; ?></span> <?php echo $crop->name; ?></td>
                <td><?php echo $crop->fragment_min; ?>-<?php echo $crop->fragment_max; ?></td>
                <td><?php echo $crop->fragment_min; ?>-<?php echo $crop->fragment_max; ?></td>
                <td><?php echo ceil($crop->fragment_min * 1.1); ?>-<?php echo ceil($crop->fragment_max * 1.1); ?></td>
                <td><?php echo ceil($crop->fragment_min * 1.2); ?>-<?php echo ceil($crop->fragment_max * 1.2); ?></td>
                <td><span class="highlight"><?php echo ceil($crop->fragment_min * 1.3); ?>-<?php echo ceil($crop->fragment_max * 1.3); ?></span></td>
                <td>1</td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <p style="margin-top: 15px; font-size: 14px; color: #aaa;">
            * 实际碎片数量在最小和最大值之间随机<br>
            * 浇水加成向上取整（例如：2 × 1.1 = 2.2 → 3）
        </p>
    </div>
    
    <div class="section success">
        <h2>🎯 最大化碎片获取策略</h2>
        
        <h3>🌟 自己的农场</h3>
        <ol>
            <li><strong>及时收获</strong>：避免作物枯萎（成熟后24小时内收获）</li>
            <li><strong>寻求帮助</strong>：邀请3个好友浇水，获得30%产量加成</li>
            <li><strong>选择高产作物</strong>：优先种植碎片产量高的作物（如太阳、海王星）</li>
            <li><strong>多开地块</strong>：升级农场，增加土地数量</li>
        </ol>
        
        <h3>🤝 好友农场</h3>
        <ol>
            <li><strong>帮助浇水</strong>：给好友浇水（获得5星尘/次）</li>
            <li><strong>善意提醒</strong>：提醒好友及时收获，而不是偷取</li>
            <li><strong>谨慎偷取</strong>：只在好友长期未上线时偷取（每块地只能偷1个）</li>
        </ol>
    </div>
    
    <div class="section warning">
        <h2>📝 收获 vs 偷取对比</h2>
        <table>
            <tr>
                <th>项目</th>
                <th>正常收获（自己）</th>
                <th>偷取（好友）</th>
            </tr>
            <tr>
                <td><strong>碎片数量</strong></td>
                <td class="highlight">1-5个（看作物+浇水）</td>
                <td>固定1个</td>
            </tr>
            <tr>
                <td><strong>经验获得</strong></td>
                <td class="highlight">完整经验</td>
                <td>无</td>
            </tr>
            <tr>
                <td><strong>限制</strong></td>
                <td>随时收获</td>
                <td>每天每人1次</td>
            </tr>
            <tr>
                <td><strong>浇水加成</strong></td>
                <td class="highlight">有（最多+30%）</td>
                <td>无</td>
            </tr>
            <tr>
                <td><strong>触发条件</strong></td>
                <td>成熟即可</td>
                <td>成熟后24小时</td>
            </tr>
        </table>
        
        <p style="margin-top: 20px; padding: 15px; background: rgba(255, 165, 0, 0.2); border-radius: 5px;">
            💡 <strong>结论</strong>：及时收获远比被偷要好！被偷只有1个碎片，而正常收获有2-6个（含浇水加成）！
        </p>
    </div>
    
    <div class="section info">
        <h2>🧮 实际案例计算</h2>
        
        <h3>案例1：月球（基础产量1-2）</h3>
        <table>
            <tr>
                <th>情况</th>
                <th>碎片数量</th>
            </tr>
            <tr>
                <td>无浇水，正常收获</td>
                <td>1-2个（随机）</td>
            </tr>
            <tr>
                <td>浇水3次，正常收获</td>
                <td class="highlight">2-3个（1.3倍，向上取整）</td>
            </tr>
            <tr>
                <td>枯萎收获</td>
                <td>1个（最小值）</td>
            </tr>
            <tr>
                <td>被偷取</td>
                <td>1个</td>
            </tr>
        </table>
        
        <h3>案例2：太阳（基础产量2-5）</h3>
        <table>
            <tr>
                <th>情况</th>
                <th>碎片数量</th>
            </tr>
            <tr>
                <td>无浇水，正常收获</td>
                <td>2-5个（随机）</td>
            </tr>
            <tr>
                <td>浇水3次，正常收获</td>
                <td class="highlight">3-7个（1.3倍，向上取整）</td>
            </tr>
            <tr>
                <td>枯萎收获</td>
                <td>2个（最小值）</td>
            </tr>
            <tr>
                <td>被偷取</td>
                <td>1个</td>
            </tr>
        </table>
        
        <p style="margin-top: 20px; padding: 15px; background: rgba(78, 205, 196, 0.2); border-radius: 5px;">
            ✨ <strong>最佳策略</strong>：种植太阳 + 3次浇水 + 及时收获 = 最多7个碎片！
        </p>
    </div>
    
    <a href="stardust_farm.php" class="btn">🏡 返回农场</a>
    <a href="index.php" class="btn">🏠 返回首页</a>
</body>
</html>

