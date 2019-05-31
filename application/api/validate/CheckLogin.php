<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/12
 * Time: 17:39
 */

namespace app\api\validate;

use My\DataReturn;
class CheckLogin extends ValCommon
{
    protected $rule = [
       // 'encodedata' => 'require|checkLogin'
    ];

    protected $msg =[
        'data.require' => '系统参数有误'
    ];

    protected function checkLogin($value)
    {
       /* $data = DataReturn::baseFormat($value);
        $open_id = $data['unionid'];
        $token = $data['token'];
        $mdvalue = $data['mdvalue'];
        $new_token = DataReturn::md5encryption($open_id,$token);*/
        $session = session('user');

        if(time() > $session['expire_time'] || !$session )
            return '校验失败，需要重新登录!';

        return true;
    }
}