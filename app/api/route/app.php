<?php
use think\facade\Route;
// 排班系统路由

Route::group('schedule', function () {
    Route::get('', 'schedule/index');
    Route::get('generate', 'schedule/generate');
    Route::get('refresh', 'schedule/refresh');
    Route::post('save', 'schedule/save');
    Route::post('validate', 'schedule/validate');
    Route::post('request', 'schedule/request');
    Route::get('get', 'schedule/get');
});
