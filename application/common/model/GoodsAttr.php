<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/16
 * Time: 9:17
 */

namespace app\common\model;


use think\Model;

class GoodsAttr extends Model
{
    public function GoodsAttribute()
    {
        return $this->hasOne('GoodsAttribute','attr_id','attr_id');
    }
}