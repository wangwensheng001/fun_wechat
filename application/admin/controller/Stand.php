<?php

namespace app\admin\controller;

use app\common\controller\Base;
use think\Request;

class Stand extends Base
{
    /**
     * 合伙人看台 
     * 【默认展示 7 天的数据统计信息】
     * 
     */
    public function investorIndex(Request $request)
    {
        $school_id = session('admin_user.school_ids');

        if (!$school_id) {
            $this->error('非法请求');
        }

        $time = $request->param('times');
        // 调取条件
        $data = conditions($time);
        $search_time = $data['search_time'];
        $res = $data['res'];
        $nums = $data['nums'];
        $temp_time = $data['temp_time'];
        
        // 调取看台统计图表数据 
        $result = $this->statisticsReport($search_time,$res,$nums,$school_id);
        $result['school_name'] = model('School')->getNameById($school_id);
        $result['time'] = implode('~',$temp_time);
        // 获取当前学校的盈利统计信息
        $profit_list = model('Orders')->getCurrentSchoolProfit($school_id,$search_time,$res,$nums);
        $result['profit'] = $profit_list;

        $this->success('这就是合伙人看台的初始页面了',['list'=>$result]);
    }


    /**
     * 老板看台 
     * 
     */
    public function BossIndex(Request $request)
    {
        $school_id = $request->param('school_id') ?? 13;
        $time = $request->param('times'); 
        // 调取条件
        $data = conditions($time);
        $search_time = $data['search_time'];
        $res = $data['res'];
        $nums = $data['nums'];
        $temp_time = $data['temp_time'];
        // 调取看台统计图表数据 
        $result = $this->statisticsReport($search_time,$res,$nums,$school_id);
        $result['school_name'] = model('School')->getNameById($school_id);
        $result['school_id'] = $school_id;
        $result['time'] = implode('~',$temp_time);
        // 获取所有学校
        $result['school_list'] = model('School')->where('level',2)->field('id,name')->select()->toArray();

        // 获取所有学校的销售数据信息
        $result['school_order'] = model('Orders')->getAllSchoolOrderStatistics($search_time);

        $this->success('这就是老板看台的初始页面了',['list'=>$result]);
    }


    /**
     * 看台统计图表数据 
     * 
     */
    public function statisticsReport($search_time,$res,$nums,$school_id)
    {
        // 获取新增用户量
        $user_new_list = model('UserNew')->getUserNewCount($search_time,$res,$nums);
        // 用户活跃量
        $user_active_list = model('UserActive')->getUserActiveCount($search_time,$res,$nums);
        // 获取当前学校的名称以及销售额相关数据
        $order_list = model('Orders')->getCurrentSchoolOrder($school_id,$search_time,$res,$nums,$nums);

        // 组装数据
        $result = [];
        $result['order_money'] = $order_list['money'];
        $result['order_count'] = $order_list['count'];
        $result['refund_money'] = $order_list['refund_money'];
        $result['new_user'] = $user_new_list;
        $result['active_user'] = $user_active_list;
        //添加订单额和红包使使用额 add by ztt 20191107
        $result['order_total_money'] =  $order_list['total_money'];//订单额
        $result['coupon_total_money'] =  $order_list['coupon_total_money'];//红包使用额

        return $result;
    }

     
}
