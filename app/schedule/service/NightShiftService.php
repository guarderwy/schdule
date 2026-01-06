<?php

namespace app\schedule\service;

use think\facade\Db;

class NightShiftService
{
    /**
     * 核心规则坑位：
     * 只要这 6 个班次每天都在 6 个人中轮转，就能保证每天都有 P 和 N
     */
    protected $shiftSlots = ['P', 'N', '休', '休(Prn)', 'A1', 'A2'];

    public function generateNextWeek($startDate)
    {
        // 1. 获取 6 名夜班护士，按 ID 排序保证顺序固定
        $staffs = Db::name('staff')
            ->where('is_night_group', 1)
            ->order('id asc')
            ->select()
            ->toArray();

        $newSchedule = [];
        $totalStaff = count($staffs); // 应为 6

        // 2. 找到一个“锚点”来决定轮转进度
        // 我们以 startDate 的前一天（上周日）护士0的班次作为进度基准
        $lastSunday = date('Y-m-d', strtotime("$startDate -1 day"));
        $firstStaffLastShift = Db::name('schedule')
            ->where(['staff_id' => $staffs[0]['id'], 'work_date' => $lastSunday])
            ->value('shift_name');

        // 找到护士0上周日在循环中的位置，以此推算这周一的起始位置
        $startPos = array_search($firstStaffLastShift, $this->shiftSlots);
        if ($startPos === false) $startPos = 0;
        $mondayPos = ($startPos + 1) % 6;

        for ($i = 0; $i < 7; $i++) {
            $currentDate = date('Y-m-d', strtotime("$startDate +$i day"));
            $weekDay = date('N', strtotime($currentDate));

            // 每天的起始位置随日期递增
            $dayOffset = ($mondayPos + $i) % 6;

            foreach ($staffs as $idx => $staff) {
                // 每个人的班次 = (当天的基准位置 - 人的索引 + 6) % 6
                // 这样能保证同一天 6 个人的班次刚好填满 shiftSlots 的 6 个坑
                $currentSlotIdx = ($dayOffset + $idx) % 6;
                $shiftName = $this->shiftSlots[$currentSlotIdx];

                // 优先检查手动需求
                $request = Db::name('schedule_requests')
                    ->where(['staff_id' => $staff['id'], 'request_date' => $currentDate])
                    ->value('shift_name');

                if ($request) {
                    $shiftName = $request;
                } else {
                    // 周末白班位（A1, A2, 助夜）自动转休
                    // 注意：这会导致周末 P/N 还在，但 A1/A2 变成休，这是正常的
                    if ($currentSlotIdx >= 4 && $weekDay >= 6) {
                        $shiftName = '休';
                    }
                }

                $newSchedule[] = [
                    'staff_id'   => $staff['id'],
                    'staff_name' => $staff['name'],
                    'work_date'  => $currentDate,
                    'shift_name' => $shiftName
                ];
            }
        }
        return $newSchedule;
    }

    public function getNextShift($lastShift, $weekDay, $staffIdx)
    {
        if (!$lastShift) return 'P';

        // 1. 如果上个班次在 [P, N, 休] 之中
        $fixedIndex = array_search($lastShift, $this->fixedCycle);
        if ($fixedIndex !== false && $fixedIndex < 3) {
            return $this->fixedCycle[$fixedIndex + 1];
        }

        // 2. 如果上个班次是 休(Prn)，进入“白班1”阶段
        if ($lastShift == '休(Prn)') {
            // 根据护士编号错位选择第一个白班
            return $this->dayShiftOptions[$staffIdx % 3];
        }

        // 3. 如果上个班次已经是白班之一，进入“白班2”阶段或回到 P
        if (in_array($lastShift, $this->dayShiftOptions)) {
            // 简单逻辑：如果是周日或已经是第二天的白班，回到 P
            // 这里我们判断如果上个班次是白班，这周期的白班就结束了，回到 P
            // （如果需要严格的“两天白班”，可以通过记录状态实现，此处采用轮转触发）

            // 如果是在白班池的第一个，则跳到池子的下一个（实现两天不同班）
            $currentOptIdx = array_search($lastShift, $this->dayShiftOptions);
            $nextOptIdx = ($currentOptIdx + 1) % 3;
            $nextShift = $this->dayShiftOptions[$nextOptIdx];

            // 周末修正
            if ($weekDay >= 6 && ($nextShift == '助夜' || $nextShift == 'A1' || $nextShift == 'A2')) {
                return '休';
            }

            // 如果上上个班次也是白班（即完成了两天），则该回 P 了。
            // 简单起见，这里逻辑设为：白班后接 P
            return 'P';
        }

        return 'P';
    }
}
