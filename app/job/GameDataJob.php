<?php
namespace app\job;

use think\facade\Cache;
use think\facade\Console;
use think\facade\Db;

class GameDataJob
{
    protected $dataStore = [];

    protected $susNum = 0;

    protected $errNum = 0;

    public function fire($job, $data)
    {
        set_time_limit(0);
        
        $batch = $data['batch'];
        $per = $data['per'];
        $end = $data['end'];
        $logId = $data['log_id'];

        $start = ($batch - 1) * $per + 1;
        if ($end) {
            $end = $start + $end - 1;
        } else {
            $end = $batch * $per;
        }

        echo "正在处理任务 {$batch}，游戏局数范围：{$start} - {$end}" . PHP_EOL;

        $redis = Cache::store('redis');

        $url = $data['url'];
        $body = $data['body'];
        for ($i = $start; $i <= $end; $i++) {
            $this->runTask($url, $body, $i, $redis, $logId);
        }

        //记录到本地文件
        /* $cacheKey = sprintf('run_%s_q_%s', $logId, $batch);
        Cache::store('file')->set($cacheKey, [
            'sus' => $this->susNum,
            'err' => $this->errNum,
            'real_sus' => count($this->dataStore),
            'game_data' => $this->dataStore
        ]); */

        Db::table('game_run_stat')->where('id', $logId)->update(['queue_' . $batch => 1]);

        // 任务处理完成后，删除任务
        $job->delete();
        echo "任务 {$batch} 处理完成" . PHP_EOL;

        // $this->doDataStore();
    }

    public function failed($data)
    {
        var_dump('任务达到最大重试次数后，失败处理');
    }

    protected function runTask($url, $body, $i, $redis, $logId)
    {
        try {
            $result = wget($url, http_build_query($body));

            //接口请求错误
            if (empty($result['dt']) || !empty($result['err'])) {
                throw new \Exception('err -> ' . $result['err']['msg'] ?? '');
            }

            $this->susNum++;
            $data = $result['dt']['si'];

            //按psid累计
            $cacheKey = sprintf('log:%s:psid:%s', $logId, $data['psid']);
            $redis->hIncrByFloat($cacheKey, 'aw', $data['aw']);
            $redis->hIncrByFloat($cacheKey, 'tbb', $data['tbb']);
            $redis->expire($cacheKey, 300);

            echo sprintf('%s rate -> %s' . PHP_EOL, $i, $data['aw'] / $data['tbb']);
        } catch (\Exception $e) {
            $this->errNum++;
            echo sprintf('%s err -> %s' . PHP_EOL, $i, $e->getMessage());
        }
    }

    protected function doDataStore() {
        Console::call('action', ['backend/stat/runData']);
    }

    /**
     * 刷新atk
     */
    protected function refreshAtk() {}
}