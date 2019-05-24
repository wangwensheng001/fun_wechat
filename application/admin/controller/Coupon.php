<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use think\Db;

/**
 * 平台红包控制器
 * @author Mike
 * date 2019/5/23
 */
class Coupon extends Controller
{
    /**
     * 红包列表
     */
    public function index(Request $request)
    {
        // 经营品类列表
        $category_list = Db::name('manage_category')->where('level',2)->field('id,name')->select();

        // 搜索条件
        $where = [];
        !empty($request->get('name/s')) ? $where[] = ['name','like',$request->get('name/s').'%'] : null;
        !empty($request->get('category/d')) ? $where[] = ['limit_use','between',implode(',',array_column($category_list,'id'))]: null;
        !empty($request->get('status/d')) ? $where[] = ['status','=',$request->get('status/d')] : null;
    
        // 优惠券列表
        $coupon_list = Db::name('platform_coupon')->field('id,batch_id,name,user_type,face_value,threshold,start_time,end_time,other_time,limit_use,num,status,type')->where($where)->paginate(10)->each(function ($item, $key) {
            // 优惠券状态
            $item['status'] = config('coupon_status')[$item['status']];
            // 用户类型
            $item['user_type'] = config('user_type')[$item['user_type']];
            // 限品类
            $item['limit_use'] = Db::name('manage_category')->where('id','in',$item['limit_use'])->value('name');
            // 有效期
            $item['type'] == 2 ? $item['indate'] = date('Y-m-d',$item['start_time']).'-'.date('Y-m-d',$item['end_time']) : $item['indate'] = '领取日起'.$item['other_time'].'天';
            
            return $item;
        });

        return json_success('ok',['category_list'=>$category_list,'coupon_list'=>$coupon_list]);

    }


    /**
     * 展示新增优惠券页面 
     * 
     */
    public function add()
    {
        // 优惠券的覆盖范围
        $school_list = $this->getShopList();

        return json_success('ok',['school_list'=>$school_list]);

    }
     

    /**
     * 保存新增优惠券
     * 
     */
    public function create(Request $request)
    {
        $data = $request->param();
        $data['add_time'] = time();
        if ($data['type'] == 2) {
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
        }

        // 验证表单数据
        $check = $this->validate($data, 'Coupon');
        if ($check !== true) {
            return json_error($check,201);
        }

        // 提交新增表单
        $result = Db::name('platform_coupon')->insert($data);
        if (!$result) {
            return json_error('添加失败',201);
        }

        return json_success('添加成功');

    }


    /**
     * 展示修改优惠券页面 
     * @param $id  优惠券表主键值
     * 
     */
    public function edit(Request $request,$id)
    {
        if (empty((int)$id) ) {
            return json_error('非法参数',201);
        }

        // 当前优惠券信息
        $coupon_info = Db::name('platform_coupon')->where('id',$id)->find();
        if ($coupon_info['type'] == 2) {
            $coupon_info['start_time'] = date('Y-m-d',$coupon_info['start_time']);
            $coupon_info['end_time'] = date('Y-m-d',$coupon_info['end_time']);
        }

        // 优惠券的覆盖范围 [学校]
        $school_list = $this->getShopList();
        // 优惠券的覆盖范围 [店铺]
        $shop_list = Db::name('shop_info')->where('school_id',$coupon_info['school_id'])->find();

        return json_success('ok',['coupon_info'=>$coupon_info,'school_list'=>$school_list,'shop_list'=>$shop_list]);

    }


    /**
     * 保存修改优惠券
     * 
     */
    public function update(Request $request)
    {
        $data = $request->param();

        if (empty((int)$data['id'])) {
            return json_error('非法参数',201);
        }
        if ($data['type'] == 2) {
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
        }
        // 验证表单数据
        $check = $this->validate($data, 'Coupon');
        if ($check !== true) {
            return json_error($check,201);
        }
        // 提交表单
        $result = Db::name('platform_coupon')->update($data);
        if ($result === false) {
            return json_error('修改失败',201);
        }

        return json_success('修改成功');

    }


    /**
     *  获取当前学校的店铺列表
     * 
     */
    public function getSchoolShop(Request $request,$id)
    {
        if (empty((int)$id)) {
            return json_error('非法参数',201);
        }
        //获取店铺列表
        $shop_list = Db::name('shop_info')->where('school_id',$id)->field('id,shop_name')->select();
        
        return json_success('ok',['shop_list'=>$shop_list]);
    }


    /**
     * 获取优惠券的覆盖区域列表【学校信息】 
     * 
     */
    public function getShopList()
    {
        // 学区列表
        $school_district_list = Db::name('school')->field('id,name')->where('level',1)->select();
        // 学校列表
        $school_list = Db::name('school')->field('id,fid,name')->where('level',2)->select();
        // 组装三维数组
        foreach ($school_district_list as $k => &$v) {
            foreach ($school_list as $ko => $vo) {
                if ($v['id'] == $vo['fid']) {
                    $v['children'][] = $vo;
                }
            }
        }

        return $school_district_list;
    }


    /**
     * 优惠券详情 
     * 
     */
    public function show(Request $request,$id)
    {
        if (empty((int)$id)) {
            return json_error('非法参数',201);
        }

        // 优惠券详情信息
        $coupon_info = Db::name('platform_coupon pc')->join('school s','pc.school_id = s.id')->where('pc.id',$id)->field('pc.*,s.name as school_name')->find();

        if (empty($coupon_info)) {
            return json_error('非法参数',202);
        }

        // 优惠券状态
        $coupon_info['status'] = config('coupon_status')[$coupon_info['status']];
        // 用户类型
        $coupon_info['user_type'] = config('coupon_status')[$coupon_info['user_type']];
        // 限品类
        $coupon_info['limit_use'] = Db::name('manage_category')->where('id','in',$coupon_info['limit_use'])->value('name');
        // 有效期
        $coupon_info['type'] == 2 ? $coupon_info['indate'] = date('Y-m-d',$coupon_info['start_time']).'-'.date('Y-m-d',$coupon_info['end_time']) : $coupon_info['indate'] = '领取日起'.$coupon_info['other_time'].'天';
        // 发放方式
        $coupon_info['type'] = config('coupon_type')[$coupon_info['type']];
        
        // 参与优惠券的店铺信息
        $shop_info = Db::name('shop_info')->where('id','in',$coupon_info['shop_ids'])->field('id,logo_img,shop_name,link_tel')->select();

        // 优惠券使用列表
        $coupon_used_list = Db::name('my_coupon mc')
                            ->join('user u','mc.user_id = u.id')   
                            ->join('platform_coupon pc','mc.platform_coupon_id = pc.id')
                            ->field('pc.id,pc.name,pc.face_value,u.nickname,u.phone,mc.indate,mc.order_num,mc.status')
                            ->select();

        return json_success('ok',['coupon_info'=>$coupon_info,'shop_info'=>$shop_info,'coupon_used_list'=>$coupon_used_list]);

    }
     
     
     
     


    
     
     

    
     
     
     
     





}
