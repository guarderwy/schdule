<?php

namespace app\api\service;

use app\api\model\Staff;
use app\api\model\Schedule;
use app\api\model\StaffRequest;


class ScheduleService
{
    // 夜班规律：P，N，休，休(prn)，A1或A2，助夜，P，N...
    protected $nightFlow = [
        'P' => ['N'],
        'N' => ['休'],
        '休' => ['休(prn)'],
        '休(prn)' => ['A1', 'A2'],
        'A1' => ['助夜', 'P'],
        'A2' => ['助夜', 'P'],
        '助夜' => ['P'],
    ];

    public function generateWeek($weekNo)
    {
        $result = [];
        $staff = Staff::where('status', 1)->select()->toArray();
        $weekDates = $this->getWeekDates($weekNo);
        $weekStart = $weekDates[0]; // 周一日期
        $weekEnd = $weekDates[6];   // 周日日期

        foreach ($weekDates as $index => $date) {
            $dayOfWeek = date('w', strtotime($date)); // 0=周日, 1=周一, ..., 6=周六
            $isWeekday = $dayOfWeek >= 1 && $dayOfWeek <= 5; // 周一到周五
            
            $slots = $this->buildSlots($date, $isWeekday);
            $this->assignShifts($slots, $date, $staff, $weekNo, $isWeekday, $index, $weekDates);
            $result[$date] = $slots;
        }
        return $result;
    }

    protected function assignShifts(&$slots, $date, $staff, $weekNo, $isWeekday, $dayIndex, $weekDates)
    {
        // 获取前一天的班次信息（用于夜班规律参考）
        $prevDate = null;
        if ($dayIndex > 0) {
            $prevDate = $weekDates[$dayIndex - 1];
        } else {
            // 如果是周一，获取上周日的数据
            $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
        }

        $lastMap = [];
        if ($prevDate) {
            $lastMap = Schedule::where('work_date', $prevDate)
                ->column('shift_code', 'staff_id');
        }

        // 分配夜班（N班）
        $this->assignNightShift($slots, $date, $staff, $lastMap);
        
        // 分配助夜班（周一到周五）
        if ($isWeekday) {
            $this->assignAssistantNightShift($slots, $date, $staff);
        }

        // 分配医嘱班和服药班
        if ($isWeekday) {
            // 周一到周五：医嘱班 + 服药班
            $this->assignMorningShifts($slots, $date, $staff);
        } else {
            // 周六到周日：医+服班
            $this->assignWeekendShift($slots, $date, $staff);
        }

        // 分配正1+正2班（每天都有）
        $this->assignRegularShifts($slots, $date, $staff);

        // 分配正(中)班（周一到周五）
        if ($isWeekday) {
            $this->assignMiddayShift($slots, $date, $staff);
        }

        // 分配A1和A2班（每天都有）
        $this->assignAShifts($slots, $date, $staff);
    }

    protected function assignNightShift(&$slots, $date, $staff, $lastMap)
    {
        $candidates = [];
        $restStaffIds = StaffRequest::restStaffIds($date);
        $shiftRequests = $this->getShiftRequestsForDate($date);
        $forbidden = $restStaffIds;

        foreach ($staff as $s) {
            if (!$s['enable_night'] || in_array($s['id'], $forbidden)) continue;
            
            // 检查是否有班次指定申请
            if (isset($shiftRequests[$s['id']]) && $shiftRequests[$s['id']] !== 'N') {
                continue; // 如果有人申请了其他班次，则不能安排N班
            }

            $lastShift = $lastMap[$s['id']] ?? null;
            if ($lastShift && isset($this->nightFlow[$lastShift])) {
                if (in_array('N', $this->nightFlow[$lastShift])) {
                    $candidates[] = $s;
                }
            } else {
                // 如果没有前一日班次或不匹配规律，作为备选
                if ($lastShift !== 'N') { // 避免连续夜班
                    $candidates[] = $s;
                }
            }
        }

        // 若无合规人，放宽到所有可上夜班且无冲突的人员（兜底）
        if (!$candidates) {
            $candidates = array_filter($staff, function($s) use ($forbidden, $lastMap, $shiftRequests) {
                if (!$s['enable_night'] || in_array($s['id'], $forbidden)) return false;
                if (isset($shiftRequests[$s['id']]) && $shiftRequests[$s['id']] !== 'N') return false;
                
                $lastShift = $lastMap[$s['id']] ?? null;
                return $lastShift !== 'N'; // 避免连续夜班
            });
        }

        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['N'] = $pick['name'];
            $slots['N_id'] = $pick['id']; // 保存ID用于验证
        }
    }

    protected function assignAssistantNightShift(&$slots, $date, $staff)
    {
        $restStaffIds = StaffRequest::restStaffIds($date);
        $assignedStaffIds = $this->getAssignedStaffIds($slots);
        $forbidden = array_merge($restStaffIds, $assignedStaffIds);
        $candidates = array_filter($staff, function($s) use ($forbidden) {
            return !in_array($s['id'], $forbidden);
        });

        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['助夜'] = $pick['name'];
            $slots['助夜_id'] = $pick['id'];
        }
    }

    protected function assignMorningShifts(&$slots, $date, $staff)
    {
        $restStaffIds = StaffRequest::restStaffIds($date);
        $assignedStaffIds = $this->getAssignedStaffIds($slots);
        $forbidden = array_merge($restStaffIds, $assignedStaffIds);
        $candidates = array_filter($staff, function($s) use ($forbidden) {
            return !in_array($s['id'], $forbidden);
        });

        // 分配医嘱班
        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['医嘱'] = $pick['name'];
            $slots['医嘱_id'] = $pick['id'];
            $forbidden[] = $pick['id'];
            $candidates = array_filter($candidates, function($s) use ($pick) {
                return $s['id'] !== $pick['id'];
            });
        }

        // 分配服药班
        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['服药'] = $pick['name'];
            $slots['服药_id'] = $pick['id'];
        }
    }

    protected function assignWeekendShift(&$slots, $date, $staff)
    {
        $restStaffIds = StaffRequest::restStaffIds($date);
        $assignedStaffIds = $this->getAssignedStaffIds($slots);
        $forbidden = array_merge($restStaffIds, $assignedStaffIds);
        $candidates = array_filter($staff, function($s) use ($forbidden) {
            return !in_array($s['id'], $forbidden);
        });

        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['医+服'] = $pick['name'];
            $slots['医+服_id'] = $pick['id'];
        }
    }

    protected function assignRegularShifts(&$slots, $date, $staff)
    {
        $restStaffIds = StaffRequest::restStaffIds($date);
        $assignedStaffIds = $this->getAssignedStaffIds($slots);
        $forbidden = array_merge($restStaffIds, $assignedStaffIds);
        $candidates = array_filter($staff, function($s) use ($forbidden) {
            return !in_array($s['id'], $forbidden);
        });

        // 分配正1班
        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['正1'] = $pick['name'];
            $slots['正1_id'] = $pick['id'];
            $forbidden[] = $pick['id'];
            $candidates = array_filter($candidates, function($s) use ($pick) {
                return $s['id'] !== $pick['id'];
            });
        }

        // 分配正2班
        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['正2'] = $pick['name'];
            $slots['正2_id'] = $pick['id'];
        }
    }

    protected function assignMiddayShift(&$slots, $date, $staff)
    {
        $restStaffIds = StaffRequest::restStaffIds($date);
        $assignedStaffIds = $this->getAssignedStaffIds($slots);
        $forbidden = array_merge($restStaffIds, $assignedStaffIds);
        $candidates = array_filter($staff, function($s) use ($forbidden) {
            return !in_array($s['id'], $forbidden);
        });

        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['正(中)'] = $pick['name'];
            $slots['正(中)_id'] = $pick['id'];
        }
    }

    protected function assignAShifts(&$slots, $date, $staff)
    {
        $restStaffIds = StaffRequest::restStaffIds($date);
        $assignedStaffIds = $this->getAssignedStaffIds($slots);
        $forbidden = array_merge($restStaffIds, $assignedStaffIds);
        $candidates = array_filter($staff, function($s) use ($forbidden) {
            return !in_array($s['id'], $forbidden);
        });

        // 分配A1班
        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['A1'] = $pick['name'];
            $slots['A1_id'] = $pick['id'];
            $forbidden[] = $pick['id'];
            $candidates = array_filter($candidates, function($s) use ($pick) {
                return $s['id'] !== $pick['id'];
            });
        }

        // 分配A2班
        if ($candidates) {
            $pick = $candidates[array_rand($candidates)];
            $slots['A2'] = $pick['name'];
            $slots['A2_id'] = $pick['id'];
        }
    }

    protected function buildSlots($date, $isWeekday)
    {
        $slots = [
            'N' => null,      // 夜班
            'N_id' => null,   // 夜班人员ID
        ];

        if ($isWeekday) {
            $slots['助夜'] = null;
            $slots['助夜_id'] = null;
            $slots['医嘱'] = null;
            $slots['医嘱_id'] = null;
            $slots['服药'] = null;
            $slots['服药_id'] = null;
            $slots['正(中)'] = null;
            $slots['正(中)_id'] = null;
        } else {
            $slots['医+服'] = null;
            $slots['医+服_id'] = null;
        }

        $slots['正1'] = null;
        $slots['正1_id'] = null;
        $slots['正2'] = null;
        $slots['正2_id'] = null;
        $slots['A1'] = null;
        $slots['A1_id'] = null;
        $slots['A2'] = null;
        $slots['A2_id'] = null;

        return $slots;
    }

    /**
     * 获取指定周的日期
     */
    public function getWeekDates($weekNo, $year = null)
    {
        if (!$year) {
            // 根据当前日期计算年份和周数，确保获取的是正确的年份
            $now = new \DateTime();
            $currentWeekOfYear = (int)$now->format('W');
            $currentYear = (int)$now->format('Y');
            
            // 如果请求的周数小于当前周数，说明是今年的周数
            // 如果请求的周数大于等于当前周数，可能是今年或明年的周数
            // 这里使用当前年份作为基准
            $year = $currentYear;
        }

        // 使用ISO 8601周日期计算方式，周一为一周的开始
        $date = new \DateTime();
        $date->setISODate($year, $weekNo, 1); // 1表示周一是这一周的第1天

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $date->format('Y-m-d');
            $date->modify('+1 day');
        }
        return $dates;
    }

    /**
     * 获取已分配人员ID列表
     */
    protected function getAssignedStaffIds($slots)
    {
        $ids = [];
        foreach ($slots as $key => $value) {
            if (is_string($key) && strpos($key, '_id') !== false && $value) {
                $ids[] = $value;
            }
        }
        return array_filter($ids);
    }

    /**
     * 获取某日期的班次申请
     */
    protected function getShiftRequestsForDate($date)
    {
        $requests = \app\api\model\StaffRequest::where('date', $date)
            ->where('request_type', \app\api\model\StaffRequest::TYPE_SHIFT)
            ->select()->toArray();
        
        $result = [];
        foreach ($requests as $req) {
            $result[$req['staff_id']] = $req['shift_code'];
        }
        return $result;
    }

    /**
     * 刷新排班
     * 多次生成 → 评分 → 选最优
     */
    public function refresh(int $weekNo, int $times = 10): array
    {
        $bestResult = [];
        $bestScore  = PHP_INT_MIN;

        for ($i = 0; $i < $times; $i++) {
            // 生成一套排班
            $result = $this->generateWeek($weekNo);

            // 评分（越高越好）
            $score = $this->scoreSchedule($result, $weekNo);

            if ($score > $bestScore) {
                $bestScore  = $score;
                $bestResult = $result;
            }
        }

        return $bestResult;
    }

    /**
     * 排班评分函数
     * 分数越高，排班越合理
     */
    protected function scoreSchedule(array $schedule, int $weekNo): int
    {
        $score = 1000;

        /**
         * 1️⃣ 夜班均衡性
         * 同一人夜班过多直接扣分
         */
        $nightCount = [];

        foreach ($schedule as $date => $slots) {
            if (!empty($slots['N'])) {
                $nightCount[$slots['N']] = ($nightCount[$slots['N']] ?? 0) + 1;
            }
        }

        foreach ($nightCount as $count) {
            if ($count > 2) {
                $score -= ($count - 2) * 50;
            }
        }

        /**
         * 2️⃣ 连续上班天数惩罚
         * 连续 ≥6 天开始扣分
         */
        $workDays = [];

        foreach ($schedule as $date => $slots) {
            foreach ($slots as $shift => $staffName) {
                if (!is_string($shift) || !$staffName || strpos($shift, '_id') !== false) continue; // 跳过ID字段
                if ($shift === 'N') continue; // 夜班单独算

                $workDays[$staffName][] = $date;
            }
        }

        foreach ($workDays as $days) {
            sort($days);
            $continue = 1;

            for ($i = 1; $i < count($days); $i++) {
                if (strtotime($days[$i]) - strtotime($days[$i - 1]) === 86400) {
                    $continue++;
                    if ($continue >= 6) {
                        $score -= 80;
                    }
                } else {
                    $continue = 1;
                }
            }
        }

        /**
         * 3️⃣ 周末公平性（简单版）
         */
        foreach ($schedule as $date => $slots) {
            $w = date('w', strtotime($date));
            if ($w == 0 || $w == 6) {
                if (!empty($slots['N'])) {
                    $score -= 10;
                }
            }
        }

        return $score;
    }

    /**
     * 验证排班是否符合要求
     */
    public function validateSchedule($weekNo, $schedule = null)
    {
        if (!$schedule) {
            $schedule = $this->generateWeek($weekNo);
        }

        $errors = [];
        $weekDates = $this->getWeekDates($weekNo);

        foreach ($weekDates as $index => $date) {
            $dayOfWeek = date('w', strtotime($date)); // 0=周日, 1=周一, ..., 6=周六
            $isWeekday = $dayOfWeek >= 1 && $dayOfWeek <= 5; // 周一到周五
            
            $slots = $schedule[$date] ?? [];
            $requiredShifts = $this->getRequiredShiftsForDay($isWeekday, $dayOfWeek);
            $actualShifts = [];

            foreach ($slots as $shift => $staff) {
                if ($staff && is_string($shift) && strpos($shift, '_id') === false) { // 不统计ID字段
                    $actualShifts[] = $shift;
                }
            }

            // 检查必需的班次是否都有人
            foreach ($requiredShifts as $shift) {
                if (!in_array($shift, $actualShifts)) {
                    $errors[] = "日期 {$date} 缺少班次: {$shift}";
                }
            }

            // 检查是否有多余的班次
            foreach ($actualShifts as $shift) {
                if (!in_array($shift, $requiredShifts)) {
                    $errors[] = "日期 {$date} 多余班次: {$shift}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 获取某天必需的班次
     */
    protected function getRequiredShiftsForDay($isWeekday, $dayOfWeek)
    {
        $required = ['N', '正1', '正2', 'A1', 'A2']; // 每天都必须有的班次

        if ($isWeekday) {
            // 周一到周五需要的额外班次
            $required = array_merge($required, ['助夜', '医嘱', '服药', '正(中)']);
        } else {
            // 周末需要的额外班次
            $required[] = '医+服';
        }

        return $required;
    }

    /**
     * 保存排班数据到数据库
     */
    public function saveSchedule($weekNo, $schedule)
    {
        $weekDates = $this->getWeekDates($weekNo);
        $staffMap = []; // staff name to id mapping

        // 清除本周的旧排班数据
        foreach ($weekDates as $date) {
            Schedule::where('work_date', $date)->delete();
        }

        // 保存新的排班数据
        foreach ($schedule as $date => $slots) {
            if (!is_array($slots)) continue; // 确保slots是数组
            
            foreach ($slots as $shift => $staffName) {
                if ($staffName && is_string($shift) && strpos($shift, '_id') === false && !empty($slots[$shift . '_id'])) {
                    $scheduleRecord = new Schedule();
                    $scheduleRecord->staff_id = $slots[$shift . '_id'];
                    $scheduleRecord->work_date = $date;
                    $scheduleRecord->shift_code = $shift;
                    $scheduleRecord->save();
                }
            }
        }

        return true;
    }

    /**
     * 获取指定周的排班数据
     */
    public function getSchedule($weekNo)
    {
        $weekDates = $this->getWeekDates($weekNo);
        $result = [];

        $hasData = false; // 检查是否有任何排班数据
        foreach ($weekDates as $date) {
            $daySchedules = Schedule::where('work_date', $date)->select();
            $slots = [];
            foreach ($daySchedules as $schedule) {
                $slots[$schedule->shift_code] = $schedule->staff->name;
                $slots[$schedule->shift_code . '_id'] = $schedule->staff_id;
                $hasData = true; // 如果有数据，则设置为true
            }
            $result[$date] = $slots;
        }

        // 如果没有任何数据，返回空的排班结构
        if (!$hasData) {
            return $this->getEmptySchedule($weekNo);
        }

        return $result;
    }

    /**
     * 生成空的排班结构（用于没有数据时显示空表）
     */
    public function getEmptySchedule($weekNo)
    {
        $weekDates = $this->getWeekDates($weekNo);
        $result = [];

        foreach ($weekDates as $date) {
            $dayOfWeek = date('w', strtotime($date)); // 0=周日, 1=周一, ..., 6=周六
            $isWeekday = $dayOfWeek >= 1 && $dayOfWeek <= 5; // 周一到周五

            $slots = $this->buildSlots($date, $isWeekday);
            $result[$date] = $slots;
        }

        return $result;
    }

    /**
     * 获取所有护士列表
     */
    public function getStaffList()
    {
        $staff = Staff::where('status', 1)->select();
        $result = [];
        foreach ($staff as $s) {
            $result[] = [
                'id' => $s->id,
                'name' => $s->name,
                'enable_night' => $s->enable_night
            ];
        }
        return $result;
    }
}