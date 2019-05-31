<?php
/**
 * 支付配置
 * User: Administrator
 * Date: 2017/11/22 0022
 * Time: 16:11
 */

namespace wechat\payment;


use think\Cache;

class WxPayConfPub
{

    public $WEB_HOST = '';
    //=======【基本信息设置】=====================================
    //微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
    public $APPID ;
    //受理商ID，身份标识
    public $MCHID ;
    //商户支付密钥Key。审核通过后，在微信发送的邮件中查看 hulibangzhijia32 md5
    public $KEY ;   //Maozhuxishuo20170230feichangOKok
    //JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
    public $APPSECRET ;

    //=======【证书路径设置】=====================================
    //证书路径,注意应该填写绝对路径
    public $SSLCERT_PATH ;
    public $SSLKEY_PATH ;

//    const SSLCERT_PATH = './cacert/apiclient_cert.pem';
//    const SSLKEY_PATH = './cacert/apiclient_key.pem';
    //=======【异步通知url设置】===================================
    //异步通知url，商户根据实际开发过程设定
    const NOTIFY_URL = '';

    //=======【curl超时设置】===================================
    //本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
    public $CURL_TIMEOUT = 30;

    public  function __construct()
    {


        //缓存
        if (empty($wechart_app_id) ||empty($wechart_app_secret) ||empty($wechart_mch_id) ||empty($wechart_key) ){
            $config = db('wx_user')->find();
            $wechart_app_id = $config["appid"];
            $wechart_app_secret = $config["appsecret"];
            $wechart_mch_id = $config["mch_id"];
            $wechart_key = $config["pay_key"];
        }

        $this->APPID = $wechart_app_id;
        $this->MCHID = $wechart_mch_id;
        $this->KEY = $wechart_key;
        $this->APPSECRET = $wechart_app_secret;

        $this->SSLCERT_PATH = EXTEND_PATH.'/wechat/payment/cacert/apiclient_cert.pem';
        $this->SSLKEY_PATH = EXTEND_PATH.'/wechat/payment/cacert/apiclient_key.pem';

    }

}