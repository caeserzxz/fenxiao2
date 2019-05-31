<?php

namespace app\common\logic;

use app\common\model\UserAddress;
use app\common\model\Users;
use think\Log;
use think\Model;
use think\Page;
use think\db;

/**
 * 计算奖励的公共逻辑
 * Class RewardLogic
 */
class RewardLogic extends Model
{
    //获得奖励的事件
    const USER_BUY = 1;
    const USER_BUY_GOODS = 2;

    public static $eventList = [
        self::USER_BUY => '购买身份产品',
        self::USER_BUY_GOODS => '购买商品订单返佣',
    ];


    //奖励的类型
    const REWARD_TYPE1 = 1;
    const REWARD_TYPE2 = 2;
    const REWARD_TYPE3 = 3;

    public static $rewardTypeList = [
        self::REWARD_TYPE1 => '分销奖',
        self::REWARD_TYPE2 => '管理奖',
        self::REWARD_TYPE3 => '上荐奖',
    ];


    //身份的类别
    const PEOPLE_TYPE0 = 0;
    const PEOPLE_TYPE1 = 1;
    const PEOPLE_TYPE2 = 2;
    const PEOPLE_TYPE3 = 3;

    public static $peopleTypeList = [
        self::PEOPLE_TYPE0 => '普通会员',
        self::PEOPLE_TYPE1 => '健康大使',
        self::PEOPLE_TYPE2 => '总代',
        self::PEOPLE_TYPE3 => '大区经理',
    ];


    //币种类别
    const MONEY_TYPE0 = 0;
    const MONEY_TYPE1 = 1;
    const MONEY_TYPE2 = 2;
    const MONEY_TYPE3 = 3;
    public static $moneyTypeList = [
        self::MONEY_TYPE0 => '人民币',
        self::MONEY_TYPE1 => '马克币',
        self::MONEY_TYPE2 => '功德',
        self::MONEY_TYPE3 => '佣金',
    ];

    /*
     * 购买身份卡，计算推荐会员奖
     *
     * $user_id 购买了身份卡的用户
     * */
    public function dealIdentity($user_id)
    {
        $userInfo = M('users')
            ->where('user_id', $user_id)
            ->find();

        //判断pid和pid2是否存在，存在则判断他们的身份，给予相应的奖励
        if ($userInfo['pid']) {
            $pidUser = M('users')
                ->where('user_id', $userInfo['pid'])
                ->find();
            if ($pidUser['user_type'] == '1') {
                //上一级身份是会员

                $goods_config = M('n_goods_config')
                    ->where('key', 'member1_reward')
                    ->find();
                $money = $goods_config['value'] ? $goods_config['value'] : 0;

                $desc = '会员一级推荐奖';
                $obj = array();
                $obj['user_id'] = $user_id;

                //获得者，金额，币种类别，描述，json
                $this->amountLog($pidUser['user_id'], $money, 0, $desc, $obj);
            }
            if ($pidUser['user_type'] == '2') {
                //上一级身份是代理

                $goods_config = M('n_goods_config')
                    ->where('key', 'agent1_reward')
                    ->find();
                $money = $goods_config['value'] ? $goods_config['value'] : 0;

                $desc = '代理一级推荐奖';
                $obj = array();
                $obj['user_id'] = $user_id;

                //获得者，金额，币种类别，描述，json
                $this->amountLog($pidUser['user_id'], $money, 0, $desc, $obj);
            }
        }
        //END上一级计算完毕

        //计算上二级
        if ($userInfo['pid2']) {
            $pid2User = M('users')
                ->where('user_id', $userInfo['pid2'])
                ->find();
            if ($pid2User['user_type'] == '1') {
                //上二级身份是会员

                $goods_config = M('n_goods_config')
                    ->where('key', 'member2_reward')
                    ->find();
                $money = $goods_config['value'] ? $goods_config['value'] : 0;

                $desc = '会员二级推荐奖';
                $obj = array();
                $obj['user_id'] = $user_id;

                //获得者，金额，币种类别，描述，json
                $this->amountLog($pid2User['user_id'], $money, 0, $desc, $obj);
            }
            if ($pid2User['user_type'] == '2') {
                //上二级身份是代理

                $goods_config = M('n_goods_config')
                    ->where('key', 'agent2_reward')
                    ->find();
                $money = $goods_config['value'] ? $goods_config['value'] : 0;

                $desc = '代理二级推荐奖';
                $obj = array();
                $obj['user_id'] = $user_id;

                //获得者，金额，币种类别，描述，json
                $this->amountLog($pid2User['user_id'], $money, 0, $desc, $obj);
            }
        }
        //END上二级计算完毕


        //自己晋升为会员
        $myUpdata = array();
        $myUpdata['user_type'] = '1'; //晋级为会员
        $mrt = M('users')
            ->where('user_id', $user_id)
            ->update($myUpdata);
        //END自己晋升为会员


        //判断上级，是否可以升级为代理
        if ($userInfo['pid']) {
            $pidUser = M('users')
                ->where('user_id', $userInfo['pid'])
                ->find();

            if ($pidUser != '2') {
                //还不是代理
                $goods_config = $goods_config = M('n_goods_config')
                    ->where('key', 'vip_agent')
                    ->find();

                $needNum = $goods_config ? $goods_config['value'] : 0;  //需要数

                //获取该上级所有的直推下级
                $sonUserList = M('users')
                    ->where('pid', $pidUser['user_id'])
                    ->select();

                $nowNum = 0;  //当前已经推荐数
                foreach ($sonUserList as $k => $v) {
                    $orderVip = M('order_vip')
                        ->where('user_id', $v['user_id'])
                        ->where('pay_status', '1')
                        ->find();
                    if ($orderVip) {
                        $nowNum += 1;
                    }
                }

                if ($nowNum >= $needNum) {
                    //晋级身份
                    $userUpdata = array();
                    $userUpdata['user_type'] = '2'; //晋级为代理
                    $urt = M('users')
                        ->where('user_id', $pidUser['user_id'])
                        ->update($userUpdata);

                    //赋予子后台登录权限
                    $adminData['user_name'] = $pidUser['mobile'];
                    $adminData['password'] = "519475228fe35ad067744465c42a19b2";
                    $adminData['user_id'] = $pidUser['user_id'];
                    $adminData['type'] = 2;
                    $adminData['role_id'] = 14;
                    $adminData['add_time'] = time();

                    $r = D('admin')->insertGetId($adminData);
                }
            }
        }
        //END判断上级，是否可以升级为代理

    }


    /*
     * 签到，进行签到奖励
     *
     * $user_id 签到的用户
     * $selfMoney 签到自己可获得的金额
     * */
    public function dealSign($user_id, $selfMoney)
    {
        $userInfo = M('users')
            ->where('user_id', $user_id)
            ->find();

        //判断是否超出每天签到的次数
        $goods_config = M('n_goods_config')
            ->where('key', 'sign_num')
            ->find();
        $num = $goods_config['value'] ? $goods_config['value'] : 0;

        $time = strtotime(date('Y-m-d', time()));
        $signList = M('n_sign')
            ->where('user_id', $user_id)
            ->where('create_time', '>', $time)
            ->where('create_time', '<', $time + 86400)
            ->select();
        if (count($signList) <= $num) {
            //签到次数小于等于配置的次数，则可以计算奖励

            //首先给自己一笔签到流水
            $desc = '签到奖';
            $obj = array();
            $obj['user_id'] = $user_id;

            //获得者，金额，币种类别，描述，json
            $this->amountLog($user_id, $selfMoney, 0, $desc, $obj);


            //判断pid和pid2是否存在，存在则判断他们的身份，给予相应的签到奖励
            if ($userInfo['pid']) {
                $pidUser = M('users')
                    ->where('user_id', $userInfo['pid'])
                    ->find();
                if ($pidUser['user_type'] == '1') {
                    //上一级身份是会员

                    $goods_config = M('n_goods_config')
                        ->where('key', 'member1_sign')
                        ->find();
                    $money = $goods_config['value'] ? $goods_config['value'] : 0;

                    $desc = '会员一级签到奖';
                    $obj = array();
                    $obj['user_id'] = $user_id;

                    //获得者，金额，币种类别，描述，json
                    $this->amountLog($pidUser['user_id'], $money, 0, $desc, $obj);
                }
                if ($pidUser['user_type'] == '2') {
                    //上一级身份是代理

                    $goods_config = M('n_goods_config')
                        ->where('key', 'agent1_sign')
                        ->find();
                    $money = $goods_config['value'] ? $goods_config['value'] : 0;

                    $desc = '代理一级签到奖';
                    $obj = array();
                    $obj['user_id'] = $user_id;

                    //获得者，金额，币种类别，描述，json
                    $this->amountLog($pidUser['user_id'], $money, 0, $desc, $obj);
                }
            }
            //END上一级计算完毕

            //计算上二级
            if ($userInfo['pid2']) {
                $pid2User = M('users')
                    ->where('user_id', $userInfo['pid2'])
                    ->find();
                if ($pid2User['user_type'] == '1') {
                    //上二级身份是会员

                    $goods_config = M('n_goods_config')
                        ->where('key', 'member2_sign')
                        ->find();
                    $money = $goods_config['value'] ? $goods_config['value'] : 0;

                    $desc = '会员二级签到奖';
                    $obj = array();
                    $obj['user_id'] = $user_id;

                    //获得者，金额，币种类别，描述，json
                    $this->amountLog($pid2User['user_id'], $money, 0, $desc, $obj);
                }

                if ($pid2User['user_type'] == '2') {
                    //上二级身份是代理

                    $goods_config = M('n_goods_config')
                        ->where('key', 'agent2_sign')
                        ->find();
                    $money = $goods_config['value'] ? $goods_config['value'] : 0;

                    $desc = '代理二级签到奖';
                    $obj = array();
                    $obj['user_id'] = $user_id;

                    //获得者，金额，币种类别，描述，json
                    $this->amountLog($pid2User['user_id'], $money, 0, $desc, $obj);
                }
            }
            //END上二级计算完毕

        }
    }


    /*
     * 流水保存
     *
     * 获得者，金额，币种类别，描述,特殊数据
     * */
    public function amountLog($user_id, $money, $type, $desc, $obj)
    {
        $user = M('users')
            ->where('user_id', $user_id)
            ->find();

        $Update = array();
        $Update['user_money'] = $user['user_money'] + $money;
        if ($money > '0') {
            $Update['total_user_money'] = $user['total_user_money'] + $money;
        }
        $userUt = M('users')->where('user_id', $user['user_id'])->update($Update);

        //记录余额的流水
        $u_amountData = array();
        $u_amountData['user_id'] = $user['user_id']; //下单用户
        $u_amountData['money'] = $money;
        $u_amountData['type'] = $type;  //币种类型：0人民币
        $u_amountData['desc'] = $desc;     //币种类型：0人民币
        $u_amountData['obj'] = $obj ? json_encode($obj) : null;
        $u_amountData['create_time'] = time();
        $u_amountRt = M('n_amount_log')->add($u_amountData);
    }


    /*
 * 签到，进行签到奖励积分
 *
 * $user_id 签到的用户
 * $selfMoney 签到自己可获得的积分
 * */
    public function dealSign1($user_id, $selfMoney)
    {
        $userInfo = M('users')
            ->where('user_id', $user_id)
            ->find();

        //判断是否超出每天签到的次数
        $goods_config = M('n_goods_config')
            ->where('key', 'sign_num')
            ->find();
        $num = $goods_config['value'] ? $goods_config['value'] : 0;

        $time = strtotime(date('Y-m-d', time()));
        $signList = M('n_sign')
            ->where('user_id', $user_id)
            ->where('create_time', '>', $time)
            ->where('create_time', '<', $time + 86400)
            ->select();
        if (count($signList) <= $num) {
            //签到次数小于等于配置的次数，则可以计算奖励

            //首先给自己一笔签到流水
            $desc = '签到奖';
            $obj = array();
            $obj['user_id'] = $user_id;

            //获得者，金额，币种类别，描述，json
            $this->integralLog($user_id, $selfMoney, $desc, $obj);


        }
    }


    /*
     * 积分流水保存
     *
     * 获得者，积分，描述,特殊数据
     * */
    public function integralLog($user_id, $money, $desc, $obj)
    {
        $user = M('users')
            ->where('user_id', $user_id)
            ->find();

        $Update = array();
        $Update['pay_points'] = $user['pay_points'] + $money;
        //累积金额
//        if ($money > '0') {
//            $Update['total_user_money'] = $user['total_user_money'] + $money;
//        }
        $userUt = M('users')->where('user_id', $user['user_id'])->update($Update);

        //记录积分的流水
        $u_amountData = array();
        $u_amountData['user_id'] = $user['user_id']; //下单用户
        $u_amountData['money'] = $money;   //积分数
        $u_amountData['desc'] = $desc;     //描述
        $u_amountData['obj'] = $obj ? json_encode($obj) : null;
        $u_amountData['create_time'] = time();
        $u_amountRt = M('n_integral_log')->add($u_amountData);
    }


}