# 🚀 天枢·宇航员主题 - 安装指南

## 📦 已完成的工作

✅ 创建了完整的宇航员主题文件结构
✅ 设计了星空背景和科幻配色系统
✅ 实现了玻璃态UI组件
✅ 优化了所有页面模块样式
✅ 添加了动态动画效果

## 📁 文件清单

```
public/styles/AstronautStyle/
├── theme.css              # 主题核心样式（必需）
├── DomTT.css             # Tooltip样式（必需）
├── index-enhance.css     # 首页增强样式（可选）
├── preview.html          # 主题预览页面
├── install.sql           # 数据库安装SQL
└── README.md             # 详细说明文档

database/seeders/
└── AstronautStyleSeeder.php  # Laravel Seeder文件
```

## 🔧 安装步骤

### 步骤1：安装主题到数据库

**方法A：使用SQL文件（推荐）**

1. 打开数据库管理工具（phpMyAdmin、Navicat等）
2. 选择您的NexusPHP数据库
3. 执行以下SQL：

```sql
INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme')
ON DUPLICATE KEY UPDATE 
    `uri` = 'styles/AstronautStyle/',
    `name` = '宇航员 (Astronaut)',
    `designer` = 'NexusPHP Team',
    `comment` = '探索浩瀚星海 - Space Explorer Theme';
```

或者直接导入文件：
```bash
mysql -u用户名 -p 数据库名 < public/styles/AstronautStyle/install.sql
```

**方法B：使用Laravel Seeder**

```bash
cd /path/to/nexusWSL
php artisan db:seed --class=AstronautStyleSeeder
```

### 步骤2：预览主题效果

在浏览器中打开：
```
http://你的域名/styles/AstronautStyle/preview.html
```

查看主题的各种样式效果。

### 步骤3：应用主题

1. 登录您的账号
2. 访问：`用户设置 (User CP)` 或 `usercp.php`
3. 找到 `外观设置` 部分
4. 在 `样式 (Stylesheet)` 下拉框中选择 `宇航员 (Astronaut)`
5. 点击保存

### 步骤4：（可选）全站默认主题

如果想让新用户默认使用宇航员主题，修改配置：

**方法1：修改配置文件**
编辑 `include/config.php`：
```php
$defcss = 8; // 设置为宇航员主题的ID
```

**方法2：修改数据库**
```sql
UPDATE `settings` SET `value` = '8' WHERE `name` = 'defcss';
```

## 🎨 主题特色一览

### 视觉特效
- 🌌 **动态星空背景** - 缓慢移动的星点
- ☄️ **流星动画** - 随机出现的流星划过
- 💎 **玻璃态卡片** - 现代化半透明设计
- ✨ **悬浮动画** - 鼠标悬停时的交互效果

### 配色方案
- 🔵 **电光蓝** (#00d4ff) - 主要链接和强调色
- 🟣 **等离子紫** (#a855f7) - 次要强调色
- 🟡 **星际金** (#fbbf24) - VIP和特殊标记
- 🟢 **霓虹绿** (#10b981) - 做种数据等

### 优化模块
- 📰 新闻公告
- 💬 Shoutbox聊天
- 🎮 Funbox娱乐
- 🗳️ 投票系统
- 📊 统计数据（HUD风格）
- 🏆 排行榜
- 📦 种子列表
- 👥 用户等级

## 🔍 验证安装

安装完成后，检查以下几点：

1. ✅ 数据库中 `stylesheets` 表有ID为8的记录
2. ✅ 用户设置中可以看到"宇航员 (Astronaut)"选项
3. ✅ 切换主题后页面背景变为星空效果
4. ✅ 按钮和卡片显示玻璃态效果

## 🐛 常见问题

### Q1: 主题列表中没有显示宇航员主题？
**A:** 检查数据库是否成功插入记录：
```sql
SELECT * FROM stylesheets WHERE id = 8;
```

### Q2: 切换主题后没有效果？
**A:** 清除浏览器缓存，或按 `Ctrl+F5` 强制刷新

### Q3: 背景是黑色的没有星空效果？
**A:** 浏览器可能不支持CSS动画，尝试升级浏览器

### Q4: 某些样式显示异常？
**A:** 检查 `public/styles/AstronautStyle/` 目录权限，确保Web服务器可读

### Q5: 如何关闭星空动画（提升性能）？
**A:** 编辑 `theme.css`，注释掉以下代码：
```css
/* 注释掉这段 */
/*
body::before {
    ...
    animation: starfield 120s linear infinite;
}
*/
```

## 🎯 下一步建议

### 进阶定制

1. **调整配色**：编辑 `theme.css` 的 `:root` 变量
2. **添加Logo**：替换 `.logo` 样式或使用图片
3. **自定义动画**：修改 `@keyframes` 动画参数
4. **增强效果**：引入 `index-enhance.css` 到主页

### 推荐配置

在 `include/functions.php` 的 `stdhead()` 函数中添加：

```php
// 为宇航员主题加载增强样式
if (isset($CURUSER['stylesheet']) && $CURUSER['stylesheet'] == 8) {
    echo '<link rel="stylesheet" href="styles/AstronautStyle/index-enhance.css" />';
}
```

## 📸 效果截图

访问预览页面查看完整效果：
- 本地：`http://localhost/styles/AstronautStyle/preview.html`
- 线上：`https://你的域名/styles/AstronautStyle/preview.html`

## 🔄 卸载方法

如果需要移除主题：

```sql
DELETE FROM stylesheets WHERE id = 8;
```

然后删除主题文件夹：
```bash
rm -rf public/styles/AstronautStyle
```

## 📧 技术支持

如遇到问题，请检查：
1. PHP版本 >= 7.4
2. MySQL版本 >= 5.7
3. 浏览器支持CSS3
4. Web服务器权限正确

## 🎉 享受您的太空之旅！

**天枢·宇航员主题** 现已就绪，开始您的星际探索吧！

---

**版本**: v1.0.0  
**发布日期**: 2025-10-30  
**设计**: NexusPHP Team  
**适用**: NexusPHP v1.6+

