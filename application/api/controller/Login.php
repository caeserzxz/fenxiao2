<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/12
 * Time: 10:22
 */

namespace app\api\controller;

use app\common\logic\UsersLogic;
use My\DataReturn;
use think\Session;

class Login extends Base
{
    private $code = '';
    /**
     * 小程序登录
     */
    public function do_login()
    {
        $this->code = trim(input('applet_code/s'));
        $wxuser = $this->getWxLoginToken();
        if($wxuser && !empty($wxuser['errcode']))
            DataReturn::returnBase64Json(500,'获取用户信息出错了');

        $wxuser['oauth'] = 'miniapp';        //设置来源
        $wxuser['nickname'] =  trim(input('name/s'));   //获取用户昵称
        $wxuser['head_pic'] =  trim(input('profile_photo/s'));   //获取用户昵称

        session_start();
        //保存openid为了支付
        $_SESSION['openid'] = $wxuser['openid'];

        $logic = new UsersLogic();
        $is_bind_account = tpCache('basic.is_bind_account');
        if($is_bind_account){
            if($wxuser['unionid']){
                $thirdUser = M('OauthUsers')->where(['unionid'=>$wxuser['unionid'], 'oauth'=>$wxuser['oauth']])->find();
            }else{
                $thirdUser = M('OauthUsers')->where(['openid'=>$wxuser['openid'], 'oauth'=>$wxuser['oauth']])->find();
            }

            if(empty($thirdUser)){
                //用户未关联账号, 跳到关联账号页
                //session('third_oauth',$wxuser);
                //$first_leader = I('first_leader');
                DataReturn::returnBase64Json(302,'',U('Mobile/User/bind_guide'));
            }else{
                //微信自动登录
                $data = $logic->thirdLogin_new($wxuser);
            }
        }else{
            $data = $logic->thirdLogin($wxuser);
        }

        if($data['status'] == 1)
        {
            //返回处理的session_id
            $session['user_id'] = $data['result']['user_id'];
            $session['expire_time'] = time() + C('session.expire');
            session('user',$session);

            DataReturn::returnBase64Json(200,'登录成功',['PHPSESSID'=>session_id()]);

        }

        DataReturn::returnBase64Json(500,$data['msg'],$data['result']);

      /*  $session['user_id'] = 2581;
        $session['expire_time'] = time() + C('session.expire');
        session('user',$session);
        DataReturn::returnBase64Json(200,'登录成功',['PHPSESSID'=>session_id(),]);*/

    }

    public function login_demo()
    {
        //返回处理的session_id
        $session['user_id'] = 2581;
        $session['expire_time'] = time() + C('session.expire');
        session('user',$session);
        DataReturn::returnBase64Json(200,'登录成功',['PHPSESSID'=>session_id()]);
    }

    /*获取微信登录凭证*/
    protected function getWxLoginToken()
    {

        $second = 60; //设置超时
        $paymentPlugin = M('Plugin')->where("code='miniAppPay' and  type = 'payment' ")->find(); // 找到小程序支付插件的配置

        $config = unserialize($paymentPlugin['config_value']);

        //参数
        $url = sprintf('https://api.weixin.qq.com/sns/jscode2session?appid=%s&secret=%s&js_code=%s&grant_type=authorization_code',$config['appid'],$config['appsecret'],$this->code);

        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); //设置返回值

        $res = curl_exec($ch);//运行curl，结果以json形式返回
        $data = json_decode($res,true);
        curl_close($ch);
        return $data;
    }

}