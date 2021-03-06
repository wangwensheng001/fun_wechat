<?php
/**
 * Created by PhpStorm.
 * User: billy
 * Date: 2019/6/3
 * Time: 9:50 AM
 */
namespace app\api\controller;

use app\common\controller\ApiBase;
use EasyWeChat\Factory;
use think\Db;
use think\facade\Cache;
use think\Request;


class Order extends ApiBase
{
    protected $noNeedLogin = ['wxNotify','getriderinfo','getshopinfo'];

    private $order_status = [
        '1'     =>  '订单待支付',
        '2'     =>  '等待商家接单',
        '3'     =>  '商家已接单',
        '4'     =>  '商家拒绝接单',
        '5'     =>  '骑手取货中',
        '6'     =>  '骑手配送中',
        '7'     =>  '订单已送达 ',
        '8'     =>  '订单已完成',
        '9'     =>  '订单已取消',
        '10'    =>  '退款中',
        '11'    =>   '退款成功',
        '12'    =>   '退款失败',
    ];


    /**
     * 订单列表
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\modelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getList(Request $request)
    {
        $pagesize = $request->param('pagesize',20);
        $page = $request->param('page');
        $user_id = $this->auth->id;

        $data = model('orders')
                ->alias('a')
                ->leftJoin('ordersInfo b','a.id = b.orders_id')
                ->leftJoin('ShopInfo c', 'a.shop_id = c.id')
                ->field([
                    'a.user_id',
                    'a.id',
                    'a.orders_sn',
                    'a.num',
                    'FROM_UNIXTIME( a.add_time, "%Y-%m-%d %H:%i" )'=> 'add_time',
                    'a.status',
                    'a.money',
                    'a.shop_id',
                    'b.product_id',
                    'c.link_tel',
                    'c.logo_img',
                    'c.shop_name'])
                ->where('user_id',$user_id)
                ->order('add_time','DESC')
                ->page($page,$pagesize)
                ->group('a.id')
                ->select();

        //dump($data);
        //exit;
        if(empty($data)){
            $this->error('你暂时还没有订单，快去挑选吧！');
        }

        $result = [];
        foreach ($data as $key => $row) {
            $result[$key] = [
                'id' => $row['id'],
                'shop_id' => $row['shop_id'],
                'user_id' => $row['user_id'],
                'orders_sn' => $row['orders_sn'],
                'num' => $row['num'],
                'add_time' => $row['add_time'],
                'status_name' => $this->order_status[$row['status']],
                'status' => $row['status'],
                'money' => $row['money'],
                'logo_img' => $row['logo_img'],
                'shop_name' => $row['shop_name'],
                'name' => model('Product')->getNameById($row['product_id']),
                'product_id' => $row['product_id'],
                'shop_tel' => $row['link_tel'],
            ];

            if(in_array($row['status'],[5,6,7,8])) {//骑手取货、配货、已送达、已完成显示配送信息

                $rider_link_tel = Db::name('takeout')->alias('a')
                    ->leftJoin('rider_info b','a.rider_id = b.id')
                    ->where('a.order_id',$row['id'])
                    ->value('b.phone');

                $result[$key]['rider_link_tel'] = $rider_link_tel;

            }
        }

        $this->success('success',$result);
    }

    //获取商家信息
    public function getShopInfo(Request $request)
    {
        $orders_id = $request->param('orders_id');
        $shop_id = Db::name('orders')->where('id',$orders_id)->value('shop_id');

        $shop_info = Db::name('shop_info')->where('id',$shop_id)->field('id,shop_name,logo_img,link_name,link_tel')->find();

        if(!$shop_info) {
            $this->error('暂无商家信息');
        }

        $this->success('获取成功',$shop_info);

    }

    //获取骑手信息
    public function getRiderInfo(Request $request) {
        $orders_id = $request->param('orders_id');
        //$rider_id = Db::name('takeout')->where('order_id',$orders_id)->value('rider_id');

        //$rider_info = Db::name('rider_info')->where('id',$rider_id)->field('id,name,headimgurl,sex,link_tel,single_time,accomplish_time')->find();

        $rider_info = Db::name('takeout')->alias('a')
            ->join('rider_info b','a.rider_id = b.id')
            ->field('a.single_time,a.accomplish_time,a.single_time,a.accomplish_time,b.id,b.name,b.headimgurl,b.sex,b.phone')
            ->where('a.order_id',$orders_id)
            ->find();

        $rider_info['delivery_time'] = floor(($rider_info['accomplish_time'] - $rider_info['single_time']) / 60);
        $rider_info['accomplish_time'] = date('H:i',$rider_info['accomplish_time']);//送达时间



        if(!$rider_info) {
            $this->error('暂无骑手信息');
        }

        $this->success('获取成功',$rider_info);
    }

    /**
     * 订单明细
     * @param Request $request
     */
    public function getDetail(Request $request)
    {
        $orders_id = $request->param('orders_id');

        if(!$orders_id) {
            $this->error('非法传参');
        }

        $result = [];

        $data = model('Orders')->getOrderDetail($orders_id);

        if(!$data) {
            $this->error('暂无数据');
        }

        $result['detail'] = $data;

        foreach ($result['detail'] as &$row) {
            $row['attr_names'] = model('Shop')->getGoodsAttrName($row['attr_ids']);
            $row['name'] = model('Product')->getNameById($row['product_id']);
            $row['goods_img'] = model('Product')->getImgById($row['product_id']);
            $row['id'] = $row['product_id'];
            $row['old_price'] = model('Product')->getGoodsOldPrice($row['id']); 
            $result['platform_discount']['id'] = $row['platform_coupon_id'];
            $result['platform_discount']['face_value'] = (int)$row['platform_coupon_money'];
            $result['shop_discount']['id'] = $row['shop_discounts_id'];
            $result['shop_discount']['face_value'] = (int)$row['shop_discounts_money'];
//            unset($row['attr_ids']);
            //unset($row['id']);
        }

        $orders = model('Orders')->getOrderById($orders_id);

        $is_refund = 1;//是否屏蔽退款入口 1:放开 0:屏蔽
        if($orders['status'] =='7') {//已送达订单 24小时之后 屏蔽申请售后入口
            if((time() - $orders['arrive_time']) > 86400) {
                $is_refund = 0;
            }
        }

        $result['orders'] = [
            'orders_sn' => $orders['orders_sn'],
            'add_time' => date("Y-m-d H:i",$orders['add_time']),
            'pay_type' => '在现支付',
            'pint_fee' => $orders['ping_fee'],
            'box_money' => $orders['box_money'],
            'money' => $orders['money'],
            'num' => $orders['num'],
            'status' => $orders['status'],
            'status_name' => $this->order_status[$orders['status']],
            'is_refund' => $is_refund,
            'remark' => $orders['message']
        ];

        $shop_info = model('ShopInfo')->where('id',$orders['shop_id'])->field('id,shop_name,logo_img,run_type')->find();

        $result['shop_info'] = [
            'id' => $shop_info['id'],
            'shop_name' => $shop_info['shop_name'],
            'logo_img' => $shop_info['logo_img'],
        ];

        $result['ping_info']['ping_time'] = '尽快送达';
        $result['ping_info']['ping_type'] = $shop_info['run_type'];
        $result['ping_info']['address'] = $orders['address'];
        $result['ping_info']['rider_info'] = '';
        if(in_array($orders['status'],[5,6,7,8])) {//骑手取货、配货、已送达、已完成显示配送信息

            $rider_info = Db::name('takeout')->alias('a')
                ->leftJoin('rider_info b','a.rider_id = b.id')
                ->where('a.order_id',$orders_id)
                ->field('b.phone,b.link_tel')
                ->find();
            $rider_info['link_tel'] = $rider_info['phone'];
            $result['ping_info']['rider_info'] = isset($rider_info) ? $rider_info : '';//骑手信息

        }

        if(in_array($orders['status'],[3,5,6,7,8])) { //商家接单 和 骑手取货配货显示时间 送达时间
            $result['plan_arrive_time'] = date("H:i",$orders['plan_arrive_time']);
        }



        $this->success('获取成功',$result);
    }
    /**
     * 支付查询
     */
    public function orderQuery(Request $request)
    {
        $orders_sn = $request->param('orders_sn');
        $wx = new \app\api\controller\Weixin();
        $result = $wx->query($orders_sn);

        $this->success('获取成功',$result);
    }


    /**
     * 订单支付真实
     * @param Request $request
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function orderPayment(Request $request)
    {
        $orders_sn = $request->param('orders_sn');
        $openid = $this->auth->openid;
        $user_id = $this->auth->id;
        
        if(!$orders_sn){
            
            $this->error('订单号不能为空');
        }
        
        $order = model('Orders')->getOrder($orders_sn);
        
        $shop_id = $order->shop_id;
        $this->isDisable($shop_id);

        if(!$order){
            $this->error('订单id错误');
        }

        if($order->user_id != $user_id){
            $this->error('非法操作');
        }
        // 订单超时15分钟，自动失效
        if($order->status == 9){
            $this->error('订单已失效');
        }

        if($order->pay_status == 1){
            $this->error('订单已支付');
        }

        // 判断该商家是否已歇业
        $shop_info = model('ShopInfo')->where('id','=',$shop_id)->field('id,open_status as business,run_time')->find();
        if ($shop_info['business'] == 1 && !empty($shop_info['run_time'])) {
            $shop_open = model('ShopInfo')->getBusiness($shop_info['run_time']);
        } else {
            $shop_open = 0;
        }
        if (!$shop_open) {
            $this->error('该商家已休息');
        }

        $data['price'] = $order['money'];

        if($data['price'] == 0) {
            model('orders')->where('orders_sn',$orders_sn)->update(['status'=>2,'pay_status'=>1,'pay_time'=>time(),'trade_no'=>'0元付']);
            $this->success('支付成功','10000');
        }

        $config = config('wx_pay');
        $app_id = config('wx_pay')['app_id'];
        $key = config('wx_pay')['key'];
        $app = Factory::payment($config);

        $ip   = request()->ip();
        $result = $app->order->unify([
            'body' => '商品支付',
            'out_trade_no' => $orders_sn,
            'total_fee' => $data['price']*100,
            'spbill_create_ip' => $ip, // 可选，如不传该参数，SDK 将会自动获取相应 IP 地址
            'notify_url' => 'https' . "://" . $_SERVER['HTTP_HOST'].'/api/notify/index', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
            'trade_type' => 'JSAPI',
            'openid' => $openid,
        ]);
        // print_r($result);
        if($result['return_code'] == "SUCCESS" && $result['result_code']=="SUCCESS"){
            $result['openid']=$openid;
            $result['timeStamp']=strval(time());
            $result['package']="prepay_id=".$result['prepay_id'];
            $result['paySign']=MD5("appId=".$app_id."&nonceStr=".$result['nonce_str']."&package=".$result['package']."&signType=MD5&timeStamp=".$result['timeStamp']."&key=".$key);

            $this->success('success',$result);
        }else{
             $this->error('下单失败'.$result['err_code_des']);
        }

    }


    //评价
    public function addEvaluation(Request $request)
    {
        
        $param = $request->param();
        $tips_ids = $request->param('tips_ids','');
        
        $data = [
            'orders_id'=>$param['orders_id'],
            'shop_id'=>$param['shop_id'],
            'star'=>$param['star'],
            'content'=>$param['content'],
            'user_id'=>$this->auth->id,
            'add_time'=>time(),
        ];

        $data2 = [
            'orders_id'=>$param['orders_id'],
            'shop_id'=>$param['shop_id'],
            'user_id'=>$this->auth->id,
            'rider_id'=>$param['rider_id'],
            'content'=>$param['rider_content'],
            'star'=> $param['rider_star'],
            'add_time'=>time(),
        ];

        $ret = model('ShopComments')->where('orders_id',$param['orders_id'])->find();

        if ($ret){
            $this->error('该商品已评价');
        }

        //骑手评价
        $rid = model('RiderComments')->insertGetId($data2);

        //商家评价
        $id = model('ShopComments')->insertGetId($data);

        if ($tips_ids){
            $res = explode(',',$tips_ids);
            $com = [];
            foreach ($res as $v) {
                $com[] = ['comments_id'=>$id,'tips_id'=>$v];
            }

            model('ShopCommentsTips')->insertAll($com);
        }
        //改变商品状态
        model('Orders')->where('id',$param['orders_id'])->update(['status'=>8,'update_time'=>time()]);
        //获取商家评分
        $maks = model('ShopComments')->getStar($param['shop_id']);
        model('ShopInfo')->where('id',$param['shop_id'])->update(['marks'=>$maks]);

        $this->success('success');
    }

    //获取评价标签
    public function getTips()
    {
        $list = model('Tips')->select();

        $this->success('success',$list);
    }

    /**
     * 退款申请
     */
    public function  orderRefund(Request $request)
    {
        $orders_id = $request->param('orders_id');

        $orders_info_ids = $request->param('orders_info_ids');

        $content = $request->param('content');

        $imgs = $request->param('imgs');

        $data = Db::name('refund')->where('orders_id',$orders_id)->find();


        if(is_array($data)){
            $this->error('退单已提交申请,请耐心等待');
        }

        $orders = model('Orders')->getOrderById($orders_id);

        $data = [
            'orders_id' => $orders_id,
            'shop_id' => $orders['shop_id'],
            'orders_info_ids' => $orders_info_ids,
            'content' => $content,
            'imgs' => $imgs,
            'refund_fee' => $orders['money'],//退单
            'total_fee' => $orders['money'],
            'ping_fee' => $orders['ping_fee'],//配送费
            'num' => $orders['num'],
            'status' => '1',
            'add_time' => time(),
            'out_refund_no' => build_order_no('T'),
            'out_trade_no' => $orders['orders_sn'],
            'user_id' => $this->auth->id
        ];


        $res = Db::name('refund')->insert($data);

        if($res) {
            //更新一下主表订单状态为退款中
            model('Orders')->updateStatus($orders['orders_sn'],10);
            $this->success('售后申请已提交成功,等待商家处理');
        }
    }


    /**
     * 确认订单，生成订单
     * @param Request $request
     * @return bool
     */
    public function sureOrder(Request $request)
    {
        $order = $request->param('order');//主表
        $this->isDisable($order['shop_id']);
        $detail = $request->param('detail');//明细
        $platform_discount = $request->param('platform_discount');//平台活动
        $shop_discount = $request->param('shop_discount');//店铺活动
        $hongbao_status = 2;//红包已经使用

        set_log('order=',$order,'sureOrder');
        set_log('detail=',$detail,'sureOrder');
        set_log('platform_discount=',$platform_discount,'sureOrder');
        set_log('shop_discount=',$shop_discount,'sureOrder');


        $orders_sn = build_order_no('D');//生成唯一订单号
        $school_id = Db::name('shop_info')->where('id',$order['shop_id'])->value('school_id');
        $total_money = $order['total_money'];//订单总价
        $order_discount = $shop_discount['face_value'] + $platform_discount['face_value'];//订单优惠金额
        $money = $order['money'];//订单结算金额


        $total_money_cash = model('Orders')->getTotalMoney($order,$detail);//订单总价
        $order_discount_cash = model('Orders')->getDisMoney($shop_discount,$platform_discount);//订单优惠

        if(bccomp($total_money_cash, $total_money, 4) != 0) {
            $this->error('订单总价不正确');
        }

        if(bccomp($total_money_cash - $order_discount_cash, $money, 4) != 0) {
            $this->error('订单结算金额不正确');
        }

        /*
        $goods_total_money = 0.00;
        foreach($detail as $row) {
             $goods_total_money += $row['price']
        }

        if($goods_total_money + $order['box_money'] + $order['ping_fee']) {
            $this->error('订单总价不正确');
        }

        if($money != ($total_money - $order_discount)) {
            $this->error('订单结算金额不正确');
        }
        */

        $orderData = [
            'orders_sn' => $orders_sn,//订单
            'user_id' => $this->auth->id,
            'shop_id' => isset($order['shop_id']) ? $order['shop_id'] : 0,
            'school_id' => $school_id,
            'hourse_id' => !empty($order['hourse_id']) ? $order['hourse_id'] : 0,//宿舍楼ID
            'save_time' => date('Y-m-d'),
            'money' => isset($order['money']) ? (float)$order['money'] : 0.00,//实付金额
            'total_money' => isset($order['total_money']) ? (float)$order['total_money'] : 0.00,//订单总价
            'box_money' => isset($order['box_money']) ? (float)$order['box_money'] : 0.00,//订单参盒费
            'ping_fee' => isset($order['ping_fee']) ? (float)$order['ping_fee'] : 0.00,//订单配送费
            'pay_mode' => isset($order['pay_mode']) ? $order['pay_mode'] : 1,//支付方式
            'address' => isset($order['address']) ? $order['address'] : '',//配送地址
            'num' => isset($order['num']) ? $order['num'] : '',//商品总数
            'message' => isset($order['remark']) ? $order['remark'] : '',//订单备注
            'source' => 1,//订单来源
            'add_time' => time(),//订单创建时间
            //店铺优惠信息
            'shop_discounts_id' => isset($shop_discount['id']) ? $shop_discount['id']: 0,
            'shop_discounts_money' => isset($shop_discount['face_value']) ? $shop_discount['face_value'] : 0.00,
            //平台优惠信息
            'platform_coupon_id' => isset($platform_discount['id']) ? $platform_discount['id'] : 0 ,
            'platform_coupon_money' => isset($platform_discount['face_value']) ? $platform_discount['face_value'] : 0.00,
        ];
        //添加日志
        set_log('orderData=',$orderData,'sureOrder');

        //启动事务
        Db::startTrans();
        try{

            //更新红包状态
            if($orderData['platform_coupon_money'] > 0){

                $data = [
                    'status' => $hongbao_status,
                    'order_sn' => $orders_sn,
                ];

                //红包使用状态判断
                $my_coupon_id = model('MyCoupon')->where([['user_id','=',$this->auth->id],['platform_coupon_id','=',$platform_discount['id']],['status','=','1']])->value('id');

                $coupon_status = model('MyCoupon')->where([['user_id','=',$this->auth->id],['id','=',$my_coupon_id]])->value('status');
                if($coupon_status == 2) {
                    throw new \Exception('红包已使用');
                }


                $res = model('MyCoupon')->updateStatus($my_coupon_id,$data);
                if(!$res) {
                    throw new \Exception('红包使用失败');
                }
            }

            //今日特价商品逻辑 start
            $id = model('TodayDeals')->getTodayProduct($orderData['shop_id']);
            if ($id){
                $product  = array_column($detail,'product_id');
                if (in_array($id,$product)){
                    model('TodayDeals')->updateTodayProductNum($orderData['shop_id'],'dec',$id);
                }
            }
            //今日特价商品逻辑 end


            $orders_id = model('Orders')->addOrder($orderData);

            if(!$orders_id) {
                throw new \Exception('订单添加失败');
            }


            foreach ($detail as $row) {

                //商品均摊金额和商品原价初始化
                $product_money = isset($row['total_money']) ? $row['total_money'] : '0.00';
                $old_money = isset($row['total_money']) ? $row['total_money'] : '0.00';

                //如果订单包含 商家或者店铺优惠均摊到 商品结算金额计算逻辑
                if($orderData['shop_discounts_id'] || $orderData['platform_coupon_id']){
                    $product_money = (float)(($product_money/$order['total_money']) * ($money - $order['box_money'] - $order['ping_fee']));
                }

                $detailData[] = [
                    'orders_id' => $orders_id,
                    'orders_sn' => $orders_sn,
                    'product_id' => isset($row['product_id']) ? $row['product_id'] : 0,
                    'attr_ids' => isset($row['attr_ids']) ? $row['attr_ids'] : '',
                    'num' => isset($row['num']) ? $row['num'] : 0,
                    'total_money' => $row['total_money'],
                    'money' => $product_money,//商品结算金额
                    'old_money' => $old_money,//商品原价
                    'price' => isset($row['price']) ? $row['price'] : 0.00,//商品单价
                    'box_money' => isset($row['box_money']) ? $row['box_money'] : 0.00,
                    'platform_coupon_id' => isset($platform_discount['id']) ? $platform_discount['id'] : 0,
                    'platform_coupon_money' => isset($platform_discount['face_value']) ? (float)$platform_discount['face_value'] : 0.00,
                    'shop_discounts_id' => isset($shop_discount['id']) ? $shop_discount['id'] : 0,
                    'shop_discounts_money' => isset($shop_discount['face_value']) ? (float)$shop_discount['face_value'] : 0.00
                ];

            }

            set_log('detailData=',$detailData,'sureOrder');
            //订单明细入库
            $res = model('Orders')->addOrderDetail($detailData);

            //dump($res);

            if(!$res) {
                throw new \Exception('明细添加失败');
            }

            Db::commit();
            $result['orders_id'] = $orders_id;
            $result['orders_sn'] = $orders_sn;

            // redis 存储  【2019-11-14更新】
            $redis = Cache::store('redis');
            $key = "order_cacle";
            $time = time() + 15*60; // 订单超时时间
            $redis->hSet($key, $orders_sn, $time);

            return json_success('提交成功',$result);

        } catch (\Exception $e) {
            Db::rollback();
            return json_error($e->getMessage());
        }

    }

    /**
     * 取消订单
     */
    public function cancelOrder(Request $request)
    {
        $order_sn = $request->param('order_sn');
        $order_status = 9;//已取消
        $hongbao_status = 1;//未使用

        if(isset($order_sn)) {
            $order_sn = trim($order_sn);
        }

        $order_info = model('Orders')->getOrder($order_sn);

        $order_detail =  model('Orders')->getOrderDetail($order_info['id']);


        if(!$order_info) {
            $this->error('订单不存在');
        }

        if($order_info['status'] == 9){
            $this->error('订单已取消');
        }

        if($order_info['status'] == 3) {
            $this->error('商家已接单,无法退款,请去申请退款');
        }

        if($order_info['status'] == 4) {
            $this->error('商家已拒单!');
        }

        if(in_array($order_info['status'], [5,6,7,8])) {
            $this->error('骑手取货、配货、已送达、已完成,无法退款,请去申请退款');
        }

        // 这块判断有问题， 状态值 2 跟 3 不能同时存在
        if($order_info['status'] == 2) {//已经支付
            $this->refund($order_info['orders_sn']);//退款
        }


        //如果使用红包 状态回滚
        if($order_info['platform_coupon_money'] > 0){

            $data['status'] = $hongbao_status;
            // Mike需调整
            $my_coupon_id = model('MyCoupon')->where([['user_id','=',$this->auth->id],['platform_coupon_id','=',$order_info['platform_coupon_id']],['status','=','2']])->value('id');
            model('MyCoupon')->updateStatus($my_coupon_id,$data);
        }

        //今日特价商品逻辑
        $id = model('TodayDeals')->getTodayProduct($order_info['shop_id']);
        if ($id){
            $product  = array_column($order_detail,'product_id');
            if (in_array($id,$product)){
                model('TodayDeals')->updateTodayProductNum($order_info['shop_id'],'inc',$id);
            }
        }

        //今日特价商品逻辑 end

        $res = model('Orders')->cancelOrder($order_sn,$order_status);

        if($res) {
            //实例化socket
            $socket = model('PushEvent','service');
            $socket->setUser('s_'.$order_info['shop_id'])->setContent('用户取消订单')->push();

            $this->success('订单取消成功');
        }


        $this->error('订单取消失败');

    }

    /**
     * 再来一单
     */
    public function agianOrder(Request $request)
    {
        $order_id = $request->param('order_id');

        $order_info = model('Orders')->getOrderById($order_id);


        if(!$order_info) {
            $this->error('订单不存在');
        }

        $order_detail = model('Orders')->getOrderDetail($order_id);
        $result = [];

        foreach ($order_detail  as $row)
        {
            $product_info = model('Product')->where('id',$row['product_id'])->find();

            //优惠商品第二件原价
            if($product_info['type'] == 3) {
                $row['limit_buy_num'] = 1;//默认是一件
            }

            //如果商品下架 则不返回
            if($product_info['status'] == 2) {
                $today = date('Y-m-d',time());
                $today_goods = model('todayDeals')
                    ->where('product_id',$row['product_id'])
                    ->where('today',$today)
                    ->where('shop_id',$order_info['shop_id'])
                    ->find();

                if($today_goods) {
                    //今日特价过期
                    if(time() > $today_goods['end_time'] ||  $today_goods['num'] < 1) {
                        continue;
                    }

                    $row['limit_buy_num'] = $today_goods['limit_buy_num'];//限购次数
                    $product_info['old_price'] = $today_goods['old_price'];
                    $product_info['price'] = $today_goods['price'];
                }else{
                    continue;
                }

            }

            //获取商家提价
            $hike_arr = model('ShopInfo')->where('id','=',$order_info['shop_id'])->field('price_hike,hike_type')->find();

            list($price,$old_price) = model('Shop')->getShopProductHikePrice($hike_arr,$product_info['price'],$product_info['old_price']);

            $result[] = [
                'orders_id' => $row['orders_id'],
                'product_id' => $product_info['id'],
                'products_classify_id' => $product_info['products_classify_id'],
                'thumb' => $product_info['thumb'],
                'name' => $product_info['name'],
                'num' => $row['num'],
                'price' => $price,
                'old_price' => $old_price,
                'box_money' => $product_info['box_money'],
                'attr_names' => model('Shop')->getGoodsAttrName($row['attr_ids']),
                'attr_ids' => $row['attr_ids'],
                'limit_buy_num' => isset($row['limit_buy_num']) ? $row['limit_buy_num'] : ''
            ];
        }

        if (count($result) < 1) {
            $this->error('该商品已下架');
        }

        $this->success('获取成功',$result);

    }


    /**
     * 退款
     */
    public function refund($orders_sn)
    {
        $number = $orders_sn;//商户订单号

        if (!$number){
            $this->error('非法传参');
        }

        $find = model('Orders')->where('orders_sn',$number)->find();

        if (!$find){
            $this->error('商户订单号错误');
        }
        $money = intval((string)($find->money * 100));
        $totalFee = $money; //订单金额
        $refundFee =  $money;//退款金额
        $refundNumber = build_order_no('T');//商户退款单号

        $pay_config = config('wx_pay');
        $app    = Factory::payment($pay_config);//pay_config 微信配置

        //根据商户订单号退款
        $result = $app->refund->byOutTradeNumber( $number, $refundNumber, $totalFee, $refundFee, $config = [
            // 可在此处传入其他参数，详细参数见微信支付文档
            'refund_desc' => '取消订单退款',
            'notify_url'    => 'http' . "://" . $_SERVER['HTTP_HOST'].'/api/notify/refundBack',
        ]);


        return $result;
    }


    /**
     * 判断用户是否被禁用 
     * 
     */
    public function checkUserDisabled()
    {
        // 判断当前用户是否是禁用用户，如果是禁用用户，则不可以下单【提示，因为您的个人原因， 您已被禁止下单啦】
        $status = model('User')->where('id','=',$this->auth->id)->value('status');
        if ($status == 2) {
            $this->error('因为您的个人原因， 您已被禁止下单啦',202);
        }

        $this->success('用户可下单',200);

    }
     
}

