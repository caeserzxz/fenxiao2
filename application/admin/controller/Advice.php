<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use think\db;
use think\Cache;
use think\AjaxPage;
use think\Page;

class Advice extends Base
{

    /*
     * 意见反馈列表
     */
    public function AdviceList()
    {
        $input = input('');
        $where = array();
        if ($input['id']) {

            //查询条件
            if ($input['phone']) {
                $where['a.phone'] = array('like', "%".trim($input['phone'])."%");
            }

            if(!empty($input['user_type']))
            {
                $where['b.user_type'] = $input['user_type']-1;
            }

//            if ($input['start_time'] && $input['end_time']) {
//                $where['create_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
//            }
        }

        $count = db('n_advice')
            ->alias('a')
            ->join('users b','a.user_id = b.user_id')
            ->where($where)
            ->count();

        $page = new Page($count);
        $lists = M('n_advice')
            ->alias('a')
            ->join('users b','a.user_id = b.user_id')
            ->where($where)
            ->field('a.* , b.user_type')
            ->order('id desc')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();


        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }


}