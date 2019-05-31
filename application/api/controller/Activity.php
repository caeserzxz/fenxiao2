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
use app\common\model\GroupBuy;

class Activity extends Base
{

		 // //需要检查登录的页面
   //  public function __construct()
   //  {
   //      parent::__construct();
   //      //不需验证登录的方法
   //      $nologin = [];

   //      if(!in_array(ACTION_NAME,$nologin))
   //          $this->checkLogin();
   //  }
		// 商品活动页面(优惠活动)
    public function promote_goods()
    {
        $now_time = time();
        $pages  = I('pages') ?: 1;//页码
        $pagesize = C('PAGESIZE');//每页显示数
        $where = " start_time <= $now_time and end_time >= $now_time ";
        $data = M('prom_goods')->field('id,title,start_time,end_time,prom_img')->where($where)->page($pages, $pagesize)->select();
        if ($data) {
        	foreach ($data as $key => $value) {
	        	  $data[$key]['prom_img'] = request()->domain().$value['prom_img'];
	        	  $data[$key]['start_time'] = date('Y.m.d',$value['start_time']);
	        	  $data[$key]['end_time'] = date('Y.m.d',$value['end_time']);
        	}
        	DataReturn::returnJson(200,'获取数据成功！',$data);
        }else{
        	DataReturn::returnJson(200,'无数据');
        }

    }

    //活动商品列表(优惠活动)
    public function discount_list()
    {
        $prom_id = I('id/d');//活动ID
        $pages  = I('pages') ?: 1;//页码
        $where = [
          'is_on_sale' => 1,
          'prom_type' => 3,
          'prom_id' => $prom_id,
        ];
        $pagesize = C('PAGESIZE');  //每页显示数
        $prom_list = M('goods')->where($where)->page($pages, $pagesize)->select();
        $spec_goods_price = M('specGoodsPrice')->where(['prom_type' => 3, 'prom_id' => $prom_id])->select(); //规格
        foreach ($prom_list as $gk => $goods) {  //将商品，规格组合
            foreach ($spec_goods_price as $spk => $sgp) {
                if ($goods['goods_id'] == $sgp['goods_id']) {
                    $prom_list[$gk]['spec_goods_price'] = $sgp;
                }
            }
        }
        foreach ($prom_list as $gk => $goods) {  //计算优惠价格
            $PromGoodsLogicuse = new \app\common\logic\PromGoodsLogic($goods, $goods['spec_goods_price']);
            if (!empty($goods['spec_goods_price'])) {
                $prom_list[$gk]['prom_price'] = $PromGoodsLogicuse->getPromotionPrice($goods['spec_goods_price']['price']);
            } else {
                $prom_list[$gk]['prom_price'] = $PromGoodsLogicuse->getPromotionPrice($goods['shop_price']);
            }

        }
        if ($prom_list) {
        	foreach ($prom_list as $key => $value) {
	        	  $prom_list[$key]['original_img'] = request()->domain().goods_thum_images($value['goods_id'],400,400);
	        	  $prom_list[$key]['goods_name'] = getSubstr($value['goods_name'],0,20);
        	}
        	DataReturn::returnJson(200,'获取数据成功！',$prom_list);
        }else{
        	DataReturn::returnJson(200,'无数据');
        }
    }

    //今天上新
    public function todaygoods()
    {
        $where = [
            'is_on_sale' => 1,
            'prom_type' => 0,
            'on_time' => ['between', strtotime(date('Y-m-d 00:00:00')) . ',' . date('Y-m-d 23:23:59')],
        ];
        $type = I('type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum';
        } else {
            $order = 'goods_id';
        }
        $pagesize = C('PAGESIZE');  //每页显示数
        $pages = I('pages') ?: 1;
        $data = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price'])->page($pages, $pagesize)->order($order)->select();
        if ($data) {
        	foreach ($data as $key => $value) {
                $data[$key]['original_img'] = request()->domain().goods_thum_images($value['goods_id'],200,200);
            }
        	DataReturn::returnJson(200,'获取数据成功！',$data);
        }else{
        	DataReturn::returnJson(200,'无数据');
        }
    }

    /**
     * 团购活动列表(团购商城)
     */
    public function group_list()
    {
        $type = I('type');
        $pages  = I('pages') ?: 1;//页码
        //以最新新品排序
        if ($type == 'new') {
            $order = 'gb.start_time';
        } elseif ($type == 'comment') {
            $order = 'g.comment_count';
        } else {
            $order = '';
        }
        $group_by_where = [
            'gb.start_time' => ['lt', time()],
            'gb.end_time' => ['gt', time()],
            'g.is_on_sale' => 1
        ];
        $GroupBuy = new GroupBuy();
        $pagesize = C('PAGESIZE');  //每页显示数
        $data = $GroupBuy->alias('gb')->join('__GOODS__ g', 'gb.goods_id=g.goods_id AND g.prom_type=2')->where($group_by_where)->page($pages, $pagesize)->order($order)->select();
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