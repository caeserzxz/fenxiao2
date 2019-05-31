<?php

namespace app\mobile\controller;
use app\common\model\UserLevel;
use My\DataReturn;
use Think\Db;
use app\common\validate\TokenValidate;
use app\common\exception\TokenInvalidException;
use app\common\response\ApiResponse;
use app\common\model;
use app\common\logic\OrderLogic;
use app\common\exception\ManagementDeniedException;
use app\common\exception\NoticeException;
use think\Exception;
use think\Log;
use think\Config;
use think\exception\DbException;
use PDO;

class Exchange extends MobileBase {

    /**
     * @var model\Users
     */
    protected $user;

    public function _initialize() {
        parent::_initialize();

        // 不自动提交
//        Config::set('database.params', [
//            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET autocommit = 0',
//        ]);

        $user = session('user');
        try {
            $user['user_id'] and $this->user = model\Users::get($user['user_id']);
        } catch (DbException $e) {
        }

        if (!$this->user) {
            // 不用登录的方法
            $publicActionList = [];
            if (!in_array(ACTION_NAME, $publicActionList)) {
                $this->toLogin();
            }

        } else {
            session('user', $this->user->toArray());
        }
    }

    //送水活动首页
    public function index(){

        //用户信息
        $users = model\Users::get($this->user['user_id']);

        $where['user_id'] = $users['user_id'];
        $where['status'] = array('in',[0,1,4]);
        $apply = db('apply_deposit')->where($where)->find();

        $payment = db('payment_log')
            ->where(['user_id'=>$this->user['user_id']])
            ->limit(1)->find();

        $deposit['deposit_msg'] = '暂无押金';
        if(!empty((int)$users['deposit'])) $deposit['deposit_msg'] = '押金：￥'.$users['deposit'];

        if (!empty($apply)) $deposit['deposit_msg'] = '待返押金：￥'.$apply['apply_deposit'];

        if (empty((int) $users['deposit']) && !empty($payment) && empty($apply)) {
            $deposit['deposit_msg'] = '押金已返，暂无押金';
        }

//        $wh['user_id'] = $this->user['user_id'];
//        $wh['amount_status'] = array('in',[model\Financial::AMOUNT_STATUS_PAID, model\Financial::AMOUNT_STATUS_REQUEST_REFUNDED]);
//        $financial = db('financial')->where($wh)->find();

        $financial = $users->isShareHolder();
        $this->assign('user',$users);
        $this->assign('apply',$apply);
        $this->assign('payment',$payment);
        $this->assign('deposit',$deposit);
        $this->assign('financial',$financial);
        $this->assign('title', '送水活动');
        return $this->fetch();
    }

    //充值前
    public function recharge_before(){

        $level = input('level');
        if($level != UserLevel::LEVEL_MEMBER && $level != UserLevel::LEVEL_SENIOR_MEMBER) {
            $this->error('系统错误', 'mobile/Exchange/index');
        }

        $where['user_id'] = $this->user['user_id'];
        $where['status'] = array('in',[0,1,4]);
        $apply = db('apply_deposit')->where($where)->find();

        if(!empty($apply)) {
            $this->error('您正在申请退押，不能参与活动', 'mobile/Exchange/index');
        }

        $user_level = db('user_level')
            ->where(['level_id'=>$level])
            ->field('level_id,amount,return_water_coin')->find();

        $exchange = db('exchange')->alias('e')
            ->join('goods g','e.goods_id = g.goods_id')
            ->where(['e.level_id'=>$level])
            ->field('e.*,g.goods_id,g.goods_name,g.original_img')
            ->select();

        $exchange_list = array();
        foreach ($exchange as $key => $val) {
            $_t = $val;
            $_t['num'] = $key + 1;
            $exchange_list['title'] = db('exchange_activity')->where(['id'=>$val['activity_id']])->value('title');
            $exchange_list['list'][] = $_t;
        }

        $users = db('users')
            ->where(['user_id'=>$this->user['user_id']])
            ->field('user_id,level,deposit')->find();

        if($users['level'] == UserLevel::LEVEL_MEMBER) {

            $user_level['amount'] -= $users['deposit'];
            $user_level['amount'] = sprintf("%.2f",$user_level['amount']);
        }

        $this->assign('user_level',$user_level);
        $this->assign('exchange_list',$exchange_list);
        $this->assign('title', '充值说明');
        return $this->fetch();
    }

    //充值会员
    public function recharge(){

        $level_id = input('level_id');

        if($level_id != UserLevel::LEVEL_MEMBER && $level_id != UserLevel::LEVEL_SENIOR_MEMBER) {
            $this->error('系统错误', 'mobile/Exchange/index');
        }
        $users = db('users')
            ->where(['user_id'=>$this->user['user_id']])
            ->field('user_id,level,deposit')->find();

        if($users['level'] == UserLevel::LEVEL_SENIOR_MEMBER) {
            $this->error('您已是高级会员，无法参与此活动', 'mobile/Exchange/index');
        }

        if($users['level'] == $level_id) {
            $this->error('您已是会员，无法参与此活动', 'mobile/Exchange/index');
        }

        $user_level = db('user_level')
            ->where(['level_id'=>$level_id])
            ->field('level_id,amount,return_water_coin')->find();

        $titles = db('exchange_activity')->where(['level_id'=>$level_id])->value('title');

        if($users['level'] == UserLevel::LEVEL_MEMBER) {

            $user_level['amount'] -= $users['deposit'];
            $user_level['amount'] = sprintf("%.2f",$user_level['amount']);
        }

        $this->assign('titles',$titles);
        $this->assign('user_level',$user_level);
        $this->assign('title', '确认充值');
        return $this->fetch();
    }

    //活动页
    public function activity(){

        //会员
        $member_level = db('user_level')
            ->where(['level_id'=>UserLevel::LEVEL_MEMBER])
            ->field('level_id,amount,return_water_coin')->find();

        //高级会员
        $senior_level = db('user_level')
            ->where(['level_id'=>UserLevel::LEVEL_SENIOR_MEMBER])
            ->field('level_id,amount,return_water_coin')->find();

        //用户信息
        $users = db('users')
            ->where(['user_id'=>$this->user['user_id']])
            ->field('user_id,nickname,level,head_pic,water_coin,is_sales')
            ->find();

        $this->assign('user',$users);
        $this->assign('member_level',$member_level);
        $this->assign('senior_level',$senior_level);
        $this->assign('title', '活动列表');
        return $this->fetch();
    }

    //活动详情

    public function activity_detail(){

        $level_id = input('level_id');
        if($level_id != UserLevel::LEVEL_MEMBER && $level_id != UserLevel::LEVEL_SENIOR_MEMBER) {
            $this->error('系统错误', 'mobile/Exchange/index');
        }
        $user_level = db('user_level')
            ->where(['level_id'=>$level_id])
            ->field('level_id,amount,return_water_coin')
            ->find();

        $exchange = db('exchange')->alias('e')
            ->join('goods g','e.goods_id = g.goods_id')
            ->where(['e.level_id'=>$level_id])
            ->field('e.*,g.goods_id,g.goods_name,g.original_img')
            ->select();

        $exchange_list = array();
        foreach ($exchange as $key => $val) {
            $_t = $val;
            $_t['num'] = $key + 1;
            $exchange_list[] = $_t;
        }

        $activity_img = db('exchange_activity')->where(['level_id'=>$level_id])->value('activity_img');
        $this->assign('activity_img',$activity_img);
        $this->assign('user_level',$user_level);
        $this->assign('exchange_list',$exchange_list);
        $this->assign('title', '活动详情');
        return $this->fetch();
    }

    //申请退押
    public function apply_deposit(){

        $where['user_id'] = $this->user['user_id'];
        $where['status'] = array('in',[0,1,4]);
        $apply = db('apply_deposit')->where($where)->find();

        if($apply && is_array($apply)) returnJson(-1,'已有正在处理中的申请,不能重复申请!');

        $users = db('users')
            ->where(['user_id'=>$this->user['user_id']])
            ->field('user_id,level,deposit')->find();

        if($users['level'] != UserLevel::LEVEL_MEMBER && $users['level'] != UserLevel::LEVEL_SENIOR_MEMBER){
            returnJson(-1,'您不是会员,不可以申请退押!');
        }

        $wh['user_id'] = $this->user['user_id'];
        $wh['amount_status'] = array('in',[model\Financial::AMOUNT_STATUS_PAID, model\Financial::AMOUNT_STATUS_REQUEST_REFUNDED]);

        $financial = db('financial')->where($wh)->find();

        if ($financial && is_array($financial)) returnJson(-1,'您正在理财中，不能申请退押!');

        $data['user_id'] = $users['user_id'];
        $data['apply_level'] = $users['level'];
        $data['apply_deposit'] = $users['deposit'];
        $data['create_time'] = time();
        $res = db('apply_deposit')->insert($data);

        if($res) returnJson(1,'申请成功');

        returnJson(-1,'申请失败');
    }


    /**
     * @猿份哥
     * 确认订单
     * @return mixed
     */
    public function order(){

        //查找默认的地址
        $defaultAdd = [];//默认地址
        $user = Db::name('users')->where(['user_id'=>$this->user['user_id']])->field('user_id,nickname,water_coin')->find();
        $address = Db::name('user_address')
            ->where(['user_id'=>$user['user_id'],'is_default'=>1])
            ->field(['address_id','consignee','mobile','province','city','district','twon','address'])
            ->find();
        if(!empty($address)){
            $defaultAdd['fulladdress'] = getTotalAddress($address['province'],$address['city'],$address['district'],$address['twon'],$address['address']);
            $defaultAdd['consignee'] = $address['consignee'];
            $defaultAdd['mobile'] = substr($address['mobile'], 0, 3).'****'.substr($address['mobile'], 7);
            $defaultAdd['address_id'] = $address['address_id'];
        }

        //连表查询兑水商品
        $exchange = Db::name('exchange')->alias('e')
            ->join('goods g','e.goods_id = g.goods_id')
            ->field('e.*,g.goods_id,g.goods_name,g.original_img')
            ->select();
        foreach ($exchange as $k=>&$v){
            $v['original_img'] = SITE_URL.$v['original_img'];
        }

        return $this->fetch('',[
            'fullAdd'=>$defaultAdd,
            'user_water_coin'=>$user['water_coin'],
            'list'=>$exchange,
            'title'=>'兑水订单',
        ]);
    }

    /**
     * @猿份哥
     * 生成水币兑换记录
     */
    public function createLog(){

        $userInfo = Db::name('users')->where(['user_id'=>$this->user['user_id']])->field('user_id,water_coin')->find();//用户id

        if(request()->isAjax()){
            //获取数据
            $input = request()->post();
            $address_id = $input['address_id'];//地址id

            //判断下用户是否填写了地址
            if(empty($address_id)){
                DataReturn::returnJson(0,'请先填写地址再兑水');
            }

            $goods_ids = implode(',',$input['ids']);//兑换的商品ids
            $goods_num = 0;//兑水商品的总数量
            $total_coin = 0;

            $data = [];
            //计算总数量
            foreach ($input['num'] as $k=>$v){
                $goods_num += $v;
            }
            //组装对应产品和数量 [商品id=>数量]
            foreach ($input['ids'] as $k=>$v){
                $data[$v] = $input['num'][$k];
            }

            //查表用户兑水的产品
            $allGoodsData = Db::name('exchange')->where('goods_id','in',$goods_ids)->select();

            //每个订单对应的拆分的小订单
            $splitData = [];
            foreach ($allGoodsData as $k=>$v){
                $splitData[$k]['goods_id'] = $v['goods_id'];
                $splitData[$k]['num'] = $data[$v['goods_id']];
                $splitData[$k]['sum_coin'] = $data[$v['goods_id']] * $v['water_coin'];
                $splitData[$k]['per_goods_coin'] = $v['water_coin'];
                $splitData[$k]['create_time'] = time();
                $total_coin += $data[$v['goods_id']] * $v['water_coin'];
            }

            //判断水币是否够
            if($userInfo['water_coin'] < $total_coin){
                DataReturn::returnJson(0,'水币不足,兑换失败');
            }

            //构建大订单数据结构
            $bigData = [
                'user_id'=>$userInfo['user_id'],
                'goods_ids'=>$goods_ids,
                'total_coin'=>$total_coin,
                'goods_num'=>$goods_num,
                'address_id'=>$address_id,
                'create_time'=>time(),
                'order_sn'=>date('YmdHis').mt_rand(1000,9999)
            ];

            // 启动事务
            Db::startTrans();
            try{
                $order_id = Db::name('exchange_order')->insertGetId($bigData);
                foreach ($splitData as $k=>&$v){
                    $v['order_id'] = $order_id;
                }
                Db::name('exchange_split')->insertAll($splitData);

                //执行扣水币操作
                Db::name('users')->where(['user_id'=>$userInfo['user_id']])->setDec('water_coin',$total_coin);

                // 提交事务
                Db::commit();
                DataReturn::returnJson(1,'兑换成功');
            } catch (Exception $e) {
                // 回滚事务
                Db::rollback();
                DataReturn::returnJson(0,'兑换失败');
            }
        }else{
            DataReturn::returnJson(0,'非法请求');
        }

    }

    /**
     * @猿份哥
     * ajax获取地址
     */
    public function getAddress(){
        $address_id = input('address_id');
        $address = Db::name('user_address')->where(['address_id'=>$address_id])
            ->field(['address_id','consignee','mobile','province','city','district','twon','address'])
            ->find();
        $address['fulladdress'] = getTotalAddress($address['province'],$address['city'],$address['district'],$address['twon'],$address['address']);
        $address['mobile'] = substr($address['mobile'], 0, 3).'****'.substr($address['mobile'], 7);
        DataReturn::returnJson(1,'选择成功',$address);
    }


    /**
     * @猿份哥
     * 获取兑水订单列表api接口
     */
    public function getOrderInfo(){

        $users = Db::name('users')->where(['user_id'=>$this->user['user_id']])->field('user_id,nickname,level,head_pic,water_coin,is_sales')->find();

        $page = $this->request->param('p');

        $orderInfo = model\ExchangeOrder::where(['user_id'=>$users['user_id']])->with(['split','address','split.info'])
            ->field(['address_id','create_time','is_deliver','delivery_time','id','total_coin'])
            ->order('create_time desc')
            ->page("$page,2")
            ->select();
        $orderInfo = collection($orderInfo)->toArray();
        foreach ($orderInfo as $k=>&$v){
            $v['day'] = date('d',$v['create_time']);
            $v['year'] = date('Y.m',$v['create_time']);

            if($v['is_deliver'] == 0){
                $deliver_info = '待发货';
            }elseif ($v['is_deliver'] == 1){
                $deliver_info = '已发货';
            }else{
                $deliver_info = '已收货';
            }

            $v['deliver_info'] = $deliver_info;

            //组合地址
            $v['address']['fulladdress'] = getTotalAddress($v['address']['province'],$v['address']['city'],$v['address']['district'],$v['address']['twon'],$v['address']['address']);

            foreach ($v['split'] as $k1=>&$v1){
                $v1['info']['original_img'] = SITE_URL.$v1['info']['original_img'];
            }
        }
        return new ApiResponse($orderInfo);
    }

    /**
     * @猿份哥
     * 修改订单状态
     */
    public function alterStatus(){
        if(request()->isAjax()){
            $order_id = input('order_id');
            $res = model\ExchangeOrder::update(['is_deliver'=>2],['id'=>$order_id]);
            if($res){
                DataReturn::returnJson(1,'收货成功');
            }else{
                DataReturn::returnJson(0,'系统繁忙,请稍后再试...');
            }

        }else{
            DataReturn::returnJson(-1,'非法请求',[]);
        }
    }


    //水币明细
    public function detailed(){

        //用户信息
        $users = db('users')
            ->where(['user_id'=>$this->user['user_id']])
            ->field('user_id,nickname,level,head_pic,water_coin,is_sales')
            ->find();
        //返币记录
        $water_log = db('water_coin_log')->where(['user_id'=>$users['user_id']])->select();

        $total_water = db('water_coin_log')
            ->where(['user_id'=>$users['user_id']])
            ->field('sum(water_coin) total_water')
            ->find();

        $water_list = array();
        foreach ($water_log as $key => $val) {
            $_t = $val;
            $_t['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s',$val['create_time']):'0000-00-00 00:00:00';
            $_t['time'] = date('m',$val['create_time']).'月返币';
            $water_list[] = $_t;
        }

        $total_coin = db('exchange_order')
            ->where(['user_id'=>$users['user_id']])
            ->field('sum(total_coin) total_coins')
            ->find();

        $exchange_order = db('exchange_order')
            ->where(['user_id'=>$users['user_id']])
            ->field('user_id,total_coin,create_time')
            ->select();

        $exchange_list = array();
        foreach ($exchange_order as $key => $val) {
            $_t = $val;
            $_t['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s',$val['create_time']):'0000-00-00 00:00:00';
            $exchange_list[] = $_t;
        }

        $this->assign('user',$users);
        $this->assign('total_water',$total_water['total_water']);
        $this->assign('water_list',$water_list);
        $this->assign('total_coin',$total_coin['total_coins']);
        $this->assign('order_list',$exchange_list);
        $this->assign('title', '水币明细');
        return $this->fetch();
    }

    //撤销退押
    public function Revoke($id){

        if(empty($id)) returnJson(-1,'缺少参数');

        $apply = db('apply_deposit')->where(['id'=>$id,'user_id'=>$this->user['user_id']])->find();

        $res = db('users')->where(['user_id'=>$apply['user_id']])->update(['level'=>$apply['apply_level']]);

        if($res !== false) {
            $where['id'] = $id;
            $where['user_id'] = $this->user['user_id'];
            $where['status'] = array('in',[0,1,4]);
            db('apply_deposit')->where($where)->delete();
            returnJson(1,'撤销成功');
        }
        returnJson(-1,'撤销失败');
    }

    //支付
    public function doPay($amount, $level_id, $checked, $__token__){
        try {

            if($checked == 0) returnJson(-1,'必须同意充值协议');

            if($level_id != UserLevel::LEVEL_MEMBER && $level_id != UserLevel::LEVEL_SENIOR_MEMBER) {
                returnJson(-1,'系统错误');
            }

            $level = db('user_level')
                ->where(['level_id'=>$level_id])
                ->field('level_id,amount')->find();

            $user = db('users')
                ->where(['user_id'=>$this->user['user_id']])
                ->field('user_id,level,deposit')->find();

            if($user && $user['level'] == 1){
                $level['amount'] -= $user['deposit'];
            }

            if((float) $amount != (float) $level['amount']) returnJson(-1,'金额错误');

            $token = array('__token__' => $__token__);
            if (!TokenValidate::checkToken($token)) {
                throw new TokenInvalidException('页面已过期，请刷新页面');
            }

            if(!$user['user_id']){
                throw new Exception();
            }

            Db::startTrans();

            $order_sn = (new OrderLogic)->get_order_sn();
            // 生成支付单
            $paymentLog = model\PaymentLog::create([
                'user_id'  => $user['user_id'],
                'status'   => model\PaymentLog::STATUS_UNPAID,
                'order_sn' => $order_sn,
                'amount'   => $amount,
                'extra'    => [
                    model\PaymentLog::EXTRA_PAY_REASON   => model\PaymentLog::PAY_FOR_DEPOSIT,
                    model\PaymentLog::EXTRA_LEVEL_ID => $level_id,
                    model\PaymentLog::EXTRA_USER_ID => $user['user_id'],
                ],
            ]);

            Db::commit();

            $this->request->token();

            $url = url('/mobile/payment_new/pay',array('pid'=>$paymentLog['id']));
            returnJson(1,'支付单创建成功',['pid' => $paymentLog['id'],'url' => $url]);

        } catch (TokenInvalidException $e) {
            return new ApiResponse($e->getMessage(), 'error');

        } catch (ManagementDeniedException $e) {
            $this->request->token();

            $iconUrl = 'images/nodata1.png';
            $this->assign('icon_url', $iconUrl);
            $this->assign('text', $e->getMessage());

            return $this->fetch('cantmanagement');

        } catch (NoticeException $e) {
            $this->request->token();

            return new ApiResponse($e->getMessage(), 'error');

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);
            $this->request->token();

            return new ApiResponse('操作失败', 'error');
        }
    }

}