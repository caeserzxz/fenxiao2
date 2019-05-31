<?php
/**
 * Created by PhpStorm.
 * User: 猿分哥
 * Date: 2017/11/22 0022
 * Time: 16:16
 */

namespace wechat\payment;

/**
 * 申请退款接口
 * Class WxPayToUser
 * @package wechat\payment
 */
class WxPayToRefund extends  WxPayClientPub
{
    function __construct()
    {
        //设置接口链接
        $this->url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
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
            $ConfigObj = new WxPayConfPub();
            $this->parameters["appid"] = $ConfigObj->APPID;//公众账号ID
            $this->parameters["mch_id"] =  $ConfigObj->MCHID;//商户号
            $this->parameters["nonce_str"] = $this->createNoncestr();//随机字符串
            $this->parameters["sign"] = $this->getSign($this->parameters);//签名
            $this->parameters["notify_url"] = config('refund_notify_url');

            //检测必填参数
            if($this->parameters["transaction_id"] == null) {
                throw new SDKRuntimeException("缺少退款申请接口必填参数商户订单号transaction_id！"."<br>");
            }elseif ($this->parameters["out_refund_no"] == null){
                throw new SDKRuntimeException("缺少退款申请接口必填参数商户退款单号out_refund_no！"."<br>");
            }elseif ($this->parameters["total_fee"] == null){
                throw new SDKRuntimeException("缺少退款申请接口必填参数商户订单金额total_fee！"."<br>");
            }elseif ($this->parameters["refund_fee"] == null){
                throw new SDKRuntimeException("缺少退款申请接口必填参数商户退款金额refund_fee！"."<br>");
            }

            return  $this->arrayToXml($this->parameters);
        }catch (SDKRuntimeException $e)
        {
            die($e->errorMessage());
        }
    }

    /**
     * 发送微信退款请求
     * @return mixed
     */
    function sendRefund()
    {
        $this->postXmlSSL();
        $this->result = $this->xmlToArray($this->response);
        return $this->result;
    }

}