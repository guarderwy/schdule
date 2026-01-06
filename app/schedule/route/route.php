<?php

use think\facade\Db;
use think\facade\Route;

Route::get('mockStaff', function () {
    // 模拟 6 名夜班组人员
    /* $staffData = [
        ['name' => '张三', 'is_night_group' => 1],
        ['name' => '李四', 'is_night_group' => 1],
        ['name' => '王五', 'is_night_group' => 1],
        ['name' => '赵六', 'is_night_group' => 1],
        ['name' => '孙七', 'is_night_group' => 1],
        ['name' => '周八', 'is_night_group' => 1],
    ];
    Db::name('staff')->insertAll($staffData); */
});

Route::get('index', 'Index/index');

// --- 排班核心接口 (Schedule.php) ---
Route::group('schedule', function () {
    Route::get('init', 'Schedule/init');             // 初始化获取本周和下周数据
    Route::post('generate', 'Schedule/generate');     // 生成/刷新夜班组
    Route::post('save', 'Schedule/save');             // 校验并保存
    Route::post('addRequest', 'Schedule/addRequest'); // 【新增】添加休息/班次需求
    Route::post('deleteRequest/:id', 'Schedule/deleteRequest'); 
    Route::get('getWeek', 'Schedule/getWeekDataByDate'); // 新增获取任意周接口
});

// --- 人员管理接口 (Staff.php) ---
Route::group('staff', function () {
    Route::get('index', 'Staff/index');                      // 获取人员列表
    Route::post('add', 'Staff/add');                         // 添加新人员
    Route::post('toggleNightGroup', 'Staff/toggleNightGroup'); // 切换夜班组状态
    Route::post('delete/:id', 'Staff/delete');               // 【新增】删除人员
});


// 1. 模拟 6 名夜班组人员
Route::get('schedule/mockStaff', function () {
    Db::name('staff')->delete(true);
    Db::name('schedule')->delete(true);

    $staffData = [];
    for ($i = 1; $i <= 6; $i++) {
        $staffData[] = [
            'name' => '护士' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'is_night_group' => 1
        ];
    }
    Db::name('staff')->insertAll($staffData);
    return "6位夜班护士已就位";
});

// 2. 仅模拟这 6 位护士的本周规律排班
Route::get('schedule/mockThisWeek', function () {
    $thisWeekStart = "2026-01-05";
    $staffs = Db::name('staff')->where('is_night_group', 1)->order('id asc')->select()->toArray();
    $slots = ['P', 'N', '休', '休(Prn)', 'A1', 'A2'];

    Db::startTrans();
    try {
        Db::name('schedule')->where('work_date', 'between', [$thisWeekStart, "2026-01-11"])->delete();
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("$thisWeekStart +$i day"));
            $weekDay = date('N', strtotime($date));
            foreach ($staffs as $idx => $staff) {
                $slotIdx = ($i + $idx) % 6; // 核心：每个人分配不同的槽位
                $shift = $slots[$slotIdx];
                if ($slotIdx >= 4 && $weekDay >= 6) $shift = '休';

                Db::name('schedule')->insert([
                    'staff_id' => $staff['id'],
                    'work_date' => $date,
                    'shift_name' => $shift
                ]);
            }
        }
        Db::commit();
        return "本周 6 人排班已按坑位法补全，确保了每日 P/N 不缺失。";
    } catch (\Exception $e) {
        Db::rollback();
        return $e->getMessage();
    }
});