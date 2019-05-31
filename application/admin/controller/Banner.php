<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use think\db;
use think\Cache;
use think\AjaxPage;
use think\Page;

class Banner extends Base
{

    /*
     * 轮转图列表
     */
    public function bannerList()
    {
        $input = input('');
        $where = array();

        $count = M('n_banner')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_banner')->where($where)->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }



    /*
     * 轮转图添加
     * */
    public function bannerAdd()
    {
        $input = input('');

        if ($input['add']) {
            if ($input['banner_path'] == null) {
                $this->error('轮转图不能为空');
            }
            if (!isset($input['is_show'])) {
                $this->error('请选择轮转图状态');
            }

           if($input['article_id'] && $input['goods_id']){
                $this->error('关联文章和关联商品只能选择其中一种情况');
            }

            if($input['article_id'] <= '0' && $input['goods_id'] <= '0'){
                $this->error('请选择关联文章或商品');
            }
            if(!isset($input['article_id'])){
                $input['article_id'] = 0;
            }
            $data = array();
            //新增
            $data['create_time'] = time();
            $data['article_id'] = $input['article_id'];
            $data['goods_id'] = $input['goods_id'];
            $data['banner_path'] = $input['banner_path'];
            $data['is_show'] = $input['is_show'];

            $Rt = M('n_banner')->add($data);

            $this->success('添加成功', 'bannerList');
        }

        //文章列表
        $articleList = M('article')->where('article_id', '>', '0')->select();

        //获取所有上架商品，让banner关联跳转
        $goodsList = M('goods')->where('is_on_sale', '1')->select();

        $this->assign('articleList', $articleList);
        $this->assign('goodsList', $goodsList);
        return $this->fetch();
    }

    /*
     * 轮转图编辑
     * */
    public function bannerUpdate()
    {
        $input = input('');

        if (!$input['update']) {
            if ($input['id'] > '0') {

                if ($input['banner_path'] == null) {
                    $this->error('轮转图不能为空');
                }
                if (!isset($input['is_show'])) {
                    $this->error('请选择轮转图状态');
                }

              if($input['article_id'] && $input['goods_id']){
                    $this->error('关联文章和关联商品只能选择其中一种情况');
                }

                if($input['article_id'] <= '0' && $input['goods_id'] <= '0'){
                    $this->error('请选择关联文章或商品');
                }

                $data = array();
                //编辑
                $data['create_time'] = time();
                $data['banner_path'] = $input['banner_path'];
                $data['is_show'] = $input['is_show'];
                $data['article_id'] = $input['article_id'];
                $data['goods_id'] = $input['goods_id'];

                $Rt = M('n_banner')->where('id', $input['id'])->update($data);
                $this->success('修改成功', 'bannerList');
            }
        } else {
            $data = M('n_banner')->where('id', $input['id'])->find();

            //文章列表
            $articleList = M('article')->where('article_id', '>', '0')->select();

            //获取所有上架商品，让banner关联跳转
            $goodsList = M('goods')->where('is_on_sale', '1')->select();

            $this->assign('articleList', $articleList);
            $this->assign('goodsList', $goodsList);
            $this->assign('data', $data);
            return $this->fetch();
        }
    }


    /*
     * 轮转图删除
     *
     * */
    public function bannerDelete()
    {
        $input = input('');

        if (!$input['id']) {
            $return_arr = array(
                'status' => -1,
                'msg' => '缺少必要参数，删除失败',
                'data' => '',
            );
            $this->ajaxReturn($return_arr);
        }

        $Rt = M('n_banner')->where('id', $input['id'])->delete();

        $return_arr = array(
            'status' => 1,
            'msg' => '删除成功',
            'data' => array('url' => U('Banner/bannerList')),
        );
        $this->ajaxReturn($return_arr);

    }

    //图标列表
    public function iconList()
    {
        $input = input('');
        $where = array();

        $count = M('n_icon')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_icon')->where($where)->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    //添加图标
    public function iconAdd()
    {
        $input = input('');

        if ($input['add']) {
            if ($input['img_path'] == null) {
                $this->error('图标不能为空');
            }
            if (!isset($input['is_show'])) {
                $this->error('请选择图标状态');
            }

            $data = array();
            //新增
            $data['create_time'] = time();
            $data['img_path'] = $input['img_path'];
            $data['is_show'] = $input['is_show'];
            $data['title'] = $input['title'];
            $data['url'] = $input['url'];

            $Rt = M('n_icon')->insert($data);

            $this->success('添加成功', 'iconList');
        }

        return $this->fetch();

    }

    //图标编辑
    public function iconUpdate()
    {
        $input = input('');
        if (!$input['update']) {
            if ($input['id'] > '0') {

                if ($input['img_path'] == null) {
                    $this->error('图标不能为空');
                }
                if (!isset($input['is_show'])) {
                    $this->error('请选择图标状态');
                }

                $data = array();
                //编辑
                $data['create_time'] = time();
                $data['img_path'] = $input['img_path'];
                $data['is_show'] = $input['is_show'];
                $data['title'] = $input['title'];
                $data['url'] = $input['url'];


                $Rt = M('n_icon')->where('id', $input['id'])->update($data);
                $this->success('修改成功', 'iconList');
            }
        } else {
            $data = M('n_icon')->where('id', $input['id'])->find();
            $this->assign('data', $data);
            return $this->fetch();
        }

    }

    //图标删除
    public function iconDel()
    {
        $input = input('');

        if (!$input['id']) {
            $return_arr = array(
                'status' => -1,
                'msg' => '缺少必要参数，删除失败',
                'data' => '',
            );
            $this->ajaxReturn($return_arr);
        }

        $Rt = M('n_icon')->where('id', $input['id'])->delete();

        $return_arr = array(
            'status' => 1,
            'msg' => '删除成功',
            'data' => array('url' => U('Banner/iconList')),
        );
        $this->ajaxReturn($return_arr);

    }


}