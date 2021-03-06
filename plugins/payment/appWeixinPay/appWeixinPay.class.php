<?php

use think\Model;
use think\Exception;
use think\Log;

/**
 * 支付 逻辑定义
 * Class
 * @package mobile\Payment
 */

class appWeixinPay extends Model
{
    public $tableName = 'plugin'; // 插件表
    public $alipay_config = array();// 支付宝支付配置参数

    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();

        require_once("lib/WxPay.Api.php"); // 微信扫码支付demo 中的文件
        require_once("example/WxPay.NativePay.php");
        require_once("example/WxPay.JsApiPay.php");

        $paymentPlugin = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        WxPayConfig::$appid = $config_value['appid']; // * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
        WxPayConfig::$mchid = $config_value['mchid']; // * MCHID：商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$smchid = isset($config_value['smchid']) ? $config_value['smchid'] : ''; // * SMCHID：服务商商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$key = $config_value['key']; // KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）
        WxPayConfig::$appsecret = $config_value['appsecret']; // 公众帐号secert（仅JSAPI支付的时候需要配置)，
    }
    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $config_value    支付方式信息
     */
    function get_code($order, $config_value)
    {
        Log::error('appWeixinPay-get_code-$order-'.json_encode($order));
        Log::error('appWeixinPay-get_code-$config_value-'.json_encode($config_value));
            $notify_url = SITE_URL.'/index.php/mobile/Payment/notifyUrl/pay_code/weixin'; // 接收微信支付异步通知回调地址，通知url必须为直接可访问的url，不能携带参数。
            //$notify_url = C('site_url').U('mobile/Payment/notifyUrl',array('pay_code'=>'weixin')); // 接收微信支付异步通知回调地址，通知url必须为直接可访问的url，不能携带参数。
            //$notify_url = C('site_url')."/index.php?m=mobile&c=Payment&a=notifyUrl&pay_code=weixin";

            $body = $config_value['body'];
            !$body && $body = "TPshop商品" ;

            $input = new WxPayUnifiedOrder();
            $input->SetBody($body); // 商品描述
            $input->SetAttach("weixin"); // 附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
            $input->SetOut_trade_no($order['order_sn'].time()); // 商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
            $input->SetTotal_fee($order['order_amount']*100); // 订单总金额，单位为分，详见支付金额
            $input->SetNotify_url($notify_url); // 接收微信支付异步通知回调地址，通知url必须为直接可访问的url，不能携带参数。
            $input->SetTrade_type("NATIVE"); // 交易类型   取值如下：JSAPI，NATIVE，APP，详细说明见参数规定    NATIVE--原生扫码支付
            $input->SetProduct_id("123456789"); // 商品ID trade_type=NATIVE，此参数必传。此id为二维码中包含的商品ID，商户自行定义。
            $notify = new NativePay();
            $result = $notify->GetPayUrl($input); // 获取生成二维码的地址
            $url2 = $result["code_url"];
            if(empty($url2))
                return  '没有获取到支付地址, 请检查支付配置'.  print_r($result,true);
            return '<img alt="模式二扫码支付" src="/index.php?m=mobile&c=Index&a=qr_code&data='.urlencode($url2).'" style="width:110px;height:110px;"/>';
    }

    /**
     * 服务器点对点响应操作给支付接口方调用
     *
     * @throws Exception
     */
    function response()
    {
        Log::error('response-');
        require_once("example/notify.php");
        $notify = new PayNotifyCallBack();
        $notify->Handle(false);
        if ($notify->GetReturn_code() !== 'SUCCESS') {
        throw new Exception($notify->GetReturn_msg());
    }
    }

    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2()
    {
        // 微信扫码支付这里没有页面返回
    }


    //app支付
    function getJSAPI($order,$goodsType){
        if(stripos($order['order_sn'],'recharge') !== false){
            $go_url = U('Mobile/User/points',array('type'=>'recharge'));
            $back_url = U('Mobile/User/recharge',array('order_id'=>$order['order_id']));
        }else{
            $go_url = U('Mobile/Order/order_detail',array('id'=>$order['order_id']));
            $back_url = U('Mobile/Cart/cart4',array('order_id'=>$order['order_id']));
        }
        //①、获取用户openid
        $tools = new JsApiPay();
        //$openId = $tools->GetOpenid();
        //$openId = $_SESSION['openid'];

        $think=$_SESSION['think'];
        $user=$think['user'];
        $openId=$user['app_openid'];    //APP支付要获取的是app_openid

        $attach = array(
            'pay_type'=>'appWeixinPay',
            'goods_type'=>$goodsType        //是否身份产品：1，是；2否
        );
        $attach = json_encode($attach);
        Log::error($attach);
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody("支付订单：".$order['order_sn']);
        //$input->SetAttach("weixin");
        $input->SetAttach($attach);
        Log::error("attach");
        $input->SetOut_trade_no($order['order_sn'].time());
        $input->SetTotal_fee($order['order_amount']*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("tp_wx_pay");
        //$input->SetNotify_url(SITE_URL.'/index.php/mobile/Payment/notifyUrl/pay_code/appWeixinPay');
        $input->SetNotify_url(SITE_URL.'/index.php/mobile/AppPayment/notify');

        //$input->SetTrade_type("JSAPI");
        $input->SetTrade_type("APP");
        $input->SetOpenid($openId);

        $order2 = WxPayApi::unifiedOrder($input);

        //echo '<font color="#f00"><b>统一下单支付单信息</b></font><br/>';
        //printf_info($order);exit;
        $jsApiParameters = $tools->GetJsApiParameters($order2);
        //组装app支付的数据格式
        $appData=array();
        $appData['status']=200;
        $appData['message']='成功';
        $appData['data']=array(
            'prepay'=>$jsApiParameters,
            'orderId'=>(string)$order['id'],
        );

        $jsonAppData=json_encode($appData);
        //END组装app支付的数据格式

        $returnData=array();
        $returnData['js']=$jsonAppData; //报文
        //该链接是通过【统一下单API】中提交的参数notify_url,不能携带参数
        if($goodsType == 1){
            $returnData['url']=SITE_URL.'/index.php/mobile/User/index';
        }else{
            $returnData['url']=SITE_URL.'/index.php/mobile/order/order_list/type/wait_delivery';
        }
        return $returnData;

    }

    //原jsapi方式支付
    function getJSAPI2($order){
        Log::error('weixinclass'.json_encode($order));
    	if(stripos($order['order_sn'],'recharge') !== false){
    		$go_url = U('Mobile/User/points',array('type'=>'recharge'));
    		$back_url = U('Mobile/User/recharge',array('order_id'=>$order['order_id']));
    	}else{
    		$go_url = U('Mobile/Order/order_detail',array('id'=>$order['order_id']));
    		$back_url = U('Mobile/Cart/cart4',array('order_id'=>$order['order_id']));
    	}
        //①、获取用户openid
        $tools = new JsApiPay();
        //$openId = $tools->GetOpenid();
        $openId = $_SESSION['openid'];
        Log::error('weixinclass$openId-'.$openId);
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody("支付订单：".$order['order_sn']);
        $input->SetAttach("weixin");
        $input->SetOut_trade_no($order['order_sn'].time());
        $input->SetTotal_fee($order['order_amount']*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("tp_wx_pay");
        $input->SetNotify_url(SITE_URL.'/index.php/mobile/Payment/notifyUrl/pay_code/weixin');
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);

        Log::error('appWeixinPay-getJSAPI-'.json_encode($input));
        $order2 = WxPayApi::unifiedOrder($input);

        //echo '<font color="#f00"><b>统一下单支付单信息</b></font><br/>';
        //printf_info($order);exit;
        $jsApiParameters = $tools->GetJsApiParameters($order2);
        $html = <<<EOF
	<script type="text/javascript">
	//调用微信JS api 支付
	function jsApiCall()
	{
		WeixinJSBridge.invoke(
			'getBrandWCPayRequest',$jsApiParameters,
			function(res){
				//WeixinJSBridge.log(res.err_msg);
				 if(res.err_msg == "get_brand_wcpay_request:ok") {
				    location.href='$go_url';
				 }else{
				     alert('支付失败');
				     location.href='$back_url';
				 }
			}
		);
	}

	function callpay()
	{
		if (typeof WeixinJSBridge == "undefined"){
		    if( document.addEventListener ){
		        document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
		    }else if (document.attachEvent){
		        document.attachEvent('WeixinJSBridgeReady', jsApiCall);
		        document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
		    }
		}else{
		    jsApiCall();
		}
	}
	callpay();
	</script>
EOF;

    return $html;

    }
    // 微信提现批量转账
    function transfer($data){
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    function array2xml($arr, $level = 1) {
    	$s = $level == 1 ? "<xml>" : '';
    	foreach($arr as $tagname => $value) {
    		if (is_numeric($tagname)) {
    			$tagname = $value['TagName'];
    			unset($value['TagName']);
    		}
    		if(!is_array($value)) {
    			$s .= "<{$tagname}>".(!is_numeric($value) ? '<![CDATA[' : '').$value.(!is_numeric($value) ? ']]>' : '')."</{$tagname}>";
    		} else {
    			$s .= "<{$tagname}>" . $this->array2xml($value, $level + 1)."</{$tagname}>";
    		}
    	}
    	$s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
    	return $level == 1 ? $s."</xml>" : $s;
    }

    function http_post($url, $param, $wxchat) {
    	$oCurl = curl_init();
    	if (stripos($url, "https://") !== FALSE) {
    		curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
    		curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
    	}
    	if (is_string($param)) {
    		$strPOST = $param;
    	} else {
    		$aPOST = array();
    		foreach ($param as $key => $val) {
    			$aPOST[] = $key . "=" . urlencode($val);
    		}
    		$strPOST = join("&", $aPOST);
    	}
    	curl_setopt($oCurl, CURLOPT_URL, $url);
    	curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($oCurl, CURLOPT_POST, true);
    	curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
    	if($wxchat){
    		curl_setopt($oCurl,CURLOPT_SSLCERT,dirname(THINK_PATH).$wxchat['api_cert']);
    		curl_setopt($oCurl,CURLOPT_SSLKEY,dirname(THINK_PATH).$wxchat['api_key']);
    		curl_setopt($oCurl,CURLOPT_CAINFO,dirname(THINK_PATH).$wxchat['api_ca']);
    	}
    	$sContent = curl_exec($oCurl);
    	$aStatus = curl_getinfo($oCurl);
    	curl_close($oCurl);
    	if (intval($aStatus["http_code"]) == 200) {
    		return $sContent;
    	} else {
    		return false;
    	}
    }

     // 微信订单退款原路退回
    public function payment_refund($data){
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }

}