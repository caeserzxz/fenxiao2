<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/16
 * Time: 15:44
 */

namespace app\api\controller;


use app\common\model\UserAddress;
use My\DataReturn;
use app\common\logic\CartLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\OrderLogic;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\logic\IntegralLogic;
use think\Db;
use think\Model;
use think\Url;


class Cart extends Base
{
    //需要检查登录的页面
    public function __construct()
    {
        parent::__construct();
        //不需验证登录的方法
        $nologin = ['changeNum'];

        if(!in_array(ACTION_NAME,$nologin))
            $this->checkLogin();
    }

    public function index(){
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getApiCartList();//用户购物车
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数

        $this->assign('userCartGoodsTypeNum', $userCartGoodsTypeNum);
        $this->assign('lists', $cartList);//购物车列表

        DataReturn::returnBase64Json(200,'',$this->viewAssign());
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
        DataReturn::jsonResult($result,true);
    }

    /**
     *  购物车加减
     */
    public function changeNum(){
        $cart = input('cart/a',[]);
        if (empty($cart)) {
            DataReturn::jsonResult(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => ''],false);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'],$cart['goods_num'],$cart['selected']);
        DataReturn::jsonResult($result,false);
    }


    /**
     * 删除购物车商品
     */
    public function delete(){
        $cart_id = input('cart_ids/i','');
        $cart_ids[] = $cart_id;
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);

        if($result !== false){
            DataReturn::jsonResult(['status'=>1,'msg'=>'删除成功','result'=>$result],true);
        }else{
            DataReturn::jsonResult(['status'=>0,'msg'=>'删除失败','result'=>$result],true);
        }
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2(){
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 行为
        $address_id = I('address_id/d');
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = UserAddress::where(['user_id'=>$this->user_id])->order(['is_default'=>'desc'])->find();
        }
       /* if(empty($address)){
            $address = M('user_address')->where(['user_id'=>$this->user_id])->find();
        }
        if(empty($address)){
            DataReturn::returnBase64Json(302,'跳转到添加地址');
            //header("Location: ".U('Mobile/User/add_address',array('source'=>'cart2')));
           // exit;
        }else{
            $this->assign('address',$address);
        }*/
        //处理地址
        $do_address = [];
        if(!empty($address))
        {
            $address->address = $address->region_text .' '. $address->address ;
            $do_address = $address;
        }


        $this->assign('address',$do_address);

        $cartLogic = new CartLogic();
        $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if($action == 'buy_now'){
            if(empty($goods_id)){
                DataReturn::returnBase64Json(500,'请选择要购买的商品');
                //$this->error('请选择要购买的商品');
            }
            if(empty($goods_num)){
                DataReturn::returnBase64Json(500,'购买商品数量不能为0');
                //$this->error('购买商品数量不能为0');
            }
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                DataReturn::returnBase64Json(500,$result['msg']);
            }
            $cartList['cartList'][0] = $result['result']['buy_goods'];
            $cartGoodsTotalNum = $goods_num;
        }else{
            if ($cartLogic->getUserCartOrderCount() == 0){
                DataReturn::returnBase64Json(500,'你的购物车没有选中商品');
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
        }
        //dump( $cartList);

        $cartGoodsList = get_arr_column($cartList,'goods');
        $cartGoodsId = get_arr_column($cartGoodsList,'goods_id');
        $cartGoodsCatId = get_arr_column($cartGoodsList,'cat_id');
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();// 物流公司
        $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId);//用户可用的优惠券列表
        $cartList = array_merge($cartList,$cartPriceInfo);
        $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);

        //过滤购物车商品字段
        array_map(function($item){
            if(gettype($item) == 'array')
            {
                $_item = $item;
                $item =  Model('goods');
                $item->data($_item);
            }

            $get_filed = $item->visible(['goods_id','goods_name','goods_price','spec_key','spec_key_name','goods_num','goods'=>['original_img']]);
            $get_filed['goods']['original_img'] = api_img_url($get_filed['goods']['original_img']);
            return $get_filed;
        },$cartList['cartList']);

        //过滤优惠卷的字段
        foreach($userCartCouponList as $key=>$item)
        {
            $userCartCouponList[$key] = array_intersect_key($item,array_flip(['id','coupon']));
        }


        $this->assign('userCartCouponList', $userCartCouponList);  //优惠券，用able判断是否可用
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('lists', $cartList['cartList']); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo);//商品优惠总价
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('defalut_shipping',array_shift($shippingList));

        DataReturn::returnBase64Json(200,'',$this->viewAssign());
    }

    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){

        $address_id = I("address_id/d"); //  收货地址id
        $shipping_code =  I("shipping_code"); //  物流编号
        $invoice_title = I('invoice_title'); // 发票
        $coupon_id =  I("coupon_id/d"); //  优惠券id
        $pay_points =  I("pay_points/d",0); //  使用积分
        $user_money =  I("user_money/f",0); //  使用余额
        $user_note = trim(I('user_note'));   //买家留言
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 立即购买
        $paypwd =  I("paypwd",''); // 支付密码

        $user_money = $user_money ? $user_money : 0;
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        if($action == 'buy_now'){
            $cartLogic->setGoodsModel($goods_id);
            if($item_id){
                $cartLogic->setSpecGoodsPriceModel($item_id);
            }
            $cartLogic->setGoodsBuyNum($goods_num);
            $result = $cartLogic->buyNow();
            if($result['status'] != 1){
                DataReturn::jsonResult($result,true);
                //$this->ajaxReturn($result);
            }
            $order_goods[0] = $result['result']['buy_goods'];
        }else{
            $userCartList = $cartLogic->getCartList(1);
            if($userCartList){
                $order_goods = collection($userCartList)->toArray();
            }else{
                DataReturn::jsonResult(array('status'=>-2,'msg'=>'你的购物车没有选中商品','result'=>null),true); // 返回结果状态
            }
            foreach ($userCartList as $cartKey => $cartVal) {
                if($cartVal->goods_num > $cartVal->limit_num){
                    DataReturn::jsonResult(['status' => 0, 'msg' => $cartVal->goods_name.'购买数量不能大于'.$cartVal->limit_num, 'result' => ['limit_num'=>$cartVal->limit_num]],true);
                }
            }
        }
        $address = M('UserAddress')->where("address_id", $address_id)->find();
        $result = calculate_price($this->user_id,$order_goods,$shipping_code,0,$address['province'],$address['city'],$address['district'],$pay_points,$user_money,$coupon_id);

        if($result['status'] < 0)
            DataReturn::jsonResult($result);
        // 订单满额优惠活动
        $order_prom = get_order_promotion($result['result']['order_amount']);
        $result['result']['order_amount'] = $order_prom['order_amount'] ;
        $result['result']['order_prom_id'] = $order_prom['order_prom_id'] ;
        $result['result']['order_prom_amount'] = $order_prom['order_prom_amount'] ;

        $car_price = array(
            'postFee'      => $result['result']['shipping_price'], // 物流费
            'couponFee'    => $result['result']['coupon_price'], // 优惠券
            'balance'      => $result['result']['user_money'], // 使用用户余额
            'pointsFee'    => $result['result']['integral_money'], // 积分支付
            'payables'     => $result['result']['order_amount'], // 应付金额
            'goodsFee'     => $result['result']['goods_price'],// 商品价格
            'order_prom_id' => $result['result']['order_prom_id'], // 订单优惠活动id
            'order_prom_amount' => $result['result']['order_prom_amount'], // 订单优惠活动优惠了多少钱
        );

        if(!$address_id) DataReturn::jsonResult(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>$car_price),true); // 返回结果状态
        if(!$shipping_code) DataReturn::jsonResult(array('status'=>-4,'msg'=>'请选择物流信息','result'=>$car_price),true);  // 返回结果状态

        trace('act:'.$_REQUEST['act'].'=='.input('act'),'debug');
        // 提交订单
        if(input('act') == 'submit_order') {
            $pay_name = '';
            if (!empty($pay_points) || !empty($user_money)) {
                if ($this->user['is_lock'] == 1) {
                    DataReturn::jsonResult(array('status'=>-5,'msg'=>"账号异常已被锁定，不能使用余额支付！",'result'=>null),true); // 用户被冻结不能使用余额支付
                }
                if (empty($this->user['paypwd'])) {
                    exit(DataReturn::jsonResult(array('status'=>-6,'msg'=>'请先设置支付密码','result'=>null),true));
                }
                if (empty($paypwd)) {
                    exit(DataReturn::jsonResult(array('status'=>-7,'msg'=>'请输入支付密码','result'=>null),true));
                }
                if (encrypt($paypwd) !== $this->user['paypwd']) {
                    exit(DataReturn::jsonResult(array('status'=>-8,'msg'=>'支付密码错误','result'=>null),true));
                }
                $pay_name = $user_money ? '余额支付' : '积分兑换';
            }
            if(empty($coupon_id) && !empty($couponCode)){
                $coupon_id = M('CouponList')->where("code", $couponCode)->getField('id');
            }
            $orderLogic = new OrderLogic();
            $orderLogic->setAction($action);
            $orderLogic->setCartList($order_goods);
            $result = $orderLogic->addOrder($this->user_id,$address_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$user_note,$pay_name); // 添加订单
            exit(DataReturn::jsonResult($result,true));
        }
        $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price); // 返回结果状态
        DataReturn::jsonResult($return_arr,true);
       // exit(json_encode($return_arr));
    }

    /*
 * 订单支付页面
 */
    public function cart4(){

        $order_id = I('order_id/d');
        $order_where = ['user_id'=>$this->user_id,'order_id'=>$order_id];
        $order = M('Order')->where($order_where)->find();
        if($order['order_status'] == 3){
            //$this->error('该订单已取消',U("Mobile/Order/order_detail",array('id'=>$order_id)));
            DataReturn::returnBase64Json(500,'该订单已取消');
        }

        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){
            DataReturn::returnBase64Json(303,'该订单已经支付过了',array('id'=>$order_id));
           /* $order_detail_url = U("Mobile/Order/order_detail",array('id'=>$order_id));
            header("Location: $order_detail_url");
            exit;*/
        }
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = ['4,5'];//预售订单，虚拟订单不支持货到付款
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if(in_array($order['order_prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if(in_array($order['order_prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1'));
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        if($order['order_prom_type'] == 4 || in_array(1,$orderGoodsPromType)){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        //$paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
            if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
                unset($paymentList[$key]);
            }
        }

        //$bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        //$payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        //$this->assign('paymentList',$paymentList);
        //$this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        //$this->assign('bankCodeList',$bankCodeList);
        //$this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));

        DataReturn::returnBase64Json(200,'',$this->viewAssign());
        //return $this->fetch();
    }

    public function all_selected()
    {
        if(in_array(input('selected/i'),[1,2]))
        {
            $data['selected'] = input('selected') == 1 ? 1:0;
            $goods = M('cart')->where('user_id',$this->user_id)->update($data);
            DataReturn::returnBase64Json(200,'更新成功');
        }else
        {
            DataReturn::returnBase64Json(500,'系统出错');
        }
    }

    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        // dump($goods_id);die;
        $goods_num = I("goods_num/d");// 商品数量
        $item_id = I("item_id/d"); // 商品规格id
        if(empty($goods_id)){
            DataReturn::jsonResult(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>''],true);
        }
        if(empty($goods_num)){
            DataReturn::jsonResult(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>''],true);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setGoodsModel($goods_id);
        if($item_id){
            $cartLogic->setSpecGoodsPriceModel($item_id);
        }
        $cartLogic->setGoodsBuyNum($goods_num);
        $result = $cartLogic->addGoodsToCart();
        DataReturn::jsonResult($result,true);
    }

}