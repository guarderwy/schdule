<?php

namespace app\api\model;

use think\Model;

class Staff extends Model
{
    protected $name = 'staff';
    protected $pk   = 'id';

    // 不使用时间戳
    protected $autoWriteTimestamp = false;

    /**
     * 是否可上夜班
     */
    public function canNight(): bool
    {
        return $this->enable_night == 1 && $this->status == 1;
    }

    /**
     * 班组（夜班组 / 白班组 / 中医组）
     */
    public function groups()
    {
        return $this->belongsToMany(
            StaffGroup::class,
            'staff_group_map',
            'group_id',
            'staff_id'
        );
    }
}
