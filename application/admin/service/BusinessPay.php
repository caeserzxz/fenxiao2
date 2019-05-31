<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/22 0022
 * Time: 10:05
 */

namespace app\admin\service;


use wechat\payment\WxPayToCar;
use wechat\payment\WxPayToCard;
use wechat\payment\WxPayToUser;
use wechat\payment\WxPem;

class BusinessPay
{
    /**
     * 企业打款
     * @param $openid
     * @param $amount
     * @param $partner_trade_no
     * @return array
     */
    public function PayToUser($openid, $amount, $partner_trade_no ){
        //数据验证
        if (empty($amount) || !($amount >= 1) || !is_int($amount))
            return array("flag" => false, "msg" => "打款金额必须为大于1的整数");
        //提现
        $Pay_pub = new WxPayToUser(); //调用请求接口基类
        $Pay_pub->setParameter('partner_trade_no', $partner_trade_no); //商户订单号
        $Pay_pub->setParameter('openid', $openid); //我的
        //$Pay_pub->setParameter('openid', $withdrawFind['open_id']);   //用户openid
        $Pay_pub->setParameter('check_name', 'NO_CHECK'); //是否判断姓名
        //$Pay_pub->setParameter('re_user_name', ''); //真实姓名（选填）
        $Pay_pub->setParameter('amount', $amount); //金额
        $Pay_pub->setParameter('desc', "退押"); //描述
        $result = $Pay_pub->sendPay();
        $wxReturnData = serialize($result);
        if ($result['return_code'] != "SUCCESS" || $result['result_code'] != "SUCCESS")
            return array("flag" => false, "msg" => $result['err_code_des'],'notify_info' => $wxReturnData);
        else
            return array("flag" => true, "msg" => "打款成功！",'notify_info' => $wxReturnData);
    }

    /**
     * 获取加密证书
     * @return bool|string
     */
    public function getPublicKey(){
        //获取rsa公钥；
        $wxPem = new WxPem();
        $result = $wxPem->getPem();
        $pubic_key = array_key_exists("pub_key",$result) ? $result['pub_key'] : '' ;
        $stream = fopen(EXTEND_PATH.'/wechat/payment/cacert/public_PKCS1.pem','w+');
        fwrite($stream,$pubic_key);
        rewind($stream);
        return file_get_contents(EXTEND_PATH.'/wechat/payment/cacert/public_PKCS1.pem');
    }

    /**
     * @param $enc_bank_no 银行卡号
     * @param $enc_true_name 银行卡持有人姓名
     * @param $bank_code 银行id
     * @param $amount 金额
     * @param $partner_trade_no 订单号
     * @return array
     */
    public function PayToCard($bank_no, $true_name, $bank_code, $amount, $partner_trade_no){
        //数据验证
        if (empty($amount) || !($amount >= 1) || !is_int($amount))
            return array("flag" => false, "msg" => "打款金额必须为大于1的整数");
        //获取rsa公钥；
        $public_key = file_get_contents(EXTEND_PATH.'/wechat/payment/cacert/public_PKCS8.pem');
        $public_key_pk = openssl_pkey_get_public($public_key);

        if (empty($public_key))
            return array("flag"=>false,"msg"=>"rsa公钥获取失败");
        if (!$public_key_pk)
            return array("flag"=>false,"msg"=>"rsa公钥无效");

        openssl_public_encrypt($bank_no,$enc_bank_no,$public_key_pk,OPENSSL_PKCS1_OAEP_PADDING);
        openssl_public_encrypt($true_name,$enc_true_name,$public_key_pk,OPENSSL_PKCS1_OAEP_PADDING);

        //提现
        $Pay_pub = new WxPayToCard(); //调用请求接口基类
        $Pay_pub->setParameter('partner_trade_no', $partner_trade_no); //商户订单号
        $Pay_pub->setParameter('enc_bank_no', base64_encode($enc_bank_no));
        $Pay_pub->setParameter('enc_true_name', base64_encode($enc_true_name));
        $Pay_pub->setParameter('bank_code', $bank_code);
        $Pay_pub->setParameter('amount', $amount); //金额
        $Pay_pub->setParameter('desc', "提现"); //描述
        $result = $Pay_pub->sendPay();
        if ($result['return_code'] != "SUCCESS" || $result['result_code'] != "SUCCESS")
            return array("flag" => false, "msg" => $result['err_code_des']);
        else
            return array("flag" => true, "msg" => "打款成功！");
    }
}