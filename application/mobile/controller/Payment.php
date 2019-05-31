<?php

namespace app\mobile\controller;
use app\common\alipay\AliAppPay;
use app\common\alipay\AliWebPay;
use app\common\exception\PayFailedException;
use app\common\model\Plugin;
use My\DataReturn;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;


class Payment extends MobileBase {

    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code

    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];

        if(!empty($pay_radio))
        {
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        }


        else // 第三方 支付商返回
        {
            //$_GET = I('get.');
            //file_put_contents('./a.html',$_GET,FILE_APPEND);
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }

        if($this->pay_code == "alipayMobile"){
            $this->indexApp();
            exit;
        }

        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
		$xml = file_get_contents('php://input');
        if(empty($this->pay_code))
            $this->error('pay_code 不能为空，请用微信授权登录选择支付方式');
        // 导入具体的支付类文件
        // plugins\payment\alipay\alipayPayment.class.php
        include_once  "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php";
        $code = '\\'.$this->pay_code;
        if (!class_exists($code)) {
            $this->error('此支付方式已失效');
        }
        $this->payment = new $code();
    }

    /**
     *  提交支付方式
     */
    public function getCode(){
//        dump(input(''));die;
            //C('TOKEN_ON',false); // 关闭 TOKEN_ON
            header("Content-type:text/html;charset=utf-8");
            $order_id = I('order_id/d'); // 订单id
            if(!session('user')) $this->error('请先登录',U('User/login'));
            // 修改订单的支付方式
            $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
            M('order')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            $order = M('order')->where("order_id", $order_id)->find();
            if($order['pay_status'] == 1){
                // 已完成支付 跳转到订单详情
                $this->redirect(U('order/order_detail', ['id' => $order['order_id']]));
            }
            //订单支付提交
            $pay_radio = $_REQUEST['pay_radio'];
            $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
            $payBody = getPayBody($order_id);
            $config_value['body'] = $payBody;
        //微信JS支付  && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')
           if($this->pay_code == 'weixin' && $_SESSION['openid']){
               // 梅克伦公众号临时支付
               $order['order_amount'] = 0.02;

               $code_str = $this->payment->getJSAPI($order);
               //Log::error('payment'.json_encode($code_str));
               exit($code_str);
           }elseif($this->pay_code == 'miniAppPay'  && $_SESSION['openid']){
               $code_str = $this->payment->getJSAPI($order);
               exit($code_str);

           } else if ($this->pay_code === Plugin::PAYMENT_CODE_MONEY_PAY) {
               // 余额支付
               try {
                   $this->payment->pay($order);

               } catch (PayFailedException $e) {
                   Db::rollback();
                   $this->error($e->getMessage() ?: '支付失败，请更换支付方式');
               }

               $this->success('支付成功', U('order/order_detail', ['id' => $order['order_id']]));

           }else{
//               Log::error("this->paymen---".$this->paymen);
               //梅克伦APP支付临时
//               $order['order_amount'] = 0.02;

               $code_str = $this->payment->getJSAPI($order,0);
               if($code_str){
                   return $code_str;
               }


              /* Log::error('payment'.json_encode($code_str));
               exit($code_str);

           	    $code_str = $this->payment->get_code($order,$config_value);*/
           }

            $this->assign('code_str', $code_str);
            $this->assign('order_id', $order_id);
            return $this->fetch('payment');  // 分跳转 和不 跳转
    }

    /*
     * 购买身份支付提交方式
     * */

    public function getCodeVip(){
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); // 订单id
        if(!session('user')) $this->error('请先登录',U('User/login'));
        // 修改订单的支付方式
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        M('order_vip')->where("id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
        $order = M('order_vip')->where("id", $order_id)->find();
        if($order['pay_status'] == 1){
            // 已完成支付 跳转到订单详情
            $this->redirect(U('order/order_detail', ['id' => $order['order_id']]));
        }


        //订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $payBody = getPayBody($order_id);
        $config_value['body'] = $payBody;
        //微信JS支付  && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')
        if($this->pay_code == 'weixin' && $_SESSION['openid']){
            // 梅克伦公众号临时支付
//            $order['order_amount'] = 0.02;

            $code_str = $this->payment->getJSAPI($order);
            //Log::error('payment'.json_encode($code_str));
            exit($code_str);
        }elseif($this->pay_code == 'miniAppPay'  && $_SESSION['openid']){
            $code_str = $this->payment->getJSAPI($order);
            exit($code_str);

        } else if ($this->pay_code === Plugin::PAYMENT_CODE_MONEY_PAY) {
            // 余额支付
            try {
                $this->payment->pay($order);

            } catch (PayFailedException $e) {
                Db::rollback();
                $this->error($e->getMessage() ?: '支付失败，请更换支付方式');
            }

            $this->success('支付成功', U('order/order_detail', ['id' => $order['order_id']]));

        }else{
            Log::error("this->paymen---".$this->paymen);
            //梅克伦APP支付临时
//            $order['order_amount'] = 0.02;

            $code_str = $this->payment->getJSAPI($order,1);
            if($code_str){
                return $code_str;
            }


            /* Log::error('payment'.json_encode($code_str));
             exit($code_str);

                 $code_str = $this->payment->get_code($order,$config_value);*/
        }

        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        return $this->fetch('payment');  // 分跳转 和不 跳转
    }

    public function getPay(){
    	//手机端在线充值
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); //订单id
        $user = session('user');
        $data['account'] = I('account');
        if($order_id>0){
        	M('recharge')->where(array('order_id'=>$order_id,'user_id'=>$user['user_id']))->save($data);
        }else{
        	$data['user_id'] = $user['user_id'];
        	$data['nickname'] = $user['nickname'];
        	$data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
        	$data['ctime'] = time();
        	$order_id = M('recharge')->add($data);
        }
        if($order_id){
        	$order = M('recharge')->where("order_id", $order_id)->find();
        	if(is_array($order) && $order['pay_status']==0){
        		$order['order_amount'] = $order['account'];
        		$pay_radio = $_REQUEST['pay_radio'];
        		$config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        		$payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        		M('recharge')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
        		//微信JS支付
        		if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
        			$code_str = $this->payment->getJSAPI($order);
        			exit($code_str);
        		}else{
        			$code_str = $this->payment->get_code($order,$config_value);
        		}
        	}else{
        		$this->error('此充值订单，已完成支付!');
        	}
        }else{
        	$this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
    	return $this->fetch('recharge'); //分跳转 和不 跳转
    }

        public function notifyUrl(){
            try {
                $this->payment->response();

            } catch (Exception $e) {
                Log::error((string) $e);
            }
        }

        public function returnUrl(){
            $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';
            if(stripos($result['order_sn'],'recharge') !== false)
            {
            	$order = M('recharge')->where("order_sn", $result['order_sn'])->find();
            	$this->assign('order', $order);
            	if($result['status'] == 1)
            		return $this->fetch('recharge_success');
            	else
            		return $this->fetch('recharge_error');
            	exit();
            }
            $order = M('order')->where("order_sn", $result['order_sn'])->find();
            //预告所获得积分
            $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");


            $this->assign('order', $order);
            $this->assign('point',$points);
            if($result['status'] == 1)
                return $this->fetch('success');
            else
                return $this->fetch('error');
        }

    //支付宝，手机网站接入
    public function indexWeb()
    {
        $order_id  = input('order_id');
        $order = M('order')->where("order_id", $order_id)->find();
//        $order['order_amount'] = 0.02;//测试支付

        $passback_params = array();//附加数据，回调原样返回

//        $newPay = new AliWebPay();
//
//        $result = $newPay->aliPay($order,$passback_params);

        $newPay = new AliWebPay();

        $result = $newPay->aliPay("order",$order['order_id'],$passback_params);

        return $result;
    }


    //支付宝app接入
    public function indexApp(){
        $input = input();
        $order_id  = $input['order_id'];

        $type = $input['goods_type'];


        if($type == 1){
            $orderInfo = Db::name('order_vip')->where("id", $order_id)->find();
            $mark = "晋升vip";
        }else{
            $orderInfo = Db::name('order')->where("order_id", $order_id)->find();
            $mark = $orderInfo['mark'];
        }


        $paymentPlugin = M('Plugin')->where("code='alipayMobile' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $aliConfig = unserialize($paymentPlugin['config_value']); // 配置反序列化,获取配置信息

        $url='http://'.$_SERVER['SERVER_NAME'].'/index.php/mobile/AppPayment/alipay_notify';//回调地址
        $return_url = 'http://'.$_SERVER['SERVER_NAME'].'/index.php/mobile/User/index';//同步回调地址

        /*构造返回app支付参数*/
        //获取支付宝配置信息
        $biz_content = array();
        $biz_content['subject']="优购支付宝支付相关";//对一笔交易的具体描述信息。如果是多种商品，请将商品描述字符串累加传给body。
        $biz_content['body']="优购支付宝支付相关";//商品的标题/交易标题/订单标题/订单关键字等。
        $biz_content['out_trade_no']=$orderInfo['order_sn'];//商户网站唯一订单号
        $biz_content['total_amount']=$orderInfo['order_amount'];//订单总金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]
//        $biz_content['total_amount']=0.01;
        $biz_content['timeout_express']="5m";//该笔订单允许的最晚付款时间，逾期将关闭交易。
        $biz_content['product_code']="QUICK_MSECURITY_PAY";//销售产品码，商家和支付宝签约的产品码，为固定值QUICK_MSECURITY_PAY

        include_once  "plugins/payment/alipayMobile/aop/AopClient.php";

        $aop = new \AopClient;
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = $aliConfig['app_id'];
        $aop->rsaPrivateKey =$aliConfig['merchant_private_key'];
        $aop->alipayrsaPublicKey = $aliConfig['alipay_public_key'];
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";

        include_once  "plugins/payment/alipayMobile/aop/request/AlipayTradeAppPayRequest.php";

        $request = new \AlipayTradeAppPayRequest();
        $request->setNotifyUrl($url);
        $request->getReturnUrl($return_url);
        $bizParameters = [
            'subject'         => "YG优购",
            'body'            => $mark,
            'out_trade_no'    => $orderInfo['order_sn'],
            'total_amount'    => $orderInfo['order_amount'],// 单位元
//            'total_amount'    => 0.01,// 单位元
            'timeout_express' => '5m',// 单位转换成分钟
            'passback_params' => $type,//回传参数
            'product_code'    => 'QUICK_MSECURITY_PAY',
        ];
        $request->setBizContent(json_encode($bizParameters));
        $orderString = $aop->sdkExecute($request);

        if ($orderString) {
            DataReturn::returnJson(1, "", $orderString);
        } else {

            DataReturn::returnJson(0, "系统繁忙，请稍后再试...");

        }


    }



}
