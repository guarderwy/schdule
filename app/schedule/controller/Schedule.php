<?php

namespace app\schedule\controller;

use app\BaseController;
use app\schedule\service\NightShiftService;
use think\facade\Db;
use think\facade\Request;

class Schedule extends BaseController
{
    protected $service;

    public function __construct(NightShiftService $service)
    {
        $this->service = $service;
    }

    /**
     * 初始化数据：获取本周、下周排班及特殊需求
     */
    public function init()
    {
        $thisWeekStart = "2026-01-05";
        $nextWeekStart = "2026-01-12";

        $staffs = Db::name('staff')->select();

        // 生成日期头
        $days = [];
        $nextDays = [];
        for ($i = 0; $i < 7; $i++) {
            $thisTime = strtotime("$thisWeekStart +$i day");
            $nextTime = strtotime("$nextWeekStart +$i day");
            $days[] = [
                'label' => '周' . ['', '一', '二', '三', '四', '五', '六', '日'][date('N', $thisTime)] . ' (' . date('m-d', $thisTime) . ')',
                'date'  => date('Y-m-d', $thisTime)
            ];
            $nextDays[] = [
                'label' => '周' . ['', '一', '二', '三', '四', '五', '六', '日'][date('N', $nextTime)] . ' (' . date('m-d', $nextTime) . ')',
                'date'  => date('Y-m-d', $nextTime)
            ];
        }

        // 获取下周的特殊需求申请
        $requests = Db::name('schedule_requests')
            ->alias('r')
            ->join('staff s', 'r.staff_id = s.id')
            ->field('r.*, s.name as staff_name')
            ->where('request_date', 'between', [$nextWeekStart, date('Y-m-d', strtotime($nextWeekStart . ' +6 day'))])
            ->select();

        $data = [
            'thisWeek' => $this->getWeekData($staffs, $thisWeekStart),
            'nextWeek' => $this->getWeekData($staffs, $nextWeekStart),
            'staffs'   => $staffs,
            'days'     => $days,
            'nextDays' => $nextDays,
            'requests' => $requests
        ];

        return json(['code' => 200, 'data' => $data]);
    }

    /**
     * 添加休息/班次申请
     */
    public function addRequest()
    {
        $params = Request::param();
        Db::name('schedule_requests')->insert([
            'staff_id'     => $params['staff_id'],
            'request_date' => $params['date'],
            'shift_name'   => $params['shift'],
            'create_time'  => date('Y-m-d H:i:s')
        ]);
        return json(['code' => 200, 'msg' => '需求已记录']);
    }

    /**
     * 保存排班并校验规则
     */
    public function save()
    {
        $schedules = Request::param('schedules');

        // 1. 规则校验
        $errors = $this->validateSchedule($schedules);
        if (!empty($errors)) {
            return json(['code' => 400, 'errors' => $errors], 400);
        }

        // 2. 事务保存
        Db::startTrans();
        try {
            foreach ($schedules as $staffId => $days) {
                foreach ($days as $date => $shift) {
                    if (empty($shift)) continue;
                    Db::name('schedule')->where(['staff_id' => $staffId, 'work_date' => $date])->delete();
                    Db::name('schedule')->insert([
                        'staff_id' => $staffId,
                        'work_date' => $date,
                        'shift_name' => $shift
                    ]);
                }
            }
            Db::commit();
            return json(['code' => 200, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => '保存失败：' . $e->getMessage()]);
        }
    }

    public function generate()
    {
        $nextWeekStart = Request::param('start_date', '2026-01-12');
        $data = $this->service->generateNextWeek($nextWeekStart);
        return json(['code' => 200, 'data' => $data]);
    }

    private function getWeekData($staffs, $startDate)
    {
        $endDate = date('Y-m-d', strtotime("$startDate +6 day"));
        $res = Db::name('schedule')
            ->where('work_date', 'between', [$startDate, $endDate])
            ->select();

        $dict = [];
        foreach ($res as $row) {
            $dict[$row['staff_id']][$row['work_date']] = $row['shift_name'];
        }
        return $dict;
    }

    private function validateSchedule($schedules)
    {
        $errors = [];
        $dates = [];
        if (empty($schedules)) return $errors;

        // 重组数据按日期检查
        foreach ($schedules as $staffId => $days) {
            foreach ($days as $date => $shift) {
                $dates[$date][] = $shift;
            }
        }

        foreach ($dates as $date => $shifts) {
            $counts = array_count_values(array_filter($shifts));
            $weekDay = date('N', strtotime($date));

            if (($counts['A1'] ?? 0) < 1) $errors[] = "$date 缺少 A1 班次";
            if (($counts['A2'] ?? 0) < 1) $errors[] = "$date 缺少 A2 班次";
            if (($counts['正1'] ?? 0) < 1 || ($counts['正2'] ?? 0) < 1) $errors[] = "$date 缺少正1或正2";

            if ($weekDay <= 5) {
                if (($counts['助夜'] ?? 0) < 1) $errors[] = "$date (工作日) 缺少助夜班";
                if (($counts['医嘱'] ?? 0) < 1) $errors[] = "$date 缺少医嘱班";
                if (($counts['服药'] ?? 0) < 1) $errors[] = "$date 缺少服药班";
            } else {
                if (($counts['医+服'] ?? 0) < 1) $errors[] = "$date (周末) 缺少医+服班";
            }
        }
        return $errors;
    }
}
