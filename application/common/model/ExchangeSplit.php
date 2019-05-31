<?php
/**
 * Created by PhpStorm.
 * User: 猿份哥
 * Date: 2018/6/8
 * Time: 11:39
 */

namespace app\common\model;


use think\Model;

/**
 * 兑水订单拆分表模型
 * Class ExchangeSplit
 * @package app\common\model
 */
class ExchangeSplit extends Model
{
    /**
     * 查询对应的商品信息
     * @return \think\model\relation\HasOne
     */
    public function info()
    {
        return $this->hasOne('Goods','goods_id','goods_id')->field(['goods_id','goods_name','original_img']);
    }
}