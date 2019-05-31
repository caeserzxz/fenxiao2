<?php

namespace app\admin\model;
use think\Model;
class PromGoods extends Model {
    public function getPromDetailAttr($value,$data)
    {
        switch ($data['type']){
            case 1:
                $title = '优惠￥'.$data['expression'];
                break;
            case 2:
                $title = '促销价￥'.$data['expression'];
                break;
            case 3:
                $title = '买就送优惠券';
                break;
            default:
                $discount = $data['expression']/10;
                $title = $discount.'折';
        }
        return $title;
    }
    public function getPromDescAttr($value,$data)
    {
        $parse_type = array('0' => '直接打折', '1' => '减价优惠', '2' => '固定金额出售', '3' => '买就赠优惠券');
        return $parse_type[$data['type']];
    }
}
