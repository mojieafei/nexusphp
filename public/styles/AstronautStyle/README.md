# 🚀 天枢·宇航员主题 (Astronaut Style)

**探索浩瀚星海，驾驭无限星辰**

## 主题特色

### 🎨 视觉设计
- **深空背景**：渐变星空背景 + 动态星点效果
- **配色系统**：电光蓝、等离子紫、星际金配色
- **玻璃态UI**：现代玻璃态卡片设计
- **流星动画**：页面随机出现流星划过效果

### ✨ 核心功能
- 全站宇航员主题风格统一
- 动态星空背景效果
- 悬浮卡片交互动画
- HUD风格数据展示
- 渐变色用户等级徽章
- 优化的按钮和表单控件

### 🎯 设计理念
将PT站点打造成**星际探索飞船控制台**的视觉体验：
- 新闻模块 = 太空通讯面板
- Shoutbox = 星际通讯频道
- 统计数据 = 飞船仪表盘
- 种子列表 = 星际货运清单

## 安装方法

### 方法一：使用Laravel Seeder（推荐）

```bash
cd /path/to/nexusWSL
php artisan db:seed --class=AstronautStyleSeeder
```

### 方法二：手动SQL插入

在数据库中执行以下SQL：

```sql
INSERT INTO `stylesheets` (`id`, `uri`, `name`, `addicode`, `designer`, `comment`) 
VALUES (8, 'styles/AstronautStyle/', '宇航员 (Astronaut)', '', 'NexusPHP Team', '探索浩瀚星海 - Space Explorer Theme');
```

## 使用方法

1. 登录账号
2. 进入 **用户设置 (User CP)**
3. 找到 **外观设置 (Style Settings)**
4. 选择 **"宇航员 (Astronaut)"** 主题
5. 保存设置

## 文件结构

```
AstronautStyle/
├── theme.css           # 主题核心样式
├── DomTT.css          # Tooltip样式
├── index-enhance.css  # 首页增强样式（可选）
└── README.md          # 说明文档
```

## 可选增强

如需更丰富的首页效果，可在主题中引入增强样式：

在 `include/functions.php` 的 `stdhead()` 函数中添加：

```php
// 在宇航员主题下加载增强样式
if ($CURUSER && $CURUSER['stylesheet'] == 8) {
    echo '<link rel="stylesheet" href="styles/AstronautStyle/index-enhance.css" />';
}
```

## 自定义调整

### 修改配色

编辑 `theme.css` 的 `:root` 部分：

```css
:root {
    --electric-blue: #00d4ff;    /* 主要强调色 */
    --plasma-purple: #a855f7;    /* 次要强调色 */
    --star-gold: #fbbf24;        /* 高亮色 */
}
```

### 调整动画速度

```css
/* 星空动画速度 */
animation: starfield 120s linear infinite;  /* 改为60s可加快速度 */

/* 流星出现频率 */
animation: shooting-star 8s ease-in-out infinite;  /* 改为5s更频繁 */
```

## 浏览器兼容性

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Edge 90+
- ✅ Safari 14+
- ⚠️ IE 11（部分效果不支持）

## 技术栈

- CSS3 (Variables, Grid, Flexbox)
- CSS Animations & Transitions
- Backdrop Filter（玻璃态效果）
- Gradient（渐变色）

## 已知问题

1. 部分旧浏览器不支持 `backdrop-filter`，会降级为纯色背景
2. 动画效果可能在低性能设备上略有卡顿

## 更新日志

### v1.0.0 (2025-10-30)
- 🎉 初始发布
- ✨ 完整的宇航员主题设计
- 🚀 动态星空背景
- 💎 玻璃态UI组件
- 🎨 首页模块优化

## 设计师

**NexusPHP Team**

## 许可证

本主题遵循 NexusPHP 项目许可证

---

**Enjoy your space journey! 🌌**

