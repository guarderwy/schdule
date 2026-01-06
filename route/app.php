<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use app\api\controller\ScheduleController;
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP6!';
});

Route::get('hello/:name', 'index/hello');

// 排班系统路由
/* Route::get('schedule', [ScheduleController::class, 'index']);
Route::get('api/schedule/generate', [ScheduleController::class, 'generate']);
Route::get('api/schedule/refresh', [ScheduleController::class, 'validate']);
Route::post('api/schedule/save', [ScheduleController::class, 'save']);
Route::post('api/schedule/validate', [ScheduleController::class, 'validate']);
Route::post('api/schedule/request', [ScheduleController::class, 'request']);
Route::get('api/schedule/get', [ScheduleController::class, 'get']);
Route::get('api/schedule/staff_list', [ScheduleController::class, 'staffList']);
Route::get('api/schedule/generate_mock_data', [ScheduleController::class, 'generateMockData']); */

// Route::get('', 'index/index');