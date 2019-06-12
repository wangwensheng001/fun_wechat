<?php

return [
    // 优惠券的状态
    'coupon_status' => [
        '1' => '未发放',        
        '2' => '已发放',
        '3' => '暂停发放',
        '4' => '已作废',
    ],

    // 优惠券的用户类型
    'user_type'   =>  [
        '1' => '所有用户',
        '2' => '首单新用户',
    ],

    // 广告的展示平台
    'show_platfrom'   =>  [
        '1' => '用户端',
        '2' => '骑手端',
        '3' => '商家端',
    ],

    // 广告的状态
    'advers_status'   =>  [
        '1' => '启用',
        '2' => '禁用',
    ],

    // 反馈建议的状态【反馈意见、意向商家、意向骑手】
    'dispose_status'   =>  [
        '1' => '未处理',
        '2' => '已处理',
        '3' => '不处理',
    ],
    
    // 【商家/骑手】审核状态
    'check_status'  =>  [
        'shop'  =>  [
            '1' => '商家logo不合格',
            '2' => '商家门店名称不规范',
            '3' => '联系方式有误',
            '4' => '地址不合格',
            '5' => '营业执照照片不合格',
            '6' => '法定代表人/经营者与营业执照不一致',
            '7' => '法人手持身份证照（正反）不合格',
            '8' => '姓名与身份证照片不一致',
            '9' => '身份证号与身份证照片不一致',
            '10' => '性别与身份证照片不一致',
            '11' => '许可证照片不合格',
        ],
        'rider' =>  [
            '1' => '身份证照片（正反面）不合格',
            '2' => '手持身份证照不合格',
            '3' => '姓名与身份证照片不一致',
            '4' => '联系方式有误',
            '5' => '身份证号与身份证照片不一致',
        ]
    ],

    // 骑手审核状态 
    'rider_check_status'    =>  [
        '0' =>  '全部',
        '1' =>  '待审核',
        '2' =>  '未通过',
        '3' =>  '已通过',
    ],

    // 商家审核状态
    'shop_check_status' => [
        '0' => '待审核',
        '1' => '已通过',
        '2' => '未通过',
        '3' => '启用',
        '4' => '禁用',

    ],

    // 订单状态
    'order_status'  =>  [
        '1'     =>  '未支付',
        '2'     =>  '已付款（商家待接单）',
        '3'     =>  '商家已接单',
        '4'     =>  '商家拒绝接单',
        '5'     =>  '骑手取货中',
        '6'     =>  '骑手配送站',
        '7'     =>  '商家出单',
        '8'     =>  '订单已送达（未评价） ',
        '9'     =>  '订单已完成（已评价）',
        '10'     =>  '交易关闭（15分钟未支付）',
        '11'     =>  '订单已取消',
    ],
    //分页参数设置
    'page_size' => 20,

    //验证码设置
    'captcha' => [
        // 验证码字体大小
        'fontSize' => 30,
        //验证码位数
        'length' => 4,
    ],
    //性别
    'sex' => [
        1 => '男',
        0 => '女',
        2 => '保密'
],

];