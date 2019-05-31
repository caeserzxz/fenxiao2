<?php

namespace app\common\logic;

use app\common\model\PromGoods;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use think\Model;
use think\db;

/**
 * 奖金获取逻辑
 */
class UserReward extends Model
{
    /**
     * 获取用户管理奖
     **/
    public function getManagentMoney($userId)
    {
        $time=mktime(0,0,0,date('m'),1,date('Y'));
        $getAll = M("n_amount_log")->where('create_time','<',$time)->where('user_id',$userId)->where('reward_type',2)->where('status',0)->select();
        $getMonth = M("n_amount_log")->where('create_time','>=',$time)->where('user_id',$userId)->where('reward_type',2)->where('status',0)->select();
        $result['numMonth'] = $this->selectMoney($getMonth);//本月佣金
        $result['num'] = $this->selectMoney($getAll);//可提取佣金
        $result['ids'] = $this->getAmountLog($getAll);
        return $result;
    }
    /**
     * 获取用户上荐奖
     **/
    public function getCommendMoney($userId)
    {
        $time=mktime(0,0,0,date('m'),1,date('Y'));
        $getAll = M("n_amount_log")->where('create_time','<',$time)->where('user_id',$userId)->where('reward_type',3)->where('status',0)->select();
        $getMonth = M("n_amount_log")->where('create_time','>=',$time)->where('user_id',$userId)->where('reward_type',3)->where('status',0)->select();
        $result['numMonth'] = $this->selectMoney($getMonth);//本月佣金
        $result['num'] = $this->selectMoney($getAll);//可提取佣金
        $result['ids'] = $this->getAmountLog($getAll);
        return $result;
    }
    /**
     * 获取用户已发放上荐奖
     **/
    public function sentCommendMoney($userId)
    {
        $time=mktime(0,0,0,date('m'),1,date('Y'));
        $getAll = M("n_amount_log")->where('create_time','<',$time)->where('user_id',$userId)->where('reward_type',3)->where('status',1)->select();
        $getMonth = M("n_amount_log")->where('create_time','>=',$time)->where('user_id',$userId)->where('reward_type',3)->where('status',1)->select();
        $result['numMonth'] = $this->selectMoney($getMonth);//本月佣金
        $result['num'] = $this->selectMoney($getAll);//可提取佣金
        $result['ids'] = $this->getAmountLog($getAll);
        return $result;
    }
    /**
     * 获取用户分销奖
     **/
    public function getDistributionMoney($userId)
    {
        $time=mktime(0,0,0,date('m'),1,date('Y'));
        $getAll = M("n_amount_log")->where('create_time','<',$time)->where('user_id',$userId)->where('reward_type',1)->where('status',0)->select();
        $getMonth = M("n_amount_log")->where('create_time','>=',$time)->where('user_id',$userId)->where('reward_type',1)->where('status',0)->select();
        $result['numMonth'] = $this->selectMoney($getMonth);//本月佣金
        $result['num'] = $this->selectMoney($getAll);//可提取佣金
        $result['ids'] = $this->getAmountLog($getAll);
        return $result;
    }
    /**
     * 获取用户已发放分销奖
     **/
    public function sentDistributionMoney($userId)
    {
        $time=mktime(0,0,0,date('m'),1,date('Y'));
        $getAll = M("n_amount_log")->where('create_time','<',$time)->where('user_id',$userId)->where('reward_type',1)->where('status',1)->select();
        $getMonth = M("n_amount_log")->where('create_time','>=',$time)->where('user_id',$userId)->where('reward_type',1)->where('status',1)->select();
        $result['numMonth'] = $this->selectMoney($getMonth);//本月佣金
        $result['num'] = $this->selectMoney($getAll);//可提取佣金
        $result['ids'] = $this->getAmountLog($getAll);
        return $result;
    }
    /**
     *  获取代收佣金
     **/
    public function getReRewardMoney($userId){
        $time=mktime(0,0,0,date('m'),1,date('Y'));
        //查找对应下级
        $getCid = M("n_user_management")->where('management_id',$userId)->select();
        //获取总佣金
        $num = 0;
        $month = 0;
        foreach($getCid as &$value){
            $check = M('users')->where('user_id',$value['user_id'])->find();
            if($check['user_type']==2){
                continue;
            }
            //获取一个下级的可提取总佣金
                $getAll = M("n_amount_log")->where('create_time','<',$time)->where('user_id',$value['user_id'])->where('reward_type','in','1,2,3')->where('status',0)->select();
            $n = $this->selectMoney($getAll);
            $num = $n+$num;
            //获取一个下级的当月佣金
            $getMonth = M("n_amount_log")->where('create_time','>=',$time)->where('user_id',$value['user_id'])->where('reward_type','in','1,2,3')->where('status',0)->select();
            $m = $this->selectMoney($getMonth);
            $month =$m + $month;
        }
        $result = array(
            'num'=>$num,//可提取代收佣金
            'month'=>$month//当月代收佣金
        );
        return $result;
    }
    /**
     * 未发放佣金
     **/
    public function totalMonth($userId){
        $man = $this->getManagentMoney($userId);
        $com = $this->getCommendMoney($userId);
        $dis = $this->getDistributionMoney($userId);
        $count = $man['num']+$com['num']+$dis['num'];
        if(!empty($userId)){
            return $count;
        }else{
            return false;
        }
    }
    //已发放功德
    public function sentedGongde($userId){
        $com = $this->sentCommendMoney($userId);
        $dis = $this->sentDistributionMoney($userId);
        $count = $com['num']+$dis['num'];
        if(!empty($userId)){
            return $count;
        }else{
            return false;
        }
    }
    /**
     * 待发佣金
     **/
    public function unSent($userId){
        $man = $this->getManagentMoney($userId);
        $com = $this->getCommendMoney($userId);
        $dis = $this->getDistributionMoney($userId);
        $count = $man['numMonth']+$com['numMonth']+$dis['numMonth'];
        if(!empty($userId)){
            return $count;
        }else{
            return false;
        }
    }

    /**
     * 获取amount_log的id，逗号分隔。标记哪些流水被提出处理
     **/
    public function getAmountLog($arr){
        $ids = array();
        foreach($arr as &$value){
            $ids[] = $value['id'];
        }
        $ids = implode(',',$ids);
        return $ids;
    }
    /**
     * 返回金额总数
     **/
    public function selectMoney($arr)
    {
        if(empty($arr)){
            return 0;
        }
        $num = 0;
        for($i=0;$i<count($arr);$i++){
            $num = $arr[$i]['money']+$num;
        }
        return $num;
    }

    //返回拼接后的ids
    public function returnStr($str1 = "",$str2 = "",$str3 = ""){

        if(empty($str1)&&empty($str2)&&empty($str3)){
            return null;
        }

        if(empty($str1)&&empty($str2)){
            return $str3;
        }

        if(empty($str1)&&empty($str3)){
            return $str2;
        }

        if(empty($str2)&&empty($str3)){
            return $str1;
        }

        if(!empty($str1)&&!empty($str2)&&!empty($str3)){
            return $str1.",".$str2.",".$str3;
        }

        if(empty($str1)&&!empty($str2)&&!empty($str3)){
            return $str2.",".$str3;
        }

        if(!empty($str1)&&empty($str2)&&!empty($str3)){
            return $str1.",".$str3;
        }

        if(!empty($str1)&&!empty($str2)&&empty($str3)){
            return $str1.",".$str2;
        }

        return null;

    }
}