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
class UsersLevel extends Model
{

    /**
     * 用户等级 level_id
     */
    const LEVEL_USER = 0;// 粉丝
    const LEVEL_MEMBER = 1;// 会员
    const LEVEL_PROXY = 2;// 高级会员
    const LEVEL_PROXY_2 = 3;// 钻石代理

    /*
     * 黄丽建——粉丝购买身份产品——提升用户身份等级
     * $userId 购买者的用户ID
     * */
    public static function upUserLevel($userId)
    {
        $userInfo = Db::name('users')
            ->where('user_id',$userId)
            ->find();

        //粉丝购买，先进行自身身份晋升，再跑晋升逻辑
        if($userInfo['user_type'] == 0){
            $upSelf = UsersLevel::upToVip($userInfo['user_id']);
            if(!$upSelf){
                return false;
            }
        }

        //若该用户不存在上级，则跳出
        if(empty($userInfo['pid'])){
            return true;
        }

        $result = UsersLevel::upUserNextLevel($userInfo['pid']);

        return $result;
    }

    /*
     * 黄丽建——购买产品——用户可提现佣金分佣
     * orderId  订单ID
     * */
    public static function userCommission($orderId)
    {
        //获取订单信息
        $orderInfo = Db::name('order')
            ->where("order_id",$orderId)
            ->find();

        if(empty($orderInfo)){
            return false;
        }
        if($orderInfo['commission_type'] == 0){
            //产生待提现佣金
            UsersLevel::canCommission($orderInfo);
        }else{
            //产生可提现佣金
            UsersLevel::waitCommission($orderInfo);
        }

    }

    //黄丽建——购买产品——用户可提现佣金分佣
    public static function canCommission($orderInfo)
    {
        //获取下单用户信息
        $userInfo = Db::name('users')
            ->where('user_id',$orderInfo['user_id'])
            ->field('user_id,user_type,pid,pid2')
            ->find();

        //获取订单中商品列表
        $goodsList = Db::name('order_goods')
            ->where('order_id',$orderInfo['order_id'])
            ->field('goods_id,goods_price')
            ->select();

        //逐个商品进行分佣
        foreach ($goodsList as $k => $v){
            $goodsInfo = Db::name('goods')
                ->where('goods_id',$v['goods_id'])
                ->field('goods_id,g_type')
                ->find();

            //每件商品计算前都需重新获取父级/祖父级信息
            $father = UsersLevel::getUserMoney($userInfo['pid']);
            $grandfather = UsersLevel::getUserMoney($userInfo['pid2']);

            //特定产品
            if($goodsInfo['g_type'] == 2){
                // 启动事务,分佣计算
                Db::startTrans();
                try{
                    //只有身份不为粉丝的才能进行分佣
                    if($father['user_type'] != 0 ){
                        //一级分佣
                        $config = UsersLevel::sysConfig($father['user_type']);//获取对应用户等级配置
                        $money = $config['distribution_one_especial'];
                        $pidData['user_money'] =$father['user_money'] + $money;
                        $pidData['total_user_money'] = $father['total_user_money'] + $config['distribution_one_especial'];
                        $pidWhere['user_id'] = $userInfo['pid'];

                        $pidResult = Db::name('users')
                            ->where($pidWhere)
                            ->update($pidData);

                        //一级分佣日志记录
                        $desc = "购买特定产品获得一级分佣（可提佣金）";
                        $pidLog = UsersLevel::writeLog($father['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $pidResult = true;
                        $pidLog = true;
                    }

                    if($grandfather['user_type'] != 0){
                        //二级分佣
                        $config = UsersLevel::sysConfig($grandfather['user_type']);//获取对应用户等级配置
                        $money = $config['distribution_two_especial'];
                        $pidTwoData['user_money'] = $grandfather['user_money'] + $money;
                        $pidTwoData['total_user_money'] = $grandfather['total_user_money'] + $config['distribution_two_especial'];
                        $pidTwoWhere['user_id'] = $userInfo['pid2'];

                        $pidTwoResult = Db::name('users')
                            ->where($pidTwoWhere)
                            ->update($pidTwoData);

                        //二级分佣日志记录
                        $desc = "购买特定产品获得二级分佣（可提佣金）";
                        $pidTwoLog = UsersLevel::writeLog($grandfather['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);
                    }else{
                        $pidTwoResult = true;
                        $pidTwoLog = true;
                    }

                    //团队奖计算——按极差
                    $config = UsersLevel::sysConfig($father['user_type']);//获取对应用户等级配置
                    $gaoMoney = $config['team_prize'];  //高级会员奖励金额

                    //获取钻石会员的系统对应等级
                    $zuanLevel = UsersLevel::nextLevel(2);

                    //获取系统配置的奖励信息
                    $config2 = Db::name('user_level')
                        ->where('level_id',$zuanLevel)
                        ->find();

                    $zuanMoney = $config2['team_prize'] - $config['team_prize'];    //钻石会员获得金额

                    $gaoji = UsersLevel::getGaoji($userInfo['user_id']); //找出上级为高级会员的管理者

                    if(!empty($gaoji)){
                        //团队奖,高级会员获取金额
                        $dataGao['user_money'] =$gaoji['user_money'] + $gaoMoney;
                        $dataGao['total_user_money'] = $gaoji['total_user_money'] + $config['team_prize'];
                        $whereGao['user_id'] = $gaoji['user_id'];

                        $gaoFen = Db::name('users')
                            ->where($whereGao)
                            ->update($dataGao);

                        //团队奖，高级会员获取金额日志记录
                        $desc = "购买特定产品高级会员获得团队奖（可提佣金）";
                        $gaoLog = UsersLevel::writeLog($gaoji['user_id'],$gaoMoney,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $gaoFen = true;
                        $gaoLog = true;
                    }

                    $zuanshi = UsersLevel::getZuanshi($userInfo['user_id']);//找出上级为钻石会员的管理者

                    if(!empty($zuanshi) && $zuanMoney != 0){

                        $dataZuan['user_money'] =$zuanshi['user_money'] + $zuanMoney;
                        $dataZuan['total_user_money'] = $zuanshi['total_user_money'] + $config['team_prize'];
                        $whereZuan['user_id'] = $zuanshi['user_id'];

                        $zuanFen = Db::name('users')
                            ->where($whereZuan)
                            ->update($dataZuan);

                        //团队奖，钻石会员获取金额日志记录
                        $desc = "购买特定产品钻石会员获得团队奖（可提佣金）";
                        $zuanLog = UsersLevel::writeLog($zuanshi['user_id'],$zuanMoney,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);


                        //平级奖,（奖励对象：该钻石会员的直推链的下一个钻石会员）
                        $pingji = UsersLevel::getZuanshi($zuanshi['user_id']);

                        if(!empty($pingji)){
                            $config = UsersLevel::sysConfig($pingji['user_type']);//获取对应用户等级配置
                            $pingMoney = $config['same_level'];
                            $pingData['user_money'] = $pingji['user_money'] + $pingMoney;
                            $pingData['total_user_money'] = $pingji['total_user_money'] + $pingMoney;
                            $pingWhere['user_id'] = $pingji['user_id'];
                            $pingFen = Db::name('users')
                                ->where($pingWhere)
                                ->update($pingData);

                            //平级奖，钻石会员获取金额日志记录
                            $desc = "购买特定产品钻石会员获得平级奖（可提佣金）";
                            $pingLog = UsersLevel::writeLog($pingji['user_id'],$pingMoney,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                        }else{
                            $pingFen = true;
                            $pingLog = true;
                        }
                    }else{
                        $zuanFen = true;
                        $zuanLog = true;
                    }

                    if($pidResult !==false && $pidLog && $pidTwoResult !==false && $pidTwoLog && $gaoFen !==false  && $gaoLog && $zuanFen !==false  && $zuanLog && $pingFen !==false  && $pingLog){
                        Db::commit();
                    }
                } catch (\Exception $e) {
                    // 回滚事务，记录日志
                    Log::error("特定产品，分佣过程出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                    Db::rollback();
                }
            }

            //普通产品分佣计算
            if($goodsInfo['g_type'] == 0){
                // 启动事务
                Db::startTrans();
                try{
                    //只有身份不为粉丝的才能进行分佣
                    if($father['user_type'] != 0 ){
                        //一级分佣
                        $config = UsersLevel::sysConfig($father['user_type']);

                        $money = $config['distribution_one_ordinary']*0.01*$v['goods_price'];
                        $pidData['user_money'] = $father['user_money'] + $money;
                        $pidData['total_user_money'] = $father['total_user_money'] + $money;
                        $pidWhere['user_id'] = $userInfo['pid'];

                        $pidResult = Db::name('users')
                            ->where($pidWhere)
                            ->update($pidData);

                        //获取下单用户信息
                        $userInfo = Db::name('users')
                            ->where('user_id',$orderInfo['user_id'])
                            ->field('user_id,user_type,pid,pid2')
                            ->find();

                        //一级分佣日志记录
                        $desc = "购买普通产品获得一级分佣（可提佣金）";
                        $pidLog = UsersLevel::writeLog($father['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $pidResult = true;
                        $pidLog = true;
                    }

                    if($grandfather['user_type'] != 0){

                        //二级分佣
                        $config = UsersLevel::sysConfig($grandfather['user_type']);
                        $money = $config['distribution_two_ordinary']*0.01*$v['goods_price'];
                        $pidTwoData['user_money'] = $grandfather['user_money'] +  $money;
                        $pidTwoData['total_user_money'] = $grandfather['total_user_money'] + $money;
                        $pidTwoWhere['user_id'] = $userInfo['pid2'];

                        $pidTwoResult = Db::name('users')
                            ->where($pidTwoWhere)
                            ->update($pidTwoData);

                        //二级分佣日志记录
                        $desc = "购买普通产品获得二级分佣（可提佣金）";
                        $pidTwoLog = UsersLevel::writeLog($grandfather['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $pidTwoResult = true;
                        $pidTwoLog = true;
                    }

                    // 提交事务
                    if($pidLog && $pidResult!==false && $pidTwoLog && $pidTwoResult!==false){
                        Db::commit();
                    }

                } catch (\Exception $e) {
                    // 回滚事务
                    Log::error("普通产品，分佣过程出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                    Db::rollback();
                }
            }

        }
    }



    //黄丽建——购买产品——用户待返佣金分佣
    public static function waitCommission($orderInfo)
    {
        //获取下单用户信息
        $userInfo = Db::name('users')
            ->where('user_id',$orderInfo['user_id'])
            ->field('user_id,user_type,pid,pid2')
            ->find();

        //获取订单中商品列表
        $goodsList = Db::name('order_goods')
            ->where('order_id',$orderInfo['order_id'])
            ->field('goods_id,goods_price')
            ->select();

        //逐个商品进行分佣
        foreach ($goodsList as $k => $v){
            $goodsInfo = Db::name('goods')
                ->where('goods_id',$v['goods_id'])
                ->field('goods_id,g_type')
                ->find();

            //每件商品计算前都需重新获取父级/祖父级信息
            $father = UsersLevel::getUserMoney($userInfo['pid']);
            $grandfather = UsersLevel::getUserMoney($userInfo['pid2']);

            //特定产品
            if($goodsInfo['g_type'] == 2){
                // 启动事务,分佣计算
                Db::startTrans();
                try{
                    //只有身份不为粉丝的才能进行分佣
                    if($father['user_type'] != 0 ){
                        //一级分佣
                        $config = UsersLevel::sysConfig($father['user_type']);//获取对应用户等级配置
                        $money = $config['distribution_one_especial'];

                        $pidData['order_id'] = $orderInfo['order_id'];
                        $pidData['goods_id'] = $v['goods_id'];
                        $pidData['user_id'] = $userInfo['user_id'];
                        $pidData['money'] = $money;
                        $pidData['goods_type'] = 1;
                        $pidData['commission_type'] = 0;
                        $pidData['create_time'] =time();

                        $pidResult = Db::name('n_wait_commission')
                            ->insert($pidData);

                        //一级分佣日志记录
                        $desc = "购买特定产品获得一级分佣（待返佣金）";
                        $pidLog = UsersLevel::writeLog($father['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $pidResult = true;
                        $pidLog = true;
                    }

                    if($grandfather['user_type'] != 0){
                        //二级分佣
                        $config = UsersLevel::sysConfig($grandfather['user_type']);//获取对应用户等级配置
                        $money = $config['distribution_two_especial'];

                        $pidTwoData['order_id'] = $orderInfo['order_id'];
                        $pidTwoData['goods_id'] = $v['goods_id'];
                        $pidTwoData['user_id'] = $userInfo['user_id'];
                        $pidTwoData['money'] = $money;
                        $pidTwoData['goods_type'] = 1;
                        $pidTwoData['commission_type'] = 1;
                        $pidTwoData['create_time'] =time();

                        $pidTwoResult = Db::name('n_wait_commission')
                            ->insert($pidTwoData);

                        //二级分佣日志记录
                        $desc = "购买特定产品获得二级分佣（待返佣金）";
                        $pidTwoLog = UsersLevel::writeLog($grandfather['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);
                    }else{
                        $pidTwoResult = true;
                        $pidTwoLog = true;
                    }

                    //团队奖计算——按极差
                    $config = UsersLevel::sysConfig($father['user_type']);//获取对应用户等级配置
                    $gaoMoney = $config['team_prize'];  //高级会员奖励金额

                    //获取钻石会员的系统对应等级
                    $zuanLevel = UsersLevel::nextLevel(2);

                    //获取系统配置的奖励信息
                    $config2 = Db::name('user_level')
                        ->where('level_id',$zuanLevel)
                        ->find();

                    $zuanMoney = $config2['team_prize'] - $config['team_prize'];    //钻石会员获得金额

                    $gaoji = UsersLevel::getGaoji($userInfo['user_id']); //找出上级为高级会员的管理者

                    if(!empty($gaoji)){
                        //团队奖,高级会员获取金额
                        $dataGao['order_id'] = $orderInfo['order_id'];
                        $dataGao['goods_id'] = $v['goods_id'];
                        $dataGao['user_id'] = $userInfo['user_id'];
                        $dataGao['money'] = $gaoji;
                        $dataGao['goods_type'] = 1;
                        $dataGao['commission_type'] = 2;
                        $dataGao['create_time'] =time();

                        $gaoFen = Db::name('n_wait_commission')
                            ->insert($dataGao);

                        //团队奖，高级会员获取金额日志记录
                        $desc = "购买特定产品高级会员获得团队奖（待返佣金）";
                        $gaoLog = UsersLevel::writeLog($gaoji['user_id'],$gaoMoney,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $gaoFen = true;
                        $gaoLog = true;
                    }

                    $zuanshi = UsersLevel::getZuanshi($userInfo['user_id']);//找出上级为钻石会员的管理者
                    if(!empty($zuanshi) && $zuanMoney != 0){
                        //团队奖,钻石会员获取金额
                        $dataZuan['order_id'] = $orderInfo['order_id'];
                        $dataZuan['goods_id'] = $v['goods_id'];
                        $dataZuan['user_id'] = $userInfo['user_id'];
                        $dataZuan['money'] = $zuanMoney;
                        $dataZuan['goods_type'] = 1;
                        $dataZuan['commission_type'] = 3;
                        $dataZuan['create_time'] =time();

                        $zuanFen = Db::name('n_wait_commission')
                            ->insert($dataZuan);

                        //团队奖，钻石会员获取金额日志记录
                        $desc = "购买特定产品钻石会员获得团队奖（待返佣金）";
                        $zuanLog = UsersLevel::writeLog($zuanshi['user_id'],$zuanMoney,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);


                        //平级奖,（奖励对象：该钻石会员的直推链的下一个钻石会员）
                        $pingji = UsersLevel::getZuanshi($zuanshi['user_id']);

                        if(!empty($pingji)){
                            $config = UsersLevel::sysConfig($pingji['user_type']);//获取对应用户等级配置
                            $pingMoney = $config['same_level'];

                            $pingData['order_id'] = $orderInfo['order_id'];
                            $pingData['goods_id'] = $v['goods_id'];
                            $pingData['user_id'] = $userInfo['user_id'];
                            $pingData['money'] = $pingMoney;
                            $pingData['goods_type'] = 1;
                            $pingData['commission_type'] = 4;
                            $pingData['create_time'] =time();

                            $pingFen = Db::name('n_wait_commission')
                                ->insert($pingData);

                            //平级奖，钻石会员获取金额日志记录
                            $desc = "购买特定产品钻石会员获得平级奖（待返佣金）";
                            $pingLog = UsersLevel::writeLog($pingji['user_id'],$pingMoney,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                        }else{
                            $pingFen = true;
                            $pingLog = true;
                        }
                    }else{
                        $zuanFen = true;
                        $zuanLog = true;
                    }

                    if($pidResult !==false && $pidLog && $pidTwoResult !==false && $pidTwoLog && $gaoFen !==false  && $gaoLog && $zuanFen !==false  && $zuanLog && $pingFen !==false  && $pingLog){
                        Db::commit();
                    }
                } catch (\Exception $e) {
                    // 回滚事务，记录日志
                    Log::error("特定产品，分佣过程出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                    Db::rollback();
                }
            }

            //普通产品分佣计算
            if($goodsInfo['g_type'] == 0){
                // 启动事务
                Db::startTrans();
                try{
                    //只有身份不为粉丝的才能进行分佣
                    if($father['user_type'] != 0 ){
                        //一级分佣
                        $config = UsersLevel::sysConfig($father['user_type']);
                        $money = $config['distribution_one_ordinary']*0.01*$v['goods_price'];

                        $pidData['order_id'] = $orderInfo['order_id'];
                        $pidData['goods_id'] = $v['goods_id'];
                        $pidData['user_id'] = $userInfo['user_id'];
                        $pidData['money'] = $money;
                        $pidData['goods_type'] = 1;
                        $pidData['commission_type'] = 5;
                        $pidData['create_time'] =time();

                        $pidResult = Db::name('n_wait_commission')
                            ->insert($pingData);


                        //获取下单用户信息
                        $userInfo = Db::name('users')
                            ->where('user_id',$orderInfo['user_id'])
                            ->field('user_id,user_type,pid,pid2')
                            ->find();

                        //一级分佣日志记录
                        $desc = "购买普通产品获得一级分佣（待返佣金）";
                        $pidLog = UsersLevel::writeLog($father['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $pidResult = true;
                        $pidLog = true;
                    }

                    if($grandfather['user_type'] != 0){

                        //二级分佣
                        $config = UsersLevel::sysConfig($grandfather['user_type']);
                        $money = $config['distribution_two_ordinary']*0.01*$v['goods_price'];

                        $pidTwoData['order_id'] = $orderInfo['order_id'];
                        $pidTwoData['goods_id'] = $v['goods_id'];
                        $pidTwoData['user_id'] = $userInfo['user_id'];
                        $pidTwoData['money'] = $money;
                        $pidTwoData['goods_type'] = 1;
                        $pidTwoData['commission_type'] = 6;
                        $pidTwoData['create_time'] =time();

                        $pidTwoResult = Db::name('n_wait_commission')
                            ->insert($pidTwoData);

                        //二级分佣日志记录
                        $desc = "购买普通产品获得二级分佣（待返佣金）";
                        $pidTwoLog = UsersLevel::writeLog($grandfather['user_id'],$money,$desc,$userInfo['user_id'],$v['goods_id'],$orderInfo['order_id']);

                    }else{
                        $pidTwoResult = true;
                        $pidTwoLog = true;
                    }

                    // 提交事务
                    if($pidLog && $pidResult!==false && $pidTwoLog && $pidTwoResult!==false){
                        Db::commit();
                    }

                } catch (\Exception $e) {
                    // 回滚事务
                    Log::error("普通产品，分佣过程出错_时间：".time()."_订单号：".$orderInfo['order_id']);
                    Db::rollback();
                }
            }

        }
    }

    //粉丝晋升为会员
    public static function upToVip($userId)
    {
        $upUserData['user_type'] = 1;
        $whereUser['user_id'] = $userId;

        $upUser = Db::name('users')
            ->where($whereUser)
            ->update($upUserData);

        if($upUser){
            return 1;
        }else{
            return 0;
        }

    }

    //递归晋升身份
    public static function upUserNextLevel($userId)
    {
//        echo "user_id_".$userId."————";
        //获取用户信息
        $userInfo = Db::name('users')
            ->where('user_id',$userId)
            ->field('user_id,user_type,pid')
            ->find();
        //若该直推上级为粉丝，则跳过
        if($userInfo['user_type'] == 0 ){
            UsersLevel::upUserNextLevel($userInfo['pid']);
            exit;
        }
        //统计用户直推下级
        $userList = Db::name('n_management')
            ->where('management_id',$userInfo['user_id'])
            ->select();

        $countArr = array();
        foreach ($userList as $k => $v){
            $getUser = Db::name('users')
                ->where('user_id',$v['user_id'])
                ->field('user_id,user_type')
                ->find();
            if($getUser['user_type'] == $userInfo['user_type']){
               array_push($countArr,$getUser['user_id']);
            }
        }
        $count = count($countArr);

        //获取系统配置
        $sysConfig = Db::name('user_level')
            ->where('level_id',$userInfo['user_type'])
            ->find();

        //大于等于系统配置晋升
        if($count >= $sysConfig['push_num']){
            $nextLevel = UsersLevel::nextLevel($userInfo['user_type']);
            $data['user_type'] = $nextLevel;
            $where['user_id'] = $userInfo['user_id'];
            $update = Db::name('users')
                ->where($where)
                ->update($data);

            //晋升成功并且父级ID不为空，进行递归
            if($update !== false && !empty($userInfo['pid'])){

                UsersLevel::upUserNextLevel($userInfo['pid']);
            }else{  //记录日志

                Log::error(time()."_update_user_Level_userId=".$userInfo['user_id']);
                return 1;

            }

        }else{
            return 2;
        }

    }

    //获取下一等级
    public static function nextLevel($level)
    {
        switch($level)
        {
            case 0:
                $nextLevel = UsersLevel::LEVEL_MEMBER;
                break;
            case 1:
                $nextLevel = UsersLevel::LEVEL_PROXY;
                break;
            case 2:
                $nextLevel = UsersLevel::LEVEL_PROXY_2;
                break;
            case 3:
                $nextLevel = 3;
                break;
            default:
                $nextLevel = null;
        }

        return $nextLevel;
    }

    //获取管理上级为高级会员的用户
    public static function getGaoji($userId)
    {
        static $arr = array();

        //用户信息
        $userInfo = Db::name('users')
            ->where('user_id',$userId)
            ->field('user_id,user_type,pid,pid2,user_money,total_user_money')
            ->find();

        if(!empty($userInfo['pid'])){
            $pidUserInfo = Db::name('users')
                ->where('user_id',$userInfo['pid'])
                ->field('user_id,user_type,pid,pid2,user_money,total_user_money')
                ->find();
        }else{
            $pidUserInfo = null;
        }

        if(($pidUserInfo['user_type'] < 2) && ($pidUserInfo['pid'] != 0 )){
             UsersLevel::getGaoji($pidUserInfo['user_id']);
        }else{
            $arr =  $pidUserInfo;
        }
        return $arr;
    }

    //获取管理上级为钻石会员的用户
    public static function getZuanshi($userId)
    {
        static $arr = array();

        //用户信息
        $userInfo = Db::name('users')
            ->where('user_id',$userId)
            ->field('user_id,user_type,pid,pid2,user_money,total_user_money')
            ->find();
        if(!empty($userInfo['pid'])){
            $pidUserInfo = Db::name('users')
                ->where('user_id',$userInfo['pid'])
                ->field('user_id,user_type,pid,pid2,user_money,total_user_money')
                ->find();
        }else{
            $pidUserInfo = null;
        }

        if(($pidUserInfo['user_type'] < 3) && ($pidUserInfo['pid'] != 0 )){
            UsersLevel::getZuanshi($pidUserInfo['user_id']);
        }else{
            $arr =  $pidUserInfo;
        }
        return $arr;
    }

    //获取当前层级的系统配置
    public static function sysConfig($type)
    {
        $config = Db::name('user_level')
            ->where('level_id',$type)
            ->find();
        return $config;
    }

    //获取用户金额
    public static function getUserMoney($userId)
    {
        $result = Db::name('users')
            ->where('user_id',$userId)
            ->field('user_id,user_type,user_money,total_user_money')
            ->find();
        return $result;
    }

    //佣金分成日志记录
    public static function writeLog($userId,$money,$desc,$objUserId,$goodsId,$orderId)
    {
        $logData['user_id'] = $userId;
        $logData['money'] = $money;
        $logData['desc'] = $desc;
        $logData['obj_user_id'] = $objUserId;
        $logData['create_time'] = time();
        $logData['goods_id'] = $goodsId;
        $logData['order_id'] = $orderId;
        $result = Db::name('n_amount_log')
            ->insert($logData);
        return $result;
    }

}