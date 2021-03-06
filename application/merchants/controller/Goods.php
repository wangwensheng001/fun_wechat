<?php

namespace app\merchants\controller;

use app\common\controller\MerchantsBase;
use think\Request;
use app\common\model\Product;
use app\common\model\TodayDeals;
use think\Db;

/**
 * 商品模块控制器
 */
class Goods extends MerchantsBase
{
    protected $noNeedLogin = [];

    /**
     * 获取商品列表
     */
    public function index()
    {

        $where = [['shop_id','=',$this->shop_id],['delete','=',0]];
        //获取商品
        $list = model('Product')
            ->field('id,name,price,old_price,attr_ids,thumb,sales,products_classify_id as classId,type,status')
            ->where($where)
            ->order('create_time desc')
            ->select()
            ->toArray();

        $cakes = [];
        $preferential = [];
        //获取热销商品
        foreach ($list as $item) {
            $item['sales'] = model('Product')->getMonthSales($item['id']);
            if ($item['type'] == 2){
                $cakes[] = $item;
            }elseif($item['type'] == 3){
                $preferential[] = $item;
            }
        }
        $data['cakes'] = $cakes;
        $data['preferential'] = $preferential;

        //获取分类
        $class = model('ProductsClassify')
            ->field('id as classId,name as className')
            ->where('shop_id','=',$this->shop_id)
            ->order('sort')
            ->select()
            ->toArray();

        foreach ($class as &$item) {
            $item['goods'] = [];
            foreach ($list as &$v) {
                if ($item['classId'] == $v['classId']){
                    $item['goods'][] = $v;
                }
                $v['sales'] = model('Product')->getMonthSales($v['id']);
            }
        }
        // dump($class);die;
        $data['class'] = $class;


        $this->success('success',$data);

    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $data   = $request->param();
        $type   = $request->param('type');
        $price   = $request->param('price');
        if ($type != 3 ){
            $data['old_price'] = $price;
        }

        $data['shop_id'] = $this->shop_id;
        $data['create_time'] = time();
        Product::create($data);

        $this->success('success');
    }


    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function modify(Request $request)
    {
        $id   = $request->param('id');
        if (!$id){
            $this->error('非法参数');
        }

        $data   = $request->param();
        if (isset($data['type']) && $data['type'] != 3 ){
            $data['old_price'] = $data['price'];
        }

        $result = Product::update($data, ['id' => $id]);
        $this->success('success',$result);
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        if (!$id){
            $this->error('非法参数');
        }
        $result = Product::get($id);
        
        if ($result->shop_id != $this->shop_id) {
            $this->error('没有权限删除');
        }

        $res = Db::name('product')->where('id','=',$id)->update(['status'=>2,'delete'=>1]);
        if (!$res) {
            $this->error('删除失败');
        }
        $this->success('success');
    }

    /**
     * 获取商品详情
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function detail($id)
    {
        if (!$id){
            $this->error('非法参数');
        }

        $result = Product::get($id);
        $result->class_name = model('ProductsClassify')->getNameById($result->products_classify_id);
        $result->attr_name = model('ProductAttrClassify')->getNameByIds($result->attr_ids);

        $data = TodayDeals::get(['product_id'=>$id]);
        if ($data->end_time > time()){
            $result->old_price = $data->old_price;
            $result->price = $data->price;
        }

        $this->success('success',$result);
    }

    /**
     * 获取下架商品
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function getOffProduct()
    {
        $result = model('Product')
           ->where('status',2)
           ->where('shop_id',$this->shop_id)
            ->where('delete',0)
            ->select();

        $this->success('success',$result);
    }
}