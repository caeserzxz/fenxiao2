<?php


use think\Db;
use think\Cache;
use app\common\logic\JssdkLogic;

function getwxconfig(){
    $wx_config = Cache::get('weixin_config');
    if(!$wx_config){
        $wx_config = M('wx_user')->find(); //获取微信配置
        Cache::set('weixin_config',$wx_config,0);
    }
    $jssdk = new JssdkLogic($wx_config['appid'], $wx_config['appsecret']);
    $signPackage = $jssdk->GetSignPackage();
    return $signPackage;
}
