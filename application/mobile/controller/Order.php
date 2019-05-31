<?php

namespace app\mobile\controller;

use app\common\model\TeamFound;
use app\common\model;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\logic\RewardLogic;
use My\DataReturn;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Page;
use think\Request;
use think\db;
use app\common\logic\UsersUpLevel;

class Order extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    public function _initialize()
    {
        parent::_initialize();

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
            $user = $this->user->toArray();
            $this->user_id = $this->user['user_id'];
            session('user', $user);

            $this->assign('user', $user);
            $this->assign('user_id', $this->user_id);
        }

        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }

    /**
     *  测试虚假支付
     */
    public function testGetCode(){
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); // 订单id

        if(!session('user')) $this->error('请先登录',U('User/login'));

        $order = M('order')->where("order_id", $order_id)->find();
        if($order['pay_status'] == 1){
            // 已完成支付 跳转到订单详情
            $this->redirect(U('order/order_detail', ['id' => $order['order_id']]));
        }

        //组装假的报文，测试支付
        $orderUpdate=array();
        $orderUpdate['order_status']='1';
        $orderUpdate['pay_status']='1';
        $orderUpdate['pay_time']=time();

        $Rt=M('order')->where('order_id',$order_id)->update($orderUpdate);

        $this->redirect(U('order/order_detail', ['id' => $order['order_id']]));
    }

    /**
     * 订单列表
     *
     * @return mixed
     */
    public function order_list()
    {
        try {
            $this->assign('title', '我的订单');
            $page = $this->request->get('p');

            $where = [
                'user_id' => $this->user['user_id'],
                'order_prom_type' => ['lt', 5],
            ];

            $type = $this->request->get('type');

            switch ($type) {

                case 'wait_pay':// 待支付
                    $where['pay_status'] = 0;
                    $where['order_status'] = 0;
                    $where['pay_code'] = ['neq', 'cod'];
                    break;

                case 'wait_delivery':// 待发货
                    $where['shipping_status'] = ['neq', 1];
                    $where['order_status'] = ['in', [0, 1]];

                    $callback = function (db\Query $query) {
                        $query
                            ->where('pay_status = 1 OR pay_code = "cod"');
                    };
                    break;

                case 'wait_receive':// 待收货
                    $where['shipping_status'] = 1;
                    $where['order_status'] = 1;

                    break;

                case 'wait_comment':// 待评价
                    $where['order_status'] = 2;
                    break;

                case 'returns':// 退换货
                    $where['order_status'] = ['in', [3, 5]];
                    break;
            }
            $list = model\Order::all(function (db\Query $query) use ($where, $page, $callback) {
                $query
                    ->where($where)
                    ->order('add_time desc')
                    ->page($page, 20);
                if (is_callable($callback)) {
                    call_user_func($callback, $query);
                }
            });

            /** @var array[] $valueList 处理后列表 */
            $valueList = [];

            $model = new UsersLogic();
            foreach ($list as $i => $item) {
                $value = set_btn_order_status($item->toArray());

                // $order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount'];// 订单总额
                $data = $model->get_order_goods($item['order_id']);
                $value['goods_list'] = $data['result'];
                $valueList[$i] = $value;
            }


            $this->assign('order_status', C('ORDER_STATUS'));
            $this->assign('shipping_status', C('SHIPPING_STATUS'));
            $this->assign('pay_status', C('PAY_STATUS'));
            $this->assign('lists', $valueList);
            $this->assign('active', 'order_list');
            $this->assign('active_status', I('get.type'));
            $this->assign('type', $type);


            if ($_GET['is_ajax']) {
                return $this->fetch('ajax_order_list');
            }

            return $this->fetch(__FUNCTION__);


        } catch (Exception $e) {
            Log::error((string)$e);
            $this->error('操作失败');

            return null;
        }
    }


    /**
     * 检查用户是否领取零元产品
     *
     *
     */
    public function checkFree(){
        $user_id = I('user_id');
        $order = M('order')
            ->alias('o')
            ->join('order_goods og','o.order_id = og.order_id')
            ->join('goods g','og.goods_id = g.goods_id')
            ->where("o.user_id", $user_id)
            ->find();
        if($order){
            $data= [
                'data'  =>$order,
                'status'=> 1,
            ];
        }else{
            $data= [
                'data'  =>'',
                'status'=> 0,
            ];
        }
        $this->ajaxReturn($data);
    }

    /**
     * 检查用户是否领取零元产品2
     *
     *
     */
    public function checkFree2(){
        if (empty($this->user_id)) {
            $this->error('请先登录',U('Home/User/login'));
        }
        $order = M('order')
            ->alias('o')
            ->join('order_goods og','o.order_id = og.order_id')
            ->join('goods g','og.goods_id = g.goods_id')
            ->where("o.user_id", $this->user_id)
            ->find();
        if($order){
            $data= [
                'data'  =>$order,
                'status'=> 1,
            ];
        }else{
            $data= [
                'data'  =>'可以领取零元产品',
                'status'=> 0,
            ];
        }
        $this->ajaxReturn($data);
    }

    public function dealCartProvider($cartList)
    {
        $ptGoods = array();   //定义普通商品的仓库，根据不同供应商排列
        foreach ($cartList as $k => $v) {
            $provider = M('n_provider')->where('id', $v['provider_id'])->find();
            if ($provider) {
                //是普通商品并且有供应商
                if (!in_array($provider, $ptGoods)) {
                    array_push($ptGoods, $provider);
                };
            }
        }

        foreach ($ptGoods as $k => $v) {
            $ptGoods[$k]['goodsList'] = array();
            foreach ($cartList as $ka => $va) {
                if ($va['provider_id'] == $v['id']) {
                    array_push($ptGoods[$k]['goodsList'], $va);
                }
            }
        }

        return $ptGoods;
    }

    //拼团订单列表
    public function team_list()
    {
        $type = input('type');
        $Order = new \app\common\model\Order();
        $order_where = ['order_prom_type' => 6, 'user_id' => $this->user_id, 'deleted' => 0, 'pay_code' => ['<>', 'cod']];//拼团基础查询
        switch (strval($type)) {
            case 'WAITPAY':
                //待支付订单
                $order_where['pay_status'] = 0;
                $order_where['order_status'] = 0;
                break;
            case 'WAITTEAM':
                //待成团订单
                $found_order_id = Db::name('team_found')->where(['user_id' => $this->user_id, 'status' => 1])->getField('order_id', true);//团长待成团
                $follow_order_id = Db::name('team_follow')->where(['found_user_id' => $this->user_id, 'status' => 1])->getField('order_id', true);//团员待成团
                $team_order_id = array_merge($found_order_id, $follow_order_id);
                if (count($team_order_id) > 0) {
                    $order_where['order_id'] = ['in', $team_order_id];
                }
                break;
            case 'WAITSEND':
                //待发货
                $order_where['pay_status'] = 1;
                $order_where['shipping_status'] = ['<>', 1];
                $order_where['order_status'] = ['in', '0,1'];
                break;
            case 'WAITRECEIVE':
                //待收货
                $order_where['shipping_status'] = 1;
                $order_where['order_status'] = 1;
                break;
            case 'WAITCCOMMENT':
                //已完成
                $order_where['order_status'] = 2;
                break;
        }
        $request = Request::instance();
        $order_count = $Order->where($order_where)->count();
        $page = new Page($order_count, 10);
        $order_list = $Order->with('orderGoods')->where($order_where)->limit($page->firstRow . ',' . $page->listRows)->order('order_id desc')->select();
        $this->assign('order_list', $order_list);
        if ($request->isAjax()) {
            return $this->fetch('ajax_team_list');
//            $this->ajaxReturn(['status'=>1,'msg'=>'获取成功','result'=>$order_list]);
        }
        return $this->fetch();
    }

    public function team_detail()
    {
        $order_id = input('order_id');
        $Order = new \app\common\model\Order();
        $TeamFound = new TeamFound();
        $order_where = ['order_prom_type' => 6, 'order_id' => $order_id, 'deleted' => 0];
        $order = $Order->with('orderGoods')->where($order_where)->find();
        if (empty($order)) {
            $this->error('该订单记录不存在或已被删除');
        }
        $orderTeamFound = $order->teamFound;
        if ($orderTeamFound) {
            //团长的单
            $this->assign('orderTeamFound', $orderTeamFound);//团长
        } else {
            //去找团长
            $teamFound = $TeamFound::get(['found_id' => $order->teamFollow['found_id']]);
            $this->assign('orderTeamFound', $teamFound);//团长
        }
        $this->assign('order', $order);
        return $this->fetch();
    }

    /**
     * 订单详情
     * @return mixed
     */
    public function order_detail()
    {
        $id = I('get.id/d');
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];

        $region_list = get_region_list();
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info['invoice_no'] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $id))->select();

        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('region_list', $region_list);
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);

//        if (I('waitreceive')) {  //待收货详情
//            return $this->fetch('wait_receive_detail');
//        }
        return $this->fetch();
    }

    /**
     * 取消订单
     */
    public function cancel_order()
    {

        $id = I('get.id/d');
        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data = $logic->cancel_order($this->user_id, $id);
        $this->ajaxReturn($data);
    }

    /**
     * 确定收货成功，根据供应商收货，当订单下面所有订单商品都收货后，才将主表改为已收货
     */
    public function order_confirm()
    {
        $id = I('id/d', 0);

        $data = confirm_order($id, $this->user_id); //针对订单ID表收货
        if($data['status']==1){
            //将分佣更新到余额
                $levelLogic = new UsersUpLevel();
                $levelLogic->receivingGoods( $id );

            $this->redirect(U('Order/order_list',array('type'=>'wait_receive')));
        }else{
            $this->error('收货失败,请联系管理员!', U('Order/order_list',array('type'=>'wait_receive')));
        }
    //注释时间2019.5.27 原因 逻辑不通
//        //针对订单商品表进行收货
//        $orderGoods = M('order_goods')->where('rec_id', $id)->find();
//        $order_id = $orderGoods['order_id'];
//
//        //首先给该笔订单商品进行收货
//        $orderGoodsData = array();
//        $orderGoodsData['confirm_time'] = time();
//        $orderGoodsData['order_status'] = 2;  //订单商品已收货
//
//        $ogRt = M('order_goods')->where('rec_id', $id)->update($orderGoodsData);
//
//        //再判断整个订单订单所属所有订单商品是否都已经收货
//        $orderGoodsList = M('order_goods')->where('order_id', $order_id)->select();
//
//        $isAll = 1;   //0还有没收货，1全部都收货了
//        foreach ($orderGoodsList as $k => $v) {
//            if ($v['order_status'] == '1') {    //只要存在一件没有收货，则都还没有全部收货
//                $isAll = 0;
//            }
//        }
//
//        if ($isAll == '1') {
//            //如果已经全部收货，则将整个订单改为已收货
//            $orderData = array();
//            $orderData['order_status'] = 2;
//            $orderData['confirm_time'] = time();
//            $oRt = M('order')->where('order_id', $order_id)->update($orderData);
//
//            if($oRt){
//            //将分佣更新到余额
//                $levelLogic = new UsersUpLevel();
//                $levelLogic->receivingGoods( $id );
//            }
//        }

//        $this->redirect(U('Order/order_list',array('type'=>'wait_receive')));

    }

    //订单支付后取消订单
    public function refund_order()
    {

        $order_id = I('get.order_id/d');

        $order = M('order')
            //->field('order_id,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount')
            ->where(['order_id' => $order_id, 'user_id' => $this->user_id])
            ->find();

        $config = tpCache('reason');
        if (!empty($config['reason_config'])) {
            $reason = explode(PHP_EOL, $config['reason_config']);
        }

        //订单取消，返还金豆，云豆
        $rewardLogic = new RewardLogic();
        $rt = $rewardLogic->cancalOrder($order, $this->user_id);

        $this->assign('reason', $reason);
        $this->assign('user', $this->user);
        $this->assign('order', $order);
        return $this->fetch();
    }

    //申请取消订单
    public function record_refund_order()
    {
        $order_id = input('post.order_id', 0);
        $user_note = input('post.user_note', '');
        $consignee = input('post.consignee', '');
        $mobile = input('post.mobile', '');

        $logic = new \app\common\logic\OrderLogic;
        $return = $logic->recordRefundOrder($this->user_id, $order_id, $user_note, $consignee, $mobile);

        $this->ajaxReturn($return);
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {
        $rec_id = I('rec_id', 0);
        $return_goods = M('return_goods')->where(array('rec_id' => $rec_id))->find();
        if (!empty($return_goods)) {
            $this->error('已经提交过退货申请!', U('Order/return_goods_info', array('id' => $return_goods['id'])));
        }
        $order_goods = M('order_goods')->where(array('rec_id' => $rec_id))->find();
        $order = M('order')->where(array('order_id' => $order_goods['order_id'], 'user_id' => $this->user_id))->find();
        $confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            $this->error('已经超过' . $confirm_time_config . "天内退货时间");
//            return ['result'=>-1,'msg'=>'已经超过' . $confirm_time_config . "天内退货时间"];
        }
        if (empty($order)) $this->error('非法操作');
        if (IS_POST) {
            $model = new OrderLogic();
            $res = $model->addReturnGoods($rec_id, $order);  //申请售后
            if ($res['status'] == 1) $this->success($res['msg'], U('Order/return_goods_list'));
            $this->error($res['msg']);
        }
        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = 0;
        $config = tpCache('reason');
        if (!empty($config['reason_config'])) {
            $reason = explode(PHP_EOL, $config['reason_config']);
        }
        $this->assign('reason', $reason);
        $return_address = M('region')->where("id in (" . implode(',', $region_id) . ")")->getField('id,name');
        $address = db('config')->where(['inc_type' => 'shop_info', 'name' => 'address'])->value('value');
        $return_address = implode('', $return_address) . $address;
        $phone = db('config')->where(['inc_type' => 'shop_info', 'name' => 'phone'])->value('value');
        $this->assign('phone', $phone);
        $this->assign('return_address', $return_address);
        $this->assign('goods', $order_goods);
        $this->assign('order', $order);
        return $this->fetch();
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        //退换货商品信息
        $count = M('return_goods')->where("user_id", $this->user_id)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('return_goods')->where("user_id", $this->user_id)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');  //获取商品ID
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        }
        $state = C('REFUND_STATUS');
        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('state', $state);
        $this->assign('page', $page->show());// 赋值分页输出

        if (I('is_ajax')) {
            return $this->fetch('ajax_return_goods_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id = I('id/d', 0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
        $state = C('REFUND_STATUS');
        $this->assign('state', $state);
        $this->assign('goods', $goods);
        $this->assign('return_goods', $return_goods);
        return $this->fetch();
    }

    public function return_goods_refund()
    {
        $order_sn = I('order_sn');
        $where = array('user_id' => $this->user_id);
        if ($order_sn) {
            $where['order_sn'] = $order_sn;
        }
        $where['status'] = 5;
        $count = M('return_goods')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('return_goods')->where($where)->order("id desc")->limit($page->firstRow, $page->listRows)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr))
            $goodsList = M('goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField('goods_id,goods_name');
        $this->assign('goodsList', $goodsList);
        $state = C('REFUND_STATUS');
        $this->assign('list', $list);
        $this->assign('state', $state);
        $this->assign('page', $page->show());// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 取消售后服务
     * @author lxl
     * @time 2017-4-19
     */
    public function return_goods_cancel()
    {
        $id = I('id', 0);
        if (empty($id)) $this->error('参数错误');
        $return_goods = M('return_goods')->where(array('id' => $id, 'user_id' => $this->user_id))->find();
        if (empty($return_goods)) $this->error('参数错误');
        M('return_goods')->where(array('id' => $id))->save(array('status' => -2, 'canceltime' => time()));
        $this->success('取消成功', U('Order/return_goods_list'));
        exit;
    }

    /**
     * 换货商品确认收货
     * @author lxl
     * @time  17-4-25
     * */
    public function receiveConfirm()
    {
        $return_id = I('return_id/d');
        $return_info = M('return_goods')->field('order_id,order_sn,goods_id,spec_key')->where('id', $return_id)->find(); //查找退换货商品信息
        $update = M('return_goods')->where('id', $return_id)->save(['status' => 3]);  //要更新状态为已完成
        if ($update) {
            M('order_goods')->where(array(
                'order_id' => $return_info['order_id'],
                'goods_id' => $return_info['goods_id'],
                'spec_key' => $return_info['spec_key']))->save(['is_send' => 2]);  //订单商品改为已换货
            $this->success("操作成功", U("Order/return_goods_info", array('id' => $return_id)));
        }
        $this->error("操作失败");
    }

    /**
     *  评论晒单
     * @return mixed
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status');
        $logic = new \app\common\logic\CommentLogic;
        $result = $logic->getComment($user_id, $status); //获取评论列表
        $this->assign('comment_list', $result['result']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_comment_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *添加评论
     */
    public function add_comment()
    {
        if (IS_POST) {
            // 晒图片
            $files = request()->file('comment_img_file');
            $save_url = 'public/upload/comment/' . date('Y', time()) . '/' . date('m-d', time());
            foreach ($files as $file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $image_upload_limit_size = config('image_upload_limit_size');
                $info = $file->rule('uniqid')->validate(['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);

                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img[] = '/' . $save_url . '/' . $info->getFilename();
                } else {

                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }

            }
            if (!empty($comment_img)) {
                $add['img'] = serialize($comment_img);
            }

            $user_info = session('user');
            $logic = new UsersLogic();
            $add['goods_id'] = I('goods_id/d');
            $add['email'] = $user_info['email'];
            $hide_username = I('hide_username');
            if (empty($hide_username)) {
                $add['username'] = $user_info['nickname'];
            }
            $add['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
            $add['order_id'] = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            $add['is_show'] = 1; //默认显示
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = request()->ip();
            $add['user_id'] = $this->user_id;

            //添加评论
//            var_dump($add);die;
            $row = $logic->add_comment($add);

            if ($row['status'] == 1) {
                $this->success('评论成功', U('/Mobile/Order/comment', array('status' => 1)));
                exit();
            } else {
                $this->error($row['msg']);
            }
        }
        $rec_id = I('rec_id/d');
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        $this->assign('order_goods', $order_goods);
        return $this->fetch();
    }

    /**
     * 待收货列表
     * @author lxl
     * @time   2017/1
     */
    public function wait_receive()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索
        if (I('type') == 'WAITRECEIVE') {
            $where .= C(strtoupper(I('type')));
        }
        $count = M('order')->where($where)->count();
        $pagesize = C('PAGESIZE');
        $Page = new Page($count, $pagesize);
        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }

        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = 0;
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
            //订单物流单号
            $invoice_no = M('DeliveryDoc')->where("order_id", $value['order_id'])->getField('invoice_no', true);
            $order_list[$key][invoice_no] = implode(' , ', $invoice_no);
        }
        $this->assign('page', $show);
        $this->assign('order_list', $order_list);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_wait_receive');
            exit;
        }

        return $this->fetch();
    }

    /*
     * 创建身份订单
     * */
    public function creatVipOrder()
    {
        $goods_config = M('n_goods_config')
            ->where('key', 'identity_money')
            ->find();
        $money = $goods_config['value'] ? $goods_config['value'] : 0;

        $userInfo = Db::name('users')->where('user_id',session('user_id'))->find();

        $data['order_sn'] = date("YmdHis",time()).rand(1111,9999);
        $data['user_id'] = $userInfo['user_id'];
        $data['order_amount'] = $money;
        $data['pay_status'] = 0;
        $data['add_time'] = time();


        $result = Db::name('order_vip')
            ->insertGetId($data);
        if($result){
            $this->redirect(U('Cart/cart5', ['order_id' =>$result]));
        }else{
            $this->error("操作失败",'mobile/user/buyVip');
        }
    }
}