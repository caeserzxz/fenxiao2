<?php

namespace app\mobile\controller;

use think\Controller;
use Think\Db;
use app\common\logic\UsersLogic;
use \think\Request;

class Category extends Controller
{

    /**
     * 通过分类，查找分类的商品列表
     *
     * */
    public function goodsList()
    {
        $input = input('');
        $parent_id = $input['parent_id'];
        $category_id = $input['category_id'];

        $Where = array();
        if (isset($input['goods_name']) && !empty($input['goods_name'])) {
            $Where['goods_name'] = array('like', "%$input[goods_name]%");
        }

        if ($parent_id) {
            //通过一级分类，找到所有子分类
            $cate = M('goods_category')
                ->where('parent_id', $parent_id)
                ->select();

            $sonCate = array();
            if ($cate) {
                foreach ($cate as $k => $v) {
                    array_push($sonCate, $v['id']);
                }
            }

            $goodsList = M('goods')
                ->where($Where)
                ->where('cat_id', 'in', $sonCate)
                ->where('is_on_sale', '1')
                ->order('goods_id desc')
                ->select();
        }

        if ($category_id) {
            //通过最低级分类，找到该级分类的商品

            $goodsList = M('goods')
                ->where($Where)
                ->where('cat_id', $category_id)
                ->where('is_on_sale', '1')
                ->order('goods_id desc')
                ->select();
        }


        $this->assign('goodsList', $goodsList);
        $this->assign('parent_id', $parent_id);
        $this->assign('category_id', $category_id);
        return $this->fetch();
    }



    /**
     * 零元商品列表
     *
     * */
    public function freeGood()
    {
        $input = input('');

        $Where = array();
        if (isset($input['goods_name']) && !empty($input['goods_name'])) {
            $Where['goods_name'] = array('like', "%$input[goods_name]%");
        }

        $goodsList = M('goods')
            ->where($Where)
            ->where('g_type', '3')//0-普通商品 1-外链商品跳转到京东淘宝 2-身份商品 3-零元商品 4-积分商品
            ->where('is_on_sale', '1')
            ->order('goods_id desc')
            ->select();


        $this->assign('goodsList', $goodsList);
        return $this->fetch();
    }


    /**
     * 零元商品列表
     *
     * */
    public function integralGood()
    {
        $input = input('');

        $Where = array();
        if (isset($input['goods_name']) && !empty($input['goods_name'])) {
            $Where['goods_name'] = array('like', "%$input[goods_name]%");
        }

        $goodsList = M('goods')
            ->where($Where)
            ->where('g_type', '4')//0-普通商品 1-外链商品跳转到京东淘宝 2-身份商品 3-零元商品 4-积分商品
            ->where('is_on_sale', '1')
            ->order('goods_id desc')
            ->select();


        $this->assign('goodsList', $goodsList);
        return $this->fetch();
    }
}