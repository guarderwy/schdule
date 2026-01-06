<?php

namespace app\api\controller;

use app\api\service\GameService;
use app\BaseController;
use app\job\GameDataJob;
use Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Request;

class Index extends BaseController
{
    public function index()
    {
        return view('index');
    }

    public function test()
    {
        var_dump(111);
        var_dump(222);
        var_dump(333);
        event('UserLogin', ['user' => 1]);
        var_dump(444);
        var_dump(555);
    }

    public function testBind()
    {
        $this->app->bind('user', function () {
            return 'bind user';
        });

        dump($this->app->make('user'));
        dump(app('user'));
        dump(app('think\exception\Handle'));
        dump(app()->make('think\TestProvider'));
        dump(app()->make('think\TestProvider')->provide());

        dump(app()->make('app\TestService')->service());
        dump(app()->make('ttest')->service());
        dump($this->app->ttest->service());
    }



    /**
     * 开始跑数据
     */
    public function startRun(GameService $gameService)
    {
        try {
            $this->validate(
                $this->request->post(),
                [
                    'user_id|用户ID' => 'require|integer',
                    'game_url|游戏地址' => 'require',
                    'nums|跑数据次数' => 'require|integer|min:1|max:1000',
                    'rtp|rtp' => 'integer'
                ]
            );
        } catch (ValidateException $e) {
            return $this->jsonError($e->getMessage());
        }

        if ($this->request->isPost()) {

            try {
                $url = $this->request->post('game_url');
                $params = parse_url($url);

                $matchs = [];
                preg_match('/\/(\d+)\//', $params['path'], $matchs);
                $gameCode = $matchs[1] ?? '';
                if (!$gameCode) {
                    throw new Exception('game_code获取失败');
                }

                $querys = [];
                parse_str($params['query'], $querys);
                $querys['game_code'] = $gameCode;
                $querys['user_id'] = $this->request->post('user_id');
                $querys['nums'] = $this->request->post('nums');
                $querys['rtp'] = $this->request->post('rtp');
                $loginInfo = $gameService->verify($querys);

                if (!$loginInfo || empty($loginInfo['dt']['tk'])) {
                    throw new Exception('登录验证失败');
                }
                $tk = $loginInfo['dt']['tk'];
                $path = $loginInfo['dt']['geu'];
                $lastId = $gameService->runQueue($gameCode, $tk, $path, $querys);

                return $this->jsonSuccess(['id' => $lastId]);
            } catch (Exception $e) {
                return $this->jsonError($e->getMessage());
            }
        }
    }

    /**
     * 结果获取
     */
    public function getResult(GameService $gameService) {
        try {
            $id = $this->request->get('id', 0, 'intval');
            if (!$id) {
                throw new Exception('参数错误');
            }
            $result = $gameService->getRunResult($id);
            return $this->jsonSuccess($result);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }
}
