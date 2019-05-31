<?php

namespace app\mobile\controller;

use app\common\logic\CartLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\OrderLogic;
use app\common\logic\UsersLevel;
use app\common\logic\UsersUpLevel;
use app\common\logic\GoodsLogic;
use app\common\model\Goods;
use app\common\model\Plugin;
use app\common\model\SpecGoodsPrice;
use app\common\logic\IntegralLogic;
use think\Db;
use think\response\Json;
use think\Url;
use think\Log;

class Cart extends MobileBase
{

    public $cartLogic; // 购物车逻辑操作类
    public $user_id = 0;
    public $user = array();

    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->cartLogic = new CartLogic();

        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            // 给用户计算会员价 登录前后不一样
            if ($user) {
                $user['discount'] = (empty($user['discount'])) ? 1 : $user['discount'];
                if ($user['discount'] != 1) {
                    $c = Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->where('member_goods_price = goods_price')->count();
                    $c && Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->update(['member_goods_price' => ['exp', 'goods_price*' . $user['discount']]]);
                }
            }
        }

    }

    public function index()
    {

       /* $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList();//用户购物车

        //定义普通商品的仓库，根据不同供应商排列
        $ptGoods = $this->dealCartProvider($cartList);



        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数
        $hot_goods = M('Goods')->where('is_hot=1 and is_on_sale=1')->limit(20)->cache(true, TPSHOP_CACHE_TIME)->select();
        $this->assign('hot_goods', $hot_goods);
        $this->assign('userCartGoodsTypeNum', $userCartGoodsTypeNum);
        $this->assign('cartList', $cartList);//购物车列表-
        $this->assign('ptGoods', $ptGoods);//购物车列表-根据不同供应商排列
        $this->assign('title', '购物车');


        return $this->fetch();*/
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList();//用户购物车
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数
        $hot_goods = M('Goods')->where('is_hot=1 and is_on_sale=1')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('hot_goods', $hot_goods);
        $this->assign('userCartGoodsTypeNum', $userCartGoodsTypeNum);
        $this->assign('cartList', $cartList);//购物车列表
        $this->assign('title','购物车');
        return $this->fetch();
    }

    /**
     * 更新购物车，并返回计算结果
     */
    public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCart($cart);
        $this->ajaxReturn($result);
    }

    /**
     *  购物车加减
     */
    public function changeNum()
    {
        $cart = input('cart/a', []);
        if (empty($cart)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'], $cart['goods_num']);
        $this->ajaxReturn($result);
    }

    /**
     * 删除购物车商品
     */
    public function delete()
    {
        $cart_ids = input('cart_ids/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if ($result !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功', 'result' => $result]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '删除失败', 'result' => $result]);
        }
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2()
    {

        $input = input('');
        $g_type = isset($input['g_type']) ? $input['g_type'] : 0;   //所属商品类型，0普通商品，1身份商品，2兑换商品

        //分享人的id
        $share_id = input("share_id/d"); // 商品id
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 行为

        if (!$this->user) {
            $this->error('请先登录', U('Home/User/login'));
        }
        $address_id = I('address_id/d');
        if ($address_id) {
            $address = M('user_address')->where("address_id", $address_id)->find();
            $address['province_name'] = db('region')->where(['id' => $address['province']])->value('name');
            $address['city_name'] = db('region')->where(['id' => $address['city']])->value('name');
            $address['district_name'] = db('region')->where(['id' => $address['district']])->value('name');
        } else {
            $address = Db::name('user_address')->where(['user_id' => $this->user_id])->order(['is_default' => 'desc'])->find();
            $address['province_name'] = db('region')->where(['id' => $address['province']])->value('name');
            $address['city_name'] = db('region')->where(['id' => $address['city']])->value('name');
            $address['district_name'] = db('region')->where(['id' => $address['district']])->value('name');
        }

        if (!isset($address['address_id'])) {
            //判断是从购物车的没有地址跳转，还是从立即购买的没有地址跳转

            if ($action == 'buy_now') {

                //立即购买
                header("Location: " . U('Mobile/User/add_address', array('source' => 'cart2',
                        'action' => 'buy_now',
                        'goods_num' => $goods_num,
                        'goods_id' => $goods_id,
                        'g_type' => $g_type,
                        'item_id' => $item_id)));
                exit;
            } else {

                //购物车跳转，则直接先去填写地址，再重新购买
                //把该购物车路径也传过去，后面新增完毕再跳转回来
                $this->error('请先新增一个收货地址后，再重新购买', U('User/add_address', array('from' => 'cart_index')));
            }

        } else {
            $this->assign('address', $address);
        }

        $cartLogic = new CartLogic();
        // $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if ($action == 'buy_now') {
            if ($g_type == 3) {
                $order = M('order_goods')
                    ->alias('og')
                    ->join('order o','o.order_id = og.order_id')
                    ->join('goods g','og.goods_id = g.goods_id')
                    ->where("o.user_id", $this->user_id)
                    ->where("g.g_type", 3)
                    ->find();
                $order ? $this->error('已经领取过零元产品') : true;
            }
            if (empty($goods_id)) {
                $this->error('请选择要购买的商品');
            }
            if (empty($goods_num)) {
                $this->error('购买商品数量不能为0');
            }

            $cartLogic->setGoodsModel($goods_id);
            if ($item_id) {
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }

            $cartLogic->setGoodsBuyNum($goods_num);

            $result = $cartLogic->buyNow();

            if ($result['status'] != 1) {
                $this->error($result['msg']);
            }
            $cartList['cartList'][0] = $result['result']['buy_goods'];
            $cartGoodsTotalNum = $goods_num;
        } else {
            if ($cartLogic->getUserCartOrderCount() == 0) {
                $this->error('你的购物车没有选中商品', 'Cart/index');
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
        }


        // $cartGoodsList = get_arr_column($cartList,'goods');
        // $cartGoodsId = get_arr_column($cartGoodsList,'goods_id');
        // $cartGoodsCatId = get_arr_column($cartGoodsList,'cat_id');
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true, TPSHOP_CACHE_TIME)->select();// 物流公司
        // $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId);//用户可用的优惠券列表
        $cartList = array_merge($cartList, $cartPriceInfo);
        ////print_r($cartList);exit;
        // if ($cartPriceInfo['total_fee']) {
        //     // 订单里有现金价格时 可使用积分抵扣
        //
        //     // $maxPoints = $this->user['pay_points'];
        //     $config = tpCache('shopping');
        //
        //     // 积分信息
        //     $integralInfo = [
        //         // 'max' => $maxPoints,
        //         'min' => $config['point_min_limit'],
        //         'pay_points' => $this->user['pay_points'],
        //         'rate' => $config['point_rate'],
        //     ];
        //     $this->assign('integralInfo', $integralInfo);
        // }

        // 未关注公众号时 下单时提示关注（必须关注才能下单


        $total_fee = $cartPriceInfo['total_fee'];   //原订单总金额

        //返回用户的地址列表
        $userAddressList = array();
        $addressList = M('user_address')->where('user_id', $this->user_id)->select();
        $userAddressList = $addressList ? $addressList : null;

        if ($userAddressList) {
            foreach ($userAddressList as $uk => $uv) {
                $regionInfo = M('region')->where('id', $uv['province'])->find();

                $userAddressList[$uk]['province_name'] = $regionInfo['name'];  //省名称

                //市
                $city = M('region')->where('id', $uv['city'])->find();
                $userAddressList[$uk]['city_name'] = $city['name'];  //市名称

                //区
                $area = M('region')->where('id', $uv['district'])->find();
                $userAddressList[$uk]['district_name'] = $area['name'];  //区名称
            }
        }

        //商品信息
        $goodsInfo = M('goods')->where('goods_id', $goods_id)->find();

        //根据购物车跳转，组装成供应商商品列表


        //返回用户的真实姓名和身份证号码
        $userInfo = M('users')->where('user_id', $this->user_id)->find();
        $this->assign('real_name', $userInfo['real_name']);    //真实姓名
        $this->assign('id_card', $userInfo['id_card']);    //身份证号码
        $this->assign('userInfo', $userInfo);    //用户信息
        $this->assign('goodsInfo', $goodsInfo);    //商品信息

        //返回后台设置的免税订单金额
        $goodsConfig = M('n_goods_config')->where('key', 'hk_free_money')->find();
        $this->assign('setHkFreeMoney', $goodsConfig['value']);    //纯商品总价（不包含运费、税费等）

        $this->assign('pureOrderMoney', $total_fee);    //纯商品总价（不包含运费、税费等）
        $this->assign('userAddressList', $userAddressList);  //用户地址列表

        $this->assign('qr_url', $this->weixin_config['qr']);
        // $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        // $this->assign('userCartCouponList', $userCartCouponList);  //优惠券，用able判断是否可用
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('share_id', $share_id);
        $this->assign('cartList', $cartList['cartList']); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo);//商品优惠总价
        $this->assign('shippingList', $shippingList); // 物流公司
        //0普通商品，1外链商品跳转到京东淘宝，2特定产品（身份产品）,3，零元产品，4积分产品
        $this->assign('g_type', $g_type); //所属商品类型，0普通商品，1身份商品，2兑换商品


        //根据不同的商品类型展示不同的页面,顺便判断是否能够提交订单
        if ($g_type == '0' ||$g_type == '2'||$g_type == '3') {

            //普通商品
            return $this->fetch();
        } elseif ($g_type == '1') {
            //身份商品
            $needJindou = 0;  //定义所需金豆
            $canDeal = 1; //0不能提交订单，1能提交订单

            foreach ($cartList['cartList'] as $k => $v) {
                $needJindou += $v['goods_price'] * $v['goods_num'];
            }
            if ($userInfo['jindou'] < $needJindou) {
                $canDeal = 0;
            }

            $this->assign('canDeal', $canDeal);
            return $this->fetch('vipCart2');
        } elseif ($g_type == '2') {
            //兑换商品

            //返回商品的折扣，并计算商品折扣后的价格，再判断是否拥有足够的金豆提交订单
            $e_discount = $goodsInfo ? $goodsInfo['e_discount'] : 0;  //为0则表示不打折扣


            $needJindou = 0;  //定义所需金豆
            $canDeal = 1; //0不能提交订单，1能提交订单

            foreach ($cartList['cartList'] as $k => $v) {
                $needJindou += $v['goods_price'] * $v['goods_num'];
            }

            if ($e_discount <= '0') {
                //不打折
                if ($userInfo['jindou'] < $needJindou) {
                    $canDeal = 0;
                }
            } else {
                if ($userInfo['jindou'] < $needJindou * $e_discount / 10) {
                    $canDeal = 0;
                }
            }

            $this->assign('canDeal', $canDeal);
            $this->assign('e_discount', $e_discount);
            return $this->fetch('exchangeCart2');
        }elseif ($g_type == '4') {
            //积分兑换商品

            //返回商品的折扣，并计算商品折扣后的价格，再判断是否拥有足够的金豆提交订单
//            $e_discount = $goodsInfo ? $goodsInfo['e_discount'] : 0;  //为0则表示不打折扣
            $e_discount =  0;  //为0则表示不打折扣


            $needJindou = 0;  //定义所需金豆
            $canDeal = 1; //0不能提交订单，1能提交订单

            foreach ($cartList['cartList'] as $k => $v) {
                $needJindou += $v['goods_price'] * $v['goods_num'];
            }

            if ($e_discount <= '0') {
                //不打折
                if ($userInfo['pay_points'] < $needJindou) {
                    $canDeal = 0;
                }
            } else {
                if ($userInfo['pay_points'] < $needJindou * $e_discount / 10) {
                    $canDeal = 0;
                }
            }

            $this->assign('canDeal', $canDeal);
            $this->assign('type', 1);
            $this->assign('e_discount', $e_discount);
            return $this->fetch('exchangeCart2');
        }

    }

    /**
     * 购物车第二步确定页面
     */
    public function cart21()
    {

        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 行为

        if ($this->user_id == 0) {
            $this->error('请先登录', U('Mobile/User/login'));
        }

        $address_id = I('address_id/d');
        if ($address_id) {
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id' => $this->user_id])->order(['is_default' => 'desc'])->find();
        }
        if (empty($address)) {
            $address = M('user_address')->where(['user_id' => $this->user_id])->find();
        }
        if (empty($address)) {
            header("Location: " . U('Mobile/User/add_address', array('source' => 'cart2')));
            exit;
        } else {
            $this->assign('address', $address);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if ($action == 'buy_now') {
            if (empty($goods_id)) {
                $this->error('请选择要购买的商品');
            }
            if (empty($goods_num)) {
                $this->error('购买商品数量不能为0');
            }
            $cartLogic->setGoodsModel($goods_id);
            if ($item_id) {
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if ($result['status'] != 1) {
                $this->error($result['msg']);
            }
            $cartList[0] = $result['result']['buy_goods'];
        } else {
            if ($cartLogic->getUserCartOrderCount() == 0) {
                $this->error('你的购物车没有选中商品', 'Cart/index');
            }
            $cartList = $cartLogic->getCartList(1); // 获取购物车商品
        }
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList);
        // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        $couponWhere = [
            'c2.uid' => $this->user_id,
            'c1.use_end_time' => ['gt', time()],
            'c1.use_start_time' => ['lt', time()],
            'c1.condition' => ['elt', $cartPriceInfo['total_fee']]
        ];
        $couponList = Db::name('coupon')->alias('c1')
            ->join('__COUPON_LIST__ c2', ' c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0', 'inner')
            ->where($couponWhere)
            ->select();

        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true, TPSHOP_CACHE_TIME)->select();// 物流公司
        if ($cartList) {
            $orderGoods = collection($cartList)->toArray();
        }
        //halt($shippingList);
        foreach ($shippingList as $k => $v) {
            $dispatchs = calculate_price($this->user_id, $orderGoods, $v['code'], 0, $address['province'], $address['city'], $address['district']);
            if ($dispatchs['status'] !== 1) {
                $this->error($dispatchs['msg']);
            }
            $shippingList[$k]['freight'] = $dispatchs['result']['shipping_price'];
        }


        $this->assign('couponList', $couponList); // 优惠券列表
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('cartList', $cartList); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo); // 总计
        return $this->fetch();
    }

    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3()
    {
        if ($this->user_id == 0) {
            exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null))); // 返回结果状态
        }

        $userInfo = M('users')->where('user_id', $this->user_id)->find();

        $input = input('');
        //(最新版)type 0普通商品，1外链商品跳转到京东淘宝，2特定产品（身份产品）,3，零元产品，4积分产品
        $g_type = isset($input['g_type']) ? $input['g_type'] : 0;   //所属商品类型，0普通商品，1身份商品，2兑换商品
        //$e_discount = isset($input['e_discount']) ? $input['e_discount'] : 0;   //兑换商品时会传过来的折扣

        $share_id = I("share_id/d"); //分享人的id
        $address_id = I("address_id/d"); //  收货地址id
        //$shipping_code = I("shipping_code"); //  物流编号
        $shipping_code = ''; //  物流编号
        $invoice_title = I('invoice_title'); // 发票
        // $coupon_id =  I("coupon_id/d"); //  优惠券id
        // $pay_points = I("pay_points/d", 0); // 使用积分
        $pay_points = 0; // 使用积分
        // $user_money =  I("user_money/f",0); //  使用余额
        $user_note = trim(I('user_note'));   //买家留言
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 立即购买
        $paypwd = isset($input['paypwd']) ? $input['paypwd'] : 0; //支付密码

        $coupon_id = 0;
        $user_money = 0;

        $cartLogic = new CartLogic();

        $cartLogic->setUserId($this->user_id);
        if ($action == 'buy_now') {
            $cartLogic->setGoodsModel($goods_id);

            if ($item_id) {
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if ($result['status'] != 1) {
                $this->ajaxReturn($result);
            }
            $order_goods[0] = $result['result']['buy_goods'];
        } else {

            $userCartList = $cartLogic->getCartList(1);
            if ($userCartList) {
                $order_goods = collection($userCartList)->toArray();
            } else {
                exit(json_encode(array('status' => -2, 'msg' => '你的购物车没有选中商品', 'result' => null))); // 返回结果状态
            }
            foreach ($userCartList as $cartKey => $cartVal) {
                if ($cartVal->goods_num > $cartVal->limit_num) {
                    exit(json_encode(['status' => 0, 'msg' => $cartVal->goods_name . '购买数量不能大于' . $cartVal->limit_num, 'result' => ['limit_num' => $cartVal->limit_num]]));
                }
            }

        }

        $address = M('UserAddress')->where("address_id", $address_id)->find();


        $result = calculate_price($this->user_id, $order_goods, $shipping_code, 0, $address['province'], $address['city'], $address['district'], $pay_points, $user_money, $coupon_id);
        /**************************************廖燕青计算,该项目单独的算钱逻辑***************************************/
        //判断如果是普通商品购买，根据不同的付款方式组合，计算需要的金豆、云豆和应该在线支付的钱

        $dealDou = array();



        /**************************************END廖燕青计算，该项目单独的算钱逻辑***************************************/

        //print_r($result['result']['order_amount']);exit;
        if ($result['status'] < 0)
            exit(json_encode($result));
        // 订单满额优惠活动
        /*$order_prom = get_order_promotion($result['result']['order_amount']);
        $result['result']['order_amount'] = $order_prom['order_amount'];
        $result['result']['order_prom_id'] = $order_prom['order_prom_id'];
        $result['result']['order_prom_amount'] = $order_prom['order_prom_amount'];*/

        //初始化时判断香港仓是否可以使用免税额度
        $isCan = $this->isCanFree();

        $car_price = array(
            //'isCan' => $isCan, //0没有订单，可使用免税；1有订单了，不可以使用免税（香港仓）
            'userInfo' => $userInfo, // 消费者用户信息,返回给前端的
            'shuiMoney' => 0, // 税费
            'makebiInfo' => 0, // 用户可使用马克币和能抵消金额信息，前端用
            'user_use_makebi' => 0, // 用户使用的马克币
            'makebi_rmb' => 0, // 马克币可抵消的金额
            //（最新版）0普通商品，1外链商品跳转到京东淘宝，2特定产品（身份产品）,3，零元产品，4积分产品
            'g_type' => $g_type, //0普通商品，1身份商品，2兑换商品
            'hk_username' => '', // 香港仓使用额度填写的姓名
            'hk_user_idcard' => '', // 香港仓使用额度填写的身份证号
            //'postFee' => $result['result']['shipping_price'], // 物流费
            'postFee' => $result['result']['shipping_price'], // 物流费
            'couponFee' => $result['result']['coupon_price'], // 优惠券
            'balance' => $result['result']['user_money'], // 使用用户余额
            'pointsFee' => $result['result']['integral_money'], // 积分支付
            'payables' => $result['result']['order_amount'], // 应付金额
            'goodsFee' => $result['result']['goods_price'],// 商品价格
            'total_integral' => $result['result']['total_integral'], // 积分支付
            'order_prom_id' => $result['result']['order_prom_id'], // 订单优惠活动id
            'order_prom_amount' => $result['result']['order_prom_amount'], // 订单优惠活动优惠了多少钱
        );

        if (!$address_id) exit(json_encode(array('status' => -3, 'msg' => '请先填写收货人信息', 'result' => $car_price))); // 返回结果状态
        //if (!$shipping_code) exit(json_encode(array('status' => -4, 'msg' => '请选择物流信息', 'result' => $car_price))); // 返回结果状态

        if ($_REQUEST['act'] == 'submit_order') {


            // 提交订单

            // 必须关注才能下单
            // if (!session('subscribe')) {
            //     return new Json([
            //         'status' => -99,
            //         'msg'    => '',
            //         'result' => null,
            //     ]);
            // }

            //地区限制
            /*   $default_id = db('shipping_area')->where(['shipping_code' => $shipping_code, 'is_default' => 1])->value('shipping_area_id');
               $shipping_area_ids = db('goods')->where(['goods_id' => $goods_id])->value('shipping_area_ids');
               //存在表示有地区限制
               if ($shipping_area_ids) {
                   $goods_area_array = explode(',', $shipping_area_ids);
                   //如果没有选该物流的全国范围
                   if (!in_array($default_id, $goods_area_array)) {
                       //商品的配送地区
                       $shipping_area = db('shipping_area')->alias('sa')
                           ->join('area_region ar', 'sa.shipping_area_id = ar.shipping_area_id')
                           ->join('region r', 'ar.region_id = r.id')
                           ->whereIn('sa.shipping_area_id', $shipping_area_ids)
                           ->where(['sa.shipping_code' => $shipping_code])
                           ->field('ar.*,r.name')
                           ->select();

                       //用户选择的地址
                       $user_address = db('user_address')->where(['address_id' => $address_id])->field('province,city,district')->find();
                       $isOk = 0;
                       foreach ($shipping_area as $key => $val) {

                           if (in_array($val['region_id'], $user_address)) {
                               $isOk = 1;
                           }
                       }

                       if ($isOk != 1) exit(json_encode(array('status' => -9, 'msg' => '您的地址不在此商品的配送范围!', 'result' => null)));
                   }
               }*/

            $pay_name = '';
            if (!empty($pay_points) || !empty($user_money) || $g_type == 4) {//商品类型为积分商品
                if ($this->user['is_lock'] == 1) {
                    exit(json_encode(array('status' => -5, 'msg' => "账号异常已被锁定，不能使用余额支付！", 'result' => null))); // 用户被冻结不能使用余额支付
                }
                // 关闭验证支付密码
                 if (empty($this->user['paypwd'])) {
                     exit(json_encode(array('status'=>-6,'msg'=>'请先设置支付密码','result'=>null)));
                 }
                 if (empty($paypwd)) {
                     exit(json_encode(array('status'=>-7,'msg'=>'请输入支付密码','result'=>null)));
                 }
//                 if (encrypt($paypwd) !== $this->user['paypwd']) {
                 if (md5($paypwd) !== $this->user['paypwd']) { //此处加密和设置支付密码处统一
                     //963852---a45fdb1e4ac646c9e65a1769663e5704
                     exit(json_encode(array('status'=>-8,'msg'=>'支付密码错误'.$paypwd.'---'.$this->user['paypwd'],'result'=>null)));
                 }
                $pay_name = $user_money ? '余额支付' : '积分兑换';
            }
            if (empty($coupon_id) && !empty($couponCode)) {
                $coupon_id = M('CouponList')->where("code", $couponCode)->getField('id');
            }
            $orderLogic = new OrderLogic();
            $orderLogic->setAction($action);
            $orderLogic->setCartList($order_goods);
            if ($share_id) {
                $result = $orderLogic->addOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $coupon_id, $car_price, $user_note, $pay_name, $share_id, $g_type, $dealDou, $e_discount=''); // 添加订单
            } else {
                $result = $orderLogic->addOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $coupon_id, $car_price, $user_note, $pay_name, '', $g_type, $dealDou, $e_discount=''); // 添加订单
            }

            exit(json_encode($result));
        }
        $return_arr = array('status' => 1, 'msg' => '计算成功', 'result' => $car_price); // 返回结果状态
        exit(json_encode($return_arr));

    }

    /*
     * 订单支付页面
     */
    public function cart4()
    {
        $order_id = I('order_id/d');
        $order_where = ['user_id' => $this->user_id, 'order_id' => $order_id];
        $order = M('Order')->where($order_where)->find();

        if ($order['order_status'] == 3) {
            $this->error('该订单已取消', U("Mobile/Order/order_detail", array('id' => $order_id)));
        }
        if (empty($order) || empty($this->user_id)) {
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if ($order['pay_status'] == 1 && $order['order_type'] == 3) {
            $this->assign('order', $order);
            return $this->fetch('free');
        }elseif($order['pay_status'] == 1){
            $order_detail_url = U("Mobile/Order/order_detail", array('id' => $order_id));
            header("Location: $order_detail_url");
            exit;
        }


        $orderGoodsPromType = M('order_goods')->where(['order_id' => $order['order_id']])->getField('prom_type', true);
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = ['4,5'];//预售订单，虚拟订单不支持货到付款
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            //微信浏览器
            if (in_array($order['order_prom_type'], $no_cod_order_prom_type) || in_array(1, $orderGoodsPromType)) {
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            } else {
                $payment_where['code'] = array('in', array('weixin', Plugin::PAYMENT_CODE_MONEY_PAY, 'cod'));
            }

            $this->assign('payFunction', 'weChat');          //微信公众号支付
        } else {
            if (in_array($order['order_prom_type'], $no_cod_order_prom_type) || in_array(1, $orderGoodsPromType)) {
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq', 'cod');
            }
            $payment_where['scene'] = array('in', array('0', '1'));
            $this->assign('payFunction', 'weChatApp');       //app微信支付
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id' => $order['order_id']])->getField('prom_type', true);
        if ($order['order_prom_type'] == 4 || in_array(1, $orderGoodsPromType)) {
            $payment_where['code'] = array('neq', 'cod');
        }

        //调试支付方式选择
        Log::error('cart4-' . json_encode($payment_where));
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
            if (($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())) {
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();

        //公众号微信支付
        if(is_weixin()){
            $wxuser = M('wx_user')->find();
            $users = M('users')->where('user_id',$order['user_id'])->find();
            $order_goods = M('order_goods')->where('order_id',$order['order_id'])->find();

            $attach = json_encode(array('user_id'=>$users['user_id'],'order_id'=>$order['order_id']));
            # 统一下单
            $data = array();
            $data['openid'] = $users['openid'];
            $data['body'] =  $order_goods['goods_name'];# 设置商品或支付单简要描述
            $data['attach'] = $attach;# 该字段主要用于商户携带订单的自定义数据
            $rand = rand(100000,999909);
            $date = date('Ymd');
            $data['out_trade_no'] =$date.$rand.$order['order_id'];# 设置商户系统内部的订单号
            $data['total_fee'] = $order['order_amount']*100;
            $data['time_start'] = date("YmdHis");
            $data['time_expire'] = date("YmdHis", time() + 600);
            $data['goods_tag'] = $order_goods['goods_name'];# 设置商品标记，代金券或立减优惠功能的参数
            $data['notify_url'] = 'http://'.$_SERVER['SERVER_NAME']."/mobile/Notify/weChatNotify";# 设置接收微信支付异步通知回调地址
            $data['trade_type'] = "JSAPI";# 支付类型
            $data['appid'] = $wxuser['appid'];
            $data['mch_id'] = $wxuser['mch_id'];
            $data['nonce_str'] = getNonceStr();
            $data['sign'] = MakeSign($data,$wxuser['pay_key']);

//             dump($data);
            $xml = ToXml($data);
//             dump($xml);
            $url = "https://api.mch.weixin.qq.com/pay/unifiedorder"; # 统一下单 接口链接
            $res = postXmlCurl($xml,$url,false,30);
//             dump($res);
            $postArr = xmlArr($res); # 将xml转成数组
//             dump($postArr);
//             dump($postArr);die;
            if($postArr['result_code']=='SUCCESS'&&$postArr['return_code']=='SUCCESS'){
                # 调起微信支付参数
                $wxPay = GetJsApiParameters($postArr,$wxuser['pay_key']);
                $this->assign('wxPay',$wxPay);
            }else{
                print '<pre>';
                print_r($postArr);
                print '</pre>';
                exit();
            }
        }

        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('order', $order);
        $this->assign('bankCodeList', $bankCodeList);
        $this->assign('pay_date', date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    /**
     * 积分兑换成功页面
     */
    public function exchangeSuccess()
    {

        $order_id = I('order_id/d');
        $order_where = ['user_id' => $this->user_id, 'order_id' => $order_id];
        $order = M('Order')->where($order_where)->find();
        if ($order['order_status'] == 3) {
            $this->error('该订单已取消', U("Mobile/Order/order_detail", array('id' => $order_id)));
        }
        if (empty($order) || empty($this->user_id)) {
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
//        if ($order['pay_status'] == 1) {
//
//            $order_detail_url = U("Mobile/Order/order_detail", array('id' => $order_id));
//            header("Location: $order_detail_url");
//            exit;
//        }
        // 如果兑换成功直接到跳到成功页面
        $this->assign('order', $order);
        if($order['pay_status'] == 1)
            return $this->fetch('success');
        else
            return $this->fetch('error');

    }

    /*
    * 购买身份产品订单支付页面
    */
    public function cart5()
    {
        $order_id = input('order_id');
        $orderInfo = Db::name('order_vip')
            ->where('id',$order_id)
            ->find();
        if(empty($orderInfo)){
            $this->error("订单不存在",'mobile/user/buyVip');
        }

        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if ($orderInfo['pay_status'] == 1) {
            $order_detail_url = U("Mobile/Order/order_detail", array('id' => $order_id));
            header("Location: $order_detail_url");
            exit;
        }

        $payment_where['type'] = 'payment';
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            //微信浏览器
            $this->assign('payFunction', 'weChat');          //微信公众号支付
        } else {
            $payment_where['scene'] = array('in', array('0', '1'));
            $this->assign('payFunction', 'weChatApp');       //app微信支付
        }
        $payment_where['status'] = 1;


        //调试支付方式选择
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
            if (($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())) {
                unset($paymentList[$key]);
            }
        }

        $this->assign('paymentList', $paymentList);
        $this->assign('order', $orderInfo);
        return $this->fetch('cart5');

    }


    /**
     * 积分兑换订单支付页面
     */
    public function cart7()
    {

        $order_id = I('order_id/d');
        $order_where = ['user_id' => $this->user_id, 'order_id' => $order_id];
        $order = M('Order')->where($order_where)->find();
        if ($order['order_status'] == 3) {
            $this->error('该订单已取消', U("Mobile/Order/order_detail", array('id' => $order_id)));
        }
        if (empty($order) || empty($this->user_id)) {
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if ($order['pay_status'] == 1) {
            $order_detail_url = U("Mobile/Order/order_detail", array('id' => $order_id));
            header("Location: $order_detail_url");
            exit;
        }
        $orderGoodsPromType = M('order_goods')->where(['order_id' => $order['order_id']])->getField('prom_type', true);
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = ['4,5'];//预售订单，虚拟订单不支持货到付款
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            //微信浏览器
            if (in_array($order['order_prom_type'], $no_cod_order_prom_type) || in_array(1, $orderGoodsPromType)) {
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            } else {
                $payment_where['code'] = array('in', array('weixin', Plugin::PAYMENT_CODE_MONEY_PAY, 'cod'));
            }

            $this->assign('payFunction', 'weChat');          //微信公众号支付
        } else {
            if (in_array($order['order_prom_type'], $no_cod_order_prom_type) || in_array(1, $orderGoodsPromType)) {
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq', 'cod');
            }
            $payment_where['scene'] = array('in', array('0', '1'));
            $this->assign('payFunction', 'weChatApp');       //app微信支付
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id' => $order['order_id']])->getField('prom_type', true);
        if ($order['order_prom_type'] == 4 || in_array(1, $orderGoodsPromType)) {
            $payment_where['code'] = array('neq', 'cod');
        }

        //调试支付方式选择
        Log::error('cart4-' . json_encode($payment_where));
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
            if (($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())) {
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('order', $order);
        $this->assign('bankCodeList', $bankCodeList);
        $this->assign('pay_date', date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $share_id = I("share_id/d");
        $goods_num = I("goods_num/d");// 商品数量
        $item_id = I("item_id/d"); // 商品规格id

        if (empty($goods_id)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '请选择要购买的商品', 'result' => '']);
        }
        if (empty($goods_num)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '购买商品数量不能为0', 'result' => '']);
        }

        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setShare_id($share_id);
        $cartLogic->setGoodsModel($goods_id);
        if ($item_id) {
            $cartLogic->setSpecGoodsPriceModel($item_id);
        }
        $cartLogic->setGoodsBuyNum($goods_num);
        $result = $cartLogic->addGoodsToCart();
        exit(json_encode($result));
    }


    /**
     * ajax 检查是否领取过零元商品
     */
    function ajaxCheckFree()
    {
        $goods_id = I("goods_id"); // 商品id
//        $user_id = I("user_id");
        $goods_num = I("goods_num"); // 商品类型
        $g_type = I("g_type"); // 商品类型

        if (empty($this->user_id)) {
            $this->error('请先登录',U('Home/User/login'));
        }
        if (empty($goods_id)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '请选择要购买的商品', 'result' => '']);
        }
        if (empty($goods_num)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '购买商品数量不能为0', 'result' => '']);
        }

        if ($g_type == 3) {
            $order = M('order_goods')
                ->alias('og')
                ->join('order o','o.order_id = og.order_id')
                ->join('goods g','og.goods_id = g.goods_id')
                ->where("o.user_id", $this->user_id)
                ->where("g.g_type", 3)
                ->find();
            $order ? $result=['status' => -1, 'msg' => '已经领取过零元产品', 'result' => ''] : $result=['status' => 1, 'msg' => '可以领取零元产品', 'result' => ''];
        }else{
            $result= ['status' => 0, 'msg' => '商品不是零元产品', 'result' => ''];
        }
        exit(json_encode($result));
    }
    /**
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress()
    {
        $regionList = get_region_list();
        $address_list = M('UserAddress')->where("user_id", $this->user_id)->select();
        $c = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址
        if ((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;

        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }

    /**
     * 预售商品下单流程
     */
    public function pre_sell_cart()
    {
        $act_id = I('act_id/d');
        $goods_num = I('goods_num/d');
        $address_id = I('address_id/d');
        if (empty($act_id)) {
            $this->error('没有选择需要购买商品');
        }
        if (empty($goods_num)) {
            $this->error('购买商品数量不能为0', U('Home/Activity/pre_sell', array('act_id' => $act_id)));
        }
        if ($address_id) {
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id' => $this->user_id])->order(['is_default' => 'desc'])->find();
        }
        if (empty($address)) {
            header("Location: " . U('Mobile/User/add_address', array('source' => 'pre_sell_cart', 'act_id' => $act_id, 'goods_num' => $goods_num)));
            exit;
        } else {
            $this->assign('address', $address);
        }
        if ($this->user_id == 0) {
            $this->error('请先登录');
        }
        $pre_sell_info = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        if (empty($pre_sell_info)) {
            $this->error('商品不存在或已下架', U('Home/Activity/pre_sell_list'));
        }
        $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
        if ($pre_sell_info['act_count'] + $goods_num > $pre_sell_info['restrict_amount']) {
            $buy_num = $pre_sell_info['restrict_amount'] - $pre_sell_info['act_count'];
            $this->error('预售商品库存不足，还剩下' . $buy_num . '件', U('Home/Activity/pre_sell', array('id' => $act_id)));
        }
        $goodsActivityLogic = new GoodsActivityLogic();
        $pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']);//预售商品的订购数量和订单数量
        $pre_sell_price['cut_price'] = $goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品价格
        $pre_sell_price['goods_num'] = $goods_num;
        $pre_sell_price['deposit_price'] = floatval($pre_sell_info['deposit']);
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $invoice_title = I('invoice_title'); // 发票
            $shipping_code = I("shipping_code"); //  物流编号
            $address_id = I("address_id/d"); //  收货地址id
            if (empty($address_id)) {
                exit(json_encode(array('status' => -3, 'msg' => '请先填写收货人信息', 'result' => null))); // 返回结果状态
            }
            if (empty($shipping_code)) {
                exit(json_encode(array('status' => -4, 'msg' => '请选择物流信息', 'result' => null))); // 返回结果状态
            }
            $orderLogic = new OrderLogic();
            $result = $orderLogic->addPreSellOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $act_id, $pre_sell_price); // 添加订单
            exit(json_encode($result));
        }
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->select();// 物流公司
        $this->assign('pre_sell_info', $pre_sell_info);// 购物车的预售商品
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('pre_sell_price', $pre_sell_price);
        return $this->fetch();
    }

    /**
     * 兑换积分商品
     */
    public function buyIntegralGoods()
    {
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num');
        if (empty($this->user)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请登录']);
        }
        if (empty($goods_id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '非法操作']);
        }
        if (empty($goods_num)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '购买数不能为零']);
        }
        $goods = Goods::get($goods_id);
        if (empty($goods)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '该商品不存在']);
        }
        $Integral = new IntegralLogic();
        if (!empty($item_id)) {
            $specGoodsPrice = SpecGoodsPrice::get($item_id);
            $Integral->setSpecGoodsPrice($specGoodsPrice);
        }
        $Integral->setUser($this->user);
        $Integral->setGoods($goods);
        $Integral->setBuyNum($goods_num);
        $result = $Integral->buy();
        $this->ajaxReturn($result);
    }

    /**
     *  积分商品结算页
     * @return mixed
     */
    public function integral()
    {
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input('address_id/d');
        if (empty($this->user)) {
            $this->error('请登录');
        }
        if (empty($goods_id)) {
            $this->error('非法操作');
        }
        if (empty($goods_num)) {
            $this->error('购买数不能为零');
        }
        $Goods = new Goods();
        $goods = $Goods->where(['goods_id' => $goods_id])->find();
        if (empty($goods)) {
            $this->error('该商品不存在');
        }
        if (empty($item_id)) {
            $goods_spec_list = SpecGoodsPrice::all(['goods_id' => $goods_id]);
            if (count($goods_spec_list) > 0) {
                $this->error('请传递规格参数');
            }
            $goods_price = $goods['shop_price'];
            //没有规格
        } else {
            //有规格
            $specGoodsPrice = SpecGoodsPrice::get(['item_id' => $item_id, 'goods_id' => $goods_id]);
            if ($goods_num > $specGoodsPrice['store_count']) {
                $this->error('该商品规格库存不足，剩余' . $specGoodsPrice['store_count'] . '份');
            }
            $goods_price = $specGoodsPrice['price'];
            $this->assign('specGoodsPrice', $specGoodsPrice);
        }
        if ($address_id) {
            $address = Db::name('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id' => $this->user_id])->order(['is_default' => 'desc'])->find();
        }
        if (empty($address)) {
            header("Location: " . U('Mobile/User/add_address', array('source' => 'integral', 'goods_id' => $goods_id, 'goods_num' => $goods_num, 'item_id' => $item_id)));
            exit;
        } else {
            $this->assign('address', $address);
        }
        $shippingList = Db('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true, TPSHOP_CACHE_TIME)->select();// 物流公司
        $point_rate = tpCache('shopping.point_rate');
        $backUrl = Url::build('Goods/goodsInfo', ['id' => $goods_id, 'item_id' => $item_id]);
        $this->assign('backUrl', $backUrl);
        $this->assign('point_rate', $point_rate);
        $this->assign('goods', $goods);
        $this->assign('goods_price', $goods_price);
        $this->assign('goods_num', $goods_num);
        $this->assign('shippingList', $shippingList);
        return $this->fetch();
    }

    /**
     *  积分商品价格提交
     * @return mixed
     */
    public function integral2()
    {
        if ($this->user_id == 0) {
            $this->ajaxReturn(['status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null]);
        }
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input("address_id/d"); //  收货地址id
        $shipping_code = input("shipping_code/s"); //  物流编号
        $user_note = input('user_note'); // 给卖家留言
        $invoice_title = input('invoice_title'); // 发票
        $user_money = input("user_money/f", 0); //  使用余额
        $pwd = input('pwd');
        $user_money = $user_money ? $user_money : 0;
        if (empty($address_id)) {
            $this->ajaxReturn(['status' => -3, 'msg' => '请先填写收货人信息', 'result' => null]);
        }
        if (empty($shipping_code)) {
            $this->ajaxReturn(['status' => -4, 'msg' => '请选择物流信息', 'result' => null]);
        }
        $address = Db::name('user_address')->where("address_id", $address_id)->find();
        if (empty($address)) {
            $this->ajaxReturn(['status' => -3, 'msg' => '请先填写收货人信息', 'result' => null]);
        }
        $Goods = new Goods();
        $goods = $Goods::get($goods_id);
        $Integral = new IntegralLogic();
        $Integral->setUser($this->user);
        $Integral->setGoods($goods);
        if ($item_id) {
            $specGoodsPrice = SpecGoodsPrice::get($item_id);
            $Integral->setSpecGoodsPrice($specGoodsPrice);
        }
        $Integral->setAddress($address);
        $Integral->setShippingCode($shipping_code);
        $Integral->setBuyNum($goods_num);
        $Integral->setUserMoney($user_money);
        $result = $Integral->order();
        if ($result['status'] != 1) {
            $this->ajaxReturn($result);
        }
        $car_price = array(
            'postFee' => $result['result']['shipping_price'], // 物流费
            'balance' => $result['result']['user_money'], // 使用用户余额
            'payables' => number_format($result['result']['order_amount'], 2, '.', ''), // 订单总额 减去 积分 减去余额 减去优惠券 优惠活动
            'pointsFee' => $result['result']['integral_money'], // 积分抵扣的金额
            'points' => $result['result']['total_integral'], // 积分支付
            'goodsFee' => $result['result']['goods_price'],// 总商品价格
            'goods_shipping' => $result['result']['goods_shipping']
        );
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            // 排队人数
            $queue = \think\Cache::get('queue');
            if ($queue >= 100) {
                $this->ajaxReturn(['status' => -99, 'msg' => "当前人数过多请耐心排队!" . $queue, 'result' => null]);
            } else {
                \think\Cache::inc('queue', 1);
            }
            //购买设置必须使用积分购买，而用户的积分足以支付
            if ($this->user['pay_points'] >= $car_price['points'] || $user_money > 0) {
                if ($this->user['is_lock'] == 1) {
                    $this->ajaxReturn(['status' => -5, 'msg' => "账号异常已被锁定，不能使用积分或余额支付！", 'result' => null]);// 用户被冻结不能使用余额支付
                }
                $payPwd = trim($pwd);
                if (encrypt($payPwd) != $this->user['paypwd']) {
                    $this->ajaxReturn(['status' => -5, 'msg' => "支付密码错误！", 'result' => null]);
                }
            }
            $result = $Integral->addOrder($invoice_title, $user_note); // 添加订单
            // 这个人处理完了再减少
            \think\Cache::dec('queue');
            $this->ajaxReturn($result);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $car_price]);
    }

    /**
     *  获取发票信息
     * @date2017/10/19 14:45
     */
    public function invoice()
    {

        $map = [];
        $map['user_id'] = $this->user_id;

        $field = [
            'invoice_title',
            'taxpayer',
            'invoice_desc',
        ];

        $info = M('user_extend')->field($field)->where($map)->find();
        if (empty($info)) {
            $result = ['status' => -1, 'msg' => 'N', 'result' => ''];
        } else {
            $result = ['status' => 1, 'msg' => 'Y', 'result' => $info];
        }
        $this->ajaxReturn($result);
    }

    /**
     *  保存发票信息
     * @date2017/10/19 14:45
     */
    public function save_invoice()
    {

        if (IS_AJAX) {

            //A.1获取发票信息
            $invoice_title = trim(I("invoice_title"));
            $taxpayer = trim(I("taxpayer"));
            $invoice_desc = trim(I("invoice_desc"));

            //B.1校验用户是否有历史发票记录
            $map = [];
            $map['user_id'] = $this->user_id;
            $info = M('user_extend')->where($map)->find();

            //B.2发票信息
            $data = [];
            $data['invoice_title'] = $invoice_title;
            $data['taxpayer'] = $taxpayer;
            $data['invoice_desc'] = $invoice_desc;

            //B.3发票抬头
            if ($invoice_title == "个人") {
                $data['invoice_title'] = "个人";
                $data['taxpayer'] = "";
            }


            //是否存贮过发票信息
            if (empty($info)) {
                $data['user_id'] = $this->user_id;
                (M('user_extend')->add($data)) ?
                    $status = 1 : $status = -1;
            } else {
                (M('user_extend')->where($map)->save($data)) ?
                    $status = 1 : $status = -1;
            }
            $result = ['status' => $status, 'msg' => '', 'result' => ''];
            $this->ajaxReturn($result);

        }
    }

    //获取选中的商品个数并判断不同类型仓库商品不能一起提交订单
    //from:订单购物车和立即购买都会来这接口判断
    public function isCanGetOrder()
    {
        $input = input('');
        $from = 'card'; //定义来源，默认是购物车

        $rate_goods = array();    //定义商品所属仓的仓库，如果仓库内有2种，则不能一起提交（香港仓和保税仓商品需要分开提交）
        $returnData = array();
        $returnData['status'] = 1;
        $returnData['rate_type'] = 0;           //所属仓的类型，0普通商品，1香港仓，2保税仓
        $returnData['have_free_hkorder'] = 0;        //判断当天是否有香港仓小于333的订单。1有，0没有。默认为0

        if (isset($input['from']) && $input['from'] == 'goodsInfo') {
            //立即购买过来的
            $goodsID = $input['goods_id'];
            $goodsInfo = M('goods')->where('goods_id', $goodsID)->find();
            if ($goodsInfo['hk_bs_good'] == '1') {
                array_push($rate_goods, 1);
                $returnData['rate_type'] = 1; //修改为香港仓
            }
            if ($goodsInfo['hk_bs_good'] == '2') {
                array_push($rate_goods, 2);
                $returnData['rate_type'] = 2; //修改为保税仓
            }
        } else {
            //购物车过来的
            $cartList = M('cart')->where(array('user_id' => $this->user_id, 'selected' => 1))->select();  // 获取购物车商品

            if ($cartList) {
                foreach ($cartList as $k => $v) {
                    $goodsInfo = M('goods')->where('goods_id', $v['goods_id'])->find();
                    if ($goodsInfo['hk_bs_good'] == '1') {
                        array_push($rate_goods, 1);
                        $returnData['rate_type'] = 1; //修改为香港仓
                    }
                    if ($goodsInfo['hk_bs_good'] == '2') {
                        array_push($rate_goods, 2);
                        $returnData['rate_type'] = 2; //修改为保税仓
                    }
                }
            } else {
                $returnData['status'] = 0;
                $returnData['msg'] = '请选择要结算的商品';
            }
        }

        if ($rate_goods) {
            if (count(array_unique($rate_goods)) > '1') {
                $returnData['status'] = 0;
                $returnData['msg'] = '不同所属仓的商品请分开提交';
            }
        }

        //判断是否已经有订单金额小于等于333的香港仓商品
        $isCan = $this->isCanFree();
        if ($isCan) {
            $returnData['have_free_hkorder'] = 1;
        }

        /*    $hk_free_money_config = M('n_goods_config')->where('key', 'hk_free_money')->find();
            $hk_free_money = $hk_free_money_config['value'];    //设置的每天免税额度

            $dateNow = date('Y-m-d', time());
            $startTime = $dateNow . ' 00:00:00';
            $endTime = $dateNow . ' 23:59:59';
            $hk_free_order = M('order')->where(array('user_id' => $this->user_id, 'order_type' => 1))
                ->where('add_time', '>', strtotime($startTime))
                ->where('add_time', '<', strtotime($endTime))
                ->where('order_status', '<>', '3')
                ->where('order_amount', '<=', $hk_free_money)
                ->find();
            if ($hk_free_order && $hk_free_order['hk_username'] != null && $hk_free_order['hk_username'] != null) {
                $returnData['have_free_hkorder'] = 1;   //判断当天是否有香港仓小于333的订单。1有，0没有。默认为0.有香港仓免税订单，不能再享受免税
            }*/

        return $returnData;
    }

    /*
     * 判断是否可以使用免税额度,$isCan=1有订单了，不能使用；$isCan=0没有订单，可以使用免税
     *
     * */
    public function isCanFree()
    {
        $isCan = 0;       //判断当天是否有香港仓小于333的订单。1有，0没有。默认为0

        //判断是否已经有订单金额小于等于333的香港仓商品
        $hk_free_money_config = M('n_goods_config')->where('key', 'hk_free_money')->find();
        $hk_free_money = $hk_free_money_config['value'];    //设置的每天免税额度

        $dateNow = date('Y-m-d', time());
        $startTime = $dateNow . ' 00:00:00';
        $endTime = $dateNow . ' 23:59:59';
        $hk_free_order = M('order')
            ->where('order_type', '=', 1)
            ->where('user_id', '=', $this->user_id)
            ->where('add_time', '>', strtotime($startTime))
            ->where('add_time', '<', strtotime($endTime))
            ->where('order_status', '<>', '3')
            ->where('order_amount', '<=', $hk_free_money)
            ->find();

        if ($hk_free_order) {
            $isCan = 1;   //判断当天是否有香港仓小于333的订单。1有，0没有。默认为0.有香港仓免税订单，不能再享受免税
        }

        return $isCan;
    }


    //计算订单税费（保税仓是已含税，香港仓是进口税）
    public function getShuiMoney($rate_type, $totalMoney)
    {
        $money = 0; //定义税费的金额
        if ($rate_type == 1) {
            //香港仓，是计算进口税
            $goods_config = M('n_goods_config')->where('key', 'hk_rate')->find();
            $rate = $goods_config['value']; //香港仓税率
            //进口税=标价×税率
            $money = $totalMoney * $rate / 100;
        } elseif ($rate_type == 2) {
            //保税仓，是计算已含税
            $goods_config = M('n_goods_config')->where('key', 'mainland_rate')->find();
            $rate = $goods_config['value']; //保税仓税率
            if ($rate > '0') {
                //进口税=标价×税率百分比/（1+税率百分比）
                $money = $totalMoney * $rate / 100 / (1 + $rate / 100);
            }
        }

        return round($money, 2);
    }

    /*
     * 获取每天免税额度
     *
     * */
    public function getFree()
    {
        $goodsConfig = M('n_goods_config')->where('key', 'hk_free_money')->find();
        return $goodsConfig;
    }

    /*
     * 获取用户马克币
     *
     * */
    public function getUserMakebi()
    {
        $user = M('users')->where('user_id', $this->user_id)->find();

        return $user['makebi'];
    }

    /*
     *
     * 根据地址id获取运费
     * Cart/getUserAddressMoney
     *
     * address_id 传递用户地址id
     * order_money 传递该订单金额（不包含运费,其实就是商品金额）
     *
     * */
    public function getUserAddressMoney()
    {
        $input = input();
        $returnData = arraY();
        $returnData['userInfo'] = M('users')->where('user_id', $this->user_id)->field('user_id,real_name,id_card')->find();

        $addressMoney = 0;
        if (!isset($input['address_id'])) {
            $returnData['status'] = 0;
            $returnData['msg'] = '缺少地址id';
            $returnData['addressMoney'] = $addressMoney;
            return $returnData;
        }

        if (!isset($input['order_money'])) {
            $returnData['status'] = 0;
            $returnData['msg'] = '缺少订单金额';
            $returnData['addressMoney'] = $addressMoney;
            return $returnData;
        }

        if (!isset($input['rate_type'])) {
            $returnData['status'] = 0;
            $returnData['msg'] = '缺少商品所属仓信息';
            $returnData['addressMoney'] = $addressMoney;
            return $returnData;
        }

        $addressID = $input['address_id'];
        $rate_type = $input['rate_type'];
        $order_money = $input['order_money'];


        $address = M('user_address')->where('address_id', $addressID)->find();
        $regionInfo = M('region')->where('id', $address['province'])->find();
        if ($rate_type == '1') {
            //香港仓

            //判断订单金额是否满足包邮设定
            $hk_freight_set = M('n_goods_config')->where('key', 'hk_freight')->find();

            if ($order_money < $hk_freight_set['value']) {  //订单金额小于香港仓包邮设定，需要计算运费
                $addressMoney = $regionInfo['freight'];
            }
        }
        if ($rate_type == '2') {
            //保税仓

            //判断订单金额是否满足包邮设定
            $b_freight_set = M('n_goods_config')->where('key', 'b_freight')->find();

            if ($order_money < $b_freight_set['value']) {  //订单金额小于保税仓包邮设定，需要计算运费
                $addressMoney = $regionInfo['b_freight'];
            }
        }

        $returnData['status'] = 1;
        $returnData['msg'] = '请求成功';
        $returnData['addressMoney'] = $addressMoney;
        //exit(json_encode($returnData)); // 返回结果状态
        return $returnData;
    }

    /*
     * 判断用户该订单可使用多少马克币，能抵现多少现金
     *
     * $orderMoney  订单实际付款
     * */
    public function canUseMakebi($orderMoney)
    {
        //返回用户该订单最多可使用多少马克币，最大可抵消多少元
        $returnData = array();
        $returnData['canOrderMakebi'] = 0;      //该订单可用的马克币
        $returnData['canExchangeMoney'] = 0;    //该订单可抵消金额
        $returnData['canUserMakebi'] = 0;       //该用户当前剩余所有的马克币


        //每笔订单马克币最高抵现金额的百分比：
        $goodsConfig_makebi_order_offset = M('n_goods_config')->where('key', 'makebi_order_offset')->find();

        //1现金等于（）马克币
        $goodsConfig_makebi_rmb = M('n_goods_config')->where('key', 'makebi_rmb')->find();

        $user = M('users')->where('user_id', $this->user_id)->find();
        if ($user['makebi'] > '0') {
            $returnData['canUserMakebi'] = $user['makebi']; //返回用户当前剩余的所有马克币

            //判断该订单可以抵消多少元
            $canExchangeMoney = floor($orderMoney * $goodsConfig_makebi_order_offset['value'] / 100); //向下取整，订单最大可用马克币抵现金额
            $needMakebi = $canExchangeMoney * $goodsConfig_makebi_rmb['value'];   //需要用到的马克币=可抵用金额 * 兑换比率
            if ($needMakebi <= $user['makebi']) {
                //当需要用的马克币小于等于用户剩余马克币
                $returnData['canOrderMakebi'] = $needMakebi;      //该订单可用的马克币
                $returnData['canExchangeMoney'] = $canExchangeMoney;    //该订单可抵消金额
            } else {
                //当用户马克币不足时，则求最大能抵消多少，能用多少马克币
                $returnData['canOrderMakebi'] = $user['makebi'];      //该订单可用的马克币,其实就是用户所有的马克币了
                $returnData['canExchangeMoney'] = floor($user['makebi'] / $goodsConfig_makebi_rmb['value']); //向下取整，求出用户的马克币本来可抵现金额
            }
        }

        return $returnData;
    }

    /*
     *
     * 处理购物车商品按供应商列表排列（普通商品）
     *
     * */
    public function dealCartProvider($cartList)
    {
        $ptGoods = array();   //定义普通商品的仓库，根据不同供应商排列
        foreach ($cartList as $k => $v) {
            $goodsInfo = M('goods')->where('goods_id', $v['goods_id'])->find();
            if ($goodsInfo['g_type'] == '0' && $goodsInfo['provider_id']) {
                $provider = M('n_provider')->where('id', $goodsInfo['provider_id'])->find();
                if ($provider) {
                    //是普通商品并且有供应商
                    if (!in_array($provider, $ptGoods)) {
                        array_push($ptGoods, $provider);
                    };
                }
            }
        }

        foreach ($ptGoods as $k => $v) {
            $ptGoods[$k]['goodsList'] = array();
            foreach ($cartList as $ka => $va) {
                $goodsInfo = M('goods')->where('goods_id', $va['goods_id'])->find();
                if ($goodsInfo['provider_id'] == $v['id']) {
                    array_push($ptGoods[$k]['goodsList'], $va);
                }
            }
        }

        return $ptGoods;
    }

    /*
     * 处理普通商品，根据所有商品的总价和用户的金豆/云豆情况，计算出三钟情况，分别使用的金豆、云豆数量
     *
     * $orderMoney  订单金额；
     * $pay_group   选择的支付方式：1在线支付，2金豆+在线支付；3云豆+在线支付
     * */
    public function dealDouMoney($orderMoney = 0, $pay_group = 0)
    {
        $input = input();
        if ($input['$orderMoney']) {
            $orderMoney = $input['$orderMoney'];
        }

        if ($input['pay_group']) {
            $pay_group = $input['pay_group'];
        }

        $returnData = array();
        $returnData['status'] = '200';  //状态码200为正确；
        $returnData['msg'] = '';
        $returnData['pay_group'] = $pay_group;
        $returnData['rmb'] = 0;       //在线支付要付出的人民币金额
        $returnData['jindou_num'] = 0;//需要使用的金豆数量
        $returnData['yundou_num'] = 0;//需要使用的云豆数量
        $returnData['jindou_money'] = 0;//需要使用的金豆数量可以抵消的金额
        $returnData['yundou_money'] = 0;//需要使用的云豆数量可以抵消的金额


        if ($orderMoney <= '0') {
            $returnData['status'] = '-1';
            $returnData['msg'] = '订单金额有误';
            return $returnData;
        }

        if ($pay_group > '3' || $pay_group <= '0') {
            $returnData['status'] = '-1';
            $returnData['msg'] = '付款方式有误';
            return $returnData;
        }


        //1现金等于（）金豆
        $goodsConfig_jindou_rmb = M('n_goods_config')->where('key', 'jindou_rmb')->find();

        //1现金等于（）云豆
        $goodsConfig_yundou_rmb = M('n_goods_config')->where('key', 'yundou_rmb')->find();

        $userInfo = M('users')->where('user_id', $this->user_id)->field('user_id,jindou,yundou')->find();

        if ($pay_group == '1') {
            //在线支付
            $returnData['rmb'] = $orderMoney;       //在线支付要付出的人民币金额
            $returnData['jindou_num'] = 0;//需要使用的金豆数量
            $returnData['yundou_num'] = 0;//需要使用的云豆数量
            $returnData['jindou_money'] = 0;//需要使用的金豆数量可以抵消的金额
            $returnData['yundou_money'] = 0;//需要使用的云豆数量可以抵消的金额
        } elseif ($pay_group == '2') {
            //金豆+在线支付

            //判断用户所拥有的金豆可以抵消多少元
            $canExchangeMoney = floor($userInfo['jindou'] / $goodsConfig_jindou_rmb['value']); //向下取整，订单最大可用金豆抵现金额

            if ($canExchangeMoney <= $orderMoney) {
                //当抵消的金豆小于等于订单价格
                $returnData['rmb'] = $orderMoney - $canExchangeMoney;      //该订单仍然需要在线支付的钱
                $returnData['jindou_num'] = $canExchangeMoney * $goodsConfig_jindou_rmb['value'];//需要使用的金豆数量
                $returnData['yundou_num'] = 0;//需要使用的云豆数量
                $returnData['jindou_money'] = $canExchangeMoney;//需要使用的金豆数量可以抵消的金额
                $returnData['yundou_money'] = 0;//需要使用的云豆数量可以抵消的金额
            } else {
                //当抵消的金豆大于订单价格，则该笔订单不用在线支付了
                $returnData['rmb'] = 0;      //该订单不需要在线支付的钱
                $returnData['jindou_num'] = $orderMoney * $goodsConfig_jindou_rmb['value'];//需要使用的金豆数量
                $returnData['yundou_num'] = 0;//需要使用的云豆数量
                $returnData['jindou_money'] = $orderMoney;//需要使用的金豆数量可以抵消的金额
                $returnData['yundou_money'] = 0;//需要使用的云豆数量可以抵消的金额
            }
        } elseif ($pay_group == '3') {
            //云豆+在线支付

            //判断用户所拥有的云豆可以抵消多少元
            $canExchangeMoney = floor($userInfo['yundou'] / $goodsConfig_yundou_rmb['value']); //向下取整，订单最大可用云豆抵现金额
            if ($canExchangeMoney <= $orderMoney) {
                //当抵消的云豆小于等于订单价格
                $returnData['rmb'] = $orderMoney - $canExchangeMoney;      //该订单仍然需要在线支付的钱
                $returnData['jindou_num'] = 0;//需要使用的金豆数量
                $returnData['yundou_num'] = $canExchangeMoney * $goodsConfig_yundou_rmb['value'];//需要使用的云豆数量
                $returnData['jindou_money'] = 0;//需要使用的金豆数量可以抵消的金额
                $returnData['yundou_money'] = $canExchangeMoney;//需要使用的云豆数量可以抵消的金额
            } else {
                //当抵消的金豆大于订单价格，则该笔订单不用在线支付了
                $returnData['rmb'] = 0;      //该订单不需要在线支付的钱
                $returnData['jindou_num'] = 0;//需要使用的金豆数量
                $returnData['yundou_num'] = $orderMoney * $goodsConfig_yundou_rmb['value'];//需要使用的云豆数量
                $returnData['jindou_money'] = 0;//需要使用的金豆数量可以抵消的金额
                $returnData['yundou_money'] = $orderMoney;//需要使用的云豆数量可以抵消的金额
            }
        }

        return $returnData;
    }

    /*
     * 申请兑换商品时，获取商品信息
     *
     * */
    public function AjaxExchange()
    {
        $input = input();

        $goods_id = I("goods_id/d"); // 商品id
        $share_id = I("share_id/d");
        $goods_num = I("goods_num/d");// 商品数量
        $item_id = I("item_id/d"); // 商品规格id

        if (empty($goods_id)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '请选择要兑换的商品', 'result' => '']);
        }
        if (empty($goods_num)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '兑换商品数量不能为0', 'result' => '']);
        }
        //判断用户该种兑换商品只能有一次的申请记录

        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setShare_id($share_id);
        $cartLogic->setGoodsModel($goods_id);
        if ($item_id) {
            $cartLogic->setSpecGoodsPriceModel($item_id);
        }

        $cartLogic->setGoodsBuyNum($goods_num);

        $result = $cartLogic->addGoodsExchange();
        exit(json_encode($result));
    }

    public function cart6()
    {
        return $this->fetch('cart6');
    }

    //黄丽建——未有支付配置，先虚拟支付
    public function testPay()
    {

//        $this->ajaxreturn('sss');
        $order_id = input('order_id');
        $orderInfo = M('order')
            ->where('order_id',$order_id)
            ->find();

        //更新订单状态
        $dataOrder['pay_status'] = 1;
        $dataOrder['pay_time'] = time();
        $upOrder = M('order')
            ->where('order_id',$order_id)
            ->update($dataOrder);

//        if(3<=5&&5<8){
//            $this->ajaxreturn(1);
//        }else{
//            $this->ajaxreturn(2);
//        }

        $model = new UsersUpLevel();
//        //付款分佣
        $c = $model->SubCommission($order_id);
        $this->ajaxreturn($c);
        //收货后操作

//        $c = $model-> receivingGoods(63);
//        $this->ajaxReturn($c);
//        $a  = UsersUpLevel::ceshi();
//        $this->ajaxreturn($a);

//        if($upOrder !== false){
//            //特定产品（身份产品）
//            if($orderInfo['order_type'] == 2){
//                UsersLevel::upUserLevel($orderInfo['user_id']);
//            }
//            //分佣
//            UsersLevel::userCommission($order_id);
//
//            $order_detail_url = U("Mobile/Order/order_detail", array('id' => $order_id));
//            header("Location: $order_detail_url");
////            exit;
////
////        }
    }


}
