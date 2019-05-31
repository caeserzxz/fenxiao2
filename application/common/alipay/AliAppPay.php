<?php

namespace app\common\alipay;

use app\common\service\Logic;
use think\Controller;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;

class AliAppPay
{
    function aliPay($table,$order_id,$type){
        //区分订单类型

        //获取订单信息
        $orderInfo = db($table)->where('order_id',$order_id)->find();

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

        $passback_params = array(
            "type" => $type,
            "order_id"=>$order_id
        );
        \think\Loader::import('alipay2.wappay.buildermodel.AlipayTradeWapPayContentBuilder',EXTEND_PATH);//导入支付宝支付类
        include_once  "plugins/payment/alipayMobile/wappay/buildermodel/AlipayTradeWapPayContentBuilder.php";
        $payRequestBuilder = new \AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        $payRequestBuilder->setPassbackParams($passback_params);


        $paymentPlugin = M('Plugin')->where("code='alipayMobile' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化,获取配置信息

        $notify_url =SITE_URL.'/index.php/Mobile/PayNotify/alipay_notify'; //异步通知地址（支付完成后支付宝回调的地址）

        $return_url =SITE_URL.'/index.php/Mobile/Order/order_detail?id='.$orderInfo['order_id'];//同步跳转(支付完成后跳转的页面)

        \think\Loader::import('alipay2.wappay.service.AlipayTradeService',EXTEND_PATH);//导入支付宝支付类
        $payResponse = new \AlipayTradeService($config_value);
        $result=$payResponse->wapPay($payRequestBuilder,$return_url,$notify_url);
        Log::error("result_".$result);
        return $result;
    }
}