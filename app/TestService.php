<?php
namespace app;

use think\app\Service;

class TestService extends Service
{
    /* public $bind = [
        
    ]; */

    public function service()
    {
        return ['test' => 'service'];
    }
}
