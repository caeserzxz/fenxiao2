<?php

namespace app\common\alipay;

use think\Controller;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;

class AliWebPay
{
    function aliPay($orderInfo,$passback_params){

        //商户订单号，    商户网站订单系统中唯一订单号，必填
        $out_trade_no = $orderInfo['order_sn'];

        //订单名称，必填
        $subject = "AliPay_".strtotime("now").rand(1000,9999);

        //付款金额，必填
        $total_amount = $orderInfo['order_amount'];
//        $total_amount = 0.01;

        //商品描述，可空
        $body = $orderInfo['mark'];
        //超时时间
        $timeout_express="1m";

        include_once  "plugins/payment/alipayMobile/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php";
        $payRequestBuilder = new \AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        $payRequestBuilder->setPassbackParams($passback_params);


        require_once  "plugins/payment/alipayMobile/wappay/service/AlipayTradeService.php";
        $paymentPlugin = M('Plugin')->where("code='alipayMobile' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化,获取配置信息
//        dump($config_value);die;
        $payResponse = new \AlipayTradeService($config_value);

        $notify_url =SITE_URL.'/index.php/Mobile/PayNotify/alipay_notify'; //异步通知地址（支付完成后支付宝回调的地址）

        $return_url =SITE_URL.'/index.php/Mobile/Order/order_detail?id='.$orderInfo['order_id'];//同步跳转(支付完成后跳转的页面)

//        Log::error("notify_info".$notify_url);
//        Log::error("notify_info".$return_url);

        $result=$payResponse->wapPay($payRequestBuilder,$return_url,$notify_url);

        return $result;
    }
}