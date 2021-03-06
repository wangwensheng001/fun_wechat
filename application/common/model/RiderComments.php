<?php

namespace app\common\model;

use think\Model;

class RiderComments extends Model
{
    //获取商家配送评分
    public function getStar($shop_id)
    {
        $where[] = ['shop_id','=',$shop_id];
        $count = $this->where($where)->count();
        $sum = $this->where($where)->sum('star');

        if ($count != 0){
            $star = round($sum / $count,2);
        }else{
            $star = 5;
        }

        return $star;

    }
}