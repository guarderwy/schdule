<?php

use think\facade\Route;

Route::get('captcha/[:length]', 'index/captcha')->pattern(['length' => '\d+'])->append(['length' => 4]);
