<?php
namespace app\api\service;

use app\job\GameDataJob;
use think\facade\Db;
use think\facade\Queue;

class GameService
{

    /**
     * 验证地址
     */
    const VERIFY_URL = 'https://api.wwapi.vip/web-api/auth/session/v2/verifyOperatorPlayerSession?traceId=FYRYLY20';

    /**
     * 游戏地址
     */
    const SPIN_URL = 'https://api.wwapi.vip/%sv2/Spin?traceId=EEBTBG20';

    /**
     * 队列数量
     */
    const QUEUE_NUMS = 5;

    public $cachKey = '';

    /**
     * 登录验证
     */
    public function verify($data)
    {
        $body = [
            'cp' => $data['op'],
            'btt' => $data['btt'],
            'vc' => 2, //TODO::未确认参数
            'pf' => 1,  //TODO::未确认参数
            'l' => $data['l'],
            'gi' => $data['game_code'],
            'os' => $data['ops'],
            'otk' => $data['ot'],
        ];

        $result = wget(self::VERIFY_URL, http_build_query($body));
        return $result;
    }

    /**
     * 定时命令行跑数据
     */
    public function runCrontab($gameCode, $tk, $path, $nums)
    {

    }

    public function test(TestService $testService)
    {
        return $testService->index();
    }

    /**
     * 队列跑数据
     */
    public function runQueue($gameCode, $tk, $path, $querys)
    {
        $config = Db::table('game_room_base_config')->where('room_id', (int)$gameCode)->find();
        $betAmounts = explode(',', $config['bet_amount']);
        $betRates = explode(',', $config['bet_rate']);

        $body = [
            'id' => strval(microtime(true) * 10000) . strval(mt_rand(10000, 99999)),
            'cs' => $betAmounts[0], //押注档位(只需最小档位)
            'ml' => $betRates[0], //押注倍率（只需最小倍率）
            'wk' => '0_C',
            'btt' => '1',
            'atk' => $tk,
            'pf' => '1',
        ];
        $url = sprintf(self::SPIN_URL, $path);

        $lastId = Db::table('game_run_stat')->insertGetId(
            [
                'user_id' => $querys['user_id'],
                'atk' => $tk,
                'body' => json_encode($body),
                'game_code' => $gameCode,
                'nums' => $querys['nums'],
                'rtp' => $querys['rtp'],
                'c_time' => time()
            ]
        );

        $queueNums = self::QUEUE_NUMS;
        $total = $querys['nums'];
        $runPerBatch = intval($total / $queueNums); //每批数量
        $remain = $total % $queueNums;

        for ($batch = 1; $batch <= $queueNums; $batch++) {
            //最后一批数据单独处理
            $end = 0;
            if ($batch == $queueNums) {
                $end = $runPerBatch + $remain;
            }

            $data = [
                'batch' => $batch,
                'per' => $runPerBatch,
                'url' => $url,
                'body' => $body,
                'end' => $end,
                'log_id' => $lastId
            ];

            $isPushed = Queue::push(GameDataJob::class, $data, 'game_data_batch_' . $batch);
        }
        return $lastId;
    }

    public function getRunResult($id)
    {
        $log = Db::table('game_run_stat')->where('id', $id)->find();
        if (!$log) {
            throw new \Exception('记录不存在');
        }
        if ($log['status'] == 0) {
            throw new \Exception('数据还在处理中，请稍后再试');
        }

        $result = [];
        $temp = json_decode($log['result'], true);
        if (empty($temp)) throw new \Exception('结果数据异常');
        $total = array_sum(array_values($temp));
        foreach ($temp as $k => $val) {
            $t = [
                'rate' => $k,
                'nums' => $val,
                'percent' => sprintf('%.2f%%', $total == 0 ? 0 : ($val / $total * 100))
            ];
            array_push($result, $t);
        }
        $log['result'] = $result;
        $log['lose'] = $temp['0'] ?? 0;
        $log['win'] = $total - $log['lose'];
        return $log;
    }
}