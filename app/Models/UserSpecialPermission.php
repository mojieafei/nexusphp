<?php

namespace App\Models;

class UserSpecialPermission extends NexusModel
{
    protected $table = 'user_special_permissions';
    
    public $timestamps = true;
    
    protected $fillable = [
        'user_id',
        'special_permission_id',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联特殊权限
     */
    public function specialPermission()
    {
        return $this->belongsTo(SpecialPermission::class, 'special_permission_id');
    }
}

