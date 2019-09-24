<?php

namespace app\common\model;

use think\Model;
use think\Db;

class Orders extends Model
{
    // 设置json类型字段
    protected $json = ['address'];
    //
    /**
     * 获取会员累计消费金额、次数
     * @param $uid
     */
    public function getUserConsume($uid)
    {
        $data = model('Orders')->where([['user_id','=',$uid],['status','notin','1']])->field('SUM(money) as total_money,count(id) as count_num')->find();
        return $data;
    }


    /**
     * 新增订单
     */
    public function addOrder($data)
    {
        return $this->name('orders')->insertGetId($data);
    }

    /**
     * 取消订单
     */
    public function cancelOrder($order_sn,$status)
    {
        return $this->name('orders')->where('orders_sn',$order_sn)->setField(['status'=>$status,'cancle_time'=>time()]);
    }

    /**
     * 获取订单
     */
    public function getOrderById($order_id)
    {
        return $this->where('id',$order_id)->find();
    }

    /**
     * 订单详情
     */
    public function getOrderDetail($order_id)
    {
        $data = $this->name('orders_info')->where('orders_id',$order_id)->select()->toArray();
        return $data;
    }

    /**
     * 获取订单
     */
    public function getOrder($order_sn)
    {
        return $this->where('orders_sn',$order_sn)->find();
    }



    /**
     * 获取订单编号
     */
    public function getOrderSnById($order_id)
    {
        return $this->where('id',$order_id)->value('orders_sn');
    }

    /**
     * 添加订单详情
     */
    public function addOrderDetail($data)
    {
        return $this->name('orders_info')->insertAll($data);
    }

    /**
     * 获取订单列表
     * @param $page_no
     * @param $page_size
     * @return mixed
     */
    public function getOrderList($page_no, $page_size)
    {
        return $this->page($page_no,$page_size)->select()->toArray();
    }

    /**
     * 用户是否首单
     * @param $uid
     * @return bool
     */
    public function isFirstOrder($map)
    {
        return $this->where($map)->value('new_buy');
    }

    /**
     * 更新订单状态
     * @param $order_sn
     * @param $status
     * @return int
     */
    public function updateStatus($order_sn,$status)
    {
        return $this->where('orders_sn',$order_sn)->setField('status',$status);
    }

    /**
     * 获取订单total_money
     * @param $product_id
     */
    public function getTotalMoney($order,$detail)
    {
        $goods_total_money = 0.00;
        foreach ($detail as $row)
        {
            $goods_total_money += Db::name('product')->where('id',$row['product_id'])->value('price');
        }

        $shop_price = Db::name('ShopInfo')->where('id',$order['shop_id'])->value('price_hike');
        $shop_ping = Db::name('ShopInfo')->where('id',$order['shop_id'])->value('ping_fee');
        //订单总价 = 商品总价 + 提价 + 配送费
        $total_money = $goods_total_money + $shop_price + $shop_ping;

        return $total_money;
    }

    /**
     *获取平台优惠金额
     */
    public function getPlatformDisCountMoney($id)
    {
        $data = Db::name('platformCoupon')->where('id',$id)->value('face_value');
        return isset($data) ? $data : 0.00;
    }

    /**
     * 获取商家优惠金额
     */
    public function getShopDisCountMoney($id)
    {
        $data = Db::name('shopDiscounts')->where('id',$id)->value('face_value');
        return isset($data) ? $data : 0.00;
    }

    /**
     * 获取订单优惠金额
     */
    public function getDisMoney($shop_dis,$plat_dis)
    {
        if($shop_dis['id']) {
            $shop_dis_money = $this->getShopDisCountMoney($shop_dis['id']);
        }else{
            $shop_dis_money = 0.00;
        }

        if($plat_dis['id']) {
            $plat_dis_money = $this->getPlatformDisCountMoney($plat_dis['id']);
        }else{
            $plat_dis_money = 0.00;
        }

        $dis_money = $shop_dis_money + $plat_dis_money;
        return $dis_money;

    }


}
