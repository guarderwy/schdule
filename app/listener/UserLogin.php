<?php
declare (strict_types = 1);

namespace app\listener;

class UserLogin
{
    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle($event)
    {
        // sleep(5);
        var_dump($event);
        // throw new \Exception("Error Processing Request", 1);
        
    }
}
