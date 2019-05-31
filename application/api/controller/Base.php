<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/12
 * Time: 10:18
 */
namespace app\api\controller;

use app\api\validate\CheckLogin;
use app\common\model\Users;
use think\Controller;
use My\DataReturn;

class Base extends Controller{

    protected $user_id = '';
    public function __construct()
    {
        parent::__construct();
        //处理数据
    }

    /*验证登录*/
    public function checkLogin()
    {
        $session = session('user');
        if(time() > $session['expire_time'] || !$session )
            DataReturn::returnBase64Json(304,'校验失败，需要重新登录!');

        /*if($check !== true)
            DataReturn::returnBase64Json(500,$check);*/

        //获取用户的user_id
        //$get_data =$this->getBase64Data();
        $get_data = session('user');

        $user_info = Users::get(['user_id'=>$get_data['user_id']]);

        if(!$user_info)
            DataReturn::returnBase64Json(500,'获取用户信息失败');

        $this->user_id = $user_info->user_id;

        return true;

    }

    public function test()
    {
        $a = array_intersect_key(['id'=>123,'array'=>['array_1'=>123],'test'=>'asds'],array_flip(['id','test']));
        dump($a);
    }

    /*获取加密的数据*/
    public function getBase64Data()
    {
        $_data = C('encode_data');
        //解密
        $decode_data = DataReturn::baseFormat($_data) ;

        return $decode_data;
    }


    //获取加密数据
    public function __get($name)
    {
        // TODO: Implement __get() method.
        if($name == C('encode_data'))
            return $this->getBase64Data();
    }


}