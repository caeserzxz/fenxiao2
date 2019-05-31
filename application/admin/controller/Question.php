<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use think\db;
use think\Cache;
use think\AjaxPage;
use think\Page;

class Question extends Base
{

    /*
     * 常见问题列表
     */
    public function questionList()
    {
        $input = input('');
        $where = array();

        $count = M('n_question')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_question')->where($where)->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }


    /*
     * 常见问题添加
     * */
    public function questionAdd()
    {
        $input = input('');

        if ($input['add']) {
            if ($input['question'] == null) {
                $this->error('问题不能为空');
            }
            if ($input['answer'] == null) {
                $this->error('答案不能为空');
            }

            $data = array();
            //新增
            $data['create_time'] = time();
            $data['question'] = $input['question'];
            $data['answer'] = $input['answer'];

            $Rt = M('n_question')->add($data);

            $this->success('添加成功', 'questionList');
        }
        return $this->fetch();
    }

    /*
     * 常见问题编辑
     * */
    public function questionUpdate()
    {
        $input = input('');

        if (!$input['update']) {
            if ($input['id'] > '0') {

                if ($input['question'] == null) {
                    $this->error('问题不能为空');
                }
                if ($input['answer'] == null) {
                    $this->error('答案不能为空');
                }

                $data = array();
                //编辑
                $data['question'] = $input['question'];
                $data['answer'] = $input['answer'];

                $Rt = M('n_question')->where('id', $input['id'])->update($data);
                $this->success('修改成功', 'questionList');
            }
        } else {
            $data = M('n_question')->where('id', $input['id'])->find();
            $this->assign('data', $data);
            return $this->fetch();
        }
    }


    /*
     * 常见问题删除
     *
     * */
    public function questionDelete()
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

        $Rt = M('n_question')->where('id', $input['id'])->delete();

        $return_arr = array(
            'status' => 1,
            'msg' => '删除成功',
            'data' => array('url' => U('Question/questionList')),
        );
        $this->ajaxReturn($return_arr);

    }

}