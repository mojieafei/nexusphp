<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>魔力值加成测试</title>
    <style>
        body { font-family: "Microsoft YaHei", Arial; padding: 20px; background: #1a1f3a; color: #fff; }
        .section { margin: 20px 0; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 10px; border-left: 4px solid #4ECDC4; }
        pre { background: #000; padding: 10px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; }
        .success { color: #4ECDC4; }
        .error { color: #FF6B6B; }
        table { border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; border: 1px solid rgba(255,255,255,0.2); text-align: left; }
        th { background: rgba(138, 43, 226, 0.3); }
    </style>
</head>
<body>
    <h1>🧪 魔力值加成系统测试</h1>
    
    <div class="section">
        <h2>1. 检查星尘农场表</h2>
        <?php
        try {
            // 使用原生SQL检查表是否存在
            $tableCheck = \Nexus\Database\NexusDB::selectOne("SHOW TABLES LIKE 'stardust_inventories'");
            $hasTable = !empty($tableCheck);
            
            if ($hasTable) {
                echo "<p class='success'>✅ stardust_inventories 表存在</p>";
                
                // 查询用户的行星
                $planets = \Nexus\Database\NexusDB::select(
                    "SELECT * FROM stardust_inventories WHERE user_id = ? AND item_type = 'planet'",
                    [$CURUSER['id']]
                );
                
                echo "<p>你当前拥有 <strong>" . count($planets) . "</strong> 个行星</p>";
                
                if (count($planets) > 0) {
                    echo "<table>";
                    echo "<tr><th>ID</th><th>行星ID</th><th>数量</th><th>获得时间</th></tr>";
                    foreach ($planets as $planet) {
                        echo "<tr>";
                        echo "<td>{$planet['id']}</td>";
                        echo "<td>{$planet['item_id']}</td>";
                        echo "<td>{$planet['quantity']}</td>";
                        echo "<td>{$planet['created_at']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<p class='error'>❌ stardust_inventories 表不存在</p>";
                echo "<p>请运行：<code>php artisan nexus:update</code></p>";
            }
        } catch (\Exception $e) {
            echo "<p class='error'>❌ 错误：" . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>2. 测试星尘农场加成函数</h2>
        <?php
        try {
            $factor = calculate_stardust_farm_addition($CURUSER['id']);
            echo "<p class='success'>✅ 函数调用成功</p>";
            echo "<p>当前加成系数：<strong>" . $factor . "</strong> (" . ($factor * 100) . "%)</p>";
        } catch (\Exception $e) {
            echo "<p class='error'>❌ 函数调用失败：" . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. 测试魔力值计算</h2>
        <?php
        try {
            $seedBonusResult = calculate_seed_bonus($CURUSER['id']);
            echo "<p class='success'>✅ calculate_seed_bonus() 调用成功</p>";
            echo "<pre>" . json_encode($seedBonusResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } catch (\Exception $e) {
            echo "<p class='error'>❌ calculate_seed_bonus() 调用失败：" . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. 测试魔力值表格生成</h2>
        <?php
        try {
            $bonusTableResult = build_bonus_table($CURUSER, $seedBonusResult ?? [], ['table_style' => 'width: 100%']);
            echo "<p class='success'>✅ build_bonus_table() 调用成功</p>";
            
            echo "<h3>返回值：</h3>";
            echo "<pre>" . json_encode([
                'has_harem_addition' => $bonusTableResult['has_harem_addition'],
                'has_official_addition' => $bonusTableResult['has_official_addition'],
                'has_medal_addition' => $bonusTableResult['has_medal_addition'],
                'has_stardust_farm_addition' => $bonusTableResult['has_stardust_farm_addition'] ?? false,
                'stardust_farm_addition_factor' => $bonusTableResult['stardust_farm_addition_factor'] ?? 0,
            ], JSON_PRETTY_PRINT) . "</pre>";
            
            echo "<h3>生成的表格：</h3>";
            echo $bonusTableResult['table'];
        } catch (\Exception $e) {
            echo "<p class='error'>❌ build_bonus_table() 调用失败：" . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. 诊断结果</h2>
        <?php
        if (isset($bonusTableResult) && isset($bonusTableResult['has_stardust_farm_addition'])) {
            if ($bonusTableResult['has_stardust_farm_addition']) {
                echo "<p class='success'>✅ 星尘农场加成正常工作！</p>";
                echo "<p>当前加成：" . ($bonusTableResult['stardust_farm_addition_factor'] * 100) . "%</p>";
            } else {
                echo "<p style='color: #FFA500;'>⚠️ 星尘农场加成为0（可能是你还没有合成行星）</p>";
                echo "<p>合成一个行星后将获得 +2% 魔力值加成</p>";
            }
        } else {
            echo "<p class='error'>❌ 星尘农场加成系统未正确集成</p>";
        }
        ?>
        
        <p style="margin-top: 20px;">
            <a href="mybonus.php" style="color: #4ECDC4;">→ 查看实际的魔力值页面</a><br>
            <a href="stardust_farm.php" style="color: #4ECDC4;">→ 进入星尘农场</a>
        </p>
    </div>
</body>
</html>

