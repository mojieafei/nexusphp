<?php

namespace App\Models;

class ForumTip extends NexusModel
{
    protected $table = 'forum_tips';

    protected $fillable = [
        'from_uid',
        'to_uid',
        'post_id',
        'topic_id',
        'amount',
        'amount_after_tax',
        'message',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_after_tax' => 'decimal:2',
    ];

    /**
     * 打赏者
     */
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_uid', 'id');
    }

    /**
     * 接收者
     */
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_uid', 'id');
    }

    /**
     * 获取指定帖子的打赏列表
     */
    public static function getPostTips($postId)
    {
        return self::where('post_id', $postId)
            ->with(['fromUser:id,username,class,donor,enabled,warned'])
            ->orderBy('amount', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 获取指定主题的打赏统计
     */
    public static function getTopicTipStats($topicId)
    {
        return self::where('topic_id', $topicId)
            ->selectRaw('COUNT(*) as tip_count, SUM(amount) as total_amount, SUM(amount_after_tax) as total_received')
            ->first();
    }
}

