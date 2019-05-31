<?php

namespace app\mobile\controller;

use app\common\exception\NoticeException;
use app\common\logic\MakebiLogic;
use app\common\logic\UserReward;
use app\common\model\CommonModel;
use app\common\model\Plugin;
use app\common\util\WechatUtil;
use app\common\logic\CartLogic;
use app\common\logic\DistributLogic;
use app\common\logic\MessageLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\logic\CouponLogic;
use app\common\model\Order;
use app\common\model\UserLevel;
use app\common\model\Users;
use app\common\model\UsersReward;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Page;
use think\Request;
use think\Response;
use think\response\Json;
use think\Verify;
use think\db;


class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();

        if (session('?user')) {
            $user = session('user');

            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];

            //初始化账户信息
            // DistributLogic::rebateDivide($this->user_id);   //初始获取分佣情况

            $this->assign('user', $user); //存储用户信息
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express', 'bind_guide', 'bind_account',
        );
        $is_bind_account = tpCache('basic.is_bind_account');

        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            if ($this->isWXBrowser() && $is_bind_account) {
                // 调到绑定账号引导页面
                $this->redirect(U('Mobile/User/bind_guide'));
            } else {
                // 获取用户失败
                // 清除会话再试
                session(null);
                $this->redirect($this->request->url());
            }
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
     * 用户中心首页
     */
    public function index()
    {
        $logic = new UsersLogic();

        $user = $logic->get_info($this->user_id); //当前登录用户信息

        $user = $user['result'];
        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();

        // 用户等级
        try {
            $user['level'] and $userLevel = UserLevel::get($user['level']);
        } catch (DbException $e) {
        }
        if ($userLevel) {
            $user['user_level'] = $userLevel;
            switch ($userLevel['level_id']) {

                case 2:
                    $userLevel['icon'] = '/images/icon_huangguan.png';
                    break;

                case 3:
                    $userLevel['icon'] = '/images/icon_huangguan.png';
                    break;

                default:
                    $userLevel['icon'] = '';
            }
        }

        try {
            $user['first_leader'] and $first_leader = Users::get($user['first_leader']);
        } catch (DbException $e) {
        }

        $first_leader or $first_leader = [
            'nickname' => '厂家',
        ];

        $where = [
            'user_id' => $this->user['user_id'],
            'order_prom_type' => ['lt', 5],
        ];

        //待付款
        $wait_pay = db('order')->where($where)->where(['pay_status' => 0, 'order_status' => 0, 'pay_code' => ['neq', 'cod']])->count();

        //待发货
        $wait_delivery = db('order')->where($where)->where(['shipping_status' => ['neq', 1], 'order_status' => ['in', [0, 1]], 'pay_status' => 1])->count();

        //待收货
        $wait_receive = db('order')->where($where)->where(['shipping_status' => 1, 'order_status' => 1])->count();

        //待评价
        $wait_comment = db('order')->where($where)->where(['order_status' => 2])->count();

        //退换货
        $returns = db('order')->where($where)->where(['order_status' => ['in', [3, 5]]])->count();

        if ($user['user_type'] == '0') {
            $user['user_type_name'] = '粉丝';
        } elseif ($user['user_type'] == '1') {
            $user['user_type_name'] = '会员';
        } elseif ($user['user_type'] == '2') {
            $user['user_type_name'] = '总代';
        } elseif ($user['user_type'] == '3') {
            $user['user_type_name'] = '大区';
        }

        if ($user['user_type'] != '0') {

            $applyInfo = M('n_apply_identity')->where('obj_user_id', $this->user_id)->find();

            //  获取省份
            $province = M('region')->where('id', $applyInfo['agent_province_id'])->find();

            //  获取市
            $city = M('region')->where('id', $applyInfo['agent_city_id'])->find();

            //  获取区
            $area = M('region')->where('id', $applyInfo['agent_area_id'])->find();

            $this->assign('province', $province['name']);
            $this->assign('city', $city['name']);
            $this->assign('area', $area['name']);
        }

        if ($user['user_type'] == '1' || $user['user_type'] == '2') {
            $n_user_management = db('n_user_management')
                ->alias('a')
                ->join('users b', 'a.management_id = b.user_id')
                ->where('a.user_id', $this->user_id)
                ->field('b.nickname')
                ->find();

        }


        $this->assign('user_management_name', $n_user_management['nickname']);
        $this->assign('wait_pay', $wait_pay);
        $this->assign('wait_delivery', $wait_delivery);
        $this->assign('wait_receive', $wait_receive);
        $this->assign('wait_comment', $wait_comment);
        $this->assign('returns', $returns);
        $this->assign('first_leader', $first_leader);
        $this->assign('user_message_count', $user_message_count);
        $this->assign('user', $user);
        $this->assign('title', '个人中心');
        return $this->fetch();
    }


    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('uname', '', time() - 3600, '/');
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('PHPSESSID', '', time() - 3600, '/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    public function account_list()
    {
        $type = I('type', 'all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->account($this->user_id, $type);

        $this->assign('type', $type);
        $this->assign('account_log', $result['account_log']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
        }
        return $this->fetch();
    }

    public function account_detail()
    {
        $log_id = I('log_id/d', 0);
        $detail = Db::name('account_log')->where(['log_id' => $log_id])->find();
        $this->assign('detail', $detail);
        return $this->fetch();
    }

    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach ($data['result'] as $k => $v) {
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if ($user_type == 1) { //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id' => $v['cid']])->getField('goods_id');
            }
            if ($user_type == 2) { //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id' => $v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
//        if ($this->user_id > 0) {
////
////            header("Location: " . U('Mobile/User/index'));
//        }
//        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
//        $this->assign('referurl', $referurl);
        return $this->fetch();
    }


    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        //验证码验证
        if (isset($_POST['verify_code'])) {
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

//        if ($this->user_id > 0) {
//            $this->redirect(U('Mobile/User/index'));
//        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname = I('post.nickname', '');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $user_id = I('user_id');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();

            //是否开启注册验证码机制
//            if (check_mobile($username)) {
//                if ($reg_sms_enable) {
//                    //手机功能没关闭
//                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
//                    if ($check_code['status'] != 1) {
//                        $this->ajaxReturn($check_code);
//                    }
//                }
//            }
            //是否开启注册邮箱验证码机制
            if (check_email($username)) {
                if ($reg_smtp_enable) {
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if ($check_code['status'] != 1) {
                        $this->ajaxReturn($check_code);
                    }
                }
            }

            $invite = I('invite');
            if (!empty($invite)) {
                $invite = get_user_info($invite, 2);//根据手机号查找邀请人
            } else {
                $invite = array();
            }
            //存储管理关系
            if (!empty($user_id)) {
                $user2 = M("users")->where('user_id', $user_id)->find();
                if (!empty($user2['pid'])) {
                    $user_id2 = $user2['pid'];
                }
                //添加管理关系
                $getUser1 = M("users")->where('user_id', $user_id)->find();
                //如果拥有管理权限，写入，没有找他们的父类
                if ($getUser1['user_type'] == 2 || $getUser1['user_type'] == 3) {
                    $logic->management($user_id);
                } elseif (!empty($getUser1['pid'])) {
                    $logic->management($getUser1['pid']);
                }
            }

            if ($is_bind_account && session("third_oauth")) { //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite, $user_id, $user_id2, $nickname, $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            } else {
                $data = $logic->reg($username, $password, $password2, 0, $invite, $user_id, $user_id2);
            }


            if ($data['status'] != 1) $this->ajaxReturn($data);

            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id' => $data['result']['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable', $reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable', $reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        $this->assign('user_id', I('user_id'));//推荐人
        return $this->fetch();
    }

    public function bind_guide()
    {
        $data = session('third_oauth');
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);

        return $this->fetch();
    }

    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        if (IS_POST) {
            $data = I('post.');
            $userLogic = new UsersLogic();
            $user['mobile'] = $data['mobile'];
            $user['password'] = encrypt($data['password']);
            $res = $userLogic->oauth_bind_new($user);
            if ($res['status'] == 1) {
                //绑定成功, 重新关联上下级
                $map['first_leader'] = cookie('first_leader');  //推荐人id
                // 如果找到他老爸还要找他爷爷他祖父等
                if ($map['first_leader']) {
                    $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
                    if ($first_leader) {
                        $map['second_leader'] = $first_leader['first_leader'];
                        $map['third_leader'] = $first_leader['second_leader'];
                    }
                    //他上线分销的下线人数要加1
                    M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
                } else {
                    $map['first_leader'] = 0;
                }
                $ruser = $res['result'];
                M('Users')->where('user_id', $ruser['user_id'])->save($map);

                $res['url'] = urldecode(I('post.referurl'));
                $res['result']['nickname'] = empty($res['result']['nickname']) ? $res['result']['mobile'] : $res['result']['nickname'];
                setcookie('user_id', $res['result']['user_id'], null, '/');
                setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
                setcookie('uname', urlencode($res['result']['nickname']), null, '/');
                setcookie('head_pic', urlencode($res['result']['head_pic']), null, '/');
                setcookie('cn', 0, time() - 3600, '/');
                //获取公众号openid,并保持到session的user中
                $oauth_users = M('OauthUsers')->where(['user_id' => $res['result']['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
                $oauth_users && $res['result']['open_id'] = $oauth_users['open_id'];
                session('user', $res['result']);
                $cartLogic = new CartLogic();
                $cartLogic->setUserId($res['result']['user_id']);
                $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
                $userlogic = new OrderLogic();//登录后将超时未支付订单给取消掉
                $userlogic->setUserId($res['result']['user_id']);
                $userlogic->abolishOrder();
                return $this->success("绑定成功", U('Mobile/User/index'));
            } else {
                return $this->error("绑定失败,失败原因:" . $res['msg']);
            }
        } else {
            return $this->fetch();
        }
    }

    public function express()
    {
        $order_id = I('get.order_id/d', 195);
        $order_goods = M('order_goods')->where("order_id", $order_id)->select();
        $delivery = M('delivery_doc')->where("order_id", $order_id)->find();

        //返回订单信息
        $order = M('order')->where('order_id', $order_id)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('delivery', $delivery);
        $this->assign('order', $order);
        return $this->fetch();
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        if (IS_POST) {
            $source = input('source');
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, $post_data);
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $order_id = input('order_id/d');
            $action = input('action');

            if ($data['status'] != 1) {
                $this->error($data['msg']);
            } elseif ($source == 'cart2') {
                $data['url'] = U('/Mobile/Cart/cart2', array('address_id' => $data['result'],
                    'action' => $action,
                    'goods_id' => $goods_id,
                    'goods_num' => $goods_num,
                    'rate_type' => input('rate_type'),
                    'have_free_hkorder' => input('have_free_hkorder'),
                    'is_use_free_order' => input('is_use_free_order'),
                    'item_id' => $item_id));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {
                $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'], 'goods_id' => $goods_id, 'goods_num' => $goods_num, 'item_id' => $item_id));
                $this->ajaxReturn($data);
            } elseif ($source == 'pre_sell_cart') {
                $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'], 'act_id' => $post_data['act_id'], 'goods_num' => $post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'team') {
                $data['url'] = U('/Mobile/Team/order', array('address_id' => $data['result'], 'order_id' => $order_id));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'exchange') {
                $data['url'] = U('/Mobile/Exchange/order', array('address_id' => $data['result']));
                $this->ajaxReturn($data);
            } else {
                $data['url'] = U('/Mobile/User/address_list');
                $this->success($data['msg'], U('/Mobile/User/address_list'));
            }

        }

        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        //return $this->fetch('edit_add ress');
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $source = input('source');
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $action = input('action');
            $order_id = input('order_id/d');
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            if ($post_data['source'] == 'cart2') {
                $data['url'] = U('/Mobile/Cart/cart2', array('address_id' => $data['result'], 'goods_id' => $goods_id, 'goods_num' => $goods_num, 'item_id' => $item_id, 'action' => $action));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {

                $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'], 'goods_id' => $goods_id, 'goods_num' => $goods_num, 'item_id' => $item_id));
                $this->ajaxReturn($data);
            } elseif ($source == 'pre_sell_cart') {
                $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'], 'act_id' => $post_data['act_id'], 'goods_num' => $post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'team') {
                $data['url'] = U('/Mobile/Team/order', array('address_id' => $data['result'], 'order_id' => $order_id));
                $this->ajaxReturn($data);
            } else {
                $data['url'] = U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }

    public function set_default1()
    {
        $id = I('get.id/d');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if (!$row)
            $this->error('设置默认地址失败', U('User/address_list'));
        else
            $this->success("设置默认地址成功", U('User/address_list'));
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];
                $dir = 'public/upload/head_pic/';
                if (!($_exists = file_exists($dir))) {
                    $isMk = mkdir($dir);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if ($info) {
                    $post['head_pic'] = '/' . $dir . $parentDir . '/' . $info->getFilename();
                } else {
                    $this->error($file->getError());//上传错误提示错误信息
                }
            }
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机

            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            setcookie('uname', urlencode($post['nickname']), null, '/');
            $this->success("操作成功");
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
//        dump($user_info);die;
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile()
    {
        $input = input();
        //查询当前用户的手机号
        $phone = Db::name('users')->field('mobile')->where(['user_id' => $this->user_id])->value('mobile');

        if (isset($input['status'])) {
            if ($input['status'] == 1) {
                $mobile = $input['mobile'];
                $mobile_code = $input['code'];
                if ($mobile == $phone) {
                    return array('status' => 500, 'msg' => '当前用户手机号跟绑定的同一个手机号', 'result' => '');
                }


                $c = Db::name('users')->where(['mobile' => mobile, 'user_id' => ['<>', $this->user_id]])->count();
                if ($c) {
                    return array('status' => 500, 'msg' => '当前手机号已有用户在使用', 'result' => '');
                }
                if (!$mobile_code) {
                    return array('status' => 500, 'msg' => '请输入验证码', 'result' => '');
                }

                $mobile_captcha = db('n_mobile_captcha')->where('mobile', $input['mobile'])->order('id desc')->find();
                if ($mobile_captcha['expire_in'] < time()) {
                    return array('status' => 500, 'msg' => '验证码已过期', 'result' => '');
                }

                if ($mobile_captcha['captcha'] != $mobile_code) {
                    return array('status' => 500, 'msg' => '验证码不正确', 'result' => '');
                }

                if ($mobile_captcha['mobile'] != $input['mobile']) {
                    return array('status' => 500, 'msg' => '手机号码有误', 'result' => '');
                }


                $res = Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile' => $mobile]);
                if ($res) {
                    return array('status' => 200, 'msg' => '登录成功', 'result' => '');
                }

            }
        }

        $this->assign('mobile', $phone);
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function zpcollect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        // dump($data);die;
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('post.goods_id');
        $user_id = $this->user_id;
        $res = M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete();
        // $res = '1';
        if ($res) {
            $data[] = $data;
            $return = [];
            $return['status'] = 'success';
            $return['message'] = '取消收藏成功';
            $return['data'] = $data;
            echo json_encode($return);
            exit;
        } else {
            $data[] = $data;
            $return = [];
            $return['status'] = 'error';
            $return['error'] = '取消收藏失败';
            $return['data'] = [];
            echo json_encode($return);
            exit;
        }
    }

    /*
     *清空收藏
     */
    public function cart_empty()
    {
        $user_id = $this->user_id;
        $res = M('goods_collect')->where(['user_id' => $user_id])->delete();
        // $res = '';
        if ($res) {
            $data[] = $data;
            $return = [];
            $return['status'] = 'success';
            $return['message'] = '清空收藏成功';
            $return['data'] = $data;
            echo json_encode($return);
            exit;
        } else {
            $data[] = $data;
            $return = [];
            $return['status'] = 'error';
            $return['error'] = '清空收藏失败';
            $return['data'] = [];
            echo json_encode($return);
            exit;
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if (!$this->verifyHandle('message')) {
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }


    public function points_list()
    {
        $type = I('type', 'all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->points($this->user_id, $type);

        $this->assign('type', $type);
        $showpage = $result['page']->show();
        $this->assign('account_log', $result['account_log']);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
        }
        return $this->fetch();
    }


    /*
     * 密码修改
     */
    public function password()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '')
                $this->ajaxReturn(['status' => -1, 'msg' => '请先绑定手机或邮箱', 'url' => U('/Mobile/User/index')]);
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1)
                $this->ajaxReturn(['status' => -1, 'msg' => $data['msg']]);
            $this->ajaxReturn(['status' => 1, 'msg' => $data['msg'], 'url' => U('/Mobile/User/index')]);
            exit;
        }
        return $this->fetch();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if (!$this->verifyHandle('forget')) {
                    $this->error("验证码错误");
                };
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field));
                    header("Location: " . U('User/find_pwd'));
                    exit;
                } else {
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        if (empty($check)) {
            header("Location:" . U('User/forget_pwd'));
        } elseif ($check['is_check'] == 0) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            if ($check['is_check'] == 1) {
                $user = M('users')->where("mobile", $check['sender'])->whereOr('email', $check['sender'])->find();
                M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($password)));
                session('validate_code', null);
                return $this->fetch('reset_pwd_sucess');
                exit;
            } else {
                $this->error('验证码还未验证通过', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' => 60,
            'imageW' => 300,
            'fontttf' => '5.ttf',
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id = I('order_id/d');

        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            // 微信浏览器
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        } else {
            $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and scene in(0,1)")->where('code', 'neq', Plugin::PAYMENT_CODE_MONEY_PAY)->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        // $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    public function recharge_list()
    {
        $usersLogic = new UsersLogic;
        $result = $usersLogic->get_recharge_log($this->user_id);  //充值记录
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_recharge_list');
        }
        return $this->fetch();
    }

    /**
     * 提现配置
     */
    public function user_wd()
    {

        if (IS_POST) {

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $user_wd = db('user_wd')->where(['type' => $data['type'], 'user_id' => $this->user_id])->find();
            if (!$user_wd) {
                $res = M('user_wd')->add($data);
            } else {
                $res = db('user_wd')->where(['type' => $data['type'], 'user_id' => $this->user_id])->update($data);
            }
            if ($res) {
                $this->ajaxReturn(['status' => 1, 'msg' => "已提交"]);
                exit;
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '提交失败!']);
                exit;
            }
        }
        $bank = db('user_wd')->where(['user_id' => $this->user_id, 'type' => 1])->find();
        $this->assign('bank', $bank);
        $zfb = db('user_wd')->where(['user_id' => $this->user_id, 'type' => 2])->find();
        $this->assign('zfb', $zfb);
        $wx = db('user_wd')->where(['user_id' => $this->user_id, 'type' => 3])->find();
        $this->assign('wx', $wx);
        return $this->fetch();
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {

        C('TOKEN_ON', true);
        if (IS_POST) {
            if (!$this->verifyHandle('withdrawals')) {
                $this->ajaxReturn(['status' => 0, 'msg' => '验证码错误']);
            };
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min = tpCache('basic.min'); // 最少提现额度
//            if(encrypt($data['paypwd']) != $this->user['paypwd']){
//                $this->error("支付密码错误");
//            }
            if ($data['money'] < $distribut_min) {
                $this->ajaxReturn(['status' => 0, 'msg' => '每次最少提现额度' . $distribut_min]);
                exit;
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status' => 0, 'msg' => "你最多可提现{$this->user['user_money']}账户余额."]);
                exit;
            }
            $withdrawal = M('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('money');
            if ($this->user['user_money'] < ($withdrawal + $data['money'])) {
                $this->ajaxReturn(['status' => 0, 'msg' => '您有提现申请待处理，本次提现余额不足']);
            }
            if (M('withdrawals')->add($data)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "已提交申请", 'url' => U('User/withdrawals_list')]);
                exit;
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '提交失败,联系客服!']);
                exit;
            }
        }
        $bank = db('user_wd')->where(['user_id' => $this->user_id, 'type' => 1])->find();
        $this->assign('bank', $bank);
        $zfb = db('user_wd')->where(['user_id' => $this->user_id, 'type' => 2])->find();
        $this->assign('zfb', $zfb);
        $wx = db('user_wd')->where(['user_id' => $this->user_id, 'type' => 3])->find();
        $this->assign('wx', $wx);
        $this->assign('user_money', $this->user['user_money']);    //用户余额
        return $this->fetch();
    }


    //提现记录
    public function cashHistory()
    {
        $result = M('n_yongjin_month')
            ->where('user_id', $this->user_id)
            ->paginate(5);
        $this->assign('result', $result);
        return $this->fetch();
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }

    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        return $this->fetch();
    }

    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type');
        $user_logic = new UsersLogic();
        $message_model = new MessageLogic();
        if ($type === '0') {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
        } else if ($type == 1) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $this->assign('messages', $user_sys_message);
        return $this->fetch('ajax_message_notice');

    }

    /**
     * ajax用户消息通知请求
     */
    public function set_message_notice()
    {
        $type = I('type');
        $msg_id = I('msg_id');
        $user_logic = new UsersLogic();
        $res = $user_logic->setMessageForRead($type, $msg_id);
        $this->ajaxReturn($res);
    }


    /**
     * 设置消息通知
     */
    public function set_notice()
    {
        //暂无数据
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id', 'IN', $visit_ids)->delete();

        if (!$row) {
            $this->error('操作失败', U('User/visit_log'));
        } else {
            $this->success("操作成功", U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if (!$row) {
            $this->error('操作失败', U('User/visit_log'));
        } else {
            $this->success("操作成功", U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if (strrchr($_SERVER['HTTP_REFERER'], '/') == '/cart2.html') {  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl', U('Mobile/Cart/cart2'));
        }
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号', U('User/userinfo', ['action' => 'mobile']));
        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if (!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))) {
                $this->ajaxReturn(['status' => -1, 'msg' => '原密码验证错误！', 'result' => '']);
            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     *  点赞
     * @author lxl
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(array('comment_id' => $comment_id))->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $result['success'] = 0;
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(array('comment_id' => $comment_id))->save($comment_data);
            $result['success'] = 1;
        }
        exit(json_encode($result));
    }


    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign()
    {
        $user_id = $this->user_id;
        $config = tpCache('sign');
        if (IS_AJAX) {
            $date = I('str'); //20170929
            //是否正确请求
            (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => -1, 'msg' => '请求错误！', 'result' => date("Y-n-j", time())]);

            $integral = $config['sign_integral'];
            $msg = "签到赠送" . $integral . "积分";
            //签到开关
            if ($config['sign_on_off'] > 0) {
                $map['lastsign'] = $date;
                $map['user_id'] = $user_id;
                $check = DB::name('user_sign')->where($map)->find();
                $check && $this->ajaxReturn(['status' => -1, 'msg' => '您今天已经签过啦！', 'result' => '']);
                if (!DB::name('user_sign')->where(['user_id' => $user_id])->find()) {
                    //第一次签到
                    $data = [];
                    $data['user_id'] = $user_id;
                    $data['signtotal'] = 1;
                    $data['lastsign'] = $date;
                    $data['cumtrapz'] = $config['sign_integral'];
                    $data['signtime'] = "$date";
                    $data['signcount'] = 1;
                    $data['thismonth'] = $config['sign_integral'];
                    if (M('user_sign')->add($data)) {
                        $status = ['status' => 1, 'msg' => '签到成功！', 'result' => $config['sign_integral']];
                    } else {
                        $status = ['status' => -1, 'msg' => '签到失败!', 'result' => ''];
                    }
                    $this->ajaxReturn($status);
                } else {
                    $update_data = array(
                        'signtotal' => ['exp', 'signtotal+' . 1], //累计签到天数
                        'lastsign' => ['exp', "'$date'"], //最后签到时间
                        'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_integral']], //累计签到获取积分
                        'signtime' => ['exp', "CONCAT_WS(',',signtime ,'$date')"], //历史签到记录
                        'signcount' => ['exp', 'signcount+' . 1], //连续签到天数
                        'thismonth' => ['exp', 'thismonth+' . $config['sign_integral']], //本月累计积分
                    );

                    $daya = Db::name('user_sign')->where('user_id', $user_id)->value('lastsign');    //上次签到时间
                    $dayb = date("Y-n-j", strtotime($date) - 86400);                                   //今天签到时间
                    //不是连续签
                    if ($daya != $dayb) {
                        $update_data['signcount'] = ['exp', 1];                                       //连续签到天数
                    }
                    $mb = date("m", strtotime($date));                                               //获取本次签到月份
                    //不是本月签到
                    if (intval($mb) != intval(date("m", strtotime($daya)))) {
                        $update_data['signcount'] = ['exp', 1];                                      //连续签到天数
                        $update_data['signtime'] = ['exp', "'$date'"];                                  //历史签到记录;
                        $update_data['thismonth'] = ['exp', $config['sign_integral']];              //本月累计积分
                    }

                    $update = Db::name('user_sign')->where(['user_id' => $user_id])->update($update_data);

                    (!$update) && $this->ajaxReturn(['status' => -1, 'msg' => '网络异常！', 'result' => '']);

                    $signcount = Db::name('user_sign')->where('user_id', $user_id)->value('signcount');
                    $integral = $config['sign_integral'];
                    //满足额外奖励
                    if (($signcount >= $config['sign_signcount']) && ($config['sign_on_off'] > 0)) {
                        Db::name('user_sign')->where(['user_id' => $user_id])->update([
                            'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_award']],
                            'thismonth' => ['exp', 'thismonth+' . $config['sign_award']]
                        ]);
                        $integral = $config['sign_integral'] + $config['sign_award'];
                        $msg = "签到赠送" . $config['sign_integral'] . "积分，连续签到奖励" . $config['sign_award'] . "积分，共" . $integral . "积分";
                    }
                }
                if ($config['sign_integral'] > 0 && $config['sign_on_off'] > 0) {
                    accountLog($user_id, 0, $integral, $msg);
                    $status = ['status' => 1, 'msg' => '签到成功！', 'result' => $integral];
                } else {
                    $status = ['status' => -1, 'msg' => '签到失败!', 'result' => ''];
                }
                $this->ajaxReturn($status);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => '该功能未开启！', 'result' => '']);
            }
        }
        $map = [];
        $map['us.user_id'] = $user_id;
        $field = [
            'u.user_id as user_id',
            'u.nickname',
            'u.mobile',
            'us.*',
        ];
        $join = [
            ['users u', 'u.user_id=us.user_id', 'left']
        ];
        $info = Db::name('user_sign')->alias('us')->field($field)
            ->join($join)->where($map)->find();

        ($info['lastsign'] != date("Y-n-j", time())) && $tab = "1";

        $signtime = explode(",", $info['signtime']);
        $str = "";
        //是否标识历史签到
        if (date("m", strtotime($info['lastsign'])) == date("m", time())) {
            foreach ($signtime as $val) {
                $str .= date("j", strtotime($val)) . ',';
            }
            $this->assign('info', $info);
            $this->assign('str', $str);
        }

        $this->assign('cumtrapz', $info['cumtrapz']);
        $this->assign("jifen", ($config['sign_signcount'] * $config['sign_integral']) + $config['sign_award']);
        $this->assign('config', $config);
        $this->assign('tab', $tab);

        return $this->fetch();
    }

    public function accountSafe()
    {
        return $this->fetch();
    }

    /**
     * 用户分享列表
     */
    public function zpshare_list()
    {
        $user_id = $this->user_id;
        // dump($user_id);die;
        $user = M('share')->where(['user_id' => $user_id])->select();
        // dump($user);die;
        $_user = array();
        foreach ($user as $key => $val) {

            $_u = $val;
            $_u['share_t'] = $val['share_t'] != 0 ? date('Y-m', $val['share_t']) : '0000-00-00';
            $_user[] = $_u;
        }

        // $_month_list_c = [];
        // foreach ($_user as $key => $val) {
        //     $_t = $val;
        //     $_month_list_c[$val['share_t']][] = $_t;
        // }

        // $_conut_month_c = [];
        //  $count = 0;
        // foreach ($_month_list_c as $key => $val) {
        //     $_m['count'] = totalpost_money($val);
        //     $_m['list'] = $val;
        //     $_conut_month_c[$key] = $_m;
        // }
        // print_r($_conut_month_c);

        $this->assign('user', $_user);
        return $this->fetch();
    }

    /*
     *清空分享
     */
    public function share_empty()
    {
        $user_id = $this->user_id;
        $res = M('share')->where(['user_id' => $user_id])->delete();
        if ($res) {
            $data[] = $data;
            $return = [];
            $return['status'] = 'success';
            $return['message'] = '清空分享成功';
            $return['data'] = $data;
            echo json_encode($return);
            exit;
        } else {
            $data[] = $data;
            $return = [];
            $return['status'] = 'error';
            $return['error'] = '清空分享失败';
            $return['data'] = [];
            echo json_encode($return);
            exit;
        }
    }

    /**
     * 用户评价列表
     */
    public function zpevaluate_list()
    {
        $user_id = $this->user_id;
        $user = M('comment')->where(['user_id' => $user_id])->select();
        $_user = array();
        foreach ($user as $key => $val) {

            $_u = $val;
            $_u['head_pic'] = M('users')->where(['user_id' => $val['user_id']])->value('head_pic');
            $_u['nickname'] = M('users')->where(['user_id' => $val['user_id']])->value('nickname');
            $rank = ($val['deliver_rank'] + $val['goods_rank'] + $val['service_rank']) / 3;
            $_u['rank'] = round($rank, 0);
            $_u['img'] = unserialize($val['img']); // 晒单图片
            $_u['add_time'] = $val['add_time'] != 0 ? date('Y-m-d H:i:s', $val['add_time']) : '0000-00-00 00:00:00';
            $_u['star_images'] = star($_u['rank']);
            $_u['original_img'] = M('goods')->where(['goods_id' => $val['goods_id']])->value('original_img');
            $_u['goods_name'] = M('goods')->where(['goods_id' => $val['goods_id']])->value('goods_name');
            $_u['shop_price'] = M('goods')->where(['goods_id' => $val['goods_id']])->value('shop_price');
            $_u['spec_key_name'] = M('order_goods')->where(['goods_id' => $val['goods_id']])->where(['order_id' => $val['order_id']])->value('spec_key_name');
            $_user[] = $_u;

        }

        $this->assign('user', $_user);
        return $this->fetch();
    }

    /**
     * 用户分销列表
     */
    public function zpdistribution_list()
    {
        try {
            $this->assign('title', '我的粉丝');

            $fansId = (int)$this->request->param('u_id');

            if ($fansId) {
                $fans = Users::get([
                    'user_id' => $fansId,
                    'first_leader' => $this->user['user_id'],
                ]);

                if (!$fans) {
                    throw new NoticeException('用户不存在');
                }

                $son['subordinate'] = $fans;
                $son['reg_time'] = '注册时间' . ' ' . ($son['subordinate']['reg_time'] != 0 ? date('Y.m.d', $son['subordinate']['reg_time']) : '0000.00.00');
                $son['first'] = M('users')->where(['first_leader' => $fans['user_id']])->count('first_leader');// 子用户第一层
                $son['second'] = M('users')->where(['second_leader' => $fans['user_id']])->count('second_leader');// 子用户第二层
                $son['third'] = M('users')->where(['third_leader' => $fans['user_id']])->count('third_leader');// 子用户第三层
                $son['count'] = $son['first'] + $son['second'] + $son['third'];// 子用户第三层

                // 统计粉丝的粉丝
                $aggregateList = CommonModel::aggregate(function (db\Query $query) use ($fans) {
                    $query
                        ->name('users')
                        ->where('first_leader', $fans['user_id'])
                        ->field(['count(1)' => 'count']);
                });
                $son['fans_count'] = $aggregateList[0]['count'];

                // $layer = M('users')->where(['user_id' => $userId])->field('first_leader,second_leader,third_leader')->find();
                //
                // // $several_layers = array_search($user_id, $layer);
                // if ($layer['first_leader'] == $this->user['user_id']) {
                //     $son['layer'] = '第一层';
                // } else if ($layer['second_leader'] == $this->user['user_id']) {
                //     $son['layer'] = '第二层';
                // } else if ($layer['third_leader'] == $this->user['user_id']) {
                //     $son['layer'] = '第三层';
                // } else {
                //     $son['layer'] = '啊哦~出错了';
                // }

                return new Json($son);

            } else {
                $user = Users::get($this->user['user_id']);
                $userLevelList = UserLevel::all(function (db\Query $query) {
                    $query->order('sort desc');
                }, [], true);

                // 统计
                $aggregateList = CommonModel::aggregate(function (db\Query $query) use ($user) {
                    $query
                        ->name('users')->alias('users')
                        ->where([
                            'users.first_leader' => $user['user_id'],
                        ])
                        ->group('users.level')
                        ->field([
                            'users.level',
                            'count(users.user_id)' => 'count',
                        ]);
                });

                /** @var int $totalCount 团队用户总数 */
                $totalCount = 0;

                /** @var array[] $dataList 左侧栏 */
                $dataList = [];

                foreach ($userLevelList as $i => $userLevelItem) {
                    // 获取计数
                    $count = 0;
                    foreach ($aggregateList as $item) {

                        if ($item['level'] == $userLevelItem['level_id']) {
                            $count = $item['count'];
                            break;
                        }
                    }
                    $totalCount += $count;

                    $dataList[] = [
                        'level_id' => $userLevelItem['level_id'],
                        'level_name' => $userLevelItem['level_name'],
                        'count' => $count,
                    ];
                }

                $this->assign('user', $user->toArray());
                $this->assign('totalCount', $totalCount);
                $this->assign('typeList', $dataList);

                return $this->fetch(__FUNCTION__);
            }

        } catch (NoticeException $e) {
            $this->error($e->getMessage());

            return null;

        } catch (Exception $e) {
            Log::error((string)$e);
            $this->error('操作失败');

            return null;
        }
    }


    //所有粉丝信息
    public function ajax_count_leader()
    {
        try {
            $levelId = (int)$this->request->param('type');
            $keyword = $this->request->param('account');

            $user = Users::get($this->user['user_id']);

            $where = [
                'first_leader' => $user['user_id'],
            ];

            if ($keyword) {
                $callback = function (db\Query $query) use ($keyword) {

                    $query->where(function (db\Query $query) use ($keyword) {
                        $query->whereOr('user_id', $keyword);
                        $query->whereOr('nickname', ['like', "%${keyword}%"]);
                    });
                };
            }

            switch ($levelId) {

                case UserLevel::LEVEL_SENIOR_MEMBER:
                    $where['level'] = UserLevel::LEVEL_SENIOR_MEMBER;
                    break;

                case UserLevel::LEVEL_MEMBER:
                    $where['level'] = UserLevel::LEVEL_MEMBER;
                    break;

                case UserLevel::LEVEL_CONSUMER:
                    $where['level'] = UserLevel::LEVEL_CONSUMER;
                    break;

                default:
                    break;
            }

            $list = Users::all(function (db\Query $query) use ($where, $callback) {
                $query->where($where);

                if ($callback instanceof \Closure) {
                    call_user_func($callback, $query);
                }
            });

            foreach ($list as $item) {
                $item['memberorder'] = $item->isPurchased();
            }

            $this->assign('list', $list);

            $this->getResponseType();

            return $this->fetch(__FUNCTION__);

        } catch (Exception $e) {
            Log::error((string)$e);
            $this->error('操作失败');

            return null;
        }
    }

    /*我的二维码*/
    public function myqrcode()
    {

        $user_id = $this->user_id;
        $this->wx_user = M('wx_user')->find();
        $this->wechatObj = new WechatUtil($this->wx_user);
        $WechatUtil = $this->wechatObj;

        $expire = 604800;
        $scene_id = $user_id;
        $ticketInfo = $WechatUtil->createTempQrcode($expire, $scene_id);
        //dump($ticketInfo);die;

        $res = $this->ticketqrcode($ticketInfo);

        if ($res['errorCode']) {
            //保存到数据库
            Db::name('users')->where(['user_id' => $user_id])->update(['param_code' => $res['url']]);
            $qrcodeUrl = SITE_URL . $res['url'];
        }


        // vendor('phpqrcode.phpqrcode');


        // //获取个人
        // //$url = request()->domain().U('contactleader',['id'=>$this->user_id]);
        // $qrcodeUrl=request()->domain().U('mobile@', ['first_leader' => $user_id]);

        // $after_path = 'public/qrcode/'.md5($qrcodeUrl).'.png';
        // //保存路径
        // $path =  ROOT_PATH.$after_path;

        // //判断是该文件是否存在
        // if(!is_file($path))
        // {
        //     //实例化
        //     $qr = new \QRcode();
        //     //1:url,3: 容错级别：L、M、Q、H,4:点的大小：1到10
        //     $qr::png($qrcodeUrl,'./'.$after_path, "M", 6,TRUE);
        // }

        // return request()->domain().'/'.$after_path;
        return $qrcodeUrl;

    }


    /*
     *
     *关联上下级
     *获取上级id
     *$this->user_id 获取用户id            
     */
    // public function contactleader()
    // {

    //     $parent_id = I('id/d');
    //     // M('users')->where('user',$parent_id)->find()
    //     $parent_info = Users::get($parent_id);

    //     if(empty($parent_info))
    //     {
    //         $this->error('所绑定上级用户的信息有误!',U('index/index'));
    //     }

    //     //是否已经绑定了上下级关系
    //     $user_info = Users::get($this->user_id);

    //     if($user_info['first_leader'])
    //     {
    //         $this->error('您已经存在上级，不可以继续绑定',U('index/index'));
    //     }

    //     if(IS_POST)
    //     {
    //         $user_logic = new UsersLogic();
    //         return $user_logic->bindLeader($this->user_id,$parent_id);
    //     }

    //     $this->assign('head_pic',$user_info['head_pic']);
    //     $this->assign('user',$parent_info);
    //     return $this->fetch();

    // }


    /**
     * 下载二维码
     * @param $url
     * @return array
     */
    public function downloadImageFromWeiXin($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        return array_merge(['body' => $package, ['header' => $httpinfo]]);
    }


    /**
     * ticket换取二维码
     * @param $url
     * @return array
     */
    public function ticketqrcode($ticketInfo)
    {
        if (!array_key_exists('errcode', $ticketInfo)) {
            $url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . urlencode($ticketInfo['ticket']);
            $imageInfo = $this->downloadImageFromWeiXin($url);

            $filename = date('Ymdmis') . mt_rand(10000, 99999) . '.jpg';//文件名

            $allPath = ROOT_PATH . "public/" . '/qr/paramcode/' . date('Y-m-d', time()) . '/';

            if (!file_exists($allPath)) {
                mkdir($allPath, 0777);
            }
            $res = file_put_contents($allPath . $filename, $imageInfo['body']);
            if ($res) {
                return ['errorCode' => 1, '返回成功', 'url' => '/public/qr/paramcode/' . date('Y-m-d', time()) . '/' . $filename];
            }
        } else {
            return ['errorCode' => 0, 'msg' => '微信返回错误'];
        }
    }

    /**
     * 我的马克币
     **/
    public function myMakebi()
    {
        //用户信息
        $user = M('users')->where('user_id', $this->user_id)->find();

        $config = M('n_goods_config')
            ->where('is_show', '1')
            ->where('is_get_type', '1')
            ->select();

        //马克币流水记录
        $count = M('n_amount_log')->where(array('user_id' => $this->user_id, 'type' => 1))->count();
        $page = new Page($count);
        $lists = M('n_amount_log')
            ->where(array('user_id' => $this->user_id, 'type' => 1))
            ->order('id desc')
            ->limit($page->firstRow . ',' . $page->listRows)->select();


        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        $this->assign('user', $user);
        $this->assign('config', $config);

        return $this->fetch();
    }

    /**
     * 创建合同
     **/
    public function createContract()
    {
        //申请的身份信息
        $applyIdentity = M('n_apply_identity')
            ->where('obj_user_id', $this->user_id)
            ->find();
        //代理省
        $address = M('region')->where('id', $applyIdentity['agent_province_id'])->find();

        //代理市
        $city = M('region')->where('id', $applyIdentity['agent_city_id'])->find();

        //代理区
        $area = M('region')->where('id', $applyIdentity['agent_area_id'])->find();

        //申请者的用户信息
        $user = M('users')->where('user_id', $this->user_id)->find();
        $applyIdentity['phone'] = $user['mobile'];
        $applyIdentity['id_card'] = $user['id_card'];

        $this->assign('applyIdentity', $applyIdentity);
        $this->assign('address', $address);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('nowDate', date('Y-m-d', time()));
        $this->assign('number', rand(10, 99) . time());
        return $this->fetch();
    }

    /*
     *
     * 保存合同
     *
     * */
    public function saveContract()
    {
        //身份认证
        $new = new UsersLogic();
        $check = $new->checkUser($this->user_id, 4);
        if ($check['status'] != 1) {
            $this->error($check['msg']);
        }

        $input = input('');

        $base64_image_content = $input['img'];
        if (!$base64_image_content) {
            $this->error('请完善签名');
        }
        //dump($base64_image_content);die;

        $path = ROOT_PATH . 'public/uploads';
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];

            $new_file = $path . "/" . date('Ymd', time()) . "/";

            if (!is_dir($new_file)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                $res = mkdir($new_file);

            }


            $new_file = $new_file . time() . ".{$type}";

            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                $data = array();
                $data['user_id'] = $this->user_id;
                $data['jia_name'] = $input['jia_name'];
                $data['yi_name'] = $input['yi_name'];
                $data['number'] = $input['number'];
                $data['phone'] = $input['phone'];
                $data['img'] = substr($new_file, strpos($new_file, "public/"));
                $data['id_card'] = $input['id_card'];
                $data['dai_province'] = $input['dai_province'];
                $data['dai_city'] = $input['dai_city'];
                $data['dai_area'] = $input['dai_area'];
                $data['create_time'] = time();

                $Rt = M('n_hetong')->add($data);

                $this->redirect('User/contractDetail');

            }

        } else {

            return false;
        }

    }

    /*
     * 查看合同
     *
     * */
    public function contractDetail()
    {
        $data = M('n_hetong')->where('user_id', $this->user_id)->find();
        //代理省
        $address = M('region')->where('id', $data['dai_province'])->find();

        //代理市
        $city = M('region')->where('id', $data['dai_city'])->find();

        //代理区
        $area = M('region')->where('id', $data['dai_area'])->find();

        $this->assign('data', $data);
        $this->assign('address', $address);
        $this->assign('city', $city);
        $this->assign('area', $area);
        return $this->fetch();
    }


    /**
     * 我的合同
     **/
    public function myContract()
    {
        $hetong = M('n_hetong')->where('user_id', $this->user_id)->find();

        if ($hetong) {
            $this->redirect('User/contractDetail');
        } else {
            //判断是否申请身份成功
            $applyIdentity = M('n_apply_identity')
                ->where('obj_user_id', $this->user_id)
                ->find();
            $this->assign('applyIdentity', $applyIdentity);
            return $this->fetch();
        }

    }

    /**
     * 常见问题
     **/
    public function usualProblem()
    {
        $res = db('n_question')->where('is_show', 1)->select();
        // dump($res);die;
        $this->assign('list', $res);
        return $this->fetch();
    }

    /**
     * 常见问题
     **/
    public function usualProblem1()
    {
        $input = input();

        $res = db('n_question')->where('id', $input['id'])->find();
        // dump($res);die;
        $this->assign('list', $res);
        return $this->fetch();
    }

    public function feedback()
    {
        $user_id = $this->user_id;
        $mobile = db('users')->where('user_id', $user_id)->value('mobile');
        $this->assign('phone', $mobile);
        return $this->fetch();
    }


    /**
     * 提交意见反馈
     **/
    public function _feedback()
    {
        //获取手机号码，意见，图片
        $input = input();

        $user_id = $this->user_id;

        $data = ['user_id' => $user_id,
            'phone' => $input['phone'],
            'content' => $input['content'],
            'create_time' => date('Y-m-d H:i:s', time()),
            'img' => $input['img']
        ];
        $res = db('n_advice')->insert($data);
        if ($res) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * 身份证号码输入/更新
     **/
    public function idcardAuth()
    {
        if (IS_POST) {
            $user_id = I('post.user_id');
            $id_card = I('post.id_card');
            $real_name = I('post.real_name');
            $find = Db::table("tp_n_user_idcard_apply")->where("user_id", $user_id)->find();
//            $user = Db::table("tp_users")->where("user_id", $user_id)->find();
            $userData = array(
                'id_card' => $id_card,
                'real_name' => $real_name,
            );
            //更新user表
            M('users')->where('user_id', $user_id)->update($userData);
            //更新操作
            if ($find) {
                $cardData = array(
                    'id_card' => $id_card,
                    'name' => $real_name,
                    'status' => 0,
                );
                $result = M('n_user_idcard_apply')->where('user_id', $user_id)->update($cardData);
                if ($result === false) {
                    $this->error('操作失败');
                } else {
                    $this->success("操作成功", U('User/userinfo'));
                }
            } else {//写入操作
                $result = Db::table('tp_n_user_idcard_apply')->data(['user_id' => $user_id, 'name' => $real_name, 'id_card' => $id_card, 'create_time' => time()])->insert();
                if ($result) {
                    $this->success("操作成功", U('User/userinfo'));
                } else {
                    $this->error('操作失败');
                }
            }

        }
        $user_id = I('user_id');
        $userInfo = M("users")->where('user_id', $this->user_id)->find();

        //查找用户是否已经有上传
        $userIDcardInfo = M('n_user_idcard_apply')->where('user_id', $user_id)->find();
//        dump($userIDcardInfo);die;
        $this->assign('user_id', $user_id);
        $this->assign('userIDcardInfo', $userIDcardInfo);
        $this->assign('userInfo', $userInfo);
        return $this->fetch();
    }

    /**
     * 上传身份证
     **/
    public function upIdcard()
    {
        $user_id = $this->user_id;
        $input = input();
        if ($input['status'] == 1) {
            $data = db('n_user_idcard_apply')->where('user_id', $user_id)->find();
            if ($data) {
                $res = db('n_user_idcard_apply')->where('user_id', $user_id)->update([
                    'positive_path' => $input['positive_path'],
                    'reverse_path' => $input['reverse_path'],
                    'status' => 0
                ]);
            } else {
                $res = db('n_user_idcard_apply')->where('user_id', $user_id)->insert([
                    'user_id' => $user_id,
                    'positive_path' => $input['positive_path'],
                    'reverse_path' => $input['reverse_path'],
                    'create_time ' => time(),
                ]);
            }


            if ($res) {
                return array('status' => 200, 'msg' => '提交成功', 'result' => '');
            } else {
                return array('status' => 500, 'msg' => '提交失败', 'result' => '');
            }

        }

        //查找用户是否已经有上传
        $userIDcardInfo = M('n_user_idcard_apply')->where('user_id', $user_id)->find();
        $this->assign('userIDcardInfo', $userIDcardInfo);
        return $this->fetch();
    }

    /**
     * 银行卡账户设置/修改
     **/
    public function bankCard()
    {
        //获取已填写的用户银行卡信息
        $userBank = M('n_user_bank')->where('user_id', $this->user_id)->find();

        if (IS_POST) {
            if ($userBank) {
                //更新
                //检测是否已存在该银行卡
                $whereData = array(
                    'card_number' => I('post.card_num'),
                );
                $check = M("n_user_bank")->where($whereData)->find();
                if (!empty($check) && $userBank['id'] != $check['id']) {
                    $this->error("该银行卡已存在");
                    exit();
                }
                $user = M("users")->where('user_id', $this->user_id)->find();
                //dump($user);die;
                $bank = M("n_bank")->where('id', I('post.card_type'))->find();
                $data = array(
                    'name' => I('post.user_name'),
                    'card_number' => I('post.card_num'),
                    'bank_id' => I('post.card_type'),
                    'user_id' => $user['user_id'],
                    'id_card' => $user['id_card'],
                    'province_id' => $user['province'],
                    'city_id' => $user['city'],
                    'area_id' => $user['district'],
                    'branch_name' => I('post.branch_name'),
                    'address' => I('post.address'),
                    'create_time' => time()
                );
                //更新操作
                $result = M('n_user_bank')->where('id', $userBank['id'])->update($data);
                if ($result) {
                    $this->success('提交成功', U('User/userinfo'));
                    exit();
                } else {
                    $this->error($result['msg']);
                }
            } else {
                //新增
                //检测是否已存在该银行卡
                $whereData = array(
                    'card_number' => I('post.card_num'),
                    'user_id' => $this->user_id
                );
                $check = M("n_user_bank")->where($whereData)->find();
                if (!empty($check)) {
                    $this->error("该银行卡已存在");
                    exit();
                }
                $user = M("users")->where('user_id', $this->user_id)->find();
                //dump($user);die;
                $bank = M("n_bank")->where('id', I('post.card_type'))->find();
                $data = array(
                    'name' => I('post.user_name'),
                    'card_number' => I('post.card_num'),
                    'bank_id' => I('post.card_type'),
                    'user_id' => $user['user_id'],
                    'id_card' => $user['id_card'],
                    'province_id' => $user['province'],
                    'city_id' => $user['city'],
                    'area_id' => $user['district'],
                    'branch_name' => I('post.branch_name'),
                    'address' => I('post.address'),
                    'create_time' => time()
                );
                //写入操作
                $result = M('n_user_bank')->insert($data);
                if ($result) {
                    $this->success('提交成功', U('User/userinfo'));
                    exit();
                } else {
                    $this->error($result['msg']);
                }
            }
        }
        //获取银行卡类型列表、
        $getCardType = M("n_bank")->where('is_show', 1)->select();
        //获取填写的资料
        $this->assign('cardType', $getCardType);
        $this->assign('userBank', $userBank);
        return $this->fetch();
    }

    /**
     * 粉丝列表
     **/
    public function getFans()
    {
        //获取个人所有粉丝列表
        $userId = M("n_user_management")->where('management_id', $this->user_id)->select();
        $userList = array();
        foreach ($userId as &$val) {
            $userInfo = M("users")->where('user_id', $val['user_id'])->find();
            $new = new UserReward();
            $getDistribution = $new->getDistributionMoney($userInfo['user_info']);
            $distribution = $getDistribution['numMonth'];
            $distribution2 = $getDistribution['num'];
            //计算管理佣金
            $getManagent = $new->getManagentMoney($userInfo['user_info']);
            $managent = $getManagent['numMonth'];
            $managent2 = $getManagent['num'];
            //计算上荐佣金
            $getCommend = $new->getCommendMoney($userInfo['user_info']);
            $commend = $getCommend['numMonth'];
            $commend2 = $getCommend['num'];
            //获取用户本月业绩
            $countMonth = $distribution + $managent + $commend;
            //获取用户总业绩
            $countAll = $distribution2 + $managent2 + $commend2;
            //获取用户城市
            $city = M("region2")->where('id', $userInfo['city'])->find();
            $userInfo['countAll'] = $countAll;
            $userInfo['countMonth'] = $countMonth;
            $userInfo['cityName'] = $city['name'];
            $userList[] = $userInfo;
        }
        $count = count($userList);
        $this->assign('countAll', $countAll);
        $this->assign('countMonth', $countMonth);
        $this->assign('userList', $userList);
        //获取当前登录用户信息
        $user = M("users")->where('user_id', $this->user_id)->find();
        $this->assign('count', $count);
        $this->assign('user', $user);
        //若提交表单
        return $this->fetch();
    }

    /**
     * 粉丝详情页
     **/
    public function fanDetail()
    {
        $userId = I('user_id');
        $userInfo = M("users")->where('user_id', $userId)->find();

        //获取身份证上的住址
        $province = M('region')->where('id', $userInfo['province'])->find();//省
        $city = M('region')->where('id', $userInfo['city'])->find();//市
        $district = M('region')->where('id', $userInfo['district'])->find();//区

        //获取家庭住址
        $address = M("user_address")->where('user_id', $userInfo['user_id'])->where('is_default', 1)->find();
        $province2 = M('region')->where('id', $address['province'])->find();//省
        $city2 = M('region')->where('id', $address['city'])->find();//市
        $district2 = M('region')->where('id', $address['district'])->find();//区
        $addressName['province'] = $province2['name'];
        $addressName['city'] = $city2['name'];
        $addressName['district'] = $district2['name'];

        //获取代理区域
        $identity = M("n_apply_identity")->where("obj_user_id", $userInfo['user_id'])->where('status', 1)->find();
        $province3 = M('region')->where('id', $identity['agent_province_id'])->find();//省
        $city3 = M('region')->where('id', $identity['agent_city_id'])->find();//市
        $district3 = M('region')->where('id', $identity['agent_area_id'])->find();//区

        //获取健康大使单位所在区域
        $province4 = M('region')->where('id', $identity['province_id'])->find();//省
        $city4 = M('region')->where('id', $identity['city_id'])->find();//市
        $district4 = M('region')->where('id', $identity['area_id'])->find();//区

        $identityName['province'] = $province3['name'];
        $identityName['city'] = $city3['name'];
        $identityName['district'] = $district3['name'];
        $identityName['h_province'] = $province4['name'];
        $identityName['h_city'] = $city4['name'];
        $identityName['h_district'] = $district4['name'];
        $identityName['unit'] = $identity['unit'];
        $identityName['title'] = $identity['title'];



//        dump($userInfo);die;
        $this->assign('identity', $identity);
        $this->assign('identityName', $identityName);
        $this->assign('addressName', $addressName);
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('district', $district);
        $this->assign('address', $address);
        $this->assign("userInfo", $userInfo);
        return $this->fetch('fandetail');
    }

    /**
     * 申请类型
     **/
    public function saleType()
    {
        $userId = I("user_id");//被申请者ID
        //获取目标用户信息
        $userInfo = M("users")->where('user_id', $userId)->find();
        $this->assign("userInfo", $userInfo);
        //获取登录用户信息
        $user = M("users")->where('user_id', $this->user_id)->find();
        //避免重复申请健康大使
        $unReApply = M("n_apply_identity")
            ->where('obj_user_id', $userId)
            ->where('user_type', 1)
            ->find();
        //避免重复申请总代
        $unReApply2 = M("n_apply_identity")
            ->where('obj_user_id', $userId)
            ->where('user_type', 2)
            ->find();
        if (empty($unReApply)) {
            $unReApply = 0;
        } else {
            $unReApply = 1;
        }
        if (empty($unReApply2)) {
            $unReApply2 = 0;
        } else {
            $unReApply2 = 1;
        }
        $this->assign('unReApply', $unReApply);
        $this->assign('unReApply2', $unReApply2);
        $this->assign('user', $user);
        return $this->fetch();
    }

    /**
     * 申请成为总代、大区
     **/
    public function agent()
    {
        //获取目标用户信息
        $userId = I("user_id");

        if (!empty($userId)) {
            //身份认证
            $new = new UsersLogic();
            $check = $new->checkUser($userId, 3);
            if ($check['status'] == 0) {
                $this->error($check['msg'], U('User/saleType', array('user_id' => $userId)));
            }
        }

        $userInfo = M("users")->where('user_id', $userId)->find();
        //若非会员，不允许申请为总代
        if ($userInfo['user_type'] != 0) {
            $this->error("非会员，不允许申请为总代！");
        }
        $this->assign("userInfo", $userInfo);
        //获取登录用户信息
        $user = M("users")->where('user_id', $this->user_id)->find();
        if (empty($user['mobile'])) {
            $this->assign('请先绑定手机！');
        }
        if (empty($user['id_card'])) {
            $this->assign('请先进行身份证号码认证！');
        }
        if (empty($user['positive_path']) || empty($user['reverse_path'])) {
            $this->assign('请上传身份证证件照！');
        }
        $this->assign('user', $user);
        if (IS_POST) {
            //获取被申请者信息
            $saleInfo = M("users")->where('user_id', I("post.obj_id"))->find();
            $userData = array(
                'user_id' => $user['user_id'],
                'obj_user_id' => I('post.obj_id'),
                'user_type' => I('post.user_type'),
                'name' => I('post.name'),
                'sex' => I('post.sex'),
                'phone' => I('post.phone'),
                'address' => I('post.address'),
                'id_card' => I('post.id_card'),
                'old_profession' => I('post.old_profession'),

                'province_id' => I('post.province1'),
                'city_id' => I('post.city1'),
                'area_id' => I('post.district1'),

                'agent_province_id' => I('post.province'),
                'agent_city_id' => I('post.city'),
                'agent_area_id' => I('post.district'),

                'create_time' => time(),
            );
            //写入申请表
            $result = M("n_apply_identity")->insert($userData);
            if ($result) {
                $this->success('提交成功', U('User/index'));
                exit();
            } else {
                $this->error($result['msg']);
            }
        }

        return $this->fetch();
    }

    /**
     * 申请成为健康大使
     **/
    public function healthy()
    {
        //获取目标用户信息
        $userId = I("user_id");
        if (!empty($userId)) {
            //身份认证
            $new = new UsersLogic();
            $check = $new->checkUser($userId, 1);
            if ($check['status'] == 0) {
                $this->error($check['msg'], U('User/saleType', array('user_id' => $userId)));
            }
        }
        $userInfo = M("users")->where('user_id', $userId)->find();
        //若非会员，不允许申请为总代
        if ($userInfo['user_type'] != 0) {
            $this->error("非会员，不允许申请为健康大使！");
        }
        $this->assign("userInfo", $userInfo);
        //获取登录用户信息
        $user = M("users")->where('user_id', $this->user_id)->find();
        if (empty($user['mobile'])) {
            $this->assign('请先绑定手机！');
        }
        $this->assign('user', $user);
        if (IS_POST) {
            //获取被申请者信息
            $saleInfo = M("users")->where('user_id', I("post.obj_id"))->find();
            $userData = array(
                'user_id' => $user['user_id'],
                'obj_user_id' => I('post.obj_id'),
                'user_type' => I('post.user_type'),
                'name' => I('post.name'),
                'sex' => I('post.sex'),
                'phone' => I('post.phone'),
                'unit' => I('post.unit'),
                'title' => I('post.title'),
                'province_id' => I('post.province1'),
                'city_id' => I('post.city1'),
                'area_id' => I('post.district1'),
                'create_time' => time(),
            );
            //写入申请表
            $result = M("n_apply_identity")->insert($userData);

            //写入users表
            $dataUser = array(
                'real_name' => I('post.name'),
            );
            $updateUser = M('users')->where('user_id', I('post.obj_id'))->update($dataUser);

            if ($result) {
                $this->success('提交成功', U('User/index'));
                exit();
            } else {
                $this->error($result['msg']);
            }
        }
        return $this->fetch();
    }

    /**
     * 申请提现
     **/
    public function withdrawalM()
    {
        //身份认证
        $new = new UsersLogic();
        $check = $new->checkUser($this->user_id, 5);
        if ($check['status'] == 0) {
            $this->error($check['msg']);
        }
        //获取个人信息
        $user = M("users")->where('user_id', $this->user_id)->find();
        if ($user['user_type'] != 2 && $user['user_type'] != 3) {
            $this->error('当前用户没有提现的权限！');
        }
        $userId = $user['user_id'];
        //计算分销佣金
        $new = new UserReward();
        $getDistribution = $new->getDistributionMoney($userId);
        $distribution = $getDistribution['num'];
        //计算管理佣金
        $getManagent = $new->getManagentMoney($userId);
        $managent = $getManagent['num'];
        //计算上荐佣金
        $getCommend = $new->getCommendMoney($userId);
        $commend = $getCommend['num'];
        //计算代收
        $coll = $new->getReRewardMoney($userId);
        $collection = $coll['num'];
        //计算总提现佣金
        $totalMoney = $distribution + $managent + $commend + $collection;
        $totalUser = $distribution + $managent + $commend;
        //若总佣金和users表中的可提佣金不一致，则代表流水出错
//        if($totalUser>=$user['yongjin']){
//            $this->error("佣金数量错误");
//        }
        if($totalMoney == 0){
            $this->error("当前可提现金额为零");
        }
        $moneyData = array(
            'user_id' => $user['user_id'],
            'reward_money' => $distribution,
            'managent_money' => $managent,
            'top_money' => $commend,
            'agent_money' => $collection,
            'total_money' => $totalMoney,
            'amount_ids' => $new->returnStr($getDistribution['ids'], $getManagent['ids'], $getCommend['ids']),
            'status' => 0,
            'create_time' => time()
        );

        // 启动事务
        Db::startTrans();
        try {
            //写入佣金申请表
            $result = M("n_yongjin_month")->insert($moneyData);
            //更新n_amount_log表status为1
            $yjId = Db::name('n_yongjin_month')->getLastInsID();
            $getInfo = M("n_yongjin_month")->where("id", $yjId)->find();
            $logIds = explode(',', $getInfo['amount_ids']);
            $logData = array(
                'status' => 1
            );
            foreach ($logIds as &$val) {
                $upLog = M("n_amount_log")->where('id', $val)->update($logData);
            }
            /*写入功德流水表——开始*/
            //查找对应下级
            $getCid = M("n_user_management")->where('management_id', $userId)->select();
            //写入功德流水表
            foreach ($getCid as &$value) {
                $getUser = M("users")->where('user_id', $value['user_id'])->where('user_type', 1)->find();
                //若找不到对应管理关系为健康大使的，直接跳出本次循环
                if (empty($getUser)) {
                    continue;
                }
                $salesMoney = $new->getDistributionMoney($value['user_id']);
                $sales_money = $salesMoney['num'];
                $topMoney = $new->getCommendMoney($value['user_id']);
                $top_money = $topMoney['num'];
                $total_money = $sales_money + $top_money;
                $gdData = array(
                    'yongjin_month_id' => $yjId,
                    'user_id' => $getUser['user_id'],
                    'sales_money' => $sales_money,
                    'top_money' => $top_money,
                    'total_money' => $total_money,
                    'status' => 0,
                    'amount_ids' => $new->returnStr($salesMoney['ids'], $topMoney['ids']),
                    'create_time' => time(),
                );
                if ($sales_money == 0 && $top_money == 0) {
                    continue;
                }
                $gdInsert = M("n_gongde_month")->insert($gdData);
                if (!$gdInsert) {
                    $this->error("写入功德流水表失败");
                }
                //更新对应n_amount_log的status为1
                $gdId = Db::name('n_gongde_month')->getLastInsID();
                $getInfo = M("n_gongde_month")->where("id", $gdId)->find();
                $logIds = explode(',', $getInfo['amount_ids']);
                $logData = array(
                    'status' => 1
                );
                foreach ($logIds as &$val) {
                    $upLog = M("n_amount_log")->where('id', $val)->update($logData);
                    if ($upLog === false) {
                        $this->error('更新流水失败');
                    }
                }
                if ($gdInsert) {
                    //减去消耗功德
                    $userData = array(
                        'gongde' => $getUser['gongde'] - $total_money,
                    );
                    $upResultGd = M("users")->where('user_id', $value['user_id'])->update($userData);
                    if ($upResultGd === false) {
                        $this->error('减去功德失败');
                    }
                }
            }

            if ($totalUser != 0) {
                //将users表剩余佣金减去提现金额
                $userData = array(
                    'yongjin' => $user['yongjin'] - $totalUser,
                );
                $upResult = M("users")->where('user_id', $user['user_id'])->update($userData);
                if (!$upResult) {
                    $this->error('提现提交失败');
                }
            }


            if ($result && ($upLog !== false) && $gdInsert && ($upLog !== false) && ($upResultGd !== false) && ($upResult !== false)) {
                // 提交事务
                Db::commit();
            }
        } catch (\Exception $e) {
            // 回滚事务
            //Log::error($e->getMessage());
            Db::rollback();
        }

        //提现成功，跳转生成发票
        return $this->redirect(U('User/fapiao', array('id' => $yjId)));
    }

    public function fapiao()
    {

        $input = input();
        //获取传过来的id
//        $input['id'] = 1;
        //获取用户id
        $user_id = $this->user_id;

//        $user_id = 2764;
        $res = db('n_yongjin_month')->where('id', $input['id'])->where('user_id', $user_id)->find();
        $number = $user_id . date('YmdHis', time());
        $this->assign('list', $res);
        $this->assign('list', $res);
        $this->assign('number', $number);
        $this->assign('id', $input['id']);
        return $this->fetch();
    }

    //拿到发票数据接口
    public function _fapiao()
    {
        $input = input();

        $user_id = $this->user_id;
        //$user_id = 2764;
        $res = db('n_user_bank')->where('user_id', $user_id)->find();
        $user_data = db('users')->where('user_id', $user_id)->find();
        $res['phone'] = $user_data['mobile'];
        $res['id_card'] = $user_data['id_card'];

        return $res;
    }

    //提交发票信息
    public function tijiao_fapiao()
    {
        $input = input();

        $base64_image_content = $input['image'];
        //dump($base64_image_content);die;
        $path = ROOT_PATH . 'public/uploads';
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];

            $new_file = $path . "/" . date('Ymd', time()) . "/";

            if (!file_exists($new_file)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($new_file, 0700, true);
            }
            $new_file = $new_file . time() . ".{$type}";

            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                $new_file = substr($new_file, strpos($new_file, "public/"));
                db('n_yongjin_month')->where('id', $input['id'])->update(['fapiao_img' => $new_file]);

//                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }

        $this->redirect('User/cashHistory');
    }

    /**
     * 总代、大区钱包页面
     **/
    public function balanceZd()
    {
        $userId = $this->user_id;

        $userInfo = M("users")->where('user_id', $userId)->find();//用户余额数据来源

        $this->assign('userInfo', $userInfo);

        $new = new UserReward();

        $result = array();

        //总余额（剩余佣金）
        $result['balance'] = $userInfo['yongjin'];

        //分销佣金
        $distribution = $new->getDistributionMoney($userId);

        $result['distribution'] = $distribution['num'];//可提取分销佣金

        $result['distributionMonth'] = $distribution['numMonth'];//本月分销佣金

        //管理佣金
        $manage = $new->getManagentMoney($userId);

        $result['manage'] = $manage['num'];//可提取管理佣金

        $result['manageMonth'] = $manage['numMonth'];//本月管理佣金

        //上荐佣金
        $commend = $new->getCommendMoney($userId);

        $result['commend'] = $commend['num'];//可提取上荐佣金

        $result['commendMonth'] = $commend['numMonth'];//本月管理佣金

        //代收佣金
        $reRewardMoney = $new->getReRewardMoney($userId);

        $result['collection'] = $reRewardMoney['num'];//可提取代收佣金

        $result['collectionMonth'] = $reRewardMoney['month'];//本月代收佣金

        //可提现总佣金
        $result['total'] = $distribution['num'] + $manage['num'] + $commend['num'] + $result['collection'];

        //当月总佣金
        $result['totalMonth'] = $distribution['numMonth'] + $manage['numMonth'] + $commend['numMonth'] + $result['collectionMonth'];

        //提现时间段
        $startDay = 1;//允许提现的开始时间,1代表当月1号

        $endDay = 26;//允许提现的结束时间

        $y = date("Y", time());

        $m = date("m", time());

        $startTime = mktime(0, 0, 0, $m, $startDay, $y); //生成本月开始时间

        $endTime = $startTime + (3600 * 24 * $endDay) - 1;//生成本月结束时间

        $nowTime = time();

        if ($nowTime >= $startTime && $nowTime <= $endTime) {
            $result['allow_time'] = 1;
        } else {
            $result['allow_time'] = 0;
        }

        $this->assign('result', $result);

        return $this->fetch();
    }

    //功德列表
    public function gongdeHistory()
    {
        $new = new UserReward();
        $getChild = M("n_user_management")->where('management_id', $this->user_id)->select();
        foreach ($getChild as &$value) {
            $getOne = M("users")->where('user_id', $value['user_id'])->find();
            //当前用户为健康大使的管理下级
            if ($getOne['user_type'] == 1) {
                //获取该健康大使的功德历史
               $his = M("n_gongde_month")->where('user_id', $getOne['user_id'])->select();
                if(!empty($his)){
                    $gdHis[] = $his;
                }
            }
        }

//        dump($gdHis);
//        die;

        //降维
        if (!empty($gdHis)) {
            $gdList = array();
            foreach ($gdHis as &$v1) {

                foreach ($v1 as &$v2) {
                    //获取个人信息
                    $find = M("users")->where('user_id', $v2['user_id'])->find();
                    $v2['name'] = $find['nickname'];
//                    $v2['totalMont'] = $new->unSent($v2['user_id']);
//                    $v2['unSent'] = $new->sentedGongde($v2['user_id']);
                    $v2['time'] = date("Y-m-d H:i:s",$v2['create_time']);
                    $gdList[] = $v2;
                }
            }
        }
        $count = count($gdList);
        $this->assign('gdList', $gdList);
        $this->assign('count', $count);
    //        dump($gdList);
    //        die;
        return $this->fetch();
    }

    /**
     * 发放功德
     **/
    public function sentGd()
    {
        $gdId = I('gd_id');
        $data = array(
            'status' => 2,
            'pay_time' => time()
        );
        $result = M('n_gongde_month')->where("id", $gdId)->update($data);
        if ($result) {
            $this->success('发放成功', U('User/gongdeHistory'));
        } else {
            $this->error('发放失败', U('User/gongdeHistory'));
        }
    }

    /**
     * 健康大使功德页面
     **/
    public function balanceHealthy()
    {
        $userId = $this->user_id;
        $userInfo = M("users")->where('user_id', $userId)->find();
        $this->assign('userInfo', $userInfo);
        $new = new UserReward();
        //分销佣金
        $distribution = $new->getDistributionMoney($userId);
        $result['distribution'] = $distribution['num'];//待领取功德
        $result['distributionMonth'] = $distribution['numMonth'];//本月功德
        //上荐佣金
        $commend = $new->getCommendMoney($userId);
        $result['commend'] = $commend['num'];
        $result['commendMonth'] = $commend['numMonth'];
        //合计
        $result['totalNum'] = $result['distribution'] + $result['commend'];
        $result['totalMonth'] = $result['distributionMonth'] + $result['commendMonth'];

        $time = date('Y-m-01', strtotime(date("Y-m-d")));
        $gdList = M('n_gongde_month')->where('user_id', $userId)->where('create_time', '>', $time)->order('create_time desc')->select();
        $this->assign('result', $result);
        $this->assign("gdList", $gdList);
        return $this->fetch();
    }

    public function getGd()
    {
        $gdId = I('gd_id');
        $findGd = M('n_gongde_month')
            ->where('id', $gdId)
            ->find();
        $data = array(
            'status' => 1,
            'get_time' => time()
        );
        $data2 = array(
            'status' => 2
        );
        //更新流水表状态
        if (!empty($findGd['amount_ids'])) {
            $exArr = explode(',', $findGd['amount_ids']);
            foreach ($exArr as $value) {
                $upAmountLog = M("n_amount_log")
                    ->where('id', $value)
                    ->update($data2);
            }

        } else {
            $this->ajaxReturn(0);
        }
        //更新功德记录表状态
        $result = M('n_gongde_month')->where("id", $gdId)->update($data);
        if ($result) {
            $this->ajaxReturn(array('msg' => '操作成功', 'status' => 1));
        } else {
            $this->ajaxReturn(0);
        }

    }

    /**
     * 收入明细页
     **/
    public function incomeDetail()
    {
        $userId = $this->user_id;
        $userInfo = M('users')
            ->where('user_id', $this->user_id)
            ->find();
        if ($userInfo['user_type'] == 1) {
            $result = M("n_amount_log")->where('user_id', $userId)->order('id desc')->where('type', 2)->paginate(10);
        }
        if ($userInfo['user_type'] == 2 || $userInfo['user_type'] == 3) {
            $result = M("n_amount_log")->where('user_id', $userId)->order('id desc')->where('type', 'in', '0,3')->paginate(10);
        }
        $this->assign('result', $result);
        return $this->fetch();
    }


    /**
     *功德表页面（只有当用户为大区、总代时才显示在前端）
     **/
    public function gongDeList()
    {
        $userId = $this->user_id;
        $user = M("users")->where('user_id', $userId)->find();
//        $gdMonth = M("n_gongde_moth")->Where('user_id',$userId)->  find();
        $this->assign('user', $user);
    }

    //消息
    public function userNotice()
    {
        //用户系统消息
        $getNotic = M('n_user_notice')->where('user_id', $this->user_id)->where('type', 1)->select();
        //订单消息
        $noticeOrder = M('n_user_notice')->where('user_id', $this->user_id)->where('type', 2)->select();
        $this->assign('noticeOrder', $noticeOrder);
        $this->assign('notice', $getNotic);
        return $this->fetch();
    }


    //图片上传
    public function upload()
    {
        $input = input();

        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');

        // 移动到框架应用根目录/public/uploads/ 目录下
        if ($file) {
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');

            if ($info) {

                $route = str_replace("\\", "/", $info->getSaveName());
                $route = '/public/uploads/' . $route;

                return $route;
            } else {
                // 上传失败获取错误信息
                //echo $file->getError();
                return flase;
            }
        }
    }

    /**
     * [将Base64图片转换为本地图片并保存]
     * @E-mial wuliqiang_aa@163.com
     * @TIME   2017-04-07
     * @WEB    http://blog.iinu.com.cn
     * @param  [Base64] $base64_image_content [要保存的Base64]
     * @param  [目录] $path [要保存的路径]
     */
    function base64_image_content()
    {
        $input = input();

        $base64_image_content = $input['image'];

        $path = 'public/uploads';
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];

            $new_file = $path . "/" . date('Ymd', time()) . "/";

            if (!file_exists($new_file)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($new_file, 0700, true);
            }
            $new_file = $new_file . time() . ".{$type}";

            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                $image = \think\Image::open('public/static/images/banner_2.jpg');
                // 给原图左上角添加水印并保存water_image.png
                $re = $image->water($new_file, \think\Image::WATER_SOUTH)->save($new_file);

                return '/' . $new_file;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /*
         * 梅克伦绑定手机号
         *
         * */
    public function bindPhone()
    {
        $input = input('');
        $phone = $input['phone'];
        $returnData = array();
        $oldPhone = M('users')->where('mobile', $phone)->find();
        if ($oldPhone) {

            $returnData['status'] = '0';
            $returnData['msg'] = '该手机号码已被优先绑定';
        } else {
            $data = array();
            $data['mobile'] = $phone;
            $data['mobile_validated'] = 1;

            $Rt = M('users')->where('user_id', $this->user_id)->update($data);


            $returnData['status'] = '1';
            $returnData['msg'] = '绑定成功';
        }

        return $returnData;
    }

    /**
     * 直推下级
     */
    public function strNext()
    {
        $getFans = M('users')->where('pid', $this->user_id)->paginate(10);
        $count = M('users')->where('pid', $this->user_id)->select();
        $count = count($count);
        $this->assign('count', $count);
        $this->assign('getFans', $getFans);
        return $this->fetch();

    }
}
