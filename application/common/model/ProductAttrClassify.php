<?php

namespace app\common\model;

use think\Model;

class ProductAttrClassify extends Model
{
    protected $autoWriteTimestamp = true;
    protected $insert             = [
        'status' => 1,
    ];

    protected $field = [
        'id'          => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
        'name','shop_id','pid',
    ];

    /**
     * 获取属性名称
     * @param $id
     * @return mixed
     */
    public function getNameByIds($id){
        $name =  $this->where('id','in',$id)->column('name');

        $name = implode(',',$name);

        return $name;
    }

}
