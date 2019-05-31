<?php

namespace app\home\controller;

use app\common\logic\WechatLogic;
use think\Log;
class Weixin
{
    /**
     * 处理接收推送消息
     */
    public function index()
    {
    	
        $logic = new WechatLogic;
        $result=$logic->handleMessage();
        if (empty($result))
        {
            echo "error";
        }
        else
        {
            
            echo $result;
        }

    }

}