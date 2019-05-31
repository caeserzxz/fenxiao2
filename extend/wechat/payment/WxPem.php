<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/22 0022
 * Time: 16:03
 */

namespace wechat\payment;

class WxPem extends WxPayClientPub
{
    function __construct()
    {
        //设置接口链接
        $this->url = "https://fraud.mch.weixin.qq.com/risk/getpublickey";
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
//            $this->parameters["mch_appid"] = WxPayConfPub::APPID;//公众账号ID
//            $this->parameters["mchid"] = WxPayConfPub::MCHID;//商户号
            $ConfigObj = new WxPayConfPub();
            $this->parameters["mch_id"] =  $ConfigObj->MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign_type"] = "MD5";
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            return  $this->arrayToXml($this->parameters);
        }catch (SDKRuntimeException $e)
        {
            die($e->errorMessage());
        }
    }


    function getPem()
    {
        $this->postXmlSSL();
        $this->result = $this->xmlToArray($this->response);
        return $this->result;
    }
}