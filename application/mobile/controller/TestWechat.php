<?php

namespace app\mobile\controller;
use think\Controller;
use think\Db;

class TestWechat extends Controller
{
 
    /*
     * 验证
     * */
    public function index()
    {

        $echoStr = input('echostr');
        echo $echoStr;
    }



}