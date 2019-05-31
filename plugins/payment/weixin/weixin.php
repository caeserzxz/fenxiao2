<?php

namespace payment\weixin;

require_once("lib/WxPay.Api.php");
require_once("example/WxPay.NativePay.php");
require_once("example/WxPay.JsApiPay.php");

use app\common\model\PaymentLog;
use app\common\model\Users;
use JsApiPay;
use NativePay;
use think\Exception;
use think\Log;
use WxPayApi;
use WxPayConfig;
use WxPayOrderQuery;
use WxPayUnifiedOrder;

/**
 * 微信支付
 * 用于payment_log
 *
 * @see ./weixin.class.php
 */
class weixin {

    public function __construct(array $config) {
        WxPayConfig::$appid = $config['appid'];// * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
        WxPayConfig::$mchid = $config['mchid'];// * MCHID：商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$smchid = isset($config['smchid']) ? $config['smchid'] : '';// * SMCHID：服务商商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$key = $config['key'];// KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）
        WxPayConfig::$appsecret = $config['appsecret'];// 公众帐号secert（仅JSAPI支付的时候需要配置)，
    }

    /**
     * 生成支付代码
     *
     * @param array $options
     * @return string
     * @throws Exception
     */
    function get_code($options = []) {

        // 单位转换为分
        $options['amount'] = ceil($options['amount'] * 100);

        $input = new WxPayUnifiedOrder();
        $input->SetTrade_type("NATIVE");// 交易类型   取值如下：JSAPI，NATIVE，APP，详细说明见参数规定    NATIVE--原生扫码支付
        $input->SetProduct_id(1);// 商品ID trade_type=NATIVE，此参数必传。此id为二维码中包含的商品ID，商户自行定义。

        $input->SetBody($options['body']);// 商品描述
        $input->SetOut_trade_no($options['trade_no']);// 商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
        $input->SetTotal_fee($options['amount']);// 订单总金额，单位为分，详见支付金额
        $input->SetNotify_url($options['notify_url']);// 接收微信支付异步通知回调地址，通知url必须为直接可访问的url，不能携带参数。
        $input->SetAttach($options['attach']);// 附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据

        $notify = new NativePay();
        $result = $notify->GetPayUrl($input);// 获取生成二维码的地址
        if (!$result['code_url']) {
            throw new Exception('没有获取到支付地址, 请检查支付配置: ' . json_encode($result, JSON_PRETTY_PRINT));
        }
        $src = U('home/Index/qr_code', ['data' => urlencode($result['code_url'])]);

        return '<img alt="模式二扫码支付" src="' . $src . '" style="width:110px;height:110px;"/>';
    }

    /**
     * @param array $options
     * @return string
     * @throws \WxPayException
     */
    function getJSAPI($options = []) {
        //Log::error('weixin:'.json_encode($options));
        $go_url = $options['go_url'];
        $back_url = $options['back_url'];

        // 单位转换为分
        $options['amount'] = ceil($options['amount'] * 100);

        $input = new WxPayUnifiedOrder();
        $input->SetTrade_type("JSAPI");

        $input->SetBody($options['body']);// 商品描述
        $input->SetOut_trade_no($options['trade_no']);// 商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
        $input->SetTotal_fee($options['amount']);// 订单总金额，单位为分，详见支付金额
        $input->SetNotify_url($options['notify_url']);// 接收微信支付异步通知回调地址，通知url必须为直接可访问的url，不能携带参数。
        $input->SetOpenid($options['open_id']);
        $input->SetAttach($options['attach']);// 附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
        // $input->SetGoods_tag();

        // $input->SetTime_start(date("YmdHis"));
        // $input->SetTime_expire(date("YmdHis", time() + 600));

        $order = WxPayApi::unifiedOrder($input);

        $tools = new JsApiPay();
        $jsApiParameters = $tools->GetJsApiParameters($order);

        $html = <<<EOF
<script type="text/javascript">
    //调用微信JS api 支付
    function jsApiCall() {
        WeixinJSBridge.invoke(
            'getBrandWCPayRequest',
            $jsApiParameters,
            function (res) {
                //WeixinJSBridge.log(res.err_msg);
                if (res.err_msg === "get_brand_wcpay_request:ok") {
                    location.assign('$go_url');
                } else {
                    console.log(res);
                    // alert(JSON.stringify(res));
                    alert('支付失败，请选择其他支付方式');
                    location.assign('$back_url');
                }
            }
        );
    }

    +function callPay() {
        if (typeof WeixinJSBridge === "undefined") {
            if (document.addEventListener) {
                document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
            } else if (document.attachEvent) {
                document.attachEvent('WeixinJSBridgeReady', jsApiCall);
                document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
            }
        } else {
            jsApiCall();
        }
    }();
</script>
EOF;

        return $html;
    }

    public static function response() {
        $notify = new PayNotifyCallBack();
        $notify->Handle(false);
    }

    /**
     * 页面跳转响应操作给支付接口方调用
     */
    public static function respond2() {
        // 微信扫码支付这里没有页面返回
    }

    /**
     * 获取支付结果
     *
     * @param array $options
     * @return bool
     */
    public function getPayResult(array $options) {
        $trade_no = $options['paymentLog']['trade_no'];
        if (!$trade_no) {
            return false;
        }

        // 查询交易结果
        $query = new WxPayOrderQuery();
        $query->SetOut_trade_no($options['trade_no']);

        $result = WxPayApi::orderQuery($query);

        return (bool) $result;
    }

    // 微信提现批量转账
    function transfer($data) {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     *
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    function array2xml($arr, $level = 1) {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);

        return $level == 1 ? $s . "</xml>" : $s;
    }

    function http_post($url, $param, $wxchat) {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== false) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
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
        if ($wxchat) {
            curl_setopt($oCurl, CURLOPT_SSLCERT, dirname(THINK_PATH) . $wxchat['api_cert']);
            curl_setopt($oCurl, CURLOPT_SSLKEY, dirname(THINK_PATH) . $wxchat['api_key']);
            curl_setopt($oCurl, CURLOPT_CAINFO, dirname(THINK_PATH) . $wxchat['api_ca']);
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
    public function payment_refund($data) {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }
}