<?php

namespace app\api\model;

use think\Model;

class Schedule extends Model
{
    protected $name = 'schedule';
    protected $pk   = 'id';

    protected $autoWriteTimestamp = false;

    /**
     * 关联人员
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * 获取某人某天的班次
     */
    public static function getShift($staffId, $date)
    {
        return self::where('staff_id', $staffId)
            ->where('work_date', $date)
            ->value('shift_code');
    }

    /**
     * 获取某天所有人的排班（staff_id => shift）
     */
    public static function dayMap($date): array
    {
        return self::where('work_date', $date)
            ->column('shift_code', 'staff_id');
    }
}
