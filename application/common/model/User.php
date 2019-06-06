<?php

namespace app\common\model;

use think\Model;

class User extends Model
{
    /**
     * 获取一条用户记录
     * @param $uid
     */
    public function getUserInfo($uid)
    {
        $res = $this->name('user')->where('id',$uid)->find();
        return $res;
    }

    /**
     * 获取用户列表
     * @param $page_no
     * @param $page_size
     * @return mixed
     */
    public function getUserList($page_no, $page_size)
    {
        $res = $this->name('user')->page($page_no,$page_size)->select();
        return $res;
    }


    /**
     * 获取用户的主键值
     * @param $openid
     */
    public function getUidByOpenId($openid)
    {
        $id = $this->where('openid',$openid)->value('id');
        return $id;
    }

}