<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use think\db;
use think\Cache;
use think\AjaxPage;
use think\Page;

class Notice extends Base
{

    /*
     * 系统通知列表
     */
    public function noticeList()
    {
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['content']) {
                $where['content'] = array('like', "%$input[content]%");
            }

            if ($input['start_time'] && $input['end_time']) {
                $where['create_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }
        }

        $count = M('n_notice')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_notice')->where($where)->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    /*
     * 确认发送系统通知
     * */
    public function send()
    {
        $id = input('id');
        if ($id) {
            //先获取该条信息
            $notice = M('n_notice')->where('id', $id)->find();
            if ($notice && $notice['is_send'] == '0') {

                //获取所有用户
                $userAll = M('users')->select();

                foreach ($userAll as $k => $v) {
                    $userNoticeArray = array();
                    $userNoticeArray['user_id'] = $v['user_id'];
                    $userNoticeArray['content'] = $notice['content'];
                    $userNoticeArray['type'] = 1;
                    $userNoticeArray['is_read'] = 0;
                    $userNoticeArray['create_time'] = time();

                    $Rt = M('n_user_notice')->add($userNoticeArray);
                }

                //标记该通知已发
                $update = array();
                $update['is_send'] = 1;
                $update['send_time'] = time();
                $update = M('n_notice')->where('id', $id)->update($update);

                $this->success('发送成功');

            } else {
                $this->error('发送失败');
            }

        } else {
            $this->error('发送失败');
        }
    }

    /*
     * 消息通知添加
     * */
    public function noticeAdd()
    {
        $input = input('');

        if ($input['add']) {
            if ($input['content'] == null) {
                $this->error('内容不能为空');
            }

            $data = array();
            //新增
            $data['create_time'] = time();
            $data['content'] = $input['content'];
            $data['type'] = 1;
            $data['is_send'] = 0;
            $Rt = M('n_notice')->add($data);

            $this->success('添加成功', 'noticeList');
        }
        return $this->fetch();
    }

    /*
     * 消息通知编辑
     * */
    public function noticeUpdate()
    {
        $input = input('');

        if (!$input['update']) {
            if ($input['id'] > '0') {

                if ($input['content'] == null) {
                    $this->error('内容不能为空');
                }

                $data = array();
                //编辑
                $data['id'] = $input['id'];
                $data['content'] = $input['content'];
                $data['type'] = 1;
                $data['is_send'] = 0;
                $Rt = M('n_notice')->where('id', $input['id'])->update($data);
                $this->success('修改成功', 'noticeList');
            }
        } else {
            $data = M('n_notice')->where('id', $input['id'])->find();
            $this->assign('data', $data);
            return $this->fetch();
        }
    }

}