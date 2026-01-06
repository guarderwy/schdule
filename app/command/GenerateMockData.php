<?php

namespace app\command;

use app\api\model\Staff;
use app\api\model\Schedule;
use app\api\service\ScheduleService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class GenerateMockData extends Command
{
    protected function configure()
    {
        $this->setName('schedule:generate-mock-data')
            ->setDescription('生成排班模拟数据');
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new ScheduleService();
        
        // 创建一些示例护士数据（如果不存在）
        $this->createSampleStaff();
        
        // 生成本周的模拟排班数据
        $weekNo = 1; // 可以根据需要修改周数
        $mockSchedule = $service->generateWeek($weekNo);
        
        // 清除当前周的数据
        $weekDates = $service->getWeekDates($weekNo);
        foreach ($weekDates as $date) {
            Schedule::where('work_date', $date)->delete();
        }
        
        // 保存到数据库
        foreach ($mockSchedule as $date => $slots) {
            foreach ($slots as $shift => $staffName) {
                if ($staffName && is_string($shift) && strpos($shift, '_id') === false && !empty($slots[$shift . '_id'])) {
                    $scheduleRecord = new Schedule();
                    $scheduleRecord->staff_id = $slots[$shift . '_id'];
                    $scheduleRecord->work_date = $date;
                    $scheduleRecord->shift_code = $shift;
                    $scheduleRecord->save();
                    
                    $output->writeln("已保存: {$date} - {$shift} - {$staffName}");
                }
            }
        }
        
        $output->writeln("模拟数据生成完成！");
    }
    
    private function createSampleStaff()
    {
        $sampleStaff = [
            ['name' => '张护士', 'status' => 1, 'enable_night' => 1],
            ['name' => '李护士', 'status' => 1, 'enable_night' => 1],
            ['name' => '王护士', 'status' => 1, 'enable_night' => 1],
            ['name' => '赵护士', 'status' => 1, 'enable_night' => 1],
            ['name' => '陈护士', 'status' => 1, 'enable_night' => 1],
            ['name' => '刘护士', 'status' => 1, 'enable_night' => 1],
            ['name' => '杨护士', 'status' => 1, 'enable_night' => 1],
            ['name' => '周护士', 'status' => 1, 'enable_night' => 1],
        ];
        
        foreach ($sampleStaff as $staffData) {
            $existing = Staff::where('name', $staffData['name'])->find();
            if (!$existing) {
                Staff::create($staffData);
            }
        }
    }
}