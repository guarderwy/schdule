<?php

namespace app\api\controller;

use app\api\service\ScheduleService;
use app\api\model\StaffRequest;
use think\Request;


class Schedule
{
    public function generate(Request $request, ScheduleService $service)
    {
        $weekNo = $request->param('week_no');
        $data = $service->generateWeek($weekNo);
        return json(['code' => 0, 'data' => $data]);
    }

    public function refresh(Request $request, ScheduleService $service)
    {
        $weekNo = $request->param('week_no');
        return json(['code' => 0, 'data' => $service->refresh($weekNo, 10)]);
    }

    /**
     * 显示排班管理界面
     */
    public function index()
    {
        return view('schedule/index');
    }

    /**
     * 保存排班数据
     */
    public function save(Request $request, ScheduleService $service)
    {
        $weekNo = $request->param('week_no');
        $schedule = $request->param('schedule/a'); // 获取数组参数
        
        // 验证排班数据
        $validation = $service->validateSchedule($weekNo, $schedule);
        if (!$validation['valid']) {
            return json(['code' => 1, 'msg' => '排班数据不符合要求', 'errors' => $validation['errors']]);
        }

        // 保存排班数据
        $result = $service->saveSchedule($weekNo, $schedule);
        
        if ($result) {
            return json(['code' => 0, 'msg' => '排班保存成功']);
        } else {
            return json(['code' => 1, 'msg' => '排班保存失败']);
        }
    }

    /**
     * 验证排班数据
     */
    public function validate(Request $request, ScheduleService $service)
    {
        $weekNo = $request->param('week_no');
        $schedule = $request->param('schedule/a'); // 获取数组参数
        
        $validation = $service->validateSchedule($weekNo, $schedule);
        
        return json([
            'code' => $validation['valid'] ? 0 : 1,
            'valid' => $validation['valid'],
            'errors' => $validation['errors']
        ]);
    }

    /**
     * 获取排班数据
     */
    public function get(Request $request, ScheduleService $service)
    {
        $weekNo = $request->param('week_no');
        $data = $service->getSchedule($weekNo);
        return json(['code' => 0, 'data' => $data]);
    }

    /**
     * 获取护士列表
     */
    public function staffList(ScheduleService $service)
    {
        $data = $service->getStaffList();
        return json(['code' => 0, 'data' => $data]);
    }

    /**
     * 获取或设置人员申请
     */
    public function request(Request $request)
    {
        $action = $request->param('action', 'get');
        
        if ($action === 'get') {
            // 获取指定日期的申请
            $date = $request->param('date');
            $requests = StaffRequest::where('date', $date)->select();
            return json(['code' => 0, 'data' => $requests]);
        } elseif ($action === 'set') {
            // 设置申请
            $date = $request->param('date');
            $staffId = $request->param('staff_id');
            $requestType = $request->param('request_type'); // '休息' 或 '指定班次'
            $shiftCode = $request->param('shift_code');
            
            // 删除之前的申请
            StaffRequest::where('date', $date)
                ->where('staff_id', $staffId)
                ->delete();
            
            if ($requestType === '休息' || ($requestType === '指定班次' && $shiftCode)) {
                $requestModel = new StaffRequest();
                $requestModel->staff_id = $staffId;
                $requestModel->date = $date;
                $requestModel->request_type = $requestType;
                if ($requestType === '指定班次') {
                    $requestModel->shift_code = $shiftCode;
                }
                $requestModel->save();
            }
            
            return json(['code' => 0, 'msg' => '申请设置成功']);
        }
    }
}