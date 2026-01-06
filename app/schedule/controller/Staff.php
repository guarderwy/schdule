<?php

namespace app\schedule\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

class Staff extends BaseController
{
    public function index()
    {
        $list = Db::name('staff')->order('is_night_group desc, id asc')->select();
        return json(['code' => 200, 'data' => $list]);
    }

    public function add()
    {
        $name = Request::param('name');
        if (!$name) return json(['code' => 400, 'msg' => '姓名不能为空']);

        $id = Db::name('staff')->insertGetId([
            'name' => $name,
            'is_night_group' => Request::param('is_night_group', 0)
        ]);
        return json(['code' => 200, 'id' => $id, 'msg' => '添加成功']);
    }

    public function toggleNightGroup()
    {
        $id = Request::param('id');
        $status = Request::param('status');
        Db::name('staff')->where('id', $id)->update(['is_night_group' => $status]);
        return json(['code' => 200, 'msg' => '设置已更新']);
    }

    /**
     * 删除人员及其排班、需求数据
     */
    public function delete($id)
    {
        if (!$id) return json(['code' => 400, 'msg' => '参数错误']);

        Db::startTrans();
        try {
            Db::name('staff')->where('id', $id)->delete();
            Db::name('schedule')->where('staff_id', $id)->delete();
            Db::name('schedule_requests')->where('staff_id', $id)->delete();
            Db::commit();
            return json(['code' => 200, 'msg' => '人员已移除']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => '删除失败']);
        }
    }
}
