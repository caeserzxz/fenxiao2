<?php

namespace app\api\controller;
use think\Request;
use My\WeixinPay;
use My\DataReturn;
class Weixinment extends Base {

    public function getPay(){
        $session = session('user');
        $user = M('users')->where('user_id',$session['user_id'])->find();
        $data['account'] = I('account');
        $openid = $user['openid'];
    	$data['user_id'] = $user['user_id'];
    	$data['nickname'] = $user['nickname'];
    	$data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
        $data['ctime'] = time();
    	$order_id = M('recharge')->add($data);
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        M('recharge')->where("order_id", $order_id)->save(array('pay_code'=>'miniAppPay','pay_name'=>$payment_arr['miniAppPay']));
        if($order_id){
            DataReturn::returnJson('200','生成订单成功',$data);
        }else{
        	DataReturn::returnJson('400','生成订单失败');
        }
    }
    //发起请求支付
    public function payfree()
    {
        $user_id = I('user_id');
        $order_sn  = I('order_sn');
        $total_fee = I('total_fee');
        $user = M('users')->where('user_id',$user_id)->find();
        $paymentPlugin = M('Plugin')->where("code='miniAppPay' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $appid = $config_value['appid']; // * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
        $mch_id = $config_value['mchid']; // * MCHID：商户号（必须配置，开户邮件中可查看）
        $key    = $config_value['key']; //
        $open_id   = $user['openid'];
        if (!$open_id || !$order_sn || !$total_fee) {
            DataReturn::returnJson('400', '系统发生错误，请稍候重试！');
        }
        $recharge = M('recharge')->where('order_sn', $order_sn)->find();
        $total_fee = $recharge['account']*100;
        if (!$recharge) {
            DataReturn::returnJson('400', '订单不存在！');
        }
        $body = "账户充值";
        // $total_fee = 1;
        $weixinpay = new WeixinPay($appid, $open_id, $mch_id, $key, $order_sn, $body, $total_fee);
        $return    = $weixinpay->pay();
        DataReturn::returnJson('200', '支付返回结果！', $return);
    }

    //支付回调处理
    public function wxpayment()
    {
        $postXml = $GLOBALS["HTTP_RAW_POST_DATA"]; //接收微信参数
        if (empty($postXml)) {
            return false;
        } else {
            $weixinReturn = $this->xmlToArray($postXml);
            $openid       = $weixinReturn['openid'];
            $out_trade_no = $weixinReturn['out_trade_no'];
            $result_code  = $weixinReturn['result_code'];
            $return_code  = $weixinReturn['return_code'];
            if ($result_code == 'SUCCESS' && $return_code == 'SUCCESS') {
                $recharge = M('recharge')->where('order_sn', $out_trade_no)->find();
                if ($out_trade_no && $recharge['pay_status'] == 0) {
                    $data['pay_status']   = 1;
                    $data['pay_time'] = time();
                    $buseness    = M('users')->where('user_id', $recharge['user_id'])->setInc('user_money',$recharge['account']);
                    $transferlog = M('recharge')->where('order_sn', $out_trade_no)->update($data);
                }
                return '<xml>
                      <return_code><![CDATA[SUCCESS]]></return_code>
                      <return_msg><![CDATA[OK]]></return_msg>
                      </xml>';
            } else {
                $data['status']   = 2;
                $data['pay_time'] = time();
                $recharge      = M('recharge')->where('order_sn', $out_trade_no)->update($data);
                return '<xml>
                      <return_code><![CDATA[FAIL]]></return_code>
                      <return_msg><![CDATA[NO]]></return_msg>
                      </xml>';
            }
        }
    }

    //将xml格式转换成数组
    private function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val       = json_decode(json_encode($xmlstring), true);
        return $val;
    }
}
