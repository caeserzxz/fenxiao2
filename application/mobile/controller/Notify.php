<?php

namespace app\mobile\controller;

use think\Controller;
use app\common\logic\UsersUpLevel;

class Notify extends Controller {

    /*
     * 微信异步回调
     * */
    public function weChatNotify(){

        // 接收微信支付回调
        $postStr = file_get_contents("php://input",'r');
        file_put_contents("wechat_pay.log",date("Y-m-d H:i:s").'----微信异步'.$postStr.PHP_EOL,FILE_APPEND);//记录日志
        if(!empty($postStr)){
            // 将xml格式转换成数组
            $data = xmlArr($postStr);
            if($data['return_code'] == 'SUCCESS'){
                $return = array(
                    'return_code' => 'SUCCESS',
                    'return_msg' => 'OK',
                );

                $msg = json_decode($data['attach'], true);

                $map['pay_time'] = time();
                $map['transaction_id'] = $data['transaction_id'];
                $map['pay_status'] = 1;
                $message =  M('order')->where('order_id',$msg['order_id'])->save($map);

                if($message){
                    //这里是为了方便测试,正式上线会删除
//                    M('order')->where('order_id',$msg['order_id'])->save(array('order_amount'=>500));

                    $model = new UsersUpLevel();
                    //付款分佣
                    $model->SubCommission($msg['order_id']);
                }

                file_put_contents("wechat_pay.log",date("Y-m-d H:i:s").'----微信成功支付异步trade_no:'.$map['transaction_id'].'----$message:'.$message.'----user_id:'.$msg['user_id'].PHP_EOL,FILE_APPEND);//记录日志

                # 回调我们商城
                if(!empty($message)){
                    echo $this->arrayToXml($return);
                    exit;
                }
                else
                {
                    checkReturn();
                }
                echo $this->arrayToXml($return);
                exit;
            }else{
                $return = array(
                    'return_code' => 'FAIL',
                    'return_msg' => '',
                );
                echo $this->arrayToXml($return);
                exit;
            }
        }
    }


    function arrayToXml($arr){
        $xml = "<xml>";
        foreach ($arr as $key => $val){
            if(is_array($val)){
                $xml.="<".$key.">".$this->arrayToXml($val)."</".$key.">";
            }else{
                $xml.='<'.$key.'>'.$val.'</'.$key.'>';
            }
        }
        $xml.="</xml>";
        return $xml;
    }
}