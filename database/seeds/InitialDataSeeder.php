<?php

use think\migration\Seeder;

class InitialDataSeeder extends Seeder
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run(): void
    {
        $staffNames = ['张护士', '李护士', '王护士', '赵护士', '孙护士', '周护士'];
        foreach ($staffNames as $name) {
            \think\facade\Db::name('staff')->insert([
                'name' => $name,
                'is_night_group' => 1
            ]);
        }

        // 模拟本周排班 (2026-01-05 至 2026-01-11)
        // 假设张护士本周日是 'P'，那么下周一她应该是 'N'
        $staffs = \think\facade\Db::name('staff')->select();
        $baseCycle = ['P', 'N', '休', '休(prn)', 'A1', '助夜'];

        $insertData = [];
        foreach ($staffs as $index => $staff) {
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime("2026-01-05 +$i day"));
                // 简单错开起始班次
                $shiftIndex = ($index + $i) % count($baseCycle);
                $insertData[] = [
                    'staff_id' => $staff['id'],
                    'work_date' => $date,
                    'shift_name' => $baseCycle[$shiftIndex]
                ];
            }
        }
        \think\facade\Db::name('schedules')->insertAll($insertData);
    }
}