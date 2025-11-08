# 星尘农场 - 安装部署指南

## 🌟 游戏简介

**星尘农场** 是一个基于"重建太阳系"背景故事的种植类小游戏。玩家通过玩流星游戏获得星尘，用星尘种植行星种子，收获碎片，合成完整行星，最终重建整个太阳系。

### 核心玩法
- ⭐ **获取星尘**: 玩流星游戏获得（100分 = 10星尘）
- 🌱 **种植作物**: 购买并种植9种天体的种子
- 💎 **收获碎片**: 等待成熟后收获碎片（9个碎片 = 1个完整行星）
- 🤝 **好友互动**: 浇水加速、偷碎片、访问好友农场
- 🏆 **成就系统**: 完成"重建太阳系"成就获得丰厚奖励
- 📊 **排行榜**: 财富榜、等级榜、收藏榜

## 📋 前置要求

- PHP 8.0+
- MySQL 5.7+
- Laravel 9.x+ (NexusPHP 框架)
- Composer

## 🚀 安装步骤

### 一条命令完成所有部署 ✨

在项目根目录执行：

```bash
php artisan nexus:update
```

**就这么简单！** 这条命令会自动：
- ✅ 创建9张数据库表
- ✅ 初始化10种天体作物数据
- ✅ 初始化10个成就数据
- ✅ 只执行一次，不会重复运行

### 工作原理

`nexus:update` 命令内部会检测 `stardust_farms` 表是否存在：
- 如果**不存在** → 自动创建表 + 初始化数据
- 如果**已存在** → 跳过，不重复执行

这是NexusPHP的标准升级流程，类似其他模块的初始化方式。

---

### 手动部署（仅用于调试）

如果需要单独执行迁移和数据初始化：

```bash
# 创建表
php artisan migrate --path=database/migrations/2025_11_08_000001_create_stardust_farm_tables.php

# 初始化数据（可选，nexus:update已包含）
php artisan db:seed --class=StardustCropsSeeder
php artisan db:seed --class=StardustAchievementsSeeder
```

### 验证安装

确保以下文件已正确创建：

**后端文件：**
- ✅ `database/migrations/2025_11_08_000001_create_stardust_farm_tables.php`
- ✅ `database/seeders/StardustCropsSeeder.php`
- ✅ `database/seeders/StardustAchievementsSeeder.php`
- ✅ `app/Models/StardustFarm.php`
- ✅ `app/Models/StardustLand.php`
- ✅ `app/Models/StardustCrop.php`
- ✅ `app/Models/StardustInventory.php`
- ✅ `app/Models/StardustInteraction.php`
- ✅ `app/Models/StardustTransactionLog.php`
- ✅ `app/Repositories/StardustFarmRepository.php`
- ✅ `app/Repositories/StardustAchievementRepository.php`

**前端文件：**
- ✅ `public/stardust_farm.php`

**API接口：**
- ✅ `public/ajax.php` (已添加星尘农场API)

**样式文件：**
- ✅ `public/styles/AstronautStyle/theme.css` (已添加星尘农场按钮样式)

**入口按钮：**
- ✅ `include/functions.php` (已添加右上角入口按钮)

### 4. 测试游戏功能

1. **访问游戏入口**
   - 登录网站后，在右上角会看到"星尘农场"按钮（绿色圆形按钮）
   - 点击进入游戏

2. **获取初始星尘**
   - 首次进入会自动获得100星尘
   - 也可以玩流星游戏获取更多星尘

3. **测试基本功能**
   - 购买种子并种植
   - 等待成熟后收获
   - 合成完整行星
   - 访问好友农场互动

## 📊 数据库表结构

系统创建了以下9张表：

1. **stardust_farms** - 用户农场信息
2. **stardust_lands** - 土地状态
3. **stardust_crops** - 作物配置（预设10种天体）
4. **stardust_inventories** - 用户背包（碎片和行星）
5. **stardust_interactions** - 互动记录
6. **stardust_achievements** - 成就配置
7. **stardust_user_achievements** - 用户成就记录
8. **stardust_transaction_logs** - 星尘交易日志
9. **stardust_leaderboards** - 排行榜缓存

## 🎮 游戏配置

### 作物配置（可在stardust_crops表中调整）

| 天体 | 种子价格 | 成熟时间 | 碎片产出 | 等级需求 |
|-----|---------|---------|---------|---------|
| 🌙 月球 | 50星尘 | 2小时 | 1-2个 | 1级 |
| ☿️ 水星 | 100星尘 | 4小时 | 1-2个 | 1级 |
| ♀️ 金星 | 150星尘 | 6小时 | 1-2个 | 2级 |
| 🌍 地球 | 200星尘 | 8小时 | 1-2个 | 3级 |
| ♂️ 火星 | 250星尘 | 12小时 | 1-2个 | 4级 |
| ♃ 木星 | 400星尘 | 24小时 | 1-3个 | 5级 |
| ♄ 土星 | 500星尘 | 36小时 | 1-3个 | 6级 |
| ⛢ 天王星 | 600星尘 | 48小时 | 1-3个 | 7级 |
| ♆ 海王星 | 700星尘 | 60小时 | 2-4个 | 8级 |
| ☀️ 太阳 | 2000星尘 | 72小时 | 2-5个 | 10级 |

### 经济平衡

- **流星游戏转换**: 100分 = 10星尘
- **平均每日获取**: 60-65星尘（游戏+访问好友）
- **完成一轮收集**: 约30天
- **土地扩展**: 1000星尘/块，最多12块
- **浇水加速**: 10%成熟时间
- **偷碎片**: 成熟后2小时内可偷，每人每天一次

## 🔧 常见问题

### Q: 如何调整游戏难度？
A: 可以在`stardust_crops`表中修改：
- `seed_price` - 种子价格
- `grow_duration` - 成熟时间（分钟）
- `fragment_min/max` - 碎片产出数量

### Q: 如何修改星尘转换比例？
A: 在`public/ajax.php`中搜索`meteor_game_submit`，修改这行：
```php
$stardustReward = intval(floor($score / 10)); // 当前是100分=10星尘
```

### Q: 如何添加新成就？
A: 在`database/seeders/StardustAchievementsSeeder.php`中添加新成就，然后重新运行seeder。

### Q: 排行榜如何更新？
A: 建议设置定时任务每天更新：
```bash
# 在crontab中添加
0 0 * * * cd /path/to/project && php artisan tinker --execute="(new \App\Repositories\StardustAchievementRepository())->updateLeaderboards()"
```

## 🎨 自定义样式

农场按钮样式位于：`public/styles/AstronautStyle/theme.css`

搜索`.stardust-farm-btn`可自定义：
- 按钮位置（top, right）
- 按钮颜色（background渐变）
- 动画效果（animation）

## 📝 API接口列表

所有接口通过`ajax.php`调用，参数格式：`action=xxx&params={json}`

### 农场管理
- `getStardustFarm` - 获取农场数据
- `plantStardustCrop` - 种植作物
- `harvestStardustCrop` - 收获作物
- `craftStardustPlanet` - 合成行星
- `purchaseStardustLand` - 购买土地

### 好友互动
- `waterStardustLand` - 浇水
- `stealStardustFragment` - 偷碎片
- `visitStardustFarm` - 访问农场
- `getStardustInteractions` - 获取互动历史

### 成就排行
- `getStardustAchievements` - 获取成就列表
- `checkStardustAchievements` - 检查并解锁成就
- `getStardustLeaderboard` - 获取排行榜

## 🐛 故障排除

### 问题1: 数据库迁移失败
**解决方案**: 确保数据库连接正常，检查是否有权限创建表

### 问题2: 页面显示空白
**解决方案**: 检查PHP错误日志，确保所有Model文件已正确创建

### 问题3: API调用失败
**解决方案**: 检查`ajax.php`中是否正确引入Repository类，查看浏览器Console错误

### 问题4: 按钮不显示
**解决方案**: 清除浏览器缓存，检查CSS文件是否正确加载

## 📞 技术支持

如遇到问题，请检查：
1. PHP错误日志 (`storage/logs/laravel.log`)
2. 浏览器Console
3. 数据库连接状态
4. 文件权限

## 🎉 完成！

安装完成后，用户可以：
1. 点击右上角的星尘农场按钮进入游戏
2. 玩流星游戏赚取星尘
3. 种植、收获、合成行星
4. 与好友互动
5. 完成成就，冲击排行榜！

**祝你重建太阳系顺利！** 🌍🪐☀️

