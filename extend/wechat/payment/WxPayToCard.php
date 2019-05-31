<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/23 0023
 * Time: 09:18
 */

namespace wechat\payment;

/**
 * 企业打款的银行卡
 * Class WxPayToCard
 * @package wechat\payment
 */
class WxPayToCard extends WxPayClientPub
{
    var $code;//code码，用以获取openid
    var $openid;//用户的openid

    function __construct()
    {
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank";
        //设置curl超时时间
        $this->curl_timeout = (new WxPayConfPub())->CURL_TIMEOUT;
    }

    /**
     * 生成接口参数xml
     */
    function createXml()
    {
        try
        {
            //检测必填参数
            if($this->parameters["partner_trade_no"] == null)
            {
                throw new SDKRuntimeException("缺少发企业付款接口必填参数partner_trade_no！"."<br>");
            }elseif($this->parameters["amount"] == null){
                throw new SDKRuntimeException("缺少发企业付款接口必填参数amount！"."<br>");
            }elseif($this->parameters["desc"] == null){
                throw new SDKRuntimeException("缺少发企业付款接口必填参数check_name！"."<br>");
            }elseif ($this->parameters["enc_bank_no"] == null) {
                throw new SDKRuntimeException("确实enc_bank_no，enc_bank_no为必填！"."<br>");
            }elseif ($this->parameters["enc_true_name"] == null) {
                throw new SDKRuntimeException("确实enc_true_name，enc_true_name为必填！"."<br>");
            }elseif ($this->parameters["bank_code"] == null) {
                throw new SDKRuntimeException("缺少发企业付款接口必填参数check_name！"."<br>");
            }
            $ConfigObj = new WxPayConfPub();
            $this->parameters["mch_id"] =  $ConfigObj->MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (SDKRuntimeException $e)
        {
            die($e->errorMessage());
        }
    }


    function sendPay()
    {
        $this->postXmlSSL();
        $this->result = $this->xmlToArray($this->response);
        return $this->result;
    }



    /**
     *     作用：生成可以获得code的url
     */
    function createOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = WxPayConf_pub::APPID;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->formatBizQueryParaMap($urlObj, false);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }



    /**
     *     作用：生成可以获得openid的url
     */
    function createOauthUrlForOpenid()
    {
        $ConfigObj = new WxPayConfPub();
        $urlObj["appid"] = $ConfigObj->APPID;
        $urlObj["secret"] = $ConfigObj->APPSECRET;
        $urlObj["code"] = $this->code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->formatBizQueryParaMap($urlObj, false);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *     作用：通过curl向微信提交code，以获取openid
     */
    function getOpenid()
    {
        $url = $this->createOauthUrlForOpenid();
        //初始化curl
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOP_TIMEOUT, $this->curl_timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //运行curl，结果以jason形式返回
        $res = curl_exec($ch);
        curl_close($ch);
        //取出openid
        $data = json_decode($res,true);
        $this->openid = $data['openid'];
        return $this->openid;
    }

    /**
     *     作用：设置code
     */
    function setCode($code_)
    {
        $this->code = $code_;
    }
}