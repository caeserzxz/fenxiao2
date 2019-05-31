<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use think\db;
use think\Cache;
use think\AjaxPage;
use think\helper\Arr;
use think\Page;

class Apply extends Base
{



    /*
   * 身份证审核列表
   */
    public function idcardList()
    {
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['name']) {
                $where['b.real_name'] = array('like', "%$input[name]%");
            }
            if ($input['start_time'] && $input['end_time']) {
                $where['a.create_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }
        }

        $count = M('n_user_idcard_apply')->where($where)
            ->alias('a')
            ->join('users b', 'a.user_id=b.user_id')
            ->where('a.positive_path', 'not null')
            ->where('a.reverse_path', 'not null')
            ->where('b.id_card', 'not null')
            ->count();

        $page = new Page($count);
        $lists = M('n_user_idcard_apply')->where($where)
            ->alias('a')
            ->field('b.real_name,b.id_card,a.positive_path,a.reverse_path,a.create_time,
            a.status,a.id')
            ->join('users b', 'a.user_id=b.user_id')
            ->where('a.positive_path', 'not null')
            ->where('a.reverse_path', 'not null')
            ->where('b.id_card', 'not null')
            ->where($where)
            ->order('a.create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }


    /*
     * 兑换商品申请列表(只查看，不需要处理)
     *
     * */
    public function exchangeList()
    {

        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件

            if ($input['start_time'] && $input['end_time']) {
                $where['add_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }
        }

        $count = M('n_apply_exchange')->where($where)
            ->where($where)
            ->count();

        $page = new Page($count);
        $lists = M('n_apply_exchange')->where($where)
            ->order('add_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach($lists as $k=>$v){
            $userInfo=M('users')->where('user_id',$v['user_id'])->find();
            $lists[$k]['userInfo']=$userInfo;
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    //提现申请列表
    public function cashWithdrawalList(){
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['start_time'] && $input['end_time']) {
                $where['add_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }
        }

        $count = M('n_withdraw')->where($where)
            ->where($where)
            ->count();

        $page = new Page($count);
        $lists = M('n_withdraw')->where($where)
            ->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach($lists as $k=>$v){
            $userInfo=M('users')->where('user_id',$v['user_id'])->find();
            $lists[$k]['userInfo']=$userInfo;
            $type = M('n_withdraw_type')->where('id',$v['withdraw_type_id'])->find();
            $lists[$k]['withdraw_type'] = $type['type'];
            $bank = M('n_bank')->where('id',$v['bank_id'])->find();
            $lists[$k]['bank'] = $bank['name'];
        }

        //提现成功金额数
        $sumAllMoney = Db::name('n_withdraw')
            ->where('status',2)
            ->sum('withdraw_money');

        //提现未支付金额（审核中）
        $sumUnCash = Db::name('n_withdraw')
            ->where('status',0)
            ->sum('withdraw_money');

        $this->assign('page', $page->show());
        $this->assign('sumAllMoney', $sumAllMoney);
        $this->assign('sumUnCash', $sumUnCash);
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();

    }

    /**
     * 提现待审核列表
     *
    */
    public function waitWithdrawalList(){
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['start_time'] && $input['end_time']) {
                $where['add_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }
        }
//        $where['status'] = 0 ;
        $count = M('n_withdraw')->where($where)
            ->where($where)
            ->where('status=0')
            ->count();

        $page = new Page($count);
        $lists = M('n_withdraw')->where($where)
            ->where('status=0')
            ->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach($lists as $k=>$v){
            $userInfo=M('users')->where('user_id',$v['user_id'])->find();
            $lists[$k]['userInfo']=$userInfo;
            $type = M('n_withdraw_type')->where('id',$v['withdraw_type_id'])->find();
            $lists[$k]['withdraw_type'] = $type['type'];
            $bank = M('n_bank')->where('id',$v['bank_id'])->find();
            $lists[$k]['bank'] = $bank['name'];
        }

        //提现成功金额数
        $sumAllMoney = Db::name('n_withdraw')
            ->where('status',2)
            ->sum('withdraw_money');

        //提现未支付金额（审核中）
        $sumUnCash = Db::name('n_withdraw')
            ->where('status',0)
            ->sum('withdraw_money');

        $this->assign('page', $page->show());
        $this->assign('sumAllMoney', $sumAllMoney);
        $this->assign('sumUnCash', $sumUnCash);
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();

    }



    //提现审核操作
    public function applyWithdraw(){
        $input = input('');
        $status = input('status');
        $withdrawId = input('id');
        $data = array(
            'status'=>$status
        );
        //提现不通过返回金额
        if($status == 3 ){
            $withdrawInfo = M('n_withdraw')->where('id',$input['id'])->find();
            $userInfo = M('users')->where('user_id',$withdrawInfo['user_id'])->find();
            $userData = array(
                'user_money'=>$userInfo['user_money']+$withdrawInfo['withdraw_money']+$withdrawInfo['service_charge']
            );
            $upUser = M('users')->where('user_id',$withdrawInfo['user_id'])->update($userData);
            $logData = array(
                'user_id'=>$withdrawInfo['user_id'],
                'money'=>$withdrawInfo['withdraw_money']+$withdrawInfo['service_charge'],
                'type'=>1,
                'number'=>null,
                'desc'=>"提现拒绝返回用户金额",
                'create_time'=>time(),
            );
            $log = M('n_amount_log')->insert($logData);
        }


        $update = M('n_withdraw')->where('id',$withdrawId)->update($data);
        if($update !== false){
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => '',
                );
                $this->ajaxReturn($return_arr);
        }else{
            $return_arr = array(
                'status' => -1,
                'msg' => '操作失败',
                'data' => '',
            );
            $this->ajaxReturn($return_arr);
        }
    }

    /**
     * 提现待转账列表
     *
     */
    public function waitTransferList(){
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['start_time'] && $input['end_time']) {
                $where['add_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }
        }
//        $where['status'] = 0 ;
        $count = M('n_withdraw')->where($where)
            ->where($where)
            ->where('status=2')
            ->count();

        $page = new Page($count);
        $lists = M('n_withdraw')->where($where)
            ->where('status=2')
            ->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach($lists as $k=>$v){
            $userInfo=M('users')->where('user_id',$v['user_id'])->find();
            $lists[$k]['userInfo']=$userInfo;
            $type = M('n_withdraw_type')->where('id',$v['withdraw_type_id'])->find();
            $lists[$k]['withdraw_type'] = $type['type'];
            $bank = M('n_bank')->where('id',$v['bank_id'])->find();
            $lists[$k]['bank'] = $bank['name'];
        }

        //提现成功金额数
        $sumAllMoney = Db::name('n_withdraw')
            ->where('status',2)
            ->sum('withdraw_money');

        //提现未支付金额（审核中）
        $sumUnCash = Db::name('n_withdraw')
            ->where('status',0)
            ->sum('withdraw_money');

        $this->assign('page', $page->show());
        $this->assign('sumAllMoney', $sumAllMoney);
        $this->assign('sumUnCash', $sumUnCash);
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();

    }

}