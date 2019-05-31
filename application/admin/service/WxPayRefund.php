<?php
/**
 * Created by PhpStorm.
 * User: 猿份哥
 * Date: 2018/5/17
 * Time: 11:04
 */

namespace app\admin\service;

use wechat\payment\WxPayToRefund;

/**
 * 微信申请退款
 * Class WxPayRefund
 * @package app\admin\service]
 */
class WxPayRefund
{

    /**
     * @param string $transaction_id 商户订单号
     * @param string $out_refund_no 商户退款单号
     * @param int $total_fee 订单金额
     * @param int $refund_fee 退款金额
     * @return array
     */
    public function wxPayRefund($transaction_id, $out_refund_no, $total_fee ,$refund_fee){
        $wxPayRefund = new WxPayToRefund();//调用请求接口基类

        $wxPayRefund->setParameter('transaction_id', $transaction_id); //商户订单号
        $wxPayRefund->setParameter('out_refund_no', $out_refund_no); //商户退款单号
        $wxPayRefund->setParameter('total_fee', $total_fee); //订单金额
        $wxPayRefund->setParameter('refund_fee', $refund_fee); //退款金额
        $wxPayRefund->setParameter('notify_url', config('refund_notify_url')); //退款异步回调地址修改

        $result = $wxPayRefund->sendRefund();
        $wxReturnData = serialize($result);//保存下微信返回回来的数据

        if ($result['return_code'] != "SUCCESS" || $result['result_code'] != "SUCCESS")
            return array("flag" => false, "msg" => $result['err_code_des'],'notify_info' => $wxReturnData);
        else
            return array("flag" => true, "msg" => "退款申请接收成功",'notify_info' => $wxReturnData);
    }

}