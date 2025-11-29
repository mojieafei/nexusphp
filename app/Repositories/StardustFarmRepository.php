<?php

namespace App\Repositories;

use App\Models\StardustFarm;
use App\Models\StardustLand;
use App\Models\StardustCrop;
use App\Models\StardustInventory;
use App\Models\StardustInteraction;
use App\Models\StardustTransactionLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StardustFarmRepository extends BaseRepository
{
    /**
     * 获取用户农场完整信息
     * @param int $userId 农场所有者ID
     * @param int|null $currentUserId 当前访问用户ID（用于判断是否可以偷取）
     */
    public function getUserFarmData(int $userId, ?int $currentUserId = null): array
    {
        $farm = StardustFarm::getOrCreateForUser($userId);
        
        // 确保土地已初始化
        if ($farm->lands()->count() === 0) {
            $farm->initializeLands();
        }

        // 获取土地信息
        $lands = $farm->lands()->orderBy('slot_index')->get();
        
        // 更新土地状态
        foreach ($lands as $land) {
            $land->updateStatus();
        }

        // 如果是访问别人的农场，需要根据当前用户调整can_be_stolen字段
        $isViewingOtherFarm = ($currentUserId !== null && $currentUserId !== $userId);
        if ($isViewingOtherFarm) {
            // 检查当前用户今天是否已经偷过这个好友
            $hasStolenToday = StardustInteraction::hasActionToday($currentUserId, $userId, 'steal');
            
            // 转换土地数组并调整can_be_stolen字段
            $landsArray = $lands->map(function($land) use ($hasStolenToday) {
                $landData = $land->toArray();
                
                // 如果当前用户今天已经偷过这个好友，或者这块地今天已经被偷过，则不能偷
                if ($hasStolenToday || StardustInteraction::hasLandBeenStolenToday($land->id)) {
                    $landData['can_be_stolen'] = false;
                }
                // 否则保持原有的can_be_stolen值
                
                return $landData;
            })->toArray();
        } else {
            // 自己的农场，直接转换
            $landsArray = $lands->toArray();
        }

        // 获取背包信息
        $fragments = StardustInventory::getUserFragments($userId);
        $planets = StardustInventory::getUserPlanets($userId);

        // 获取可用作物
        $crops = StardustCrop::getAvailableCrops($farm->level);
        
        // 获取用户等级信息
        $user = \App\Models\User::find($userId);
        $canPurchaseLand = $user && $user->class >= UC_POWER_USER;
        $nextLandPrice = $farm->getNextLandPrice();

        return [
            'farm' => $farm->toArray(),
            'lands' => $landsArray,
            'fragments' => $fragments,
            'planets' => $planets,
            'crops' => $crops,
            'can_purchase_land' => $canPurchaseLand,
            'next_land_price' => $nextLandPrice,
            'required_class' => UC_POWER_USER,
            'required_class_name' => get_user_class_name(UC_POWER_USER, false, false, true),
        ];
    }

    /**
     * 种植作物
     */
    public function plantCrop(int $userId, int $landId, int $cropId): array
    {
        $farm = StardustFarm::getOrCreateForUser($userId);
        $land = StardustLand::where('id', $landId)
            ->where('farm_id', $farm->id)
            ->first();

        if (!$land) {
            throw new \InvalidArgumentException('土地不存在');
        }

        if ($land->status !== 'empty') {
            throw new \InvalidArgumentException('土地未空闲');
        }

        $crop = StardustCrop::find($cropId);
        if (!$crop || !$crop->is_active) {
            throw new \InvalidArgumentException('作物不存在');
        }

        if ($crop->level_required > $farm->level) {
            throw new \InvalidArgumentException('等级不足');
        }

        // 扣除星尘
        if (!$farm->spendStardust($crop->seed_price, 'plant', "种植{$crop->name}")) {
            throw new \InvalidArgumentException('星尘不足');
        }

        // 种植
        $land->plant($cropId);

        return [
            'success' => true,
            'message' => "成功种植{$crop->name}",
            'land' => $land->toArray(),
            'stardust' => $farm->stardust,
        ];
    }

    /**
     * 收获作物
     */
    public function harvestCrop(int $userId, int $landId): array
    {
        $farm = StardustFarm::getOrCreateForUser($userId);
        $land = StardustLand::where('id', $landId)
            ->where('farm_id', $farm->id)
            ->first();

        if (!$land) {
            throw new \InvalidArgumentException('土地不存在');
        }

        $result = $land->harvest();
        if (!$result) {
            throw new \InvalidArgumentException('无法收获');
        }

        // 添加碎片到背包
        StardustInventory::addItem($userId, 'fragment', $result['crop_id'], $result['fragments']);

        // 增加经验
        $farm->addExperience($result['experience']);

        // 记录交易日志
        StardustTransactionLog::create([
            'user_id' => $userId,
            'type' => 'earn',
            'source' => 'harvest',
            'amount' => $result['fragments'],
            'balance_after' => $farm->stardust,
            'description' => "收获 {$result['crop_name']} 获得 {$result['fragments']} 碎片",
            'metadata' => [
                'crop_id' => $result['crop_id'],
                'crop_name' => $result['crop_name'],
                'land_id' => $landId,
            ],
        ]);

        // 广播：收获大量碎片（≥3个）
        if ($result['fragments'] >= 3) {
            $user = \App\Models\User::find($userId);
            \App\Models\StardustBroadcast::createBroadcast(
                $userId,
                $user->username,
                'fragment',
                "收获了 {$result['fragments']} 个{$result['crop_emoji']}{$result['crop_name']}碎片",
                ['crop_id' => $result['crop_id'], 'crop_name' => $result['crop_name'], 'fragments' => $result['fragments'], 'rarity' => $result['fragments'] >= 5 ? 'rare' : 'normal']
            );
        }

        return [
            'success' => true,
            'message' => "收获了 {$result['fragments']} 个{$result['crop_name']}碎片",
            'harvest' => $result,
            'farm' => [
                'level' => $farm->level,
                'experience' => $farm->experience,
                'next_level_exp' => $farm->getNextLevelExp(),
            ],
        ];
    }

    /**
     * 合成行星
     */
    public function craftPlanet(int $userId, int $cropId): array
    {
        $crop = StardustCrop::find($cropId);
        if (!$crop) {
            throw new \InvalidArgumentException('作物不存在');
        }

        if (!StardustInventory::craftPlanet($userId, $cropId)) {
            throw new \InvalidArgumentException('碎片不足（需要9个）');
        }

        // 广播：合成行星（重要事件！）
        $user = \App\Models\User::find($userId);
        \App\Models\StardustBroadcast::createBroadcast(
            $userId,
            $user->username,
            'planet',
            "成功合成了完整的{$crop->emoji}{$crop->name}！",
            ['crop_id' => $cropId, 'name' => $crop->name, 'emoji' => $crop->emoji]
        );

        return [
            'success' => true,
            'message' => "成功合成完整的{$crop->name}！",
            'planet' => [
                'crop_id' => $cropId,
                'name' => $crop->name,
                'emoji' => $crop->emoji,
            ],
        ];
    }

    /**
     * 购买土地
     */
    public function purchaseLand(int $userId): array
    {
        // 检查用户等级（需要 Veteran User 以上）
        $user = \App\Models\User::find($userId);
        if (!$user || $user->class < UC_POWER_USER) {
            $requiredClassName = get_user_class_name(UC_POWER_USER, false, false, true);
            throw new \InvalidArgumentException("购买土地需要达到 {$requiredClassName} 等级");
        }
        
        $farm = StardustFarm::getOrCreateForUser($userId);

        if (!$farm->purchaseLand()) {
            throw new \InvalidArgumentException('购买失败（星尘不足或已达上限）');
        }

        return [
            'success' => true,
            'message' => '成功购买土地',
            'land_slots' => $farm->land_slots,
            'stardust' => $farm->stardust,
        ];
    }

    /**
     * 浇水（帮助好友）
     */
    public function waterFriendLand(int $userId, int $targetUserId, int $landId): array
    {
        if ($userId === $targetUserId) {
            throw new \InvalidArgumentException('不能给自己浇水');
        }

        $targetFarm = StardustFarm::where('user_id', $targetUserId)->first();
        if (!$targetFarm) {
            throw new \InvalidArgumentException('目标农场不存在');
        }

        $land = StardustLand::where('id', $landId)
            ->where('farm_id', $targetFarm->id)
            ->first();

        if (!$land) {
            throw new \InvalidArgumentException('土地不存在');
        }

        // 尝试浇水（Land模型会检查是否已经浇过、是否超过3次等）
        if (!$land->water($userId)) {
            // 检查具体原因
            $wateredBy = $land->watered_by ?? [];
            if (in_array($userId, $wateredBy)) {
                throw new \InvalidArgumentException('你已经给这块地浇过水了');
            } elseif ($land->watered_times >= 3) {
                throw new \InvalidArgumentException('这块地已经被浇水3次了（达到上限）');
            } elseif ($land->status !== 'growing') {
                throw new \InvalidArgumentException('这块地当前不需要浇水（可能已经成熟）');
            } else {
                throw new \InvalidArgumentException('无法浇水');
            }
        }

        // 记录互动
        StardustInteraction::record(
            $userId,
            $targetUserId,
            'water',
            $landId,
            0,
            '浇水加速10%'
        );

        // 奖励浇水者一些星尘
        $myFarm = StardustFarm::getOrCreateForUser($userId);
        $myFarm->addStardust(5, 'water', '帮助好友浇水');

        return [
            'success' => true,
            'message' => '浇水成功！获得5星尘',
            'reward' => 5,
        ];
    }

    /**
     * 偷碎片
     */
    public function stealFragment(int $userId, int $targetUserId, int $landId): array
    {
        if ($userId === $targetUserId) {
            throw new \InvalidArgumentException('不能偷自己的');
        }

        if (StardustInteraction::getTodayStealCount($userId) >= 10) {
            throw new \InvalidArgumentException('今天的偷取次数已达上限（最多10次）');
        }

        $targetFarm = StardustFarm::where('user_id', $targetUserId)->first();
        if (!$targetFarm) {
            throw new \InvalidArgumentException('目标农场不存在');
        }

        $land = StardustLand::where('id', $landId)
            ->where('farm_id', $targetFarm->id)
            ->first();

        if (!$land) {
            throw new \InvalidArgumentException('土地不存在');
        }

        if (StardustInteraction::hasLandBeenStolenToday($landId)) {
            throw new \InvalidArgumentException('该土地今天已经被偷取过了');
        }

        // 检查今天是否已经偷过
        if (StardustInteraction::hasActionToday($userId, $targetUserId, 'steal')) {
            throw new \InvalidArgumentException('今天已经偷过该好友了');
        }

        $result = $land->steal($userId);
        if (!$result) {
            throw new \InvalidArgumentException('无法偷取（作物未成熟或已被偷）');
        }

        // 添加到自己背包
        StardustInventory::addItem($userId, 'fragment', $result['crop_id'], $result['fragments']);

        // 记录互动
        StardustInteraction::record(
            $userId,
            $targetUserId,
            'steal',
            $landId,
            $result['fragments'],
            "偷取了{$result['fragments']}个{$result['crop_name']}碎片"
        );

        return [
            'success' => true,
            'message' => "成功偷取 {$result['fragments']} 个{$result['crop_name']}碎片！",
            'stolen' => $result,
        ];
    }

    /**
     * 访问好友农场（获得奖励）
     */
    public function visitFriend(int $userId, int $targetUserId): array
    {
        if ($userId === $targetUserId) {
            throw new \InvalidArgumentException('不能访问自己');
        }

        // 使用数据库事务和锁来防止并发问题
        return DB::transaction(function () use ($userId, $targetUserId) {
            // 先锁定用户农场记录，防止并发修改
            $myFarm = StardustFarm::where('user_id', $userId)->lockForUpdate()->first();
            if (!$myFarm) {
                $myFarm = StardustFarm::getOrCreateForUser($userId);
            }

            // 检查今天访问次数（在事务内检查，避免并发问题）
            $todayVisits = StardustInteraction::where('from_user_id', $userId)
                ->where('action', 'visit')
                ->whereDate('created_at', today())
                ->count();
            
            if ($todayVisits >= 5) {
                throw new \InvalidArgumentException('今天访问次数已用完（最多5次）');
            }

            // 检查是否已访问过该好友
            $hasVisited = StardustInteraction::where('from_user_id', $userId)
                ->where('to_user_id', $targetUserId)
                ->where('action', 'visit')
                ->whereDate('created_at', today())
                ->exists();
            
            if ($hasVisited) {
                throw new \InvalidArgumentException('今天已访问过该好友');
            }

            // 检查今天是否已经获得过访问奖励（锁定相关记录防止并发）
            $rewardLog = StardustTransactionLog::where('user_id', $userId)
                ->where('source', 'visit')
                ->where('type', 'earn')
                ->where('amount', '>', 0)
                ->whereDate('created_at', today())
                ->lockForUpdate()
                ->first();
            
            $alreadyRewarded = $rewardLog !== null;

            // 获取目标农场数据
            $targetFarmData = $this->getUserFarmData($targetUserId);

            // 记录访问（无论是否有奖励都要记录）
            $reward = $alreadyRewarded ? 0 : 10;
            StardustInteraction::record(
                $userId,
                $targetUserId,
                'visit',
                null,
                $reward,
                '访问好友农场'
            );

            // 只有在今天还没有获得过奖励时才发放奖励
            if ($reward > 0 && !$alreadyRewarded) {
                $myFarm->addStardust($reward, 'visit', '访问好友农场');
            }

            return [
                'success' => true,
                'message' => $reward > 0 ? "访问成功！获得{$reward}星尘" : '访问成功！今日奖励已领取',
                'reward' => $reward,
                'target_farm' => $targetFarmData,
                'remaining_visits' => 5 - $todayVisits - 1,
            ];
        });
    }

    /**
     * 获取互动历史
     */
    public function getInteractionHistory(int $userId, int $limit = 20): array
    {
        $interactions = StardustInteraction::where(function ($query) use ($userId) {
            $query->where('from_user_id', $userId)
                  ->orWhere('to_user_id', $userId);
        })
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();

        return $interactions->toArray();
    }
}

