<?php

namespace app\rider\controller;

use think\Controller;
use think\Request;
use app\common\model\RiderInfo;


/**
 * 骑手个人中心控制器
 * @author Mike
 * date 2019/6/10
 */
class Member extends Controller
{
    /**
     * 我的资料
     * 
     */
    public function index()
    {
        $info = model('RiderInfo')->getRiderInfo($this->auth->id);
        return json_success('获取骑手信息成功',['info'=>$info]);

    }

    
    /**
     * 更换手机号【保存】
     * 
     */
    public function setRiderPhone(Request $request)
    {
        $rid = $this->auth->id;
        $phone = $request->param('phone');
        $code  = $request->param('code');
        $type  = $request->param('type');

        // 校验验证码
        $result = model('Alisms', 'service')->checkCode($phone, $type, $code);
        if (!$result) {
            return json_error(model('Alisms', 'service')->getError());
        }

        // 更新数据
        $rider = RiderInfo::get($rid);
        $rider->phone = $phone;
        $res = $rider->save();
        if (!$res) {
            return json_error('更换失败');
        }
        $rider_info = RiderInfo::get($rid);
        return json_success('更换成功',['rider_info'=>$rider_info]);
        
    }


    /**
     * 申请入驻【成为骑手】 
     * 
     */
    public function applyRider(Request $request)
    {
        $data = $request->post();
        $data['status'] = 1;
        $data['add_time'] = time();

        // 验证表单数据
        $check = $this->validate($data, 'RiderInfo');
        if ($check !== true) {
            $this->error($check,201);
        }

        // 添加数据
        $result = RiderInfo::create($data);
        if (!$result) {
            $this->error('添加失败',201);
        }
        $this->success('添加成功');
    }


    /**
     * 重新编辑申请入驻【成为骑手】 
     * 
     */
    public function edit()
    {
        $info = model('RiderInfo')->where('id',$this->auth->id)->field('id,headimgurl,name,identity_num,card_img,back_img,hand_card_img,school_id')->find();

        $this->success('获取成功',['info'=>$info]);        
    }


    /**
     * 保存编辑后的申请入驻【成为骑手】 
     * 
     */
    public function uodate(Request $request)
    {
        $data = $request->post();

        // 验证表单数据
        $check = $this->validate($data, 'RiderInfo');
        if ($check !== true) {
            $this->error($check,201);
        }
        
        // 更新数据
        $result = model('RiderInfo')->update($data);

        if (!$result) {
            $this->error('更新失败',201);
        }
        $this->success('更新成功');
    }
     
     
}
