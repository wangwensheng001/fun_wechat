<?php
/**
 * Created by PhpStorm.
 * User: zhangtaotao
 * Date: 2019/6/3
 * Time: 2:40 AM
 */

namespace app\merchants\controller;


use app\common\controller\MerchantsBase;
use think\Request;
use think\Db;

class Property extends MerchantsBase
{

    protected $noNeedLogin = [""];

    protected $type = [1=>'收入',2=>'支出'];



    /**
     * 我的资产
     */
    public function myProperty(Request $request)
    {

        $shop_id = $this->shop_id;//从Token中获取

        isset($shop_id) ? $shop_id : $request->param('shop_id');


        $data = model('IncomeExpenditure')->index($shop_id);

        //商家信息
        $shop_info = model('Shop')->getShopInfo();

        $data = [
            'balanceMoney' => $data['balance_money'],//可提现余额
            'totalMoney' => model('Shop')->getCountSales($shop_id),//总收入
            'monthMoney' => model("Shop")->getMonthSales($shop_id)//本月收入
        ];

        $this->success('获取成功',$data);

    }

    /**
     * 收支明细
     */
    public function receiptPay(Request $request)
    {
        $shop_id = $this->shop_id;

        isset($shop_id) ? $shop_id : $request->param('shop_id');

        $res = Db::name('withdraw')->where('shop_id',$shop_id)->select();

        if(!$res) {
            $this->error('暂时没有提现记录');
        }
        $this->success('success',$res);

    }

    /**
     * 提现
     */
    public function withdraw(Request $request)
    {
        $shop_id = $this->shop_id;
        $withdraw_sn = 'TXBH'.build_order_no();
        $moeny = $request->param('money');//提现金额

        //收支明细
        $szmx = model('incomeExpenditure')->get($shop_id);

        if($szmx['balance_money'] < $moeny) {
            $this->error('提现金额不正确');
        }

        //提现申请入库
        $txsq = [
            'shop_id' => $shop_id,
            'withdraw_sn' => $withdraw_sn,
            'money' => $moeny,
            'status' => 1,
        ];

        $res = DB::name('withdraw')->insert($txsq);

        if($res) {
            $this->success('申请成功');
        }
        $this->error('申请失败');

    }
}