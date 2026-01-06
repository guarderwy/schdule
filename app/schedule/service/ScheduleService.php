<?php

namespace app\schedule\service;

use think\facade\Db;

class ScheduleService
{
    // 循环顺序
    protected $cycle = ['P', 'N', '休', '休(prn)', 'A1', '助夜'];

    /**
     * 生成夜班组排班
     */
    public function generateNightShift($startDate)
    {
        $staffs = Db::name('staff')->where('is_night_group', 1)->order('sort_order')->select();
        $results = [];

        foreach ($staffs as $staff) {
            // 1. 获取该员工上一条记录（参考上周最后一天）
            $lastRecord = Db::name('schedules')
                ->where('staff_id', $staff['id'])
                ->where('work_date', '<', $startDate)
                ->order('work_date', 'desc')
                ->first();

            $currentDate = $startDate;
            $lastShift = $lastRecord ? $lastRecord['shift_name'] : null;

            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime("$startDate +$i day"));
                $weekDay = date('N', strtotime($date));

                // 2. 检查是否有申请休息
                $request = Db::name('staff_requests')->where(['staff_id' => $staff['id'], 'request_date' => $date])->value('shift_name');

                if ($request) {
                    $shift = $request;
                } else {
                    $shift = $this->getNextShift($lastShift, $weekDay);
                }

                $results[] = ['staff_id' => $staff['id'], 'work_date' => $date, 'shift_name' => $shift];
                $lastShift = $shift;
            }
        }
        return $results;
    }

    private function getNextShift($lastShift, $weekDay)
    {
        if (!$lastShift) return 'P';

        $key = array_search($lastShift, $this->cycle);
        $nextKey = ($key + 1) % count($this->cycle);
        $nextShift = $this->cycle[$nextKey];

        // 规则：只有周一到周五有助夜
        if ($nextShift == '助夜' && $weekDay > 5) {
            return '休'; // 周末助夜自动转休，或者跳过
        }
        return $nextShift;
    }

    /**
     * 校验逻辑
     */
    public function validate($data)
    {
        $errors = [];
        $daily = [];
        foreach ($data as $item) {
            $daily[$item['work_date']][] = $item['shift_name'];
        }

        foreach ($daily as $date => $shifts) {
            $w = date('N', strtotime($date));
            $counts = array_count_values($shifts);

            if ($w <= 5 && !isset($counts['助夜'])) $errors[] = "$date 缺少助夜班";
            if (!isset($counts['A1']) || !isset($counts['A2'])) $errors[] = "$date 缺少A1/A2";
            // ... 补充其他规则 3, 4, 5
        }
        return $errors;
    }

}
