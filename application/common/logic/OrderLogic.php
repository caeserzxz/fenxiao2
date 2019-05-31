<?php

namespace app\common\logic;

use app\common\model\Order;
use app\common\model\SpecGoodsPrice;
use app\common\model\Users;
use app\common\logic\RewardLogic;
use think\Db;

/**
 * Class orderLogic
 * @package Common\Logic
 */
class OrderLogic
{
    protected $user_id = 0;
    protected $action;
    protected $cartList;

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function setCartList($cartList)
    {
        $this->cartList = $cartList;
    }

    /**
     * 取消订单
     * @param $user_id |用户ID
     * @param $order_id |订单ID
     * @param string $action_note 操作备注
     * @return array
     */
    public function cancel_order($user_id, $order_id, $action_note = '您取消了订单')
    {

        $order = M('order')->where(array('order_id' => $order_id, 'user_id' => $user_id))->find();
        //检查是否未支付订单 已支付联系客服处理退款
        if (empty($order))
            return array('status' => -1, 'msg' => '订单不存在', 'result' => '');
        if ($order['order_status'] == 3) {
            return array('status' => -1, 'msg' => '该订单已取消', 'result' => '');
        }
        //检查是否未支付的订单
        if ($order['pay_status'] > 0 || $order['order_status'] > 0)
            return array('status' => -1, 'msg' => '支付状态或订单状态不允许', 'result' => '');
        //获取记录表信息
        //$log = M('account_log')->where(array('order_id'=>$order_id))->find();
        //有余额支付的情况
        /*if ($order['user_money'] > 0 || $order['integral'] > 0) {
            accountLog($user_id, $order['user_money'], $order['integral'], "订单取消，退回{$order['user_money']}元,{$order['integral']}积分", 0, $order['order_id'], $order['order_sn']);
        }

        if ($order['coupon_price'] > 0) {
            $res = array('use_time' => 0, 'status' => 0, 'order_id' => 0);
            M('coupon_list')->where(array('order_id' => $order_id, 'uid' => $user_id))->save($res);
        }*/

        $row = M('order')->where(array('order_id' => $order_id, 'user_id' => $user_id))->save(array('order_status' => 3));
        if (tpCache('shopping.reduce') == 1) {
            $this->alterReturnGoodsInventory($order);
        }
        $data['order_id'] = $order_id;
        $data['action_user'] = 0;
        $data['action_note'] = $action_note;
        $data['order_status'] = 3;
        $data['pay_status'] = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['log_time'] = time();
        $data['status_desc'] = '用户取消订单';
        M('order_action')->add($data);//订单操作记录

        //订单取消，返还金豆，云豆
//        $rewardLogic=new RewardLogic();
//        $rt=$rewardLogic->cancalOrder($order,$user_id);


        if (!$row)
            return array('status' => -1, 'msg' => '操作失败', 'result' => '');
        return array('status' => 1, 'msg' => '操作成功', 'result' => '');

    }

    public function addReturnGoods($rec_id, $order)
    {
        $data = I('post.');
        $confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            return ['result' => -1, 'msg' => '已经超过' . ($confirm_time_config ?: 0) . "天内退货时间"];
        }

        $img = $this->uploadReturnGoodsImg();
        if ($img['status'] !== 1) {
            return $img;
        }
        $data['imgs'] = $img['result'] ?: ($data['imgs'] ?: ''); //兼容小程序，多传imgs

        $data['addtime'] = time();
        $data['user_id'] = $order['user_id'];
        $order_goods = M('order_goods')->where(array('rec_id' => $rec_id))->find();
        if ($data['type'] < 2) {
            //退款申请，若该商品有赠送积分或优惠券，在平台操作退款时需要追回
            $rate = round($order_goods['member_goods_price'] * $data['goods_num'] / $order['goods_price'], 2);
            if ($order['order_amount'] > 0 && !empty($order['pay_code'])) {
                $data['refund_money'] = $rate * $order['order_amount'];//退款金额
                $data['refund_deposit'] = $rate * $order['user_money'];//该退余额支付部分
                $data['refund_integral'] = floor($rate * $order['integral']);//该退积分支付
            } else {
                $data['refund_deposit'] = $rate * $order['user_money'] + $rate * $order['order_amount'];//该退余额支付部分
                $data['refund_integral'] = floor($rate * $order['integral']);//该退积分支付
            }
        }

        if (!empty($data['id'])) {
            $result = M('return_goods')->where(array('id' => $data['id']))->save($data);
        } else {
            $result = M('return_goods')->add($data);
        }

        if ($result) {
            return ['status' => 1, 'msg' => '申请成功'];
        }
        return ['status' => -1, 'msg' => '申请失败'];
    }

    /**
     * 上传退换货图片，兼容小程序
     * @return array
     */
    public function uploadReturnGoodsImg()
    {
        $return_imgs = '';
        if ($_FILES['return_imgs']['tmp_name']) {
            $files = request()->file("return_imgs");
            if (is_object($files)) {
                $files = [$files]; //可能是一张图片，小程序情况
            }
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];
            $dir = 'public/upload/return_goods/';
            if (!($_exists = file_exists($dir))) {
                $isMk = mkdir($dir);
            }
            $parentDir = date('Ymd');
            foreach ($files as $key => $file) {
                $info = $file->rule($parentDir)->validate($validate)->move($dir, true);
                if ($info) {
                    $filename = $info->getFilename();
                    $new_name = '/' . $dir . $parentDir . '/' . $filename;
                    $return_imgs[] = $new_name;
                } else {
                    return ['status' => -1, 'msg' => $file->getError()];//上传错误提示错误信息
                }
            }
            if (!empty($return_imgs)) {
                $return_imgs = implode(',', $return_imgs);// 上传的图片文件
            }
        }

        return ['status' => 1, 'msg' => '操作成功', 'result' => $return_imgs];
    }

    /**
     * 获取可申请退换货订单商品
     * @param $sale_t
     * @param $keywords
     * @param $user_id
     * @return array
     */
    public function getReturnGoodsIndex($sale_t, $keywords, $user_id)
    {
        if ($keywords) {
            $condition['order_sn'] = $keywords;
        }
        if ($sale_t == 1) {
            //三个月内
            $condition['add_time'] = array('gt', 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
        } else if ($sale_t == 2) {
            //三个月前
            $condition['add_time'] = array('lt', 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
        }
        $condition['user_id'] = $user_id;
        $condition['pay_status'] = 1;
        $condition['shipping_status'] = 1;
        $condition['deleted'] = 0;
        $count = M('order')->where($condition)->count();
        $Page = new \think\Page($count, 10);
        $show = $Page->show();
        $order_list = M('order')->where($condition)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $data = M('order_goods')->where(['order_id' => $v['order_id'], 'is_send' => ['lt', 2]])->select();
            if (!empty($data)) {
                $order_list[$k]['goods_list'] = $data;
            } else {
                unset($order_list[$k]);  //除去没有可申请的订单
            }

        }

        return [
            'order_list' => $order_list,
            'page' => $show
        ];
    }

    /**
     * 获取退货列表
     * @param type $keywords
     * @param type $addtime
     * @param type $status
     * @return type
     */
    public function getReturnGoodsList($keywords, $addtime, $status, $user_id = 0)
    {
        if ($keywords) {
            $where['order_sn|goods_name'] = array('like', "%$keywords%");
        }
        if ($status === '0' || !empty($status)) {
            $where['status'] = $status;
        }
        if ($addtime == 1) {
            $where['addtime'] = array('gt', (time() - 90 * 24 * 3600));
        }
        if ($addtime == 2) {
            $where['addtime'] = array('lt', (time() - 90 * 24 * 3600));
        }
        $query = M('return_goods')->alias('r')->field('r.*,g.goods_name')
            ->join('__ORDER__ o', 'r.order_id = o.order_id AND o.deleted = 0 AND o.user_id=' . $user_id)
            ->join('__GOODS__ g', 'r.goods_id = g.goods_id', 'LEFT')
            ->where($where);
        $query2 = clone $query;
        $count = $query->count();
        $page = new \think\Page($count, 10);
        $list = $query2->order("id desc")->limit($page->firstRow, $page->listRows)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField('goods_id,goods_name');
        }

        return [
            'goodsList' => $goodsList,
            'return_list' => $list,
            'page' => $page->show()
        ];
    }

    /**
     * 删除订单
     * @param type $order_id
     * @return type
     */
    public function delOrder($order_id)
    {
        $validate = validate('order');
        if (!$validate->scene('del')->check(['order_id' => $order_id])) {
            return ['status' => 0, 'msg' => $validate->getError()];
        }
        if (empty($this->user_id)) return ['status' => -1, 'msg' => '非法操作'];
        $row = M('order')->where(['user_id' => $this->user_id, 'order_id' => $order_id])->update(['deleted' => 1]);
        if (!$row) {
            M('order_goods')->where(['order_id' => $order_id])->update(['deleted' => 1]);
            return ['status' => -1, 'msg' => '删除失败'];
        }
        return ['status' => 1, 'msg' => '删除成功'];
    }

    /**
     * 记录取消订单
     */
    public function recordRefundOrder($user_id, $order_id, $user_note, $consignee, $mobile)
    {
        $order = M('order')->where(['order_id' => $order_id, 'user_id' => $user_id])->find();
        if (!$order) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }
        $order_return_num = M('return_goods')->where(['order_id' => $order_id, 'user_id' => $user_id, 'status' => ['neq', 5]])->count();
        if ($order_return_num > 0) {
            return ['status' => -1, 'msg' => '该订单中有商品正在申请售后'];
        }
        $order_status = 3;//已取消
        $order_info = [
            'user_note' => $user_note,
            'consignee' => $consignee,
            'mobile' => $mobile,
            'order_status' => $order_status,
        ];

        $result = M('order')->where(['order_id' => $order_id])->update($order_info);
        if (!$result) {
            return ['status' => 0, 'msg' => '操作失败'];
        }

        $data['order_id'] = $order_id;
        $data['action_user'] = $user_id;
        $data['action_note'] = $user_note;
        $data['order_status'] = $order_status;
        $data['pay_status'] = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['log_time'] = time();
        $data['status_desc'] = '用户取消已付款订单';
        M('order_action')->add($data);//订单操作记录
        return ['status' => 1, 'msg' => '提交成功'];
    }

    /**
     *    生成兑换码
     * 长度 =3位 + 4位 + 2位 + 3位  + 1位 + 5位随机  = 18位
     * @param $order
     * @return mixed
     */
    function make_virtual_code($order)
    {
        $order_goods = M('order_goods')->where(array('order_id' => $order['order_id']))->find();
        $goods = M('goods')->where(array('goods_id' => $order_goods['goods_id']))->find();
        M('order')->where(array('order_id' => $order['order_id']))->save(array('order_status' => 1, 'shipping_time' => time()));
        $perfix = mt_rand(100, 999);
        $perfix .= sprintf('%04d', $order['user_id'] % 10000)
            . sprintf('%02d', (int)$order['user_id'] % 100) . sprintf('%03d', (float)microtime() * 1000);

        for ($i = 0; $i < $order_goods['goods_num']; $i++) {
            $order_code[$i]['order_id'] = $order['order_id'];
            $order_code[$i]['user_id'] = $order['user_id'];
            $order_code[$i]['vr_code'] = $perfix . sprintf('%02d', (int)$i % 100) . rand(5, 1);
            $order_code[$i]['pay_price'] = $goods['shop_price'];
            $order_code[$i]['vr_indate'] = $goods['virtual_indate'];
            $order_code[$i]['vr_invalid_refund'] = $goods['virtual_refund'];
        }

        $res = checkEnableSendSms("7");

        //生成虚拟订单, 向用户发送短信提醒
        if ($res && $res['status'] == 1) {
            $sender = $order['mobile'];
            $goods_name = $goods['goods_name'];
            $goods_name = getSubstr($goods_name, 0, 10);
            $params = array('goods_name' => $goods_name);
            sendSms("7", $sender, $params);
        }

        return M('vr_order_code')->insertAll($order_code);
    }

    /**
     * 自动取消订单
     */
    public function abolishOrder()
    {
        $set_time = 1; //自动取消时间/天 默认1天
        $abolishtime = strtotime("-$set_time day");
        $order_where = [
            'user_id' => $this->user_id,
            'add_time' => ['lt', $abolishtime],
            'pay_status' => 0,
            'order_status' => 0
        ];
        $order = Db::name('order')->where($order_where)->getField('order_id', true);
        foreach ($order as $key => $value) {
            $result = $this->cancel_order($this->user_id, $value);
        }
        return $result;
    }

    /**
     * 添加一个订单
     *
     * @param $user_id |用户id
     * @param $address_id |地址id
     * @param $shipping_code |物流编号
     * @param $invoice_title |发票
     * @param int $coupon_id |优惠券id
     * @param $car_price |各种价格
     * @param string $user_note |用户备注
     * @param string $pay_name |支付名称
     * @param string $share_id |分享者id
     * @param string $g_type   |商品类型
     * @param string $share_id |分享者id
     * @return array
     * @throws \think\exception\DbException
     */
    public function addOrder($user_id, $address_id, $shipping_code, $invoice_title, $coupon_id = 0, $car_price = [], $user_note = '', $pay_name = '', $share_id = "", $g_type = 0, $dealDou = array(), $e_discount = 0)
    {

        // 仿制灌水 1天只能下 50 单  // select * from `tp_order` where user_id = 1  and order_sn like '20151217%'
        //$order_count = M('Order')->where("user_id",$user_id)->where('order_sn', 'like', date('Ymd')."%")->count(); // 查找购物车商品总数量
        //if($order_count >= 50) return array('status'=>-9,'msg'=>'一天只能下50个订单','result'=>'');

        // 检查积分价格
        /*  $total_integral = $car_price['total_integral'];
          if ($total_integral) {
              $user = Users::get($user_id);
              $pointsRate = tpCache('shopping.point_rate');

              // 积分必须足够 积分总价 和 积分抵扣
              if ($user['pay_points'] < $total_integral + $car_price['pointsFee'] * $pointsRate) {
                  return array('status' => -31, 'msg' => '用户积分不足', 'result' => NULL);
              }

              // 扣除积分
              $user->save([
                  'pay_points' => ['exp', 'pay_points - ' . $total_integral],
              ]);
          }*/

        // 0插入订单 order
        $address = M('UserAddress')->where("address_id", $address_id)->find();
        $shipping = M('Plugin')->where("code", $shipping_code)->cache(true, TPSHOP_CACHE_TIME)->find();
        $order_sn = $this->get_order_sn();

        $config = M('n_goods_config')
            ->where('key','sys_commission_type')
            ->find();

        $data = array(
            //身份产品，直接是已付款，已确认
            'order_sn' => $order_sn, // 订单编号
            'user_id' => $user_id, // 用户id
            'consignee' => $address['consignee'], // 收货人
            'province' => $address['province'],//'省份id',
            'city' => $address['city'],//'城市id',
            'district' => $address['district'],//'县',
            'twon' => $address['twon'],// '街道',
            'address' => $address['address'],//'详细地址',
            'mobile' => $address['mobile'],//'手机',
            'zipcode' => $address['zipcode'],//'邮编',
            'email' => $address['email'],//'邮箱',
            //'shipping_code' => $shipping_code,//'物流编号',
            'shipping_code' => '',//'物流编号',
            //'shipping_name' => $shipping['name'], //'物流名称',                为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
            'shipping_name' => '', //'物流名称',                为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
            'invoice_title' => $invoice_title, //'发票抬头',
            'goods_price' => $car_price['goodsFee'],//'商品价格',
            'shipping_price' => $car_price['postFee'],//'物流价格',
            'user_money' => $car_price['balance'],//'使用余额',
            'coupon_price' => $car_price['couponFee'],//'使用优惠券',
            'integral' => $g_type == 4 ? intval(($car_price['goodsFee'] + $car_price['postFee'])) :($car_price['pointsFee'] * tpCache('shopping.point_rate')), //'使用积分',
            'integral_money' => $car_price['pointsFee'],//'使用积分抵多少钱',
            //'total_amount' => ($car_price['goodsFee'] + $car_price['postFee']),// 订单总额
            //'total_amount' => $car_price['goodsFee'],// 订单总额
            //若商品为积分商品和零元商品，只计算邮费
            'total_amount' => ($g_type == 3 || $g_type == 4 ) ? $car_price['postFee'] :$car_price['payables'],// 订单总额
            'order_amount' => ($g_type == 3 || $g_type == 4 ) ? $car_price['postFee'] :$car_price['payables'],//'应付款金额',     //身份订单时，这是要扣的金豆
            'add_time' => time(), // 下单时间
            'order_prom_id' => $car_price['order_prom_id'],//'订单优惠活动id',
            'order_prom_amount' => $car_price['order_prom_amount'],//'订单优惠活动优惠了多少钱',
            'user_note' => $user_note, // 用户下单备注
            'pay_name' => $pay_name,//支付方式，金豆，云豆，在线
            'order_type' => $g_type, //0普通商品，1外链商品跳转到京东淘宝，2特定产品（身份产品）,3，零元产品，4积分产品
        );
        //print_r($car_price);
      /*  print_r($data);
        exit;*/

        /*  $cartList = $this->cartList;
          print_r($cartList);exit;*/

        //黄丽建项目——查看系统是否开始自动确认收货
        if($config['value'] == 0){
            $data['commission_type'] = 0;
        }else{
            $data['commission_type'] = 1;
        }

        $order = new Order();
        $order->data($data, true);
        $orderSaveResult = $order->save();
        if ($orderSaveResult === false) {
            return array('status' => -8, 'msg' => '添加订单失败', 'result' => NULL);
        }

        // 记录订单操作日志
        $action_info = array(
            'order_id' => $order['order_id'],
            'action_user' => 0,
            'action_note' => '您提交了订单，请等待系统确认',
            'status_desc' => '提交订单', //''
            'log_time' => time(),
        );
        M('order_action')->insertGetId($action_info);

        // 1插入order_goods 表
        if ($this->action == 'buy_now') {
            $cartList = $this->cartList;
        } else {
            $cartList = M('Cart')->where(['user_id' => $user_id, 'selected' => 1])->select();
        }
        foreach ($cartList as $key => $val) {
            $goods = M('goods')->where("goods_id", $val['goods_id'])->cache(true, TPSHOP_CACHE_TIME)->find();
            $data2['order_id'] = $order['order_id']; // 订单id
            $data2['order_goods_sn'] = $this->get_order_goods_sn();   //单号
            $data2['goods_id'] = $val['goods_id']; // 商品id
            $data2['share_user_id'] = $share_id;
            $data2['goods_name'] = $val['goods_name']; // 商品名称
            $data2['goods_sn'] = $val['goods_sn']; // 商品货号
            $data2['goods_num'] = $val['goods_num']; // 购买数量
            $data2['market_price'] = $val['market_price']; // 市场价
            $data2['goods_price'] = $val['goods_price']; // 商品价               为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
            $data2['spec_key'] = $val['spec_key']; // 商品规格
            $data2['spec_key_name'] = $val['spec_key_name']; // 商品规格名称
            $data2['member_goods_price'] = $val['member_goods_price']; // 会员折扣价
            $data2['cost_price'] = $goods['cost_price']; // 成本价
            $data2['give_integral'] = $goods['give_integral']; // 购买商品赠送积分
            $data2['prom_type'] = $val['prom_type']; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
            $data2['prom_id'] = $val['prom_id']; // 活动id
            $data2['add_time'] = time();
            $data2['commission_type'] = $data['commission_type'];
            $order_goods_id = M("OrderGoods")->insertGetId($data2);
        }

        if (tpCache('shopping.reduce') == 1) {
            minus_stock($order);//下单减库存
        }
        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
        if ($g_type ==4 && $data['order_amount'] == 0) { //$g_type=4 代表积分兑换
            //扣除用户积分，改变订单状态
            $pay_points = -$data['integral'];
            $update_data['pay_points'] = ['exp', 'pay_points+' . $pay_points];
            $update = Db::name('users')->where('user_id', $user_id)->update($update_data);
            if($update){
                update_pay_status($order['order_sn']);
            }

        }
        //如果是零元产品，这里订单支付状态直接变成已支付
        if ($g_type== 3) { //商品类型：0普通商品，1外链商品跳转到京东淘宝，2特定产品（身份产品）,3，零元产品，4积分产品
            update_pay_status($order['order_sn']);
        }


        /*********************廖燕青商城订单处理****************************/


        /*********************END廖燕青商城订单处理****************************/

        // 2修改优惠券状态
        if ($coupon_id > 0) {
            $data3['uid'] = $user_id;
            $data3['order_id'] = $order['order_id'];
            $data3['use_time'] = time();
            $data3['status'] = 1;
            M('CouponList')->where("id", $coupon_id)->save($data3);
            $cid = M('CouponList')->where("id", $coupon_id)->getField('cid');
            M('Coupon')->where("id", $cid)->setInc('use_num'); // 优惠券的使用数量加一
        }

//        return 999;
        // 3 扣除积分 扣除余额
        if ($car_price['pointsFee'] > 0)
            M('Users')->where("user_id", $user_id)->setDec('pay_points', ($car_price['pointsFee'] * tpCache('shopping.point_rate'))); // 消费积分
        if ($car_price['balance'] > 0)
            M('Users')->where("user_id", $user_id)->setDec('user_money', $car_price['balance']); // 抵扣余额
        // 4 删除已提交订单商品
        if ($this->action != 'buy_now') {
            M('Cart')->where(['user_id' => $user_id, 'selected' => 1])->delete();
        }

        // 5 记录log 日志
        $data4['user_id'] = $user_id;
        $data4['user_money'] = -$car_price['balance'];
        $data4['pay_points'] = $g_type == 4 ? -(intval(($car_price['goodsFee'] + $car_price['postFee']))) : -($car_price['pointsFee'] * tpCache('shopping.point_rate'));
        $data4['change_time'] = time();
        $data4['desc']     = $g_type == 4 ? '积分兑换产品' : '下单消费';
        $data4['order_sn'] = $order['order_sn'];
        $data4['order_id'] = $order['order_id'];
        // 如果使用了积分或者余额才记录
        ($data4['user_money'] || $data4['pay_points']) && M("AccountLog")->add($data4);
        //记录积分的流水
        $u_amountData = array();
        $u_amountData['user_id'] = $user_id; //下单用户
        $u_amountData['money'] = $data4['pay_points'];   //积分数
        $u_amountData['desc'] = $data4['desc'];     //描述
        $u_amountData['obj'] = $data4 ? json_encode($data4) : null;
        $u_amountData['create_time'] = time();
        // 如果使用了积分才记录
        ($data4['pay_points']) && M('n_integral_log')->add($u_amountData);
//        return json_encode($data4);
        //分销开关全局
        try {
            $distribut_switch = tpCache('distribut.switch');
            if ($distribut_switch == 1 && file_exists(APP_PATH . 'common/logic/DistributLogic.php')) {
                $distributLogic = new \app\common\logic\DistributLogic();
                $distributLogic->rebateLog($order); // 生成分成记录
            }
        } catch (\Exception $e) {
            trace('生成记录:' . $e->getMessage(), 'debug');
        }

        // 如果有微信公众号 则推送一条消息到微信
        $user = M('OauthUsers')->where(['user_id' => $user_id, 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ($user) {
            $wx_user = M('wx_user')->find();
            $jssdk = new JssdkLogic($wx_user['appid'], $wx_user['appsecret']);
            $wx_content = "你刚刚下了一笔订单:{$order['order_sn']} 尽快支付,过期失效!";
            $jssdk->push_msg($user['openid'], $wx_content);
        }
        //用户下单, 发送短信给商家
        $res = checkEnableSendSms("3");
        $sender = tpCache("shop_info.mobile");

        if ($res && $res['status'] == 1 && !empty($sender)) {

            $params = array('consignee' => $order['consignee'], 'mobile' => $order['mobile']);
            $resp = sendSms("3", $sender, $params);
        }
        return array('status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_id']); // 返回新增的订单id
    }

    /**
     * 添加预售商品订单
     * @param $user_id
     * @param $address_id
     * @param $shipping_code
     * @param $invoice_title
     * @param $act_id
     * @param $pre_sell_price
     * @return array
     */
    public function addPreSellOrder($user_id, $address_id, $shipping_code, $invoice_title, $act_id, $pre_sell_price)
    {
        // 仿制灌水 1天只能下 50 单
        $order_count = M('Order')->where("user_id= $user_id and order_sn like '" . date('Ymd') . "%'")->count(); // 查找购物车商品总数量
        if ($order_count >= 50) {
            return array('status' => -9, 'msg' => '一天只能下50个订单', 'result' => '');
        }
        $address = M('UserAddress')->where(array('address_id' => $address_id))->find();
        $shipping = M('Plugin')->where(array('code' => $shipping_code))->find();
        $data = array(
            'order_sn' => date('YmdHis') . rand(1000, 9999), // 订单编号
            'user_id' => $user_id, // 用户id
            'consignee' => $address['consignee'], // 收货人
            'province' => $address['province'],//'省份id',
            'city' => $address['city'],//'城市id',
            'district' => $address['district'],//'县',
            'twon' => $address['twon'],// '街道',
            'address' => $address['address'],//'详细地址',
            'mobile' => $address['mobile'],//'手机',
            'zipcode' => $address['zipcode'],//'邮编',
            'email' => $address['email'],//'邮箱',
            'shipping_code' => $shipping_code,//'物流编号',
            'shipping_name' => $shipping['name'], //'物流名称',
            'invoice_title' => $invoice_title, //'发票抬头',
            'goods_price' => $pre_sell_price['cut_price'] * $pre_sell_price['goods_num'],//'商品价格',
            'total_amount' => $pre_sell_price['cut_price'] * $pre_sell_price['goods_num'],// 订单总额
            'add_time' => time(), // 下单时间
            'order_prom_type' => 4,
            'order_prom_id' => $act_id
        );
        if ($pre_sell_price['deposit_price'] == 0) {
            //无定金
            $data['order_amount'] = $pre_sell_price['cut_price'] * $pre_sell_price['goods_num'];//'应付款金额',
        } else {
            //有定金
            $data['order_amount'] = $pre_sell_price['deposit_price'] * $pre_sell_price['goods_num'];//'应付款金额',
        }
        $order_id = Db::name('order')->insertGetId($data);
//        M('goods_activity')->where(array('act_id'=>$act_id))->setInc('act_count',$pre_sell_price['goods_num']);
        if ($order_id === false) {
            return array('status' => -8, 'msg' => '添加订单失败', 'result' => NULL);
        }
        logOrder($order_id, '您提交了订单，请等待系统确认', '提交订单', $user_id);
        $order = M('Order')->where("order_id = $order_id")->find();
        $goods_activity = M('goods_activity')->where(array('act_id' => $act_id))->find();
        $goods = M('goods')->where(array('goods_id' => $goods_activity['goods_id']))->find();
        $data2['order_id'] = $order_id; // 订单id
        $data2['goods_id'] = $goods['goods_id']; // 商品id
        $data2['goods_name'] = $goods['goods_name']; // 商品名称
        $data2['goods_sn'] = $goods['goods_sn']; // 商品货号
        $data2['goods_num'] = $pre_sell_price['goods_num']; // 购买数量
        $data2['market_price'] = $goods['market_price']; // 市场价
        $data2['goods_price'] = $goods['shop_price']; // 商品团价
        $data2['cost_price'] = $goods['cost_price']; // 成本价
        $data2['member_goods_price'] = $pre_sell_price['cut_price']; //预售价钱
        $data2['give_integral'] = $goods_activity['integral']; // 购买商品赠送积分
        $data2['prom_type'] = 4; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠 ,4 预售商品
        $data2['prom_id'] = $goods_activity['act_id'];
        Db::name('order_goods')->insert($data2);
        // 如果有微信公众号 则推送一条消息到微信
        $user = M('OauthUsers')->where(['user_id' => $user_id, 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ($user['oauth'] == 'weixin') {
            $wx_user = M('wx_user')->find();
            $jssdk = new JssdkLogic($wx_user['appid'], $wx_user['appsecret']);
            $wx_content = "你刚刚下了一笔预售订单:{$order['order_sn']} 尽快支付,过期失效!";
            $jssdk->push_msg($user['openid'], $wx_content);
        }
        return array('status' => 1, 'msg' => '提交订单成功', 'result' => $order_id); // 返回新增的订单id
    }

    /**
     * 获取订单 order_sn
     * @return string
     */
    public function get_order_sn()
    {
        $order_sn = null;
        // 保证不会有重复订单号存在
        while (true) {
            $order_sn = date('YmdHis') . rand(1000, 9999); // 订单编号
            $order_sn_count = M('order')->where("order_sn = " . $order_sn)->count();
            if ($order_sn_count == 0)
                break;
        }

        return $order_sn;
    }

    /**
     * 获取订单商品单号 order_sn
     * @return string
     */
    public function get_order_goods_sn()
    {
        $order_sn = null;
        // 保证不会有重复订单号存在
        while (true) {
            $order_sn = date('YmdHis') . rand(1000, 9999); // 订单编号
            $order_sn_count = M('order_goods')->where("order_goods_sn = " . $order_sn)->count();
            if ($order_sn_count == 0)
                break;
        }

        return $order_sn;
    }

    /*
     * 订单操作记录
     */
    public function orderActionLog($order_id, $action, $note = '')
    {
        $order = M('order')->where(array('order_id' => $order_id))->find();
        $data['order_id'] = $order_id;
        $data['action_user'] = session('admin_id');
        $data['action_note'] = $note;
        $data['order_status'] = $order['order_status'];
        $data['pay_status'] = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['log_time'] = time();
        $data['status_desc'] = $action;
        return M('order_action')->add($data);//订单操作记录
    }

    /**
     * 取消订单后改变库存，根据不同的规格，商品活动修改对应的库存
     * @param $order
     * @param $rec_id |订单商品表id 如果有只返还订单某个商品的库存,没有返还整个订单
     */
    public function alterReturnGoodsInventory($order, $rec_id = '')
    {
        if ($rec_id) {
            $orderGoodsWhere['rec_id'] = $rec_id;
            $retunn_info = Db::name('return_goods')->where($orderGoodsWhere)->select(); //查找购买数量和购买规格
            $order_goods_prom = Db::name('order_goods')->where($orderGoodsWhere)->find(); //购买时参加的活动
            $order_goods = $retunn_info;
            $order_goods[0]['prom_type'] = $order_goods_prom['prom_type'];
            $order_goods[0]['prom_id'] = $order_goods_prom['prom_id'];
        } else {
            $orderGoodsWhere = ['order_id' => $order['order_id']];
            $order_goods = Db::name('order_goods')->where($orderGoodsWhere)->select(); //查找购买数量和购买规格
        }
        foreach ($order_goods as $key => $val) {
            if (!empty($val['spec_key'])) { // 先到规格表里面扣除数量
                $SpecGoodsPrice = new SpecGoodsPrice();
                $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
                if ($specGoodsPrice) {
                    $specGoodsPrice->store_count = $specGoodsPrice->store_count + $val['goods_num'];
                    $specGoodsPrice->save();//有规格则增加商品对应规格的库存
                }
            } else {
                M('goods')->where(['goods_id' => $val['goods_id']])->setInc('store_count', $val['goods_num']);//没有规格则增加商品库存
            }
            update_stock_log($order['user_id'], $val['goods_num'], $val, $order['order_sn']);//库存日志

            Db::name('Goods')->where("goods_id", $val['goods_id'])->setDec('sales_sum', $val['goods_num']); // 减少商品销售量
            //更新活动商品购买量
            if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
                $GoodsPromFactory = new GoodsPromFactory();
                $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
                $prom = $goodsPromLogic->getPromModel();
                if ($prom['is_end'] == 0) {
                    $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                    M($tb)->where("id", $val['prom_id'])->setDec('buy_num', $val['goods_num']);
                    M($tb)->where("id", $val['prom_id'])->setDec('order_num', $val['goods_num']);
                }
            }
        }
    }


    /*
     *
     * 订单支付后回调。如果支付成功：
     * 1：购买的是总代身份产品，需要给用户更改身份
     * 2：订单是香港仓或者保税仓订单，需要进行分销计算
     *
     * $orderID 订单id
     * */
    public function callBackDealOrder($orderID)
    {
        //订单信息
        $orderInfo = M('order')->where('order_id', $orderID)->find();
        if ($orderInfo) {
            if ($orderInfo['order_type'] == '1' || $orderInfo['order_type'] == '2') {
                //香港仓或者保税仓订单，需要计算分销
                //改为收货后才计算分佣，所以支付成功后不再马上执行分佣计算
                /*$rewardLogic = new RewardLogic();
                $saleData = $rewardLogic->salesReward($orderID);
                $managent_top_Data = $rewardLogic->managementReward($orderID);*/


            }/* elseif ($orderInfo['order_type'] == '0') {
                //普通订单，判断是否购买的总代身份产品
                $is_identity = 0; //是否为总代身份商品。0不是，1是。

                $orderGoodsList = M('order_goods')->where('order_id', $orderID)->select();
                foreach ($orderGoodsList as $k => $v) {
                    $goods = M('goods')->where('goods_id', $v['goods_id'])->find();
                    if ($goods['is_identity'] == '1') {
                        $is_identity = 1;
                    }
                }

                if ($is_identity == '1') {
                    //是购买的总代身份产品，需要更改用户身份
                    $userInfo = M('users')->where('user_id', $orderInfo['user_id'])->find();   //下单用户信息
                    if ($userInfo['user_type'] == '0') {    //用户仍旧还是会员身份，才支持修改
                        $userData = array();
                        $userData['user_type'] = '2';   //0普通消费者，会员；1健康大使；2总代；3大区经理
                        $userRt = M('users')->where('user_id', $userInfo['user_id'])->update($userData);

                        //判断是否存在旧的管理关系，存在则改变管理关系
                        $userManagement = M('n_user_management')->where('user_id', $userInfo['user_id'])->find();

                        if ($userManagement) {
                            //存在旧的管理关系，需要改变
                            $oldManagement = M('users')->where('user_id', $userManagement['management_id'])->find();//该用户是原来的管理者
                            if ($userData['user_type'] >= $oldManagement['user_type']) {
                                //申请后如果平级或者高级，则需要改变管理

                                $topManagement = M('n_user_management')->where('user_id', $oldManagement['user_id'])->find();

                                if ($topManagement) {
                                    //原管理者的上级，成为了现在的新上级
                                    $newManagementData = array();
                                    $newManagementData['management_id'] = $topManagement['management_id'];
                                    $newUserRt = M('n_user_management')->where('user_id', $userInfo['user_id'])->update($newManagementData);
                                } else {
                                    //原来的管理者没有上级了，则删除自己的管理关系链
                                    $deleteRt = M('n_user_management')->where('user_id', $userInfo['user_id'])->delete();
                                }
                            }
                        }
                    }
                }
            }*/
            //END结束计算分销判断

            //用户支付购买成功，获取马克币,按照设置的订单金额百分比
            $makebi_buy_order = 0;
            $makebi_buy_order_config = M('n_goods_config')->where('key', 'makebi_buy_order')->find();
            $makebi_buy_order = $makebi_buy_order_config ? $makebi_buy_order_config['value'] : 0;
            if ($makebi_buy_order > '0') {
                $obj = array();
                $obj['order_id'] = $orderInfo['order_id'];

                $buyOrder = array();
                $buyOrder['user_id'] = $orderInfo['user_id'];
                $buyOrder['money'] = $orderInfo['order_amount'] * $makebi_buy_order / 100;  //按照订单金额和设置的百分比获得马克币
                $buyOrder['reward_type'] = 0; //0不参与分销的奖励；1分销奖，2管理奖，3上荐奖
                $buyOrder['type'] = 1;    //0人民币，1马克币，2功德，3佣金
                $buyOrder['number'] = rand(10000, 99999) . time();
                $buyOrder['desc'] = '购物获取马克币';
                $buyOrder['obj'] = json_encode($obj);
                $buyOrder['create_time'] = time();

                $Rt = M('n_amount_log')->add($buyOrder);

                //记录用户马克币变动
                $users = M('users')->where('user_id', $orderInfo['user_id'])->find();
                $userUpdate = array();
                $userUpdate['total_makebi'] = $users['total_makebi'] + $buyOrder['money'];
                $userUpdate['makebi'] = $users['makebi'] + $buyOrder['money'];
                $userRt = M('users')->where('user_id', $orderInfo['user_id'])->update($userUpdate);
            }
        }

        return true;
    }
}