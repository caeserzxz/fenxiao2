<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/16
 * Time: 15:40
 */

namespace app\api\controller;

use My\DataReturn;
use think\Db;
use think\Page;

class Hot extends Base
{

	//热卖产品
    public function goods_list()
    {
        $where = [
            'is_on_sale' => 1,
            'prom_type' => 0,
            'is_hot' => 1,
        ];
        $type = I('type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum desc';
        } else {
            $order = 'goods_id';
        }
        $pagesize = C('PAGESIZE');  //每页显示数
        $pages  = I('pages') ?: 1;//页码
        $data = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price', 'sales_sum'])->page($pages, $pagesize)->order($order)->select();
        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]['original_img'] = request()->domain().goods_thum_images($value['goods_id'],200,200);
            }
            DataReturn::returnJson(200,'获取数据成功！',$data);
        }else{
            DataReturn::returnJson(200,'无数据');
        }
    }

}