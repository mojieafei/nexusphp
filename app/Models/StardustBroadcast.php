<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StardustBroadcast extends Model
{
    protected $table = 'stardust_broadcasts';
    
    protected $fillable = [
        'user_id',
        'username',
        'type',
        'message',
        'data',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * 创建广播
     */
    public static function createBroadcast(int $userId, string $username, string $type, string $message, ?array $data = null): void
    {
        // 只记录重要事件
        $importantTypes = ['planet', 'achievement', 'milestone'];
        
        if (in_array($type, $importantTypes) || ($type === 'fragment' && isset($data['rarity']) && $data['rarity'] === 'rare')) {
            self::create([
                'user_id' => $userId,
                'username' => $username,
                'type' => $type,
                'message' => $message,
                'data' => $data ? json_encode($data) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            
            // 只保留最近1000条广播
            $count = self::count();
            if ($count > 1000) {
                $deleteCount = $count - 1000;
                self::orderBy('id')->limit($deleteCount)->delete();
            }
        }
    }

    /**
     * 获取最近的广播
     */
    public static function getRecent(int $limit = 50): array
    {
        return self::orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取特定类型的广播
     */
    public static function getByType(string $type, int $limit = 20): array
    {
        return self::where('type', $type)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}

