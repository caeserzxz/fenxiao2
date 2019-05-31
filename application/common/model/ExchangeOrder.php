<?php
/**
 * Created by PhpStorm.
 * User: 猿份哥
 * Date: 2018/6/8
 * Time: 11:40
 */

namespace app\common\model;


use think\Model;

/**
 * 兑水订单表模型
 * Class ExchangeOrder
 * @package app\common\model
 */
class ExchangeOrder extends Model
{
    /**
     * 拆分小订单
     * @return \think\model\relation\HasMany
     */
    public function split()
    {
        return $this->hasMany('ExchangeSplit','order_id','id')->field(['order_id','num','goods_id']);
    }

    /**
     * 关联地址
     * @return \think\model\relation\HasOne
     */
    public function address()
    {
        return $this->hasOne('UserAddress','address_id','address_id')->field(['address_id','consignee','mobile','country','province','city','district','twon','address']);
    }
}