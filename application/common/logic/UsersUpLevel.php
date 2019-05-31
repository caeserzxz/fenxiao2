<?php

namespace app\common\logic;

use app\common\model\UserAddress;
use app\common\model\UserLevel;
use app\common\model\Users;
use think\Log;
use think\Model;
use think\Page;
use think\db;

/**
 * 黄丽建项目用户身份晋升逻辑、分佣逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class UsersUpLevel extends Model
{
    protected $orderInfo = [];
    protected $orderGoods = [];
    protected $userInfo = [];
    protected $user_id = '';
    protected $levels = [];
    protected $total_goods = [];

    public function SubCommission($order_id){
        //获取订单信息
        $orderInfo = M('order')
            ->where('order_id',$order_id)
            ->find();

        $this->orderInfo = $orderInfo;

        //获取订单商品信息
        $orderGoods =  M('order_goods')
            ->where('order_id',$order_id)
            ->select();

        $this->orderGoods = $orderGoods;
        //获取当前用参与分佣的金额与数量
        $total_goods =  $this->getOrderPrice();
        if(empty($total_goods)){
            $return['msg'] = '没有购买身份产品,不参与分佣';
            $return['status'] = 1;
            return $return;
        }
        $this->total_goods= $total_goods;

        //获得当前用户信息
        $this->user_id = $orderInfo['user_id'];
        $userInfo = $this->getUserInfo($orderInfo['user_id']);
        $this->userInfo = $userInfo;
        //获取等级信息
        $this->levels = $this->getLevels();


        //开启事务
        M('users')->startTrans();
        M('n_wait_commission')->startTrans();

        //当用户存在上级才进行,给上级分佣
        if($userInfo['pid']){
            //直推奖分佣
            $direct = $this->directPrice();

            //团队管理奖
            $team = $this->teamPrice();

            //平级奖


        }
        //更新团队业绩,并升级
        $achievement = $this->updateAchievement($userInfo['user_id']);

        //区域奖分佣
        $region = $this->regionPrice();

        if($direct||$team||$region||$achievement){
            M('users')->rollback('users');
            M('users')->rollback('n_wait_commission');
            $return['msg'] = '分佣过程出错,请联系管理员';
            $return['status'] = 1;
        }else{
            M('users')->commit('users');
            M('users')->commit('n_wait_commission');
            $return['msg'] = '分佣成功';
            $return['status'] = 2;
        }
        return $return;

    }

    /**
     *  直推奖
     *
     */
    public function directPrice(){
        $orderInfo = $this->orderInfo;
        $userInfo = $this->userInfo;
        $total_goods = $this->total_goods;

        //获取上级用户信息
        $top_userInfo = $this->getUserInfo($userInfo['pid']);

        //获取上级用户等级信息
        $top_level = $this->getLevelInfo($top_userInfo['level']);
        //如果上级等级大于粉丝,则分佣直推奖
        if($top_userInfo['level']>1){

            //更新用户冻结金额
            $commission_price = $total_goods['total_money'] * $top_level['distribution_one_especial']/100;
            $code1 = $this->updateWaitMoney($top_userInfo['user_id'],$commission_price);
            //添加用户冻结流水
            $code2 = $this->addRecord($orderInfo['order_id'],$commission_price,$top_userInfo['user_id'],0);

            //sql执行失败,返回状态
            if(empty($code1)||empty($code2)){
                // 回滚事务，记录日志
                Log::error("身份产品，分佣过程直推奖励出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                return 10001;
                exit;

            }
        }
    }

    //团队管理奖
    public  function teamPrice(){
        $userInfo = $this->userInfo;
        //获取上级用户信息
        if(!empty($userInfo['pid'])){
           $a =  $this->getTeams($userInfo['pid'],0,0);
           return $a;
        }


    }
    //团队级差分佣

    /**
     * @param $user_id    当前分佣的用户
     * @param $level_id   最近一次分佣的等级
     * @param $ratio     已分佣的比例和
     * @param $is_pingji    是否已分佣过平级
     * @return float|int|string
     */
    public function getTeams($user_id, $level_id, $ratio){
        $orderInfo = $this->orderInfo;
        $total_goods = $this->total_goods;
        $userInfo = $this->getUserInfo($user_id);
        if($userInfo['level']>$level_id&&$userInfo['level']>2){
            $userLevel = $this->getLevelInfo($userInfo['level']);

            //分佣
            $user_ratio = $userLevel['team_price']-$ratio;
            if($user_ratio>0){
                $total_amount = $total_goods['total_money'] * $user_ratio/100;
                //更新用户冻结金额
                $return_code3 = $this->updateWaitMoney($userInfo['user_id'],$total_amount);
                //添加待返佣金流水
                $return_code4 = $this->addRecord($orderInfo['order_id'],$total_amount,$userInfo['user_id'],1);

                //sql执行失败,返回状态
                if(empty($return_code3)||empty($return_code4)){
                    Log::error("身份产品，分佣过程中团队奖励出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                    return 10002;
                    exit;
                }
            }
            //分佣平级奖金
            if($userInfo['level']>5){
                $top_userInfo = $this->getUserInfo($userInfo['pid']);
                $identical = $this->identicalPrice($top_userInfo['user_id'],$userInfo['level']);
                if($identical){
                    return '分佣平级奖失败';
                }
            }


            $ratio = $userLevel['team_price'];//分佣过的比例和
            $level_id = $userInfo['level'];//当前分佣的等级

        }
        if(empty($userInfo['pid'])||$userInfo['level']==9)
        {
            return  '';
            exit;
        }
        else
        {
            $c = $this->getTeams($userInfo['pid'],$level_id,$ratio);

        }
        if($c){
            return $c;
            exit;
        }
    }

    //平级奖
    public function identicalPrice($user_id,$level){
        $orderInfo = $this->orderInfo;

        $top_userInfo = $this->getUserInfo($user_id);
        //遇到更高等级的停止平级奖分佣
        if($top_userInfo['level']>$level){
            return '';
            exit;
        }

        if($level==$top_userInfo['level'])
        {

            $identLevel = M('user_level')->where('level_id',$top_userInfo['level'])->find();
            //更新平级的冻结余额
            $data['wait_money'] = $top_userInfo['wait_money']+$identLevel['same_level'];
            $code4 = M('users')->where('user_id',$top_userInfo['user_id'])->save($data);
            //添加流水
            $code5 = $this->addRecord($orderInfo['order_id'],$identLevel['same_level'],$top_userInfo['user_id'],3);

            //sql执行失败,返回状态
            if(empty($code4)||empty($code5)){
                Log::error("身份产品，分佣过程中平级奖励出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                return  10003;
                exit;
            }else{
                return  '';
                exit;
            }

        }

            if(empty($top_userInfo['pid']))
            {
                return '';
                exit;
            }else
            {
                $c = $this->identicalPrice($top_userInfo['pid'],$level);
            }

            if($c){
                return $c;
                exit;
            }
    }

    //区域奖金
    public function regionPrice(){
        $orderInfo = $this->orderInfo;

        //省代
        $provinces= M('users')->where(array('level'=>9,'provinceid'=>$orderInfo['province']))->select();
        $province_level = $this->getLevelInfo(9);//省级配置信息

        //循环省代分佣
        foreach ($provinces as $key=>$value){
            //更新冻结金额
            $code6 = $this->updateWaitMoney($value['user_id'],$province_level['region_price']);
            //添加返佣流水
            $code7 = $this->addRecord($orderInfo['order_id'],$province_level['region_price'],$value['user_id'],4);
        }

        //市代
        $citys = M('users')->where(array('level'=>8,'cityid'=>$orderInfo['city']))->select();
        $city_level = $this->getLevelInfo(8);//市代配置信息

        //循环市代分佣
        foreach ($citys as $k=>$v){
            //更新冻结金额
            $code8 = $this->updateWaitMoney($v['user_id'],$city_level['region_price']);
            //添加返佣流水
            $code9 = $this->addRecord($orderInfo['order_id'],$city_level['region_price'],$v['user_id'],4);
        }

        //sql执行失败,返回状态
            return '';
            exit;


    }

    //升级
    public function upgradeLevel($user_id){
        $userInfo = $this->getUserInfo($user_id);
        if($userInfo['level']>1&&$userInfo['level']<7){
            $levels = $this->levels;
            $team_num = $userInfo['team_achievement'];

            if($levels[2]['achievement_num']<=$team_num && $team_num<$levels[3]['achievement_num']){
                $level_id = 3;//正式会员
            }else if($levels[3]['achievement_num']<=$team_num && $team_num<$levels[4]['achievement_num']){
                $level_id=4;//高级会员
            }else if($levels[4]['achievement_num']<=$team_num && $team_num<$levels[5]['achievement_num']){
                $level_id=5;//超级会员
            }else if($levels[5]['achievement_num']<=$team_num && $team_num<$levels[6]['achievement_num']){
                $level_id=6;//合伙人
            }else if($levels[6]['achievement_num']<=$team_num ){
                $level_id=7;//超级合伙人
            }else{
                $level_id = '';
            }

            if(!empty($level_id)&&$level_id>$userInfo['level']){
                $data['level'] = $level_id;
                $return['code'] = M('users')->where('user_id',$userInfo['user_id'])->save($data);
                $return['status'] = 1;
                return $return;
            }else{
                $return['status'] =2;
                return  $return;
            }

        }
    }


    //更新团队业绩
    public function updateAchievement($user_id){
        $orderInfo = $this->orderInfo;
        $userInfo = $this->getUserInfo($user_id);
        //给用户自己升级
        if($userInfo['user_id']==$this->user_id&&$userInfo['level']<2){
            $u_save['level'] = 2;
            $code12 = M('users')->where('user_id',$userInfo['user_id'])->save($u_save);
            //更新用户信息
            $userInfo = $this->getUserInfo($user_id);
            $this->userInfo = $userInfo ;

            //sql执行失败,返回状态
            if(empty($code12)){
                Log::error("身份产品，分佣过程中更新团队业绩自己升级出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                return 10005;
                exit;
            }
        }

//        if($userInfo['level']>1){
            $orderGoodsNum = $this->total_goods['total_num'];//产品数量
            $data['team_achievement'] = $userInfo['team_achievement'] + $orderGoodsNum;
            $code10=M('users')->where('user_id',$user_id)->save($data);

            //sql执行失败,返回状态
            if(empty($code10)){
                Log::error("身份产品，分佣过程中更新团队业绩出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                return 10005;
                exit;
            }
            //升级
            $code11=$this->upgradeLevel($userInfo['user_id']);
            //sql执行失败,返回状态
            if($code11['status']==1&&!empty($code['code'])){
                // 回滚事务，记录日志
                Log::error("身份产品，分佣过程中更新团队业绩出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                return 10005;
                exit;
            }

            if($userInfo['user_id']==$this->user_id){
                $this->userInfo =  $this->getUserInfo($this->user_id);
            }
//        }
        if(empty($userInfo['pid']))
        {
            return  '';
            exit;
        }
        else
        {
            $c = $this->updateAchievement($userInfo['pid'],$a);
        }
        if($c){
            return $c;
            exit;
        }
    }

    //获取当前等级配置
    public function getLevelInfo($level_id){
        $level_info = M('user_level')->where('level_id',$level_id)->find();
        return $level_info;
    }

    //获取所有等级信息
    public function getLevels(){
        $levels = M('user_level')->select();
        return $levels;
    }

    //获取用户信息
    public function getUserInfo($user_id){
        $user_info = M('users')->where('user_id',$user_id)->find();
        return $user_info ;
    }

    //更新用户冻结资金
    public function updateWaitMoney($user_id,$price){
        $userInfo = $this->getUserInfo($user_id);
        $data['wait_money'] = $userInfo['wait_money'] + $price;
        $code = M('Users')->where('user_id',$user_id)->save($data);
        return $code;
    }

    //更新用户余额
    public function updateUserMoney($user_id,$price){
        $userInfo = $this->getUserInfo($user_id);
        $data['user_money'] = $userInfo['user_money'] + $price;
        $code = M('Users')->where('user_id',$user_id)->save($data);
        return $code;
    }

    /*
    *    添加待返佣金流水表
    *    $order_id         订单id
    *    $money            获得的佣金
    *    $user_id          受益人
    *    $commission_type  分佣类型   '0，身份产品获得直推奖；1，身份产品获得团队奖；2，身份产品获得团队管理奖；3，身份产品获得评级奖金；4，身份产品获得区域奖；'
    * */
    public function addRecord($order_id,$money,$user_id,$commission_type){
        $orderInfo =  M('order')->where('order_id',$order_id)->find();
        $order_goods = M('order_goods')->where('order_id',$order_id)->find();

        $data['order_id'] = $order_id;
        $data['goods_id'] =  $order_goods['goods_id'];
        $data['user_id'] = $user_id;
        $data['money'] = $money;
        $data['status'] = 0 ;
        $data['goods_type'] = $orderInfo['order_type'];
        $data['commission_type'] = $commission_type;
        $data['create_time'] = time();
        $data['source_user_id'] = $orderInfo['user_id'];

        if($commission_type==0){
            $describe = '身份产品获得直推奖';
        }else if($commission_type==1){
            $describe = '身份产品获得团队奖';
        }else if($commission_type==2){
            $describe = '身份产品获得团队管理奖';
        }else if($commission_type==3){
            $describe = '身份产品获得平级奖';
        }else if($commission_type==4){
            $describe = '身份产品获得区域奖';
        }

        $data['describe'] = $describe;
        $code = M('n_wait_commission')->add($data);
        return $code;
     }
    //更新余额流水
    /*
    *    添加余额流水表
    *    $order_id  订单id
    *    $money     获得的佣金
    *    $user_id   受益人
    *    $desc      描述
    * */
    public function amountLog($order_id,$money,$user_id,$desc,$admin_id){
        $orderInfo =  M('order')->where('order_id',$order_id)->find();
        $order_goods = M('order_goods')->where('order_id',$order_id)->find();
        $data['user_id'] = $user_id;
        $data['money'] = $money;
        $data['desc'] = $desc;
        $data['create_time'] = time();
        $data['obj_user_id'] = $orderInfo['user_id'];
        $data['goods_id'] = $order_goods['goods_id'];
        $data['order_id'] = $orderInfo['order_id'];
        $data['admin_id'] = $admin_id;

        $code = M('n_amount_log')->add($data);
        return $code;
    }


    //获取订单商品参与分佣的金额
    public function getOrderPrice(){
        $orderGoods = $this->orderGoods;
        $total_money = 0;
        $total_num = 0;
        foreach ($orderGoods as $k=>$v){
            $goods = M('goods')->where('goods_id',$v['goods_id'])->find();
            //只有身份产品才进行分佣
            if($goods['g_type']==2){
                $total_money =  $total_money+($v['goods_num']*$v['goods_price']*$goods['commission']/100);
                $total_num = $total_num + $v['goods_num'];
            }
        }

        $data['total_money'] = $total_money;
        $data['total_num'] = $total_num;
        return $data;
    }


    /**
     * 确认收货等操作后,解冻金额,增加余额
     * @param $order_id
     * @return int
     */
    public function receivingGoods($order_id){
        $wiat_commission = M('n_wait_commission')->where('order_id',$order_id)->select();

        //开启事务
        M('users')->startTrans();
        M('n_wait_commission')->startTrans();
        M('n_amount_log')->startTrans();
        foreach($wiat_commission as $k=>$v){
            //更改待返状态
            $save['status'] = 1;
            $code1 = M('n_wait_commission')->where('com_id',$v['com_id'])->save($save);
            //添加余额流水
            $code2 = $this->amountLog($v['order_id'],$v['money'],$v['user_id'],$v['describe'],'');
            //更新账户余额与冻结金额
            $userInfo = $this->getUserInfo($v['user_id']);

            $save_user['wait_money'] = $userInfo['wait_money'] - $v['money'];
            $save_user['user_money'] = $userInfo['user_money'] + $v['money'];

            $code3 = M('users')->where('user_id',$v['user_id'])->save($save_user);
            if(!empty($code1)&&!empty($code2)&&!empty($code3)){
                $status=1000;
            }else{
                $status=1001;
                break;
            }
        }

        if($status==1000){
            M('users')->commit();
            M('n_wait_commission')->commit();
            M('n_amount_log')->commit();

            $return['msg'] = '分佣更新成功';
            $return['status'] = 1000;
        }else{
            M('users')->rollback();
            M('n_wait_commission')->rollback();
            M('n_amount_log')->rollback();
            Log::error("更新分佣状态出错_时间：".time()."_订单号：".$order_id);
            $return['msg'] = '分佣更新出错,请联系管理员';
            $return['status'] = 1001;
        }
        return $return;

    }
}