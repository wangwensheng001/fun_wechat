<?php

namespace app\api\controller;

use think\Controller;
use think\Request;
use think\Db;
use app\common\controller\ApiBase;
use app\common\model\PlatformCoupon;

/**
 * 我的红包控制器
 * @autor  mike 
 * date 2019-5-31
 */
class MyCoupon extends ApiBase
{
    protected  $noNeedLogin = [];


    /**
     * 我的红包列表
     * @param $type  $type = 1，可用红包列表 否则为历史红包 
     * 
     */
    public function index(Request $request)
    {

        $uid = $this->auth->id;
        $shop_id = $request->param('shop_id');//店铺ID
        $category_id = $request->param('category_id');//品类ID

        // 条件
        $type = $request->get('type');
        $type == 1 ? $where[] = ['m.status','=',1] : $where[] = ['m.status','in','2,3'];
        $where[] = ['m.user_id','=',$uid];

        //是否可用
        $is_use = 1;
        //不可用原因
        $remark = '';
        $list = Db::name('my_coupon m')->leftJoin('platform_coupon p','m.platform_coupon_id = p.id')->where($where)
                ->field('m.id,m.phone,m.indate,m.status,p.face_value,p.threshold,p.type,p.name,p.limit_use,p.school_id,p.shop_ids')->select();


        $userInfo = model('user')->where('id',$uid)->find();


        foreach ($list as &$row) {
            $row['is_use'] = $is_use;
            $row['limit_use'] = explode(',',$row['limit_use']);
            $row['shop_ids'] = explode(',',$row['shop_ids']);
            //手机使用条件判断
            if($row['phone'] != $userInfo['phone']) {
                $row['is_use'] = 0;
                $row['remark'] = '仅限手机号'.$row['phone'].'使用';
            }elseif (in_array($shop_id,$row['shop_ids'])) {//店铺使用条件判断
                $row['is_use'] = 0;
                $row['remark'] = '仅限部分商家使用';
            }elseif (in_array($category_id,$row['limit_use'])) {//品类使用条件判断
                $row['is_use'] = 0;
                $row['remark'] = '仅限部分品类使用';
            }
            unset($row['limit_use']);
            unset($row['shop_ids']);
        }



        $this->success('获取红包列表成功',['list'=>$list]);
    }


    /**
     * 我的红包-个人中心 
     * 
     * @param $type  $type = 1，可用红包列表 否则为历史红包 
     */
    public function myCoupon(Request $request)
    {
        // 条件
        $type = $request->get('type');
        $type == 1 ? $where[] = ['m.status','=',1] : $where[] = ['m.status','in','2,3'];
        $where[] = ['m.user_id','=',$this->auth->id];

        !empty($request->get('pagesize/d')) ? $pagesize = $request->get('pagesize/d') : $pagesize = 10;
        
        $list = Db::name('my_coupon m')->join('platform_coupon p','m.platform_coupon_id = p.id')->where($where)
                ->field('m.phone,m.indate,m.status,p.face_value,p.threshold,p.type,p.name')->paginate($pagesize);

        $this->success('获取红包列表成功',['list'=>$list]);
    }


    /**
     * 我的可用红包-下单 
     * 
     */
    public function myOrderCoupon(Request $request)
    {
        $shop_id = $request->param('shop_id');//店铺ID
        $category_id = $request->param('category_id');//品类ID

        $where = [['m.user_id','=',$this->auth->id],['m.status','=',1]];

        $list = Db::name('my_coupon m')->leftJoin('platform_coupon p','m.platform_coupon_id = p.id')->where($where)
                ->field('m.id,m.phone,m.indate,m.status,p.face_value,p.threshold,p.type,p.name,p.limit_use,p.school_id,p.shop_ids')->select();

        // 当前用户的手机号
        $phone = $this->auth->phone;

        // 需进一步思考。。。。。。。
        foreach ($list as &$row) {
            $row['is_use'] = 1;
            $limit_use =  explode(',',$row['limit_use']);  // limit_use = 0 ，表示全部【不限品类】，当limit = 1,2,...n,表示限部分品类
            $shop_ids = explode(',',$row['shop_ids']); // 
            unset($row['limit_use']);
            unset($row['shop_ids']);
            // 手机使用条件判断
            if($row['phone'] != $phone) {
                $row['is_use'] = 0;
                $row['remark'] = '仅限手机号'.$row['phone'].'使用';
                continue;
            }
            // 店铺使用条件判断
            if (!in_array($shop_id,$shop_ids) && $row['type'] != 4) {
                $row['is_use'] = 0;
                $row['remark'] = '仅限部分商家使用';
                continue;
            }
            // 品类使用条件判断
            if (($limit_use != 0) && !in_array($category_id,$limit_use)) {
                $row['is_use'] = 0;
                $row['remark'] = '仅限部分品类使用';
                continue;
            }
        }

        $this->success('获取红包列表成功',['list'=>$list]);
    }


    /**
     * 领取优惠券 
     * 
     */
    public function getCoupon(Request $request)
    {
        $coupon_id = $request->get('coupon_id');
        $info = PlatformCoupon::get($coupon_id);

        $data['user_id'] = $this->auth->id;
        $data['platform_coupon_id'] = $info->id;
        $data['indate'] = date('Y.m.d',time()).'-'.date('Y.m.d',time()+3600*24*$info->other_time);
        $data['add_time'] = time();
        $data['phone'] = $this->auth->phone;

        $count = Db::name('platform_coupon')->where('id',$info->id)->value('surplus_num');
        if ($count < 1) {
            $this->error('该优惠券已被领取完了');            
        }

        // 启动事务
        Db::startTrans();
        try {
           $res_add =  Db::name('my_coupon')->insert($data);
           $res_dec =  Db::name('platform_coupon')->where('id',$info->id)->setDec('surplus_num');
            // 提交事务
            Db::commit();
            
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            
        }

        if ($res_add && $res_dec) {
            $this->success('领取成功');
        }
        $this->error('网络繁忙，领取失败');
        
        
    }


    /**
     * 展示弹框的优惠券列表信息 
     * 
     */
    public function showCoupon(Request $request)
    {
        $school_id = $request->param('school_id');
        $user_id = $this->auth->id;

        // 查询当前学校下，已发放的平台发放或自主领取的红包列表
        $list = model('PlatformCoupon')->getSchoolCouponList($school_id);

        // 这里需注意：针对首单减红包， 仅限新注册用户，如若是老用户，则不展示
        $new_buy = model('user')->getNewBuy($user_id);
        
        foreach ($list as $k => &$v) {
            // 老用户 去掉首单立减
            if ($new_buy == 2 && $v['coupon_type'] == 2) { 
                // unset($v);  // 删除数组元素后，新数组不会重新建立索引
                array_splice($list,$k,1); // 删除数组元素后，新数组会自动重新建立索引
                continue;
            }
            
            // 当红包类型为平台发放时， type =2 时， 自动领取到我的红包表中
            if ($v['type'] == 2) {
                $data['user_id'] = $user_id;
                $data['platform_coupon_id'] = $v['id'];
                $data['indate'] = date('Y.m.d',$v['start_time']).'-'.date('Y.m.d',$v['end_time']);
                $data['add_time'] = time();
                $data['phone'] = $this->auth->phone;
                Db::name('my_coupon')->insert($data);
                $v['indate'] = '有效期限至'.date('Y.m.d',$v['end_time']);
                $v['tips'] = '立即使用';
            } else {
                $v['indate'] = '领取日起'.$v['other_time'].'日有效';
                $v['tips'] = '立即领取';
            }
        }

        $this->success('获取成功',['list'=>$list]);

    }
     
     
     
     

     
     
}
