<?php

namespace app\schedule\service;

use think\facade\Db;

class NightShiftService
{
    /**
     * 推算下一天班次（基于状态机规律）
     */
    protected function nextShift($current, $weekDay, $staffIndex)
    {
        switch ($current) {
            case 'N':
                return '休';
            case '休':
                return '休(Prn)';
            case '休(Prn)':
                return ($staffIndex % 2 === 0) ? 'A1' : 'A2';
            case 'A1':
            case 'A2':
                // 周一~周五才有助夜
                return ($weekDay <= 5) ? '助夜' : 'P';
            case '助夜':
                return 'P';
            case 'P':
                return 'N';
            default:
                return 'P'; // 兜底
        }
    }

    /**
     * 生成下一周夜班逻辑
     */
    public function generateNextWeek($startDate)
    {
        $staffs = Db::name('staff')
            ->where('is_night_group', 1)
            ->order('id asc')
            ->select()
            ->toArray();

        $result = [];
        $lastSunday = date('Y-m-d', strtotime($startDate . ' -1 day'));

        foreach ($staffs as $idx => $staff) {

            // 1️⃣ 获取起始锚点（上周日的班次）
            $prevDayShift = Db::name('schedule')
                ->where([
                    'staff_id'  => $staff['id'],
                    'work_date' => $lastSunday
                ])
                ->value('shift_name') ?: 'P';

            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($startDate . " +{$i} day"));
                $weekDay = (int)date('N', strtotime($date));

                // 2️⃣ 【核心修改】：先考虑个人申请
                $reqShift = Db::name('schedule_requests')
                    ->where([
                        'staff_id'     => $staff['id'],
                        'request_date' => $date
                    ])
                    ->value('shift_name');

                if ($reqShift) {
                    // 如果有申请，当天班次等于申请班次
                    $currentDayShift = $reqShift;
                } else {
                    // 如果没申请，基于前一天的班次推算今天的规律班次
                    $currentDayShift = $this->nextShift($prevDayShift, $weekDay, $idx);
                }

                $result[] = [
                    'staff_id'   => $staff['id'],
                    'staff_name' => $staff['name'],
                    'work_date'  => $date,
                    'shift_name' => $currentDayShift,
                ];

                // 3️⃣ 状态推进：将今天的班次（无论是申请的还是推算的）作为明天的锚点
                $prevDayShift = $currentDayShift;
            }
        }

        return $result;
    }
}
