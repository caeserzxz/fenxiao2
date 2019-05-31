<?php
/**
 *  消息功能
 */

namespace app\mobile\controller;

use think\Db;

class News extends  MobileBase{
    /**
     * 订单消息
     **/
    public function orderNews(){
        return $this->fetch();
    }
    /**
     * 热门推荐
     **/
    public function recommendNews(){
        return $this->fetch();
    }
    /**
     * 系统消息
     **/
    public function systemNews(){
        return $this->fetch();
    }
}