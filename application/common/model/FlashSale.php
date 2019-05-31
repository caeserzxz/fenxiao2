<?php

namespace app\common\model;
use think\Model;
class FlashSale extends Model {
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function specGoodsPrice()
    {
        return $this->hasOne('SpecGoodsPrice','item_id','item_id');
    }

    public function goods()
    {
        return $this->hasOne('goods','prom_id','id');
    }
    //剩余抢购库存
    public function getStoreCountAttr($value, $data)
    {
        return $data['goods_num'] - $data['buy_num'];
    }

}
