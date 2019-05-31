<?php
namespace app\admin\controller;

use app\common\logic\UsersLogic;
use app\common\model\Users;
use think\AjaxPage;
use think\Controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;

class Distribut extends Base
{

    //分销商品列表
    public function goods_list()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);
        return $this->fetch();
    }

    public function ajaxGoodsList()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . " = 1";
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " and cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }
        $where .= " and commission > 0 ";
        $count = M('Goods')->where($where)->count();
        $Page = new AjaxPage($count, 20);
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList', $catList);
        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show);
        return $this->fetch();
    }

    /**
     * 分销设置
     */
    public function set()
    {
        header("Location:" . U('Admin/System/index', array('inc_type' => 'distribut')));
        exit;
    }

    /**分销关系
     * @return mixed
     */
    public function tree()
    {
        $where = 'is_distribut = 1';
        if ($this->request->param('user_id')) $where = "user_id = '{$this->request->param('user_id')}'";
        $list = M('users')->where($where)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 获取某个人下级元素
     */
    public function ajax_lower()
    {
        $id = $this->request->param('id');
        $userlevel = $this->request->param('userlevel');
        $userlevel_field = '';
        if ($userlevel == "first_leader") {
            $userlevel_field = "second_leader";
        } else if ($userlevel == "second_leader") {
            $userlevel_field = "third_leader";
        }
        $where = '';
        if ($userlevel == 'first_leader') $where .= "first_leader =" . $id;
        if ($userlevel == 'second_leader') $where .= "second_leader =" . $id;
        if ($userlevel == 'third_leader') $where .= "third_leader =" . $id;
        $list = M('users')->where($where)->select();
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['user_level'] = $userlevel_field;
            $_list[] = $_t;
        }
        $this->assign('list', $_list);
        return $this->fetch();
    }

    //分销商列表
    public function distributor_list()
    {
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function distributorajaxindex()
    {
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;
        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');
        $condition['is_distribut'] = 1;
        $model = M('users');
        $count = $model->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }
        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    //分成日志
    public function rebate_log()
    {

        return $this->fetch();
    }

    public function ajax_rebate_log(){
        $input = I('');
        // dump($input);die;
        $search_key = $input['search_key'];//搜索方式
        $search_value= $input['search_value'];//input框的值
        $where =  array();
        if($search_key && $search_value){
            // if($search_key == 'user_id'){
            //     $userdata = M('users')->where(['mobile' => $search_value])->field(array('user_id'))->select();
            //     $user_string = '';
            //     foreach ($userdata as $k => $v) {
            //          $user_string = $v['user_id'].',';
            //     }
            //     $where[$search_key] =  array('in',$user_string);
            // else {
                $where[$search_key] =  array('like','%'.$search_value.'%');//字段名=。。。
            // }
        }
        $count = M('rebate_log')->where($where)->count();
        $Page = new AjaxPage($count, 10);
        $lists = M('rebate_log')->order("field(status,2,1,0,3,4)")->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        // dump($lists);die;
        $_lists = array();
        foreach ($lists as $key => $val) {
            $_t = $val;
            $_t['user_name'] = M('users')->where(['user_id'=>$val['user_id']])->value('nickname');
            // $status_string = ['未付款','已付款','等待分成(已收货)','已分成','已取消'];
            $status_string =[0=>"<font color='#DC143C'>未付款</font>",1=>"<font color='#436EEE'>已付款</font>",2=>"<font color='#00CD00'>等待分成(已收货)</font>",3=>"<font color='#969696'>已分成</font>",4=>"<font color='#BFEFFF'>已取消</font>"];
            // $_t['is_off'] = $val['status'] == 3 ? "<font color='red'>已分成</font>" : '确定分成';
            $_t['statuss'] = $status_string[$_t['status']] ;
            $_t['yesurl'] = url('Distribut/yesurl', array('id' => $val['id']));
            $_lists[] = $_t;
        }
         $show = $Page->show();
        // dump($_lists);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('lists', $_lists);
        return $this->fetch();
    }

    //确定分成
    public function yesurl(){
        $id = input('id/d');
        $rebate = Db::name('rebate_log')->where(['id'=>$id])->find();

        if(!$rebate)
            $this->error('该分成记录不存在');
        if($rebate['status'] != 2)
            $this->error('不能修改分成记录');
        if(!$rebate['confirm'] || time()< $rebate['confirm'] + tpCache('distribut.date'))
            $this->error('确认收货后，未达到规定时间进行确认分成。'.tpCache('distribut.date').'天');

        Db::startTrans();

        //更新记录
        $data['confirm_time'] = time();
        $data['status'] = 3;
        $result = Db::name('rebate_log')->where(['id'=>$id])->update($data);
        //更新分成金额
        $user_logic = new UsersLogic();
        $rebate_log = ['desc'=>'分佣获得余额','order_sn'=>$rebate['order_sn'],'order_id'=>$rebate['order_id']];
        $set_user = $user_logic->setAccountOrPoints($rebate['user_id'],'account',$rebate['money'],$rebate_log);
        //更新用户所累积的分佣金额
        $user = Users::get($rebate['user_id']);
        $user->distribut_money += $rebate['money'];
        $user_add = $user->save();

        //添加账户金额记录
        if($set_user !== true || !$result || !$user_add)
        {
            Db::rollback();
            $this->error('操作失败，稍后再试！');
        }

        Db::commit();
        $this->Success('操作成功');
    }

}