<?php
namespace app\backend\controller;

use think\facade\Cache;
use think\facade\Console;
use think\facade\Db;

class Stat
{
    public function __construct()
    {
        error_reporting(E_ALL & ~E_NOTICE);
    }

    public function runData()
    {
        $logs = Db::table('game_run_stat')->where('status', 0)->select();
        if (!$logs) return;

        $redis = Cache::store('redis')->handler();
        foreach ($logs as $log) {
            $queueTotal = $log['queue_1'] + $log['queue_2'] + $log['queue_3'] + $log['queue_4'] + $log['queue_5'];
            if ($queueTotal < 5) continue;

            $cacheKey = sprintf('log:%s:psid:*', $log['id']);

            $result = [];
            $iterator = null;
            $batchSize = 500;
            $sus = 0;
            do {
                $hashKeys = $redis->scan($iterator, $cacheKey, $batchSize);
                if (!$hashKeys) continue;
                $sus += count($hashKeys);

                $pipeline = $redis->pipeline();
                foreach ($hashKeys as $hashKey) {
                    $pipeline->hGetAll($hashKey);
                    // $pipeline->del($hashKey);
                }
                $ret = $pipeline->exec();
                // var_dump($ret);
                $result = $this->dataFormat($result, $ret);

            } while ($iterator > 0);
            var_dump($result);

            Db::table('game_run_stat')->where('id', $log['id'])->update(['status' => 1, 'sus' => $sus, 'result' => json_encode($result)]);
        }
    }

    protected function dataFormat($result, $ret)
    {
        $fields = [
            [0, 1], [1, 2], [2, 3], [3, 4], [4, 5], [5, 10], [10, 15], [15, 20], [20, 25], [25, 30], [30, 40], [40, 50], [50, 75], [75, 100]
        ];

        if (empty($result)) {
            $result = ['0' => 0];
            foreach ($fields as $field) {
                $result[sprintf('%s~%s', $field[0], $field[1])] = 0;
            }
            $result['>100'] = 0;
        }
        
        foreach ($ret as $val) {
            $rate = $val['tbb'] == 0 ? 0 : $val['aw'] / $val['tbb'];
            if ($rate == 0) {
                $result['0'] += 1;
            } elseif ($rate >= 100) {
                $result['>100'] += 1;
            } else {
                foreach ($fields as $field) {
                    if ($rate > $field[0] && $rate <= $field[1]) {
                        $result[sprintf('%s~%s', $field[0], $field[1])] += 1;
                        break;
                    }
                }
            }
        }
        return $result;
    }

    public function testHinc()
    {
        $redis = Cache::store('redis')->handler();
        $pipeline = $redis->pipeline();
        $key = 'game:user:1001';

        $pipeline->hMSet($key, ['gold' => 1, 'aa' => 'fsdfdf']);

        $pipeline->hIncrBy($key, 'gold', 100);
        $pipeline->expire($key, 30);
        $pipeline->exec();
    }

    public function testCommand()
    {
        $out = Console::call('action', ['backend/stat/runData']);
        $result = $out->fetch();
        var_dump($result);
    }

    //O(n^2)
    public function bubbleSort($arr = [])
    {
        $len = count($arr);
        for ($i = 0; $i < $len - 1; $i++) {
            for ($j = 0; $j < $len -1 - $i; $j++) {
                if ($arr[$j] > $arr[$j + 1]) {
                    $temp = $arr[$j];
                    $arr[$j] = $arr[$j + 1];
                    $arr[$j + 1] = $temp;
                }
            }
        }
        return $arr;
    }

    /**
     * 快速排序算法实现
     * 对数组进行排序，使用分治法实现快速排序
     * 
     * @param array $arr 需要排序的数组
     * @return array 排序后的数组
     */
    public function quickSort($arr = [])
    {
        $len = count($arr);
        if ($len <= 1) {
            return $arr;
        }

        // 选择第一个元素作为基准值
        $flag = $arr[0];
        $left = $right = [];
        for ($i = 1; $i < count($arr); $i++) {
            // 将小于基准值的元素放入左数组
            if ($arr[$i] < $flag) {
                $left[] = $arr[$i];
            } else {
                // 将大于等于基准值的元素放入右数组
                $right[] = $arr[$i];
            }
        }

        // 合并左数组、基准值和右数组
        return array_merge($left, [$flag], $right);
    }

}