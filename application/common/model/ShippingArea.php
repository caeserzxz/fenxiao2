<?php

namespace app\common\model;
use think\Model;
class ShippingArea extends Model {
    public function plugin(){
        return $this->hasOne('plugin','code','shipping_code');
    }
}
