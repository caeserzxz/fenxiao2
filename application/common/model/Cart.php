<?php

namespace app\common\model;
use app\common\logic\FlashSaleLogic;
use app\common\logic\GroupBuyLogic;
use think\Log;
use think\Model;
class Cart extends Model {
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }
    public function promGoods()
    {
        return $this->hasOne('PromGoods', 'id', 'prom_id')->cache(true,10);
    }

    public function goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id')->cache(true,10);
    }

    public function getSpecKeyNameArrAttr($value, $data)
    {
        if ($data['spec_key_name']) {
            $specKeyNameArr = explode(' ', $data['spec_key_name']);
            return $specKeyNameArr;
        } else {
            return [];
        }
    }

    /**
     * 商品优惠总额
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getGoodsFeeAttr($value, $data)
    {
        return $data['goods_num'] * $data['member_goods_price'];
    }
    /**
     * 商品总额
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getTotalFeeAttr($value, $data)
    {
        return $data['goods_num'] * $data['goods_price'];
    }
    /**
     * 商品总额优惠
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getCutFeeAttr($value, $data)
    {
        return round(($data['goods_num'] * ($data['goods_price'] - $data['member_goods_price'])), 2);
    }

    /**
     * 商品积分总额
     * @param int $value
     * @param array $data
     * @return int
     */
    public function getTotalIntegralAttr($value, $data) {
        return ceil($data['integral'] * $data['goods_num']);
    }

    /**
     * 限购数量
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getLimitNumAttr($value, $data)
    {
        $spec_goods_price = null;
        $goods = Goods::get($data['goods_id'], '', 20);
        //有规格
        if ($data['spec_key']) {
            $spec_goods_price = SpecGoodsPrice::get(['goods_id'=>$data['goods_id'],'key' => $data['spec_key']]);
            if ($data['prom_type'] == 1) {
                $FlashSaleLogic = new FlashSaleLogic($goods, $spec_goods_price);
                $limitNum = $FlashSaleLogic->getUserFlashResidueGoodsNum($data['user_id']);
            } else if ($data['prom_type'] == 2) {
                $groupBuyLogic = new GroupBuyLogic($goods, $spec_goods_price);
                $limitNum = $groupBuyLogic->getPromotionSurplus();//团购剩余库存
            } else {
                $limitNum = $spec_goods_price['store_count'];
            }
        }else{
            //没有规格
            if ($data['prom_type'] == 1) {
                $FlashSaleLogic = new FlashSaleLogic($goods, null);
                $limitNum = $FlashSaleLogic->getUserFlashResidueGoodsNum($data['user_id']);
            } else if ($data['prom_type'] == 2) {
                $groupBuyLogic = new GroupBuyLogic($goods, null);
                $limitNum = $groupBuyLogic->getPromotionSurplus();//团购剩余库存
            } else {
                $limitNum = $goods['store_count'];
            }
        }
        return $limitNum;
    }
}
