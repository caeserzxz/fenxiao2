<?php

namespace app\common\model;

use think\Db;
use think\Model;

class SpecGoodsPrice extends Model
{

    public function promGoods()
    {
        return $this->hasOne('PromGoods', 'id', 'prom_id')->cache(true,10);
    }

    public function goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id')->cache(true,10);
    }
}
