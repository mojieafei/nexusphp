# 🔧 星尘农场排行榜修复说明

## ❌ 问题描述

**错误信息**：`A facade root has not been set`

**原因**：
- `StardustAchievementRepository.php` 使用了 Laravel Facade `DB::`
- 在传统 PHP 环境（如 `ajax.php`）中调用时，Laravel 的 Facade 系统未初始化
- 导致排行榜API调用失败

---

## ✅ 修复方案

### 1. 替换 Laravel Facade

**Before（使用 Facade）**：
```php
use Illuminate\Support\Facades\DB;

return DB::table('stardust_leaderboards')
    ->join('users', ...)
    ->where(...)
    ->get()
    ->toArray();
```

**After（使用原生SQL）**：
```php
use Nexus\Database\NexusDB;

$sql = "SELECT ... FROM ... WHERE ... ORDER BY ... LIMIT ?";
$results = NexusDB::select($sql, [$limit]);
return $results ?: [];
```

---

### 2. 实时排行榜生成

**优势**：
- ✅ 不依赖定时任务
- ✅ 数据实时更新
- ✅ 即使没有预生成数据也能工作
- ✅ 适合初期用户少的情况

**实现逻辑**：
```php
switch ($type) {
    case 'wealth':  // 财富榜
        // 按星尘数量排序
        SELECT u.id, u.username, f.stardust as value
        FROM stardust_farms f
        JOIN users u ON f.user_id = u.id
        ORDER BY f.stardust DESC
        
    case 'level':   // 等级榜
        // 按等级和经验排序
        ORDER BY f.level DESC, f.experience DESC
        
    case 'planets': // 行星收藏榜
        // 按合成的行星数量排序
        SELECT SUM(i.quantity) as value
        WHERE i.item_type = 'planet'
        GROUP BY i.user_id
        
    case 'fragments': // 碎片收藏榜
        // 按碎片数量排序
        WHERE i.item_type = 'fragment'
}
```

---

## 📊 排行榜类型

### 1. 💰 财富榜（wealth）
- **排序规则**：按星尘数量降序
- **数据来源**：`stardust_farms.stardust`
- **显示内容**：用户名 + 星尘数量

### 2. 🏆 等级榜（level）
- **排序规则**：按等级降序，经验值次之
- **数据来源**：`stardust_farms.level`, `stardust_farms.experience`
- **显示内容**：用户名 + 等级

### 3. 🌍 行星收藏榜（planets）
- **排序规则**：按合成的完整行星数量降序
- **数据来源**：`stardust_inventories` (item_type='planet')
- **显示内容**：用户名 + 行星数量

### 4. 💎 碎片收藏榜（fragments）
- **排序规则**：按碎片总数降序
- **数据来源**：`stardust_inventories` (item_type='fragment')
- **显示内容**：用户名 + 碎片数量

---

## 🎮 使用方式

### 前端调用
```javascript
// 获取财富榜
fetch('ajax.php', {
    method: 'POST',
    body: 'action=getStardustLeaderboard&params=' + 
          encodeURIComponent(JSON.stringify({
              type: 'wealth',
              limit: 50
          }))
})
.then(res => res.json())
.then(data => {
    if (data.ret === 0) {
        // data.data = [{id, username, value, rank}, ...]
        renderLeaderboard(data.data);
    }
});
```

### 返回数据格式
```json
{
    "ret": 0,
    "msg": "OK",
    "data": [
        {
            "id": 1,
            "username": "admin",
            "value": 1500,
            "rank": 1
        },
        {
            "id": 2,
            "username": "user2",
            "value": 1200,
            "rank": 2
        }
    ]
}
```

---

## 🚀 性能优化

### 当前实现（实时查询）
- **优点**：数据实时、无需维护
- **缺点**：每次查询都要扫描全表
- **适用场景**：用户数 < 10,000

### 未来优化（预生成排行榜）
如果用户数增加到1万以上，可以考虑：
1. 创建定时任务每小时更新 `stardust_leaderboards` 表
2. API直接从缓存表读取
3. 减少实时查询压力

**实现方式**：
```php
// 添加到 Laravel 定时任务
$schedule->call(function () {
    $repo = new StardustAchievementRepository();
    $repo->updateLeaderboards();
})->hourly();
```

---

## ✅ 修复完成

### 文件变更
- ✅ `app/Repositories/StardustAchievementRepository.php`
  - 移除 `Illuminate\Support\Facades\DB`
  - 添加 `Nexus\Database\NexusDB`
  - 重写 `getLeaderboard()` 方法

### 测试方式
1. **访问排行榜页面**：`https://dubhe.site/stardust_leaderboard.php`
2. **切换不同榜单**：财富、等级、行星、碎片
3. **验证数据**：确认显示实时数据，无报错

---

## 🎊 部署完成

**刷新排行榜页面即可看到实时数据！**

现在排行榜会：
- ✅ 实时显示最新数据
- ✅ 支持4种排行榜类型
- ✅ 不再报 Facade 错误
- ✅ 即使没有数据也能正常显示"暂无数据"

🌟✨

