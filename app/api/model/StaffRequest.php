<?php

namespace app\api\model;

use think\Model;

class StaffRequest extends Model
{
    protected $name = 'staff_request';
    protected $pk   = 'id';

    protected $autoWriteTimestamp = false;

    const TYPE_REST  = '休息';
    const TYPE_SHIFT = '指定班次';

    /**
     * 关联人员
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * 当天申请休息的人员 ID 列表
     */
    public static function restStaffIds($date): array
    {
        return self::where('date', $date)
            ->where('request_type', self::TYPE_REST)
            ->column('staff_id');
    }

    /**
     * 指定班次申请（如：下周一上白班）
     */
    public static function shiftRequest($staffId, $date)
    {
        return self::where('staff_id', $staffId)
            ->where('date', $date)
            ->where('request_type', self::TYPE_SHIFT)
            ->value('shift_code');
    }
}
