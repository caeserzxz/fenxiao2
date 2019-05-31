<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use think\db;
use think\Cache;
use think\AjaxPage;
use think\Page;

class Express extends Base
{

    /*
     * 物流公司列表
     */
    public function expressList()
    {
        $input = input('');
        $where = array();

        $count = M('n_express')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_express')->where($where)->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }


    /*
     * 物流公司配置添加
     * */
    public function expressAdd()
    {
        $input = input('');

        if ($input['add']) {
            if ($input['name'] == null) {
                $this->error('名称不能为空');
            }
            if ($input['code'] == null) {
                $this->error('编码不能为空');
            }

            if ($input['code2'] == null) {
                $this->error('快递鸟编码不能为空');
            }

            $data = array();
            //新增
            $data['create_time'] = time();
            $data['name'] = $input['name'];
            $data['code'] = $input['code'];
            $data['code2'] = $input['code'];

            $Rt = M('n_express')->add($data);

            $this->success('添加成功', 'expressList');
        }
        return $this->fetch();
    }

    /*
     * 物流公司编辑
     * */
    public function expressUpdate()
    {
        $input = input('');

        if (!$input['update']) {
            if ($input['id'] > '0') {

                if ($input['name'] == null) {
                    $this->error('名称不能为空');
                }
                if ($input['code'] == null) {
                    $this->error('编码不能为空');
                }
                if ($input['code2'] == null) {
                    $this->error('快递鸟编码不能为空');
                }

                $data = array();
                //编辑
                $data['name'] = $input['name'];
                $data['code'] = $input['code'];
                $data['code2'] = $input['code2'];

                $Rt = M('n_express')->where('id', $input['id'])->update($data);
                $this->success('修改成功', 'expressList');
            }
        } else {
            $data = M('n_express')->where('id', $input['id'])->find();
            $this->assign('data', $data);
            return $this->fetch();
        }
    }


    /*
     * 物流公司删除
     *
     * */
    public function expressDelete()
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

        $Rt = M('n_express')->where('id', $input['id'])->delete();

        $return_arr = array(
            'status' => 1,
            'msg' => '删除成功',
            'data' => array('url' => U('Express/expressList')),
        );
        $this->ajaxReturn($return_arr);

    }

}