<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\api\service\ScheduleService;
use app\api\model\Staff;
use app\api\model\Schedule;

class Action extends Command
{
    protected function configure()
    {
        $this->setName("action")
            ->addArgument("route", Argument::OPTIONAL, "your run route path!") //路由地址必须输入
            ->addOption('option', 'o', Option::VALUE_REQUIRED, 'set Controller Argument')
            ->setDescription("Command run Controller Action !");
    }

    protected function execute(Input $input, Output $output)
    {
        $Argument = $input->getArguments();
        if ($Argument['command'] == 'action') {
            if ($input->hasOption('option')) {
                $params = $this->option($input->getOption('option'));
                $class_fun = $this->route($Argument['route']);
                $fun = $class_fun['fun'];
                $result = app("{$class_fun['class']}")->$fun($params);
                // $output->writeln($result);
            } else {
                $class_fun = $this->route($Argument['route']);
                $fun = $class_fun['fun'];
                $result = app("{$class_fun['class']}")->$fun();
                // $output->writeln($result);
            }
        }
    }

    public function route($route = '')
    {
        // throw new Exception("路由是".$route);

        $class_fun['class'] = "app\\api\\controller\\index";
        $class_fun['fun'] = "index";

        if ($route) {
            $app = "app";
            $controller = "controller";
            $route = explode("/", $route);
            $module = isset($route[0]) ? $route[0] : "index";
            $class = isset($route[1]) ? $route[1] : 'index';
            $fun = isset($route[2]) ? $route[2] : 'index';

            $class_fun['class'] = $app . "\\" . $module . "\\" . $controller . "\\" . $class;
            $class_fun['fun'] = $fun;
            $class_fun['module'] = $module;
            $class_fun['app'] = $app;
            $class_fun['controller'] = $controller;
        }
        return $class_fun;
    }

    public function option($option)
    {

        /* 整理成数组 start */
        $params = array();
        $option_arr = explode(",", $option);
        foreach ($option_arr as $key => $val) {
            $temp_params = explode("=", $val);
            $params[$temp_params[0]] = $temp_params[1];
        }
        /* 整理成数组 end */
        return $params;
    }
}