<?php

namespace app\api\controller;

use app\common\logic\CommentLogic;
use app\common\logic\OrderLogic;
use app\common\logic\UsersLogic;
use app\common\model\TeamFound;
use My\DataReturn;
use think\db;

class Order extends Base
{
    //需要检查登录的页面
    public function __construct()
    {
        parent::__construct();
        //不需验证登录的方法
        $nologin = [];

        if (!in_array(ACTION_NAME, $nologin)) {
            $this->checkLogin();
        }

    }
    //订单列表
    public function order_list()
    {
        $user_id = $this->user_id;
        $where   = ' user_id=' . $user_id;
        //条件搜索
        if (I('state')) {
            $where .= C(strtoupper(I('state')));
        }
        $where .= ' and order_prom_type < 5 '; //虚拟订单和拼团订单不列出来
        $pages      = I('pages') ?: 1; //页码
        $pagesize   = C('PAGESIZE'); //每页显示数
        $order_list = M('order')->where($where)->page($pages, $pagesize)->order('order_id desc')->select();

        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v); // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $order_goods_data = $model->get_order_goods($v['order_id']);
            $goods_list       = $order_goods_data['result'];
            foreach ($goods_list as $y => $a) {
                $goods_list[$y]['img'] = request()->domain() . goods_thum_images($a['goods_id'], 200, 200);
            }
            // $goods_list[$k]['img'] = goods_thum_images($goods_list[$k]['goods_id'],200,200);
            $order_list[$k]['goods_list'] = $goods_list;
        }
        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = 0;
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
                // $value['goods_list']['img'] = goods_thum_images($value['goods_list']['goods_id'],200,200);
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
            if ($value['order_status_desc'] == '待支付') {
                $order_state = 1;
            } else if ($value['order_status_desc'] == '待发货') {
                $order_state = 2;
            } else if ($value['order_status_desc'] == '待收货') {
                $order_state = 3;
            } else if ($value['order_status_desc'] == '待评价') {
                $order_state = 4;
            } else if ($value['order_status_desc'] == '已取消') {
                $order_state = 5;
            } else if ($value['order_status_desc'] == '已完成') {
                $order_state = 6;
            }
            $order_list[$key]['order_state'] = $order_state;
        }
        $data['lists'] = $order_list;
        DataReturn::returnJson(200, '获取数据成功！', $data);
    }
    //订单详情
    public function order_detail()
    {
        $order_id        = I('order_id/d');
        $map['order_id'] = $order_id;
        $map['user_id']  = $this->user_id;
        $order_info      = M('order')->where($map)->find();
        $order_info      = set_btn_order_status($order_info); // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            DataReturn::returnJson(500, '没有获取到订单信息');
        }
        //获取订单商品
        $model       = new UsersLogic();
        $region_list = get_region_list();
        $data        = $model->get_order_goods($order_info['order_id']);
        $result      = $data['result'];
        foreach ($result as $key => $value) {
            $result[$key]['img'] = request()->domain().goods_thum_images($value['goods_id'],100,100);
        }
        $order_info['goods_list'] = $result;
        $order_info['province']   = $region_list[$order_info['province']];
        $order_info['city']       = $region_list[$order_info['city']];
        $order_info['district']   = $region_list[$order_info['district']];
        $order_info['add_time']   = date('Y-m-d H:i:s', $order_info['add_time']);
        // $invoice_no = M('DeliveryDoc')->where("order_id", $order_id)->getField('invoice_no', true);
        // $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $order_id))->select();
        DataReturn::returnJson(200, '获取数据成功！', $order_info);
    }

    //取消订单(未支付)
    public function cancel_order()
    {
        $order_id = I('order_id/d');
        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data  = $logic->cancel_order($this->user_id, $order_id);
        if ($data['status'] == '-1') {
            DataReturn::returnJson(500, $data['msg']);
        } else {
            DataReturn::returnJson(200, $data['msg']);
        }
    }
    //确定收货
    public function order_confirm()
    {
        $order_id = I('order_id/d', 0);
        $data     = confirm_order($order_id, $this->user_id);
        if ($data['status'] != 1) {
            DataReturn::returnJson(500, $data['msg']);
        } else {
            $model = new UsersLogic();

            $order_goods = $model->get_order_goods($order_id);
            DataReturn::returnJson(200, '成功', $order_goods);
        }
    }
    //订单支付后取消订单详情
    public function refund_order()
    {
        $order_id = I('order_id/d');
        if (!$order_id) {
            DataReturn::returnJson(500, '订单号不能为空');
        }
        $order = M('order')
            ->field('order_id,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount')
            ->where(['order_id' => $order_id, 'user_id' => $this->user_id])
            ->find();
        $user = M('users')->where(['user_id' => $this->user_id])->find();

        $order['nickname'] = $user['nickname'];
        $order['mobile']   = $user['mobile'];
        $order['reason']   = ['订单不能按预计时间送达', '操作有误(商品、地址等选错)', '重复下单/误下单', '其他渠道价格更低', '该商品降价了', '不想买了', '其他原因'];
        DataReturn::returnJson(200, '获取数据成功', $order);
    }
    //申请取消订单(已支付)
    public function record_refund_order()
    {
        $order_id  = input('order_id', 0);
        $user_note = input('user_note', '');
        $consignee = input('consignee', '');
        $mobile    = input('mobile', '');

        $logic  = new \app\common\logic\OrderLogic;
        $return = $logic->recordRefundOrder($this->user_id, $order_id, $user_note, $consignee, $mobile);
        if ($return['status'] != 1) {
            DataReturn::returnJson(500, $return['msg']);
        } else {
            DataReturn::returnJson(200, $return['msg']);
        }
    }

    //申请售后商品数据
    public function return_goods_detail()
    {
        $rec_id = I('rec_id', 0);
        if (!$rec_id) {
            DataReturn::returnJson(500, '系统出错');
        }
        $return_goods = M('return_goods')->where(['rec_id' => $rec_id])->find();
        if ($return_goods) {
            DataReturn::returnJson(500, '已经提交过退货申请!');
        }
        $tp_config = M('config')->cache(true)->select();
        foreach ($tp_config as $k => $v) {
            $tpshop_config[$v['inc_type'] . '_' . $v['name']] = $v['value'];
        }
        $order_goods = M('order_goods')->where(['rec_id' => $rec_id])->find();
        $delivery    = M('delivery_doc')->where("order_id", $order_goods['order_id'])->find();

        $order_goods['consignee'] = $delivery['consignee'];
        $order_goods['mobile']    = $delivery['mobile'];
        $order_goods['order_sn']  = $delivery['order_sn'];
        $order_goods['img']       = request()->domain() . goods_thum_images($order_goods['goods_id'], 100, 100);

        $order_goods['shop_info_address'] = $tpshop_config['shop_info_address'];
        $order_goods['shop_info_phone']   = $tpshop_config['shop_info_phone'];

        $order_goods['addretu'] = '(周一至周五) 08:00-19:00';
        $order_goods['reason']  = ['订单不能按预计时间送达', '操作有误（商品、地址等选错）', '重复下单/误下单', '其他渠道价格更低', '该商品降价了', '不想买了', '其他原因'];
        DataReturn::returnJson(200, '获取数据成功', $order_goods);
    }
    //申请退货
    public function return_goods()
    {
        $rec_id = I('rec_id', 0);
        if (!$rec_id) {
            DataReturn::returnJson(500, '系统出错');
        }
        $order_goods         = M('order_goods')->where(['rec_id' => $rec_id])->find();
        $order               = M('order')->where(['order_id' => $order_goods['order_id'], 'user_id' => $this->user_id])->find();
        $confirm_time_config = tpCache('shopping.auto_service_date'); //后台设置多少天内可申请售后
        $confirm_time        = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            DataReturn::returnJson(500, '已经超过' . $confirm_time_config . "天内退货时间");
        }
        if (!$order) {
            DataReturn::returnJson(500, '非法操作');
        }

        $data = I('post.');
        if (!$data['goods_num']) {
            DataReturn::returnJson(500,'商品数量不正确');
        }
        if (!$data['reason']) {
            DataReturn::returnJson(500,'请选择原因');
        }
        if (!$data['describe']) {
            DataReturn::returnJson(500,'请输入问题描述');
        }
        if ($data['type'] > 0) {
            if ($data['is_receive'] ==0) {
                DataReturn::returnJson(500,'退货或者换货,需要选择已收到货');
            }
        }
        $data['addtime'] = time();
        $data['user_id'] = $order['user_id'];
        if ($data['type'] < 2) {
            //退款申请，若该商品有赠送积分或优惠券，在平台操作退款时需要追回
            $rate = round($order_goods['member_goods_price'] * $data['goods_num'] / $order['goods_price'], 2);
            if ($order['order_amount'] > 0 && !empty($order['pay_code'])) {
                $data['refund_money']    = $rate * $order['order_amount']; //退款金额
                $data['refund_deposit']  = $rate * $order['user_money']; //该退余额支付部分
                $data['refund_integral'] = floor($rate * $order['integral']); //该退积分支付
            } else {
                $data['refund_deposit']  = $rate * $order['user_money'] + $rate * $order['order_amount']; //该退余额支付部分
                $data['refund_integral'] = floor($rate * $order['integral']); //该退积分支付
            }
        }

        if (!empty($data['id'])) {
            $result = M('return_goods')->where(array('id' => $data['id']))->save($data);
        } else {
            $result = M('return_goods')->add($data);
        }
        if ($result) {
            DataReturn::returnJson(200, $res['msg']);
        } else {
            DataReturn::returnJson(500, $res['msg']);
        }
    }

    //退换货列表
    public function return_goods_list()
    {
        //退换货商品信息
        $pages        = I('pages') ?: 1; //页码
        $pagesize     = C('PAGESIZE'); //每页显示数
        $list         = M('return_goods')->where("user_id",$this->user_id)->order("id desc")->page($pages, $pagesize)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id'); //获取商品ID
        $goodsList    = M('goods')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        $state        = C('REFUND_STATUS');
        $lists = '';
        if ($list) {
            foreach ($list as $key => $value) {
                $_t = $value;
                $_t['original_img'] = request()->domain() . goods_thum_images($value['goods_id'], 100, 100);
                $_t['goods_news']   = $goodsList[$value['goods_id']];
                $_t['status_news']  = $state[$value['status']];
                $_t['addtime']      = date('Y-m-d H:i:s', $value['addtime']);
                switch ($value['status']) {
                    case '-2':
                        $service_state = '您的服务单已经取消';
                        break;
                    case '-1':
                        $service_state = '很抱歉！您的服务单未通过审核';
                        break;
                    case '0':
                        $service_state = '您的服务单已申请成功，待售后审核中';
                        break;
                    case '1':
                        $service_state = '您的服务单已通过审核';
                        break;
                    case '3':
                        $service_state = '您的服务单完成';
                        break;
                    default:
                        break;
                }
                if ($value['status'] == 2 && $value['type'] == 1) {
                    $service_state = '卖家已收到您寄回的物品,卖家已重新发货';
                }
                $_t['service_state'] = $service_state;
                $lists[] =$_t;
            }
        }
        $data['lists'] = $lists;
        DataReturn::returnJson(200, '获取数据成功', $data);
    }

    //退货详情
    public function return_goods_info()
    {
        $id = I('return_goods_id/d', 0);
        if (!$id) {
            DataReturn::returnJson(500, '系统出错');
        }
        $state        = C('REFUND_STATUS');
        $return_goods = M('return_goods')->where("id = $id")->find();
        $goods_id     = $return_goods['goods_id'];
        $goods        = M('goods')->where("goods_id = $goods_id")->find();

        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']); //订单的物流信息，服务类型为换货会显示
        if ($return_goods['imgs']) {
            $imgs = explode(',', $return_goods['imgs']);
            $imgs = array_filter($imgs);
            foreach ($imgs as $key => $value) {
                $_t = request()->domain().$value;
                $list[] = $_t;
            }
            $return_goods['imgs'] = $list;
        }
        $return_goods['status_new'] = $state[$return_goods['status']];
        $return_goods['addtime']    = date('Y-m-d H:i:s', $return_goods['addtime']);
        $return_goods['goods_img']  = request()->domain() . goods_thum_images($goods['goods_id'], 100, 100);
        $return_goods['goods_name'] = $goods['goods_name'];
        $return_goods['shop_price'] = $goods['shop_price'];
        switch ($return_goods['type']) {
            case '0':
               $return_goods['type_name'] = '期望处理方式:退款';
                break;
            case '1':
               $return_goods['type_name'] = '期望处理方式:退货退款';
                break;
            case '2':
               $return_goods['type_name'] = '期望处理方式:换货';
                break;
            default:
                break;
        }
        DataReturn::returnJson(200, '获取数据成功', $return_goods);
    }

    // public function return_goods_refund()
    // {
    //     $order_sn = I('order_sn');
    //     $where = array('user_id'=>$this->user_id);
    //     if($order_sn){
    //         $where['order_sn'] = $order_sn;
    //     }
    //     $where['status'] = 5;
    //     $count = M('return_goods')->where($where)->count();
    //     $page = new Page($count,10);
    //     $list = M('return_goods')->where($where)->order("id desc")->limit($page->firstRow, $page->listRows)->select();
    //     $goods_id_arr = get_arr_column($list, 'goods_id');
    //     if(!empty($goods_id_arr))
    //         $goodsList = M('goods')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
    //     $this->assign('goodsList', $goodsList);
    //     $state = C('REFUND_STATUS');
    //     $this->assign('list', $list);
    //     $this->assign('state',$state);
    //     $this->assign('page', $page->show());// 赋值分页输出
    //     return $this->fetch();
    // }

    //取消售后服务
    public function return_goods_cancel()
    {
        $id = I('return_goods_id', 0);
        if (!$id) {
            DataReturn::returnJson(500, '系统出错');
        }
        $return_goods = M('return_goods')->where(['id' => $id, 'user_id' => $this->user_id])->find();
        if (!$return_goods) {
            DataReturn::returnJson(500, '系统出错');
        }
        $result = M('return_goods')->where(['id' => $id])->save(['status' => -2, 'canceltime' => time()]);
        if ($result) {
            DataReturn::returnJson(200, '取消成功');
        } else {
            DataReturn::returnJson(500, '取消失败');
        }
    }
    // //换货商品确认收货
    // public function receiveConfirm(){
    //     $return_id=I('return_id/d');
    //     $return_info=M('return_goods')->field('order_id,order_sn,goods_id,spec_key')->where('id',$return_id)->find(); //查找退换货商品信息
    //     if (!$return_info) {
    //         DataReturn::returnJson(500,'系统出错');
    //     }
    //     $update = M('return_goods')->where('id',$return_id)->save(['status'=>3]);  //要更新状态为已完成
    //     if($update) {
    //         M('order_goods')->where([
    //             'order_id' => $return_info['order_id'],
    //             'goods_id' => $return_info['goods_id'],
    //             'spec_key' => $return_info['spec_key']])->save(['is_send' => 2]);  //订单商品改为已换货
    //         DataReturn::returnJson(200,'操作成功');
    //     }
    //     DataReturn::returnJson(500,'操作失败');
    // }
    //查询物流
    public function express()
    {
        $order_id = I('get.order_id/d', 1553);
        if (!$order_id) {
            DataReturn::returnJson(500, '系统出错');
        }
        $delivery  = M('delivery_doc')->where("order_id", $order_id)->find();
        $order     = M('order')->where("order_id", $order_id)->find();
        $logistics = queryExpress($delivery['shipping_code'], $delivery['invoice_no']);
        if ($logistics['status'] == 200) {
            foreach ($logistics['data'] as $key => $value) {
                $time = strtotime($value['time']);
                $_t   =[
                    'specificdate' => date('Y.m.d', $time),
                    'timedivision' => date('H:i', $time),
                    'context'      => $value['context'],
                ];
                $list[] = $_t;
            }
            $status      = 200;
            $message     = '查询成功';
            $region_list = get_region_list();
            $data = [
                'shipping_name' => $delivery['shipping_name'],
                'invoice_no'    => $delivery['invoice_no'],
                'list'          => $list,
                'consignee'     => $order['consignee'],
                'address'       => $region_list[$order['province']] . $region_list[$order['city']] . $region_list[$order['district']] . $order['address'],
            ];
        } else {
            $message = $logistics['message'];
            $status  = 500;
            $data    = [
                'list' => '',
            ];
        }
        DataReturn::returnJson($status, $message, $data);
    }
    //评论晒单列表
    public function comment()
    {
        $user_id  = $this->user_id;
        $status   = I('status');
        $pages    = I('pages', 1); //页码
        $pagesize = C('PAGESIZE'); //每页显示数
        if ($status == 1) {
            //已评论
            $query = M('comment')->alias('c')
                ->join('__ORDER__ o', 'o.order_id = c.order_id')
                ->join('__ORDER_GOODS__ og', 'c.goods_id = og.goods_id AND c.order_id = og.order_id AND og.is_comment=1')
                ->where('c.user_id', $user_id);
            $query2       = clone ($query);
            $comment_list = $query2->field('og.*,o.*')
                ->order('c.add_time', 'desc')
                ->page($pages, $pagesize)
                ->select();
        } else {
            $comment_where = ['og.is_send' => 1];
            if ($status == 0) {
                $comment_where['og.is_comment'] = 0;
            }
            $query = M('order_goods')->alias('og')
                ->join('__ORDER__ o', "o.order_id = og.order_id AND o.user_id=$user_id AND o.order_status IN (2,4)")
                ->where($comment_where);
            $query2       = clone ($query);
            $comment_list = $query2->field('og.*,o.*')
                ->order('o.order_id', 'desc')
                ->page($pages, $pagesize)
                ->select();
        }
        $list = [];
        foreach ($comment_list as $key => $value) {
            $_t = [
                'rec_id'     => $value['rec_id'],
                'order_id'   => $value['order_id'],
                'order_sn'   => $value['order_sn'],
                'goods_name' => $value['goods_name'],
                'add_time'   => date('Y-m-d H:i:s', $value['add_time']),
                'is_comment' => $value['is_comment'],
                'img'        => request()->domain() . goods_thum_images($value['goods_id'], 200, 200),
            ];
            $list[] = $_t;
        }
        $data['lists'] = $list;
        DataReturn::returnJson(200, '获取数据成功', $data);
    }
    //评论列表商品
    public function comment_details()
    {
        $rec_id                  = I('rec_id/d');
        $order_goods             = M('order_goods')->where("rec_id", $rec_id)->find();
        $order                   = M('order')->where("order_id", $order_goods['order_id'])->find();
        $order_goods['order_sn'] = $order['order_sn'];
        $order_goods['img']      = request()->domain() . goods_thum_images($order_goods['goods_id'], 100, 100);
        DataReturn::returnJson(200, '获取数据成功', $order_goods);
    }

    //添加评论
    public function add_comment()
    {
        $content = I('content');
        if (!$content) {
            DataReturn::returnJson(500, '评论内容不能为空！');
        }
        $user_info     = M('users')->where(['user_id' => $this->user_id])->find();
        $hide_username = I('hide_username') ?: 0;
        if (!$hide_username) {
            $username = $user_info['nickname'];
        }
        $comment_img  = I('comment_img');
        if ($comment_img) {
            $img         = explode(',', $comment_img);
            $comment_img = serialize($img);
        }
        // $comment_img = serialize([I('comment_img/a')]); // 上传的图片文件
        $add = [
            'content'      => $content,
            'is_show'      => 1, //默认显示
            'goods_rank'   => I('goods_rank'),
            'service_rank' => I('service_rank'),
            'deliver_rank' => I('deliver_rank'),
            'order_id'     => I('order_id'),
            'goods_id'     => I('goods_id'),
            'user_id'      => $this->user_id,
            'username'     => $username ?: '',
            'img'          => $comment_img ?: '',
            'email'        => $user_info['email'],
            'is_anonymous' => $hide_username, //是否匿名评价:0不是\1是
            'add_time'     => time(),
            'ip_address'   => request()->ip(),
        ];
        //添加评论
        $logic = new UsersLogic();
        $row   = $logic->add_comment($add);
        if ($row['status'] == 1) {
            DataReturn::returnJson(200, '评论成功');
        } else {
            DataReturn::returnJson(500, $row['msg']);
        }
    }

    //待收货列表
    public function wait_receive()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索
        if (I('state') == 'WAITRECEIVE') {
            $where .= C(strtoupper(I('state')));
        }
        $pages      = I('pages') ?: 1; //页码
        $pagesize   = C('PAGESIZE'); //每页显示数
        $order_str  = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->page($pages, $pagesize)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v); // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $order_data = $model->get_order_goods($v['order_id']);

            $order_list[$k]['goods_list'] = $order_data['result'];
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

            $order_list[$key]['invoice_no'] = implode(' , ', $invoice_no);
        }
        $data['lists'] = $order_list;
        DataReturn::returnJson(200, '获取数据成功', $data);
    }

    //拼团订单列表
    public function team_list()
    {
        $state = I('state');
        $Order = new \app\common\model\Order();

        $order_where = ['order_prom_type' => 6, 'user_id' => $this->user_id, 'deleted' => 0, 'pay_code' => ['<>', 'cod']]; //拼团基础查询
        switch (strval($state)) {
            case 'WAITPAY':
                //待支付订单
                $order_where['pay_status']   = 0;
                $order_where['order_status'] = 0;
                break;
            case 'WAITTEAM':
                //待成团订单
                $found_order_id  = Db::name('team_found')->where(['user_id' => $this->user_id, 'status' => 1])->getField('order_id', true); //团长待成团
                $follow_order_id = Db::name('team_follow')->where(['found_user_id' => $this->user_id, 'status' => 1])->getField('order_id', true); //团员待成团
                $team_order_id   = array_merge($found_order_id, $follow_order_id);
                if (count($team_order_id) > 0) {
                    $order_where['order_id'] = ['in', $team_order_id];
                }
                break;
            case 'WAITSEND':
                //待发货
                $order_where['pay_status']      = 1;
                $order_where['shipping_status'] = ['<>', 1];
                $order_where['order_status']    = ['in', '0,1'];
                break;
            case 'WAITRECEIVE':
                //待收货
                $order_where['shipping_status'] = 1;
                $order_where['order_status']    = 1;
                break;
            case 'WAITCCOMMENT':
                //已完成
                $order_where['order_status'] = 2;
                break;
        }
        $pages         = I('pages') ?: 1; //页码
        $pagesize      = C('PAGESIZE'); //每页显示数
        $order_list    = $Order->with('orderGoods')->where($order_where)->page($pages, $pagesize)->order('order_id desc')->select();
        $data['lists'] = $order_list;
        DataReturn::returnJson(200, '获取数据成功！', $data);
    }

    public function team_detail()
    {
        $order_id    = input('order_id');
        $Order       = new \app\common\model\Order();
        $TeamFound   = new TeamFound();
        $order_where = ['order_prom_type' => 6, 'order_id' => $order_id, 'deleted' => 0];
        $order       = $Order->with('orderGoods')->where($order_where)->find();
        if (empty($order)) {
            $this->error('该订单记录不存在或已被删除');
        }
        $orderTeamFound = $order->teamFound;
        if (!$orderTeamFound) {
            //去找团长
            $teamFound = $TeamFound::get(['found_id' => $order->teamFollow['found_id']]);
        }
        DataReturn::returnJson(200, '获取成功', $order);
    }
    //上传图片
    public function uploadimgfile()
    {
        $catalog = I('catalog') ?: 'comment';
        $model   = new CommentLogic;
        $imgfile = $model->uploadCommentImgFile('img', $catalog);
        if ($imgfile['status'] == 1) {
            $data['img']  = $imgfile['result'][0];
            $data['img2'] = request()->domain() . $imgfile['result'][0];
            DataReturn::returnJson(200, '上传成功', $data);
        } else {
            DataReturn::returnJson(500, $imgfile['msg']);
        }

    }

}
