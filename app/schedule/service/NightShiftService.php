<?php

namespace app\schedule\service;

use think\facade\Db;

class NightShiftService
{
    /**
     * 推算下一天班次（PHP 7.4 兼容）
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
                // 兜底：任何异常状态，强制回到 P
                return 'P';
        }
    }

    /**
     * 生成下一周夜班
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

            // 1️⃣ 上周日作为锚点
            $current = Db::name('schedule')
                ->where([
                    'staff_id'  => $staff['id'],
                    'work_date' => $lastSunday
                ])
                ->value('shift_name');

            if (!$current) {
                $current = 'P'; // 默认从 P 起
            }

            for ($i = 0; $i < 7; $i++) {

                $date = date('Y-m-d', strtotime($startDate . " +{$i} day"));
                $weekDay = (int)date('N', strtotime($date));

                // 2️⃣ 推进夜班状态机
                $shift = $this->nextShift($current, $weekDay, $idx);

                // 3️⃣ 覆盖个人申请
                $req = Db::name('schedule_requests')
                    ->where([
                        'staff_id'     => $staff['id'],
                        'request_date' => $date
                    ])
                    ->value('shift_name');

                if ($req) {
                    $shift = $req;
                }

                $result[] = [
                    'staff_id'   => $staff['id'],
                    'staff_name' => $staff['name'],
                    'work_date'  => $date,
                    'shift_name' => $shift,
                ];

                // 4️⃣ 状态推进
                $current = $shift;
            }
        }

        return $result;
    }
}
