<?php
namespace wechat\payment;
/**
* 	配置账号信息
*/
class WxPayConf_pub
{
	const WEB_HOST = '';
	//=======【基本信息设置】=====================================
	//微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
	const APPID = 'wxc262d8e4c444d013';
	//受理商ID，身份标识
	const MCHID = '1220090601';
	//商户支付密钥Key。审核通过后，在微信发送的邮件中查看 hulibangzhijia32 md5
	const KEY = '12282103021228210302122821030214';   //Maozhuxishuo20170230feichangOKok
	//JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
	const APPSECRET = '5d38a66680daf61aaeb8b2914fe88ac1';

	//=======【JSAPI路径设置】===================================
	//获取access_token过程中的跳转uri，通过跳转将code传入jsapi支付页面
	const JS_API_CALL_URL = "";

	//=======【证书路径设置】=====================================
	//证书路径,注意应该填写绝对路径
	const SSLCERT_PATH = 'D:\phpStudy\WWW\hotel\extend\wechat\payment\cacert\apiclient_cert.pem';
	const SSLKEY_PATH = 'D:\phpStudy\WWW\hotel\extend\wechat\payment\cacert\apiclient_key.pem';

	//=======【异步通知url设置】===================================
	//异步通知url，商户根据实际开发过程设定
	const NOTIFY_URL = '';
	const REFUND_NOTIFY_URL = '';//退款异步通知配置

	//=======【curl超时设置】===================================
	//本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
	const CURL_TIMEOUT = 30;
}

?>