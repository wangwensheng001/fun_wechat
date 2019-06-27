<?php


namespace app\merchants\controller;
use app\common\controller\MerchantsBase;
use EasyWeChat\Factory;
use think\Request;

class Refund extends MerchantsBase
{
    protected $noNeedLogin = ['*'];
    /*
     * 退款申请查询
     */
    public function index(Request $request) {
        $shop_id = $this->shop_id;
        $type = $request->param('type','');//1申请退款， 2退款成功， 3退款失败
        $map = [];

        if($type) {
            $map[] = ['status','=',$type];
        }

        if($shop_id) {
            $map[] = ['shop_id','=',$shop_id];
        }


        $refund_info = Model('Refund')
            ->field('')
            ->where($map)
            ->select();

//        dump($refund_info);

        foreach ($refund_info as &$row) {
            $row['add_time'] = date('m-d H:i',$row['add_time']);//申请退款时间
            $row['refund_time'] = date('m-d H:i',$row['refund_time']);//退款完成时间
            $row['detail'] = $this->detail($row['id']);
        }


        return $this->success('获取成功',$refund_info);
    }

    /**
     * 获取店铺订单详情
     */
    public function detail($id)
    {
        $detail = model('OrdersInfo')
            ->field('id,orders_id,product_id,num,ping_fee,box_money,attr_ids,total_money,old_money')
            ->where('orders_id','=',$id)
            ->select();

        foreach ($detail as &$row)
        {
            $row['attr_names'] = model('Shop')->getGoodsAttrName($row['attr_ids']);
            $row['name'] = model('Product')->getNameById($row['product_id']);
        }
        return $detail;
    }

    /**
     * 商家同意用户的申请退款
     */
    public function refund(Request $request) {
        $orders_sn = $request->param('orders_sn');

        try{
            if(!$orders_sn) {
                throw new \Exception('订单号不能为空!');
            }
            $find = model('Refund')->where('out_trade_no',$orders_sn)->find();

            if($find['status'] == 2) {
                throw new \Exception('商家已退款!');
            }

            $res = $this->wxRefund($orders_sn);
            //dump($res);

            if('SUCCESS' == $res['data']['return_code'] && 'SUCCESS' == $res['data']['result_code']) {

                model('Refund')->where('out_trade_no',$orders_sn)->setField('status',2);
                model('Orders')->where('orders_sn',$orders_sn)->setField('status',13);

                return json_success('退款成功');
            }

            throw new \Exception($res['err_code_des']);


        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     *  商家拒绝用户的申请退款
     */
    public function refuse(Request $request) {
        $orders_sn = $request->param('orders_sn');

        if(!$orders_sn) {
            $this->error('订单号不能为空');
        }

        $find = model('Refund')->where('out_trade_no',$orders_sn)->find();

        if($find['status'] == 2) {
            $this->error('商家已退款!');
        }

        if($find['status'] == 3) {
            $this->error('商家已拒绝退款!');
        }

        //外卖表添取消原因,取消时间

        model('Refund')->where('out_trade_no',$orders_sn)->setField('status',3);
        model('Orders')->where('orders_sn',$orders_sn)->setField('status',12);

        $this->success('拒绝退款成功');

    }

    /**
     * 微信退款处理
     */
    public function wxRefund($orders_sn)
    {

        $number = trim($orders_sn);//商户订单号

        if (!$number){
            $this->error('非法传参');
        }

        $find = model('Refund')->where('out_trade_no',$number)->find();

        if (!$find){
            $this->error('商户订单号错误');
        }

        if ($find->total_fee < $find->refund_fee){
            $this->error('退款金额不能大于订单总额');
        }

        $totalFee = $find->total_fee * 100; //订单金额
        $refundFee =  $find->refund_fee * 100;//退款金额
        $refundNumber = $find->out_refund_no;//商户退款单号

        $pay_config = config('wx_pay');

        //dump($pay_config);
        $app    = Factory::payment($pay_config);//pay_config 微信配置

        //根据商户订单号退款
        $result = $app->refund->byOutTradeNumber( $number, $refundNumber, $totalFee, $refundFee, $config = [
            // 可在此处传入其他参数，详细参数见微信支付文档
            'refund_desc' => '取消订单退款',
            //'notify_url'    => 'https' . "://" . $_SERVER['HTTP_HOST'].'/api/notify/refundBack',
        ]);


        return $result;
    }


    //退款查询
    public function refundQuery(Request $request)
    {
        $outTradeNumber = $request->param('outTradeNumber');
        $pay_config = config('wx_pay');
        $app    = Factory::payment($pay_config);//pay_config 微信配置
        $result = $app->refund->queryByOutTradeNumber($outTradeNumber);

        $this->success('success',$result);
    }
}