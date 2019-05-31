<?php

namespace app\mobile\controller;

use app\common\exception\PayFailedException;
use app\common\logic\RewardLogic;
use app\common\model\Plugin;
use think\Controller;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;

class AppPayment extends Controller
{

    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 专门处理app微信支付 ，回调通知和结果
     * */
    public function notify()
    {
        //微信app支付回调
        $postXml = file_get_contents("php://input");    // 接受通知参数；

        libxml_disable_entity_loader(true);

        $postObj = json_decode(json_encode(simplexml_load_string($postXml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        Log::error('$postObj-' . json_encode($postObj));
        if (!$postObj) {
            //支付完成后，点击返回商家。没有数据，直接跳转去订单列表
            $order_list_url = U("Mobile/Order/order_list");
            header("Location: $order_list_url");
            exit;
        }

        //确认支付成功
        if ($postObj['return_code'] == "SUCCESS" && $postObj['result_code'] == "SUCCESS") {

            //获取支付的订单编号
            $order_sn = $postObj['out_trade_no']; //订单编号
            if (strlen($order_sn) > 18) {
                $order_sn = substr($order_sn, 0, 18);
            }

            //购买身份卡逻辑
            $attach = json_decode($postObj['attach'],true);
            if($attach['goods_type'] == '1') {        //廖燕青——支付的为身份卡的，晋升用户身份
                Log::error( "attach".json_encode($attach));

//            if ($result && $result['pay_status'] == '0' && $postObj['total_fee'] == $result['order_amount'] * 100) {
                $this->returnWxResult();// 向微信后台返回结果。

                $orderInfo = Db::name('order_vip')->where('order_sn', $order_sn)->find();
                $orderInfo2 = Db::name('order_vip')->where('order_sn', $order_sn)->fetchSql()->find();

                Log::error( "orderInfo".json_encode($orderInfo));
                Log::error( "orderInfo2".json_encode($orderInfo2));

                $insertVip = $this->upToVip($orderInfo['user_id'], $postObj['transaction_id']);

                //廖燕青——用户身份晋升
                $rewardLogic = new RewardLogic();
                $r = $rewardLogic->dealIdentity($orderInfo['user_id']);
//            }


            }else{
                $result = M('order')->where('order_sn', $order_sn)->find();
                Log::error('$result-' . json_encode($result));
                //是否存在该订单
//            if ($result && $result['pay_status'] == '0' && $postObj['total_fee'] == $result['order_amount'] * 100) {
                //订单第一次时未支付状态,并且支付金额要对上

                $this->returnWxResult();// 向微信后台返回结果。

                update_pay_status($order_sn, array('transaction_id' => $postObj['transaction_id'])); // 修改订单支付状态
                exit;
//            }


            }

        }


    }


    /*
    * 处理支付宝支付 ，回调通知和结果
    * */
    public function alipay_notify()
    {
        $notifyInfo = input();
//        Log::error("notify".json_encode($notifyInfo));

        $out_trade_no   = $notifyInfo['out_trade_no'];//获取订单号

        if (!$out_trade_no){

            $this->error('无效的通知请求');
            return;
        }


//        Log::error($notifyInfo['passback_params']);
//        Log::error("out_trade_no----".$notifyInfo['out_trade_no']);

        if($notifyInfo['passback_params'] == 1){
            $orderInfo = Db::name('order_vip')->where('order_sn',$notifyInfo['out_trade_no'])->find();
        }else{
            $orderInfo = Db::name('order')->where('order_sn',$notifyInfo['out_trade_no'])->find();
        }

//        Log::error("orderInfo".json_encode($orderInfo));

        if(empty($orderInfo)){
            $this->error("查无该订单");
        }

        if($orderInfo['order_amount'] != $notifyInfo['total_amount']){
            $this->error("订单金额错误");
        }

//        Log::error(1111);

        if($notifyInfo['trade_status'] == "TRADE_SUCCESS"){

            if($notifyInfo['passback_params'] == 1){

                //晋升自身身份
               $result = $this->upToVip($orderInfo['user_id'],$notifyInfo['trade_no']);
//                Log::error($result);

                //身份晋升
                $rewardLogic = new RewardLogic();
//                Log::error(2222);

                $r = $rewardLogic->dealIdentity($orderInfo['user_id']);

//                Log::error($r);

            }else{

                update_pay_status($notifyInfo['out_trade_no'], array('transaction_id' => $notifyInfo['trade_no'])); // 修改订单支付状态

                exit;
            }
        }else{

            $this->error("订单支付失败");
        }

        $this->verify_result(true);	//返回验证结果给支付网关

    }


    public function returnWxResult()
    {
        $reply = "<xml>
                      <return_code><![CDATA[SUCCESS]]></return_code>
                      <return_msg><![CDATA[OK]]></return_msg>
                  </xml>";
        echo $reply;
    }

    public function upToVip($userId,$outOrderSn)
    {
        $where['user_id'] = $userId;
        $data['pay_status'] = 1;
        $data['pay_time'] = time();
        $data['transaction_id'] = $outOrderSn;

        $rt = Db::name('order_vip')
            ->where($where)
            ->update($data);

        return $rt;
    }

    /**
     * 对象 转 数组
     *
     * @param object $obj 对象
     * @return array
     */
    function object_to_array($obj) {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return;
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = (array)object_to_array($v);
            }
        }

        return $obj;
    }

    /**
     *    将验证结果反馈给网关
     *
     * @param     bool $result
     * @return    void
     */
    function verify_result($result)
    {
        if ($result) {
            echo 'success';
        } else {
            echo 'fail';
        }
    }



}
