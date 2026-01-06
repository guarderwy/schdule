<?php

namespace app\api\model;

use think\Model;

class StaffGroup extends Model
{
    protected $name = 'staff_group';
    protected $pk   = 'id';

    protected $autoWriteTimestamp = false;

    /**
     * 组内人员
     * 夜班组 / 白班组 / 中医组
     */
    public function staffs()
    {
        return $this->belongsToMany(
            Staff::class,
            'staff_group_map',
            'staff_id',
            'group_id'
        );
    }

    /**
     * 根据班组名获取人员 ID 列表
     * 用于排班过滤
     */
    public static function staffIdsByName(string $groupName): array
    {
        $group = self::where('name', $groupName)->find();
        if (!$group) {
            return [];
        }

        return $group->staffs()->column('staff.id');
    }
}
