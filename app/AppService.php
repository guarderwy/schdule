<?php
declare (strict_types = 1);

namespace app;

use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    
    // public $bind = ['ttest' => 'app\TestService'];

    public function register()
    {
        $this->app->bind('ttest', \app\TestService::class);
        // 服务注册
    }

    public function boot()
    {
        // 服务启动
    }
}
