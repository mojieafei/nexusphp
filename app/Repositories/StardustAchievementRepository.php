<?php

namespace App\Repositories;

use App\Models\StardustInventory;
use App\Models\StardustFarm;
use App\Models\StardustInteraction;
use Nexus\Database\NexusDB;

class StardustAchievementRepository extends BaseRepository
{
    /**
     * 检查并解锁成就
     */
    public function checkAndUnlockAchievements(int $userId): array
    {
        $unlockedAchievements = [];
        
        // 获取所有成就
        $achievements = DB::table('stardust_achievements')->orderBy('sort_order')->get();
        
        foreach ($achievements as $achievement) {
            $conditions = json_decode($achievement->conditions, true);
            
            if ($this->checkConditions($userId, $achievement->type, $conditions)) {
                // 检查是否已完成
                $userAchievement = DB::table('stardust_user_achievements')
                    ->where('user_id', $userId)
                    ->where('achievement_id', $achievement->id)
                    ->first();
                
                if (!$userAchievement) {
                    // 首次完成
                    DB::table('stardust_user_achievements')->insert([
                        'user_id' => $userId,
                        'achievement_id' => $achievement->id,
                        'completed_times' => 1,
                        'first_completed_at' => now(),
                        'last_completed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // 发放奖励
                    $farm = StardustFarm::getOrCreateForUser($userId);
                    $farm->addStardust($achievement->reward_stardust, 'achievement', "完成成就：{$achievement->name}");
                    
                    $unlockedAchievements[] = [
                        'name' => $achievement->name,
                        'reward' => $achievement->reward_stardust,
                    ];
                } elseif ($achievement->is_repeatable && $userAchievement) {
                    // 可重复成就
                    DB::table('stardust_user_achievements')
                        ->where('id', $userAchievement->id)
                        ->update([
                            'completed_times' => $userAchievement->completed_times + 1,
                            'last_completed_at' => now(),
                            'updated_at' => now(),
                        ]);
                    
                    // 发放奖励
                    $farm = StardustFarm::getOrCreateForUser($userId);
                    $farm->addStardust($achievement->reward_stardust, 'achievement', "完成成就：{$achievement->name}（第{$userAchievement->completed_times}次）");
                    
                    $unlockedAchievements[] = [
                        'name' => $achievement->name,
                        'reward' => $achievement->reward_stardust,
                        'times' => $userAchievement->completed_times + 1,
                    ];
                }
            }
        }
        
        return $unlockedAchievements;
    }

    /**
     * 检查成就条件
     */
    private function checkConditions(int $userId, string $type, array $conditions): bool
    {
        switch ($type) {
            case 'collect':
                return $this->checkCollectConditions($userId, $conditions);
            case 'interaction':
                return $this->checkInteractionConditions($userId, $conditions);
            case 'special':
                return $this->checkSpecialConditions($userId, $conditions);
            default:
                return false;
        }
    }

    /**
     * 检查收集类成就
     */
    private function checkCollectConditions(int $userId, array $conditions): bool
    {
        if (isset($conditions['planets'])) {
            // 检查是否拥有所有指定行星
            $planets = StardustInventory::getUserPlanets($userId);
            foreach ($conditions['planets'] as $planetId) {
                if (!isset($planets[$planetId]) || $planets[$planetId]['quantity'] < ($conditions['each_count'] ?? 1)) {
                    return false;
                }
            }
            return true;
        }
        
        if (isset($conditions['total_fragments'])) {
            // 累计收获碎片数（通过交互记录统计）
            $totalFragments = DB::table('stardust_inventories')
                ->where('user_id', $userId)
                ->where('item_type', 'fragment')
                ->sum('quantity');
            return $totalFragments >= $conditions['total_fragments'];
        }
        
        if (isset($conditions['planet_id']) && isset($conditions['count'])) {
            // 拥有指定行星的数量
            $count = StardustInventory::getItemQuantity($userId, 'planet', $conditions['planet_id']);
            return $count >= $conditions['count'];
        }
        
        return false;
    }

    /**
     * 检查互动类成就
     */
    private function checkInteractionConditions(int $userId, array $conditions): bool
    {
        if (isset($conditions['water_times'])) {
            $count = StardustInteraction::where('from_user_id', $userId)
                ->where('action', 'water')
                ->count();
            return $count >= $conditions['water_times'];
        }
        
        if (isset($conditions['visit_unique_farms'])) {
            $count = StardustInteraction::where('from_user_id', $userId)
                ->where('action', 'visit')
                ->distinct('to_user_id')
                ->count('to_user_id');
            return $count >= $conditions['visit_unique_farms'];
        }
        
        if (isset($conditions['steal_times'])) {
            $count = StardustInteraction::where('from_user_id', $userId)
                ->where('action', 'steal')
                ->count();
            return $count >= $conditions['steal_times'];
        }
        
        return false;
    }

    /**
     * 检查特殊类成就
     */
    private function checkSpecialConditions(int $userId, array $conditions): bool
    {
        $farm = StardustFarm::where('user_id', $userId)->first();
        if (!$farm) {
            return false;
        }
        
        if (isset($conditions['first_harvest'])) {
            // 检查是否有过收获记录
            return DB::table('stardust_transaction_logs')
                ->where('user_id', $userId)
                ->where('source', 'harvest')
                ->exists();
        }
        
        if (isset($conditions['land_slots'])) {
            return $farm->land_slots >= $conditions['land_slots'];
        }
        
        if (isset($conditions['stardust'])) {
            return $farm->stardust >= $conditions['stardust'];
        }
        
        return false;
    }

    /**
     * 获取用户成就列表
     */
    public function getUserAchievements(int $userId): array
    {
        // 获取所有成就
        $achievementsSql = "SELECT * FROM stardust_achievements ORDER BY sort_order";
        $achievements = NexusDB::select($achievementsSql);
        
        // 获取用户已完成的成就
        $userAchievementsSql = "SELECT * FROM stardust_user_achievements WHERE user_id = {$userId}";
        $userAchievementsRaw = NexusDB::select($userAchievementsSql);
        
        // 转换为以achievement_id为键的数组
        $userAchievements = [];
        foreach ($userAchievementsRaw as $ua) {
            $userAchievements[$ua['achievement_id']] = $ua;
        }
        
        $result = [];
        foreach ($achievements as $achievement) {
            $userAchievement = isset($userAchievements[$achievement['id']]) ? $userAchievements[$achievement['id']] : null;
            $conditions = json_decode($achievement['conditions'], true);
            
            // 获取进度
            $progress = $this->getAchievementProgress($userId, $achievement['type'], $conditions);
            
            $result[] = [
                'id' => (int)$achievement['id'],
                'name' => $achievement['name'],
                'description' => $achievement['description'],
                'icon' => $achievement['icon'],
                'reward_stardust' => (int)$achievement['reward_stardust'],
                'is_repeatable' => (bool)$achievement['is_repeatable'],
                'is_completed' => $userAchievement ? true : false,
                'completed_times' => $userAchievement ? (int)$userAchievement['completed_times'] : 0,
                'progress' => $progress['current'],
                'target' => $progress['target'],
            ];
        }
        
        return $result;
    }

    /**
     * 获取成就进度
     */
    private function getAchievementProgress(int $userId, string $type, array $conditions): array
    {
        switch ($type) {
            case 'collect':
                // 收集类成就
                if (isset($conditions['total_fragments'])) {
                    // 累计收获碎片数
                    $sql = "SELECT COUNT(*) as count FROM stardust_transaction_logs 
                            WHERE user_id = {$userId} AND source = 'harvest'";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['count'] ?? 0),
                        'target' => $conditions['total_fragments'],
                    ];
                } elseif (isset($conditions['planets'])) {
                    // 重建太阳系：集齐所有行星
                    $sql = "SELECT COUNT(DISTINCT item_id) as count FROM stardust_inventories 
                            WHERE user_id = {$userId} AND item_type = 'planet'";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['count'] ?? 0),
                        'target' => count($conditions['planets']),
                    ];
                } elseif (isset($conditions['planet_id'])) {
                    // 特定行星收藏
                    $sql = "SELECT quantity FROM stardust_inventories 
                            WHERE user_id = {$userId} AND item_type = 'planet' AND item_id = {$conditions['planet_id']}";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['quantity'] ?? 0),
                        'target' => $conditions['count'],
                    ];
                } elseif (isset($conditions['stardust'])) {
                    // 财富自由
                    $sql = "SELECT stardust FROM stardust_farms WHERE user_id = {$userId}";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['stardust'] ?? 0),
                        'target' => $conditions['stardust'],
                    ];
                }
                break;
                
            case 'interaction':
                if (isset($conditions['water_times'])) {
                    // 浇水次数
                    $sql = "SELECT COUNT(*) as count FROM stardust_interactions 
                            WHERE from_user_id = {$userId} AND action = 'water'";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['count'] ?? 0),
                        'target' => $conditions['water_times'],
                    ];
                } elseif (isset($conditions['visit_unique_farms'])) {
                    // 访问农场数
                    $sql = "SELECT COUNT(DISTINCT to_user_id) as count FROM stardust_interactions 
                            WHERE from_user_id = {$userId} AND action = 'visit'";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['count'] ?? 0),
                        'target' => $conditions['visit_unique_farms'],
                    ];
                } elseif (isset($conditions['steal_times'])) {
                    // 偷取次数
                    $sql = "SELECT COUNT(*) as count FROM stardust_interactions 
                            WHERE from_user_id = {$userId} AND action = 'steal'";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['count'] ?? 0),
                        'target' => $conditions['steal_times'],
                    ];
                }
                break;
                
            case 'special':
                if (isset($conditions['first_harvest'])) {
                    // 首次收获
                    $sql = "SELECT COUNT(*) as count FROM stardust_transaction_logs 
                            WHERE user_id = {$userId} AND source = 'harvest'";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['count'] ?? 0) > 0 ? 1 : 0,
                        'target' => 1,
                    ];
                } elseif (isset($conditions['land_slots'])) {
                    // 土地大亨
                    $sql = "SELECT land_slots FROM stardust_farms WHERE user_id = {$userId}";
                    $result = NexusDB::selectOne($sql);
                    return [
                        'current' => (int)($result['land_slots'] ?? 3),
                        'target' => $conditions['land_slots'],
                    ];
                }
                break;
        }
        
        // 默认返回
        return [
            'current' => 0,
            'target' => 1,
        ];
    }

    /**
     * 更新排行榜
     */
    public function updateLeaderboards(): void
    {
        $today = today();
        
        // 财富榜
        $wealthData = DB::table('stardust_farms')
            ->orderBy('stardust', 'desc')
            ->limit(100)
            ->get();
        
        foreach ($wealthData as $index => $farm) {
            DB::table('stardust_leaderboards')->updateOrInsert(
                [
                    'type' => 'wealth',
                    'user_id' => $farm->user_id,
                    'period_date' => $today,
                ],
                [
                    'value' => $farm->stardust,
                    'rank' => $index + 1,
                    'updated_at' => now(),
                ]
            );
        }
        
        // 等级榜
        $levelData = DB::table('stardust_farms')
            ->orderBy('level', 'desc')
            ->orderBy('experience', 'desc')
            ->limit(100)
            ->get();
        
        foreach ($levelData as $index => $farm) {
            DB::table('stardust_leaderboards')->updateOrInsert(
                [
                    'type' => 'level',
                    'user_id' => $farm->user_id,
                    'period_date' => $today,
                ],
                [
                    'value' => $farm->level,
                    'rank' => $index + 1,
                    'updated_at' => now(),
                ]
            );
        }
        
        // 行星收藏榜
        $planetsData = DB::table('stardust_inventories')
            ->select('user_id', DB::raw('SUM(quantity) as total'))
            ->where('item_type', 'planet')
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->limit(100)
            ->get();
        
        foreach ($planetsData as $index => $data) {
            DB::table('stardust_leaderboards')->updateOrInsert(
                [
                    'type' => 'planets',
                    'user_id' => $data->user_id,
                    'period_date' => $today,
                ],
                [
                    'value' => $data->total,
                    'rank' => $index + 1,
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * 获取排行榜
     */
    public function getLeaderboard(string $type, int $limit = 50): array
    {
        // 确保limit是整数，防止SQL注入
        $limit = intval($limit);
        
        // 根据类型实时生成排行榜
        switch ($type) {
            case 'wealth':
                // 财富榜：按星尘数量排序，返回完整农场信息
                $sql = "
                    SELECT u.id, u.username, 
                           f.stardust, f.level, f.experience, f.land_slots
                    FROM stardust_farms f
                    INNER JOIN users u ON f.user_id = u.id
                    ORDER BY f.stardust DESC
                    LIMIT {$limit}
                ";
                break;
                
            case 'level':
                // 等级榜：按等级和经验排序，返回完整信息
                $sql = "
                    SELECT u.id, u.username,
                           f.level, f.experience, f.stardust, f.land_slots
                    FROM stardust_farms f
                    INNER JOIN users u ON f.user_id = u.id
                    ORDER BY f.level DESC, f.experience DESC
                    LIMIT {$limit}
                ";
                break;
                
            case 'planets':
                // 行星收藏榜：按合成的行星数量排序
                $sql = "
                    SELECT u.id, u.username, SUM(i.quantity) as total_planets
                    FROM stardust_inventories i
                    INNER JOIN users u ON i.user_id = u.id
                    WHERE i.item_type = 'planet'
                    GROUP BY i.user_id, u.id, u.username
                    ORDER BY total_planets DESC
                    LIMIT {$limit}
                ";
                break;
                
            case 'fragments':
                // 碎片收藏榜：按碎片数量排序
                $sql = "
                    SELECT u.id, u.username, SUM(i.quantity) as total_fragments
                    FROM stardust_inventories i
                    INNER JOIN users u ON i.user_id = u.id
                    WHERE i.item_type = 'fragment'
                    GROUP BY i.user_id, u.id, u.username
                    ORDER BY total_fragments DESC
                    LIMIT {$limit}
                ";
                break;
                
            default:
                return [];
        }
        
        $results = NexusDB::select($sql);
        
        // 格式化结果，手动添加排名
        $formatted = [];
        $rank = 1;
        foreach ($results ?: [] as $row) {
            $item = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'rank' => $rank++,
            ];
            
            // 根据类型添加特定字段
            switch ($type) {
                case 'wealth':
                case 'level':
                    $item['stardust'] = (int)($row['stardust'] ?? 0);
                    $item['level'] = (int)($row['level'] ?? 1);
                    $item['experience'] = (int)($row['experience'] ?? 0);
                    $item['land_slots'] = (int)($row['land_slots'] ?? 3);
                    break;
                case 'planets':
                    $item['total_planets'] = (int)($row['total_planets'] ?? 0);
                    break;
                case 'fragments':
                    $item['total_fragments'] = (int)($row['total_fragments'] ?? 0);
                    break;
            }
            
            $formatted[] = $item;
        }
        
        return $formatted;
    }
}

