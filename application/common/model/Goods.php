<?php

namespace app\common\model;
use think\Model;
class Goods extends Model {
    public function FlashSale()
    {
        return $this->hasOne('FlashSale','id','prom_id');
    }

    public function PromGoods()
    {
        return $this->hasOne('PromGoods','id','prom_id')->cache(true,10);
    }
    public function GroupBuy()
    {
        return $this->hasOne('GroupBuy','id','prom_id');
    }
    public function getDiscountAttr($value, $data)
    {
        if ($data['market_price'] == 0) {
            $discount = 10;
        } else {
            $discount = round($data['shop_price'] / $data['market_price'], 2) * 10;
        }
        return $discount;
    }

    public function getOriginImgUrlAttr($value,$data)
    {
        return request()->domain().$data['original_img'];
    }
}
