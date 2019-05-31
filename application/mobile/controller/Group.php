<?php

namespace app\mobile\controller;

use app\common\logic\GoodsLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use think\Db;
use think\Page;
use think\AjaxPage;
use app\common\logic\ActivityLogic;

class Group extends MobileBase
{
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 热卖产品
     */
    public function goods_list()
    {
        $where = array(     //条件
            'is_on_sale' => 1,
            'prom_type' => 0,
            'sale_type' => 1,
        );
        $type = I('get.type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum';
        } else {
            $order = 'goods_id';
        }
        $count = M('goods')->where($where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
        $list = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price', 'integral', 'sale_type'])->page($p, $pagesize)->order($order)->select();
        $this->assign('list', $list);
        if (I('is_ajax')) {
            return $this->fetch('goods_list/ajax_goods_list');//输出分页
        }
        $this->assign('U','Mobile/Group/goods_list');
        $this->assign('title', '组合购买');
        $url = '/index.php?m=Mobile&c=Group&is_ajax=1&a=goods_list&goods_type=group&p=';
        $this->assign('url', $url);
        return $this->fetch('goods_list/goods_list');
    }
}