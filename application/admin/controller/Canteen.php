<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\common\controller\Base;
use think\Db;

class Canteen extends Base
{
    /**
     * 添加食堂【编辑学校时，有用到】
     * 
     */
    public function insert(Request $request)
    {
        $data = $request->param();

        // 验证表单数据
        $check = $this->validate($data, 'Canteen');
        if ($check !== true) {
            $this->error($check,201);
        }
        // 添加数据库
        $res = Db::name('canteen')->insert($data);
        if (!$res) {
            $this->error('添加失败');
        }

        $this->success('添加成功');

    }


    /**
     * 删除食堂【编辑学校时，有用到】
     *
     * @param  int  $id
     */
    public function delete($id)
    {
        $result = Db::name('canteen')->delete($id);
        if (!$result) {
            $this->error('删除失败');
        }

        $this->success('删除成功');
    }
}
