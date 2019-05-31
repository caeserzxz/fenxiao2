<?php
namespace app\admin\controller;

use think\AjaxPage;
use think\Db;
use think\Page;

class Invoice extends Base {
    /*
     * 初始化操作
     */

    public function _initialize() {

        parent::_initialize();
        C('TOKEN_ON', false); // 关闭表单令牌验证
    }

    /*
     * 发票列表
     */

    public function index() {
       header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }

    /**
     * 发票列表 ajax
     * @date 2017/10/23
     */
    public function ajaxindex() {
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }


     //开票时间
    function changetime(){

     header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");

    }


    public function export_invoice()
    {
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }

}
