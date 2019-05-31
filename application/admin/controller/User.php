<?php

namespace app\admin\controller;

use app\admin\logic\InvoiceLogic;
use app\admin\logic\OrderLogic;
//use app\common\logic\UsersLogic;
use app\admin\validate\UserLevel;
use app\common\logic\RewardLogic;
use app\common\logic\UserReward;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use think\Loader;
use app\common\model\Users;

class User extends Base
{

    public function index()
    {
        //统计粉丝
        $countUser = Db::name('users')
            ->where('user_type',0)
            ->count();

        //统计会员
        $countVip = Db::name('users')
            ->where('user_type',1)
            ->count();

        //统计总人数
        $countAll = Db::name('users')
            ->count();

        $this->assign('countUser',$countUser);
        $this->assign('countVip',$countVip);
        $this->assign('countAll',$countAll);
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex()
    {
        $timegap = I('timegap');
        if ($timegap) {
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        } else {
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end = strtotime(I('add_time_end'));
        }
        // 搜索条件
        $condition = array();
        I('user_id') ? $condition['user_id'] = I('user_id') : false;
        I('super_account') ? $condition['super_account'] = I('super_account') : false;

        I('real_name') && ($condition['real_name'] = I('real_name')); // 姓名
        I('mobile') && ($condition['mobile'] = I('mobile')); // 手机号

        $sort_order = I('order_by') . ' ' . I('sort');
        if ($begin && $end) {
            $condition['reg_time'] = array('between', "$begin,$end");
//            $condition['reg_time'] = array('between', $begin,$end);
        }

        $model = M('users');
        $count = $model->where($condition)->count();

        $Page = new AjaxPage($count, 10);

//        //  搜索条件下 分页赋值
//        foreach ($condition as $key => $val) {
//            $Page->parameter[$key] = urlencode($val);
//        }
//
        $time = date('m', time());
        $beginThismonth = mktime(0, 0, 0, date($time), 1, date('Y'));
        $endThismonth = mktime(23, 59, 59, date($time), date('t'), date('Y'));

        $userList = $model->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->order('user_id desc')->select();
        $list = array();
        foreach ($userList as $key => $value) {
            $t = $value;
//            $t['user_type'] = M('user_level')->where('level_id',$value['user_type'])->getField('level_name'));
            $t['user_type'] = userType($value['level']);
            $list[] = $t;
        }

        $show = $Page->show();
        $this->assign('userList', $list);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
//        dump($list);
    }


    //直推关系图
    public function topRefferal()
    {
        return $this->fetch();
    }

    /**
     * 直推列表
     */
    public function ajaxtopRefferal()
    {

        $input = input();

        // 搜索条件
        $condition = array();
        I('pid') ? $condition['pid'] = I('pid') : $condition['pid'] = '0';
        I('id') ? $condition['user_id'] = I('id') : '0';
        I('real_name') && ($condition['real_name'] = I('real_name')); // 姓名
        $model = M('users');
        $count = $model->where($condition)->count();
        $time = date('m', time());
        $beginThismonth = mktime(0, 0, 0, date($time), 1, date('Y'));
        $endThismonth = mktime(23, 59, 59, date($time), date('t'), date('Y'));
        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }

        $userList = $model->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $list = array();
        foreach ($userList as $key => $value) {
            $t = $value;

            $t['month_money'] = db('n_amount_log')
                ->where('user_id', $value['user_id'])
                ->where('create_time', 'between', [$beginThismonth, $endThismonth])
                ->where('type', 3)
                ->sum('money');

            $list[] = $t;
        }

        $show = $Page->show();
        $this->assign('userList', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }


    //管理关系图
    public function manageList()
    {

        return $this->fetch();
    }


    /**
     * 管理列表
     */
    public function ajaxmanageList()
    {

        $input = input();
        //dump($input);die;
        // 搜索条件
        $condition = array();
        $input['user_id'] ? $condition['a.user_id'] = $input['user_id'] : false;
        $input['name'] ? $condition['a.real_name'] = $input['name'] : false;
        $input['province_name'] ? $condition['c.name'] = $input['province_name'] : false;
        $input['user_type'] ? $condition['a.user_type'] = $input['user_type'] : $condition['a.user_type'] = 3;

        //dump($condition);die;

        $count = db('users')
            ->alias('a')
            ->join('n_apply_identity b', 'a.user_id=b.obj_user_id')
            ->join('region c', 'b.agent_province_id=c.id')
            ->where($condition)
            ->where('b.status', 1)
            ->count();

        $Page = new AjaxPage($count, 8);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }

        //找到大区经理
        $userList = db('users')
            ->alias('a')
            ->join('n_apply_identity b', 'a.user_id=b.obj_user_id')
            ->join('region c', 'b.agent_province_id=c.id')
            ->where($condition)
            ->where('b.status', 1)
            ->field('a.user_type,a.nickname,a.user_id,a.reg_time,a.real_name,c.name as c_name,b.id as apply_id')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        //根据申请表id,apply_id去获取对应的管理区域，总代和大区为代理区域，健康大使为所在区域
        foreach ($userList as &$value) {

            //获取申请表
            $apply = db('n_apply_identity')->where('id', $value['apply_id'])->find();

            if ($apply['user_type'] == 1) {
                //省
                $province = db('region')->where('id', $apply['province_id'])->find();
                $value['province'] = $province['name'];
                //市
                $city = db('region')->where('id', $province['city_id'])->find();
                $value['city'] = $city['name'];
                //区
                $area = db('region')->where('id', $city['area_id'])->find();
                $value['area'] = $area['name'];

            } else {
                //省
                $province = db('region')->where('id', $apply['agent_province_id'])->find();
                $value['province'] = $province['name'];
                //市
                $city = db('region')->where('id', $province['agent_city_id'])->find();
                $value['city'] = $city['name'];
                //区
                $area = db('region')->where('id', $city['agent_area_id'])->find();
                $value['area'] = $area['name'];
            }
        }

        $show = $Page->show();
        $this->assign('userList', $userList);
        //$this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }


    //查看管理下级
    public function manageListxiaji()
    {
        $input = input();

        $this->assign('id', $input['id']);
        return $this->fetch();
    }

    //查看管理下级
    public function ajaxmanageListxiaji()
    {
        $input = input();

        // 搜索条件
        $condition = array();

        I('id') ? $condition['management_id'] = I('id') : false;
        I('user_id') ? $condition['user_id'] = I('user_id') : false;
//        I('name') ? $condition['real_name'] = I('name') : false;

        $model = M('n_user_management');
        $count = $model->where($condition)->count();

        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }

        $userList = $model->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $list = array();
        foreach ($userList as $k => $v) {
            if ($input['name']) {
                $where['real_name'] = $input['name'];
                $where['user_id'] = $v['user_id'];
            } else {
                $where['user_id'] = $v['user_id'];
            }

            $_t = $v;
            $user_name = db('users')->field('user_type,nickname,real_name')->where($where)->find();
            $apply_identity_data = db('n_apply_identity')->field('agent_province_id')->where('status', 1)->where('obj_user_id', $v['user_id'])->find();
            $management_name = db('users')->field('nickname')->where('user_id', $v['management_id'])->find();
            $_t['real_name'] = $user_name['real_name'];
            $_t['agent_province_id'] = $apply_identity_data['agent_province_id'];
            $_t['user_name'] = $user_name['nickname'];
            $_t['user_type'] = $user_name['user_type'];
            $_t['management_name'] = $management_name['nickname'];
            $list[] = $_t;

        }


        foreach ($list as $k1 => $v1) {

            $agent_province_data = db('region')->field('name')->where('id', $v1['agent_province_id'])->find();
            $list[$k1]['province_name'] = $agent_province_data['name'];
        }

        $show = $Page->show();
        $this->assign('userList', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }


    //修改管理关系
    public function modify()
    {

        $uid = I('get.id');
        $user = D('users')->where(array('user_id' => $uid))->find();
        $management_id = D('n_user_management')->field('management_id')->where(array('user_id' => $uid))->find();

        if (!$user)
            exit($this->error('会员不存在'));
        if (IS_POST) {

            $uid = $_POST['user_id'];
            $row = db('n_user_management')->where(array('user_id' => $uid))->update(['management_id' => $_POST['management_id']]);
            if ($row)
                exit($this->success('修改成功'));
            exit($this->error('未作内容修改或修改失败'));
        }

        $this->assign('user', $user);
        $this->assign('management_id', $management_id['management_id']);
        return $this->fetch();
    }


    //x选择用户
    public function search_users1()
    {

        $input = input();

        $model = M('users')->where('user_type', 2)->whereor('user_type', 3);
        $count = $model->count();

        $Page = new Page($count, 10);

        if ($input['keywords']) {
            $userList = $model->where('user_id', $input['keywords'])->limit($Page->firstRow . ',' . $Page->listRows)->select();
            $this->assign('userList', $userList);
            return $this->fetch();
            dump($input);
            die;
        }

        //$count = $model->count();
        $userList = $model->where('user_type', 2)->whereor('user_type', 3)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('userList', $userList);

        return $this->fetch();
    }

    //健康大使发放列表
    public function gongde_list()
    {

        $input = input();

        $model = M('n_gongde_month')->where('pay_user_id', $input['user_id']);
        $count = $model->count();

        $Page = new Page($count, 10);

        //$count = $model->count();
        $userList = $model->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('userList', $userList);

        return $this->fetch();
    }


    /**
     * 身份列表
     */
    public function identitylist()
    {

        return $this->fetch();
    }

    /**
     * 身份列表
     */
    public function identity_ajaxindex()
    {
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');
        //$condition['is_sales'] ='1';
        //dump($condition);die;
        $model = M('users');
        $count = $model->where($condition)->count();

        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }

        //dump($condition);die;
        $userList = $model->where($condition)->where('level', '<>', '3')->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //dump($userList);
        $list = array();
        foreach ($userList as $key => $value) {
            $_t = $value;
            $all_user_data = Users::get($value['user_id']);
            //dump($all_user_data);die;
            $gudong = $all_user_data->isShareHolder();
            $_t['gudong'] = $gudong;
            $list[] = $_t;
        }

        //dump($list);die;

        $user_id_arr = get_arr_column($userList, 'user_id');
        if (!empty($user_id_arr)) {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(" . implode(',', $user_id_arr) . ")  group by first_leader");
            $first_leader = convert_arr_key($first_leader, 'first_leader');

            $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(" . implode(',', $user_id_arr) . ")  group by second_leader");
            $second_leader = convert_arr_key($second_leader, 'second_leader');

            $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(" . implode(',', $user_id_arr) . ")  group by third_leader");
            $third_leader = convert_arr_key($third_leader, 'third_leader');
        }
        $this->assign('first_leader', $first_leader);
        $this->assign('second_leader', $second_leader);
        $this->assign('third_leader', $third_leader);
        $show = $Page->show();
        $this->assign('userList', $list);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * 下级列表
     * 根据用户id查询出 上级id(first_leader) =用户(id)
     */
    public function xiaji()
    {
        $input = input();
        //dump($input);die;
        $model = M('users');
        $gaoji_count = $model->where('pid', $input['id'])->count();
        $this->assign('gaoji_count', $gaoji_count);
        $this->assign('id', $input['id']);
        return $this->fetch();

    }


    /**
     * 下级列表
     */
    public function xiaji_ajaxindex()
    {
        // 搜索条件
        //
        $input = input();

        $condition = array();
        I('id') ? $condition['pid'] = I('id') : false;
        I('next_id') ? $condition['user_id'] = I('next_id') : false;
        I('name') ? $condition['real_name'] = trim(I('name')) : false; // 姓名

        $model = M('users');
        //dump($input);die;
        $count = $model->where($condition)->count();

        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }

        $userList = $model->where($condition)->where('pid', $input['id'])->limit($Page->firstRow . ',' . $Page->listRows)->select();


        //根据上级id($input['id']),找上级id的昵称
        $pid_nickname = $model->where('user_id', $input['id'])->value('nickname');

        $this->assign('pid_nickname', $pid_nickname);

        $show = $Page->show();
        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }


    /**
     * 会员详细信息查看
     */
    public function detail()
    {
        $uid = I('get.id');
        $user = D('users')->where(array('user_id' => $uid))->find();

        $input = input('');

        if (!$user)
            exit($this->error('会员不存在'));
        if (IS_POST) {
//            $a = I('post.');
//
//            print '<pre>';
//            print_r($a);
//            print '</pre>';
//            exit();
            //存在管理id，入库管理表
            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if (!empty($_POST['mobile'])) {
                $mobile = trim($_POST['mobile']);
                $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            if($_POST['level']==8){
                if(empty($_POST['cityid'])){
                    $this->error('城市代理必须选择城市区域');
                }else{
                    $where_c['level'] = 8 ;
                    $where_c['cityid'] = $_POST['cityid'];
                    $citys = M('users')->where($where_c)->find();
                    if($citys){
                        $this->error('当前区域已存在市级代理');
                    }
                }

            }

            if($_POST['level']==9){
                if(empty($_POST['provinceid'])){
                    $this->error('省级代理必须选择省级区域');
                }else{
                    $where_p['level'] = 9;
                    $where_p['provinceid'] = $_POST['provinceid'];
                    $provinces = M('users')->where($where_p)->find();
                    if($provinces){
                        $this->error('当前区域已存在省级代理');
                    }
                }

//                $c && exit();
            }

            if($_POST['level']<8){
                $_POST['provinceid']=0;
                $_POST['cityid']=0;
            }

            if (!empty($_POST['user_money'])) {     //用户余额修改
                $money = trim($_POST['user_money']);
                $userData['user_money'] = $money;

                $logMoney =sprintf('%.2f',$money - $user['user_money']); // 记录进日志的金额 = 修改的金额 - 用户原有的金额

                $logObj = array(
                    'befor_money'=>$user['user_money'],
                    'after_money'=>$money,
                );
                $logData['user_id'] = $uid;
                $logData['money'] = $logMoney;
                $logData['obj'] = json_encode($logObj);
                $logData['type'] = 0;
                $logData['desc'] = "平台管理员手动修改用户余额";
                $logData['admin_id'] = session('admin_id');
                $logData['create_time'] = time();

                Db::startTrans();
                try{
                    $upUserMoney = Db::name('users')->where('user_id',$uid)->update($userData);
                    $insertLog = Db::name('n_amount_log')->insert($logData);

                    if($upUserMoney !== false && $insertLog){
                        // 提交事务
                        Db::commit();
                    }
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }

                if($upUserMoney === false || !$insertLog){
                    exit($this->error('修改失败'));
                }

            }



            //判断后台是否有更改金豆
            $RewardLogic = new RewardLogic();
            if (!empty($_POST['jindou'])) {
                $jindou = $_POST['jindou'];
                if ($user['jindou'] != $jindou) {
                    //改变的金豆比原来的大
                    if ($jindou > $user['jindou']) {
                        //增加金豆
                        $amt = $RewardLogic->amountLog($user['user_id'], $jindou - $user['jindou'], 1, '后台增加金豆', array());
                    }

                    //改变的金豆比原来的小
                    if ($jindou < $user['jindou']) {
                        //减少金豆
                        $amt = $RewardLogic->amountLog($user['user_id'], -($user['jindou'] - $jindou), 1, '后台减少金豆', array());
                    }
                }
            }

            //判断后台是否有更改云豆
            if (!empty($_POST['yundou'])) {
                $yundou = $_POST['yundou'];
                if ($user['yundou'] != $yundou) {
                    //改变的云豆比原来的大
                    if ($yundou > $user['yundou']) {
                        //增加云豆
                        $amt = $RewardLogic->amountLog($user['user_id'], $yundou - $user['yundou'], 2, '后台增加云豆', array());
                    }

                    //改变的云豆比原来的小
                    if ($yundou < $user['yundou']) {
                        //减少云豆
                        $amt = $RewardLogic->amountLog($user['user_id'], -($user['jindou'] - $yundou), 2, '后台减少云豆', array());
                    }
                }
            }

            //赋予子后台登录权限
            if ($_POST['user_type'] == 2) {
                $adminData['user_name'] = $_POST['mobile'];
                $adminData['password'] = "519475228fe35ad067744465c42a19b2";
                $adminData['user_id'] = $user['user_id'];
                $adminData['type'] = 2;
                $adminData['role_id'] = 14;
                $adminData['add_time'] = time();

                $r = D('admin')->insert($adminData);
            }


            $row = M('users')->where(array('user_id' => $uid))->save($_POST);

            if ($_POST['management_id']) {
                exit($this->success('修改成功'));
            } else {
                if ($row === false) {
                    exit($this->error('未作内容修改或修改失败'));
                } else {
                    exit($this->success('修改成功'));
                }
            }

        }
        $level = M('user_level')->order('level_id')->select();
        $province = M("region")->where('level', 1)->select();
        if(!empty($user['provinceid'])){
            $city = M("region")->where('parent_id', $user['provinceid'])->select();

            $this->assign(city,$city);
        }
        $this->assign('province', $province);
        $this->assign('level',$level);
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function add_user()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if ($res['status'] == 1) {
                $this->success('添加成功', U('User/index'));
                exit;
            } else {
                $this->error('添加失败,' . $res['msg'], U('User/index'));
            }
        }
        return $this->fetch();
    }

    /**
     * 用户列表导出
     * 根据筛选条件：注册时间、手机号、昵称、id
     */
    public function export_user()
    {
        $timegap = I('timegap');
        if ($timegap) {
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        } else {
            //@new 新后台UI参数
            $begin = strtotime(I('add_time_begin'));
            $end = strtotime(I('add_time_end'));
        }
        // 搜索条件
        $condition = array();
        I('user_id') ? $condition['user_id'] = I('user_id') : false;
        I('super_account') ? $condition['super_account'] = I('super_account') : false;

        I('real_name') && ($condition['real_name'] = I('real_name')); // 姓名
        I('mobile') && ($condition['mobile'] = I('mobile')); // 手机号

        $sort_order = I('order_by') . ' ' . I('sort');
        if ($begin && $end) {
            $condition['reg_time'] = array('between', "$begin,$end");
        }
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">身份</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">团队人数</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">直推层人数</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">团队销售总额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">佣金</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';

        $strTable .= '</tr>';
        $count = M('users')->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $userList = M('users')->where($condition)->order('user_id')->limit($start . ',' . $end)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . ($val['user_type'] == 0 ? '粉丝': ($val['user_type']==1 ? '会员':'代理') ) . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . M('n_management')->where('management_id', $val['user_id'])->count() . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . db('users')->where('pid', $val['user_id'])->count() . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . null. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['distribut_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_amount'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['reg_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['last_login']) . '</td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }



    //x选择用户
    public function search_users()
    {

        $input = input();
        //dump($input);die;
        $model = M('users')->where('user_type', '>', 1);
        $count = $model->count();
        $Page = new Page($count, 10);;

        if ($input['keywords']) {
            $userList = $model->where('user_id', $input['keywords'])->where('user_type', '>', 1)->limit($Page->firstRow . ',' . $Page->listRows)->select();
            $this->assign('userList', $userList);
            return $this->fetch();
            dump($input);
            die;
        }

        //$count = $model->count();
        $userList = $model->where('user_type', '>', 1)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('userList', $userList);

        return $this->fetch();
    }

    /**
     * 用户收货地址查看
     */
    public function address()
    {
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id' => $uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList', $regionList);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete()
    {
        $uid = I('get.id');
        $row = M('users')->where(array('user_id' => $uid))->delete();
        if ($row) {
            $this->success('成功删除会员');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 删除会员
     */
    public function ajax_delete()
    {
        $uid = I('id');
        if ($uid) {
            $row = M('users')->where(array('user_id' => $uid))->delete();
            if ($row !== false) {
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->where(array('user_id' => $user_id))->count();
        $page = new Page($count);
        $lists = M('account_log')->where(array('user_id' => $user_id))->order('change_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) $this->ajaxReturn(['status' => 0, 'msg' => "参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id', $user_id)->find();
        if (IS_POST) {
            $desc = I('post.desc');
            if (!$desc)
                $this->ajaxReturn(['status' => 0, 'msg' => "请填写操作说明"]);
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money = $m_op_type ? $user_money : 0 - $user_money;
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points = $p_op_type ? $pay_points : 0 - $pay_points;
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if ($revision_frozen_money != 0) {    //有加减冻结资金的时候
                $frozen_money = $f_op_type ? $revision_frozen_money : 0 - $revision_frozen_money;
                $frozen_money = $user['frozen_money'] + $frozen_money;    //计算用户被冻结的资金
                if ($f_op_type == 1 and $revision_frozen_money > $user['user_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
                }
                if ($f_op_type == 0 and $revision_frozen_money > $user['frozen_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0 - $revision_frozen_money : $revision_frozen_money;    //计算用户剩余资金
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $frozen_money]);
            }
            if (accountLog($user_id, $user_money, $pay_points, $desc, 0)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/User/account_log", array('id' => $user_id))]);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => "操作失败"]);
            }
            exit;
        }
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);
        return $this->fetch();
    }

    /**
     * 用户充值
     */
    public function recharge()
    {
        //dump(input());die;
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $money = I('money');
        $map = array();
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['ctime'] = array('between', array(strtotime($begin), strtotime($end)));
        }
        if ($nickname) {
            $map['nickname'] = array('like', "%$nickname%");
        }
        if ($money) {
            $map['account'] = $money;
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }


    public function level()
    {

        $act = I('get.act', 'add');

        $this->assign('act', $act);
        $level_id = I('get.level_id');
        if ($level_id) {
            $level_info = D('user_level')->where('level_id=' . $level_id)->find();
            $this->assign('info', $level_info);
        }
        return $this->fetch();
    }

    public function levelList()
    {
        $a = I('get.');
        $Ad = M('user_level');
        $p = $this->request->param('p');

        $res = $Ad->order('level_id')->page($p . ',10')->select();

        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {

            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $next = $data['level_id'] + 1;
                $config = D('user_level')->where('level_id',$next)->find();


                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if((int)$data['team_prize'] > (int)$config['team_prize'] && (!empty($config))){
                    $return = ['status' => 0, 'msg' => '下级会员设置的团队奖不能高于上级', 'result' => ''];
                } elseif ($r !== false) {
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if (strstr($search_key, '@')) {
            $list = M('users')->where(" email like '%$search_key%' ")->select();
            foreach ($list as $key => $val) {
                echo "<option value='{$val['user_id']}'>{$val['email']}</option>";
            }
        } else {
            $list = M('users')->where(" mobile like '%$search_key%' ")->select();
            foreach ($list as $key => $val) {
                echo "<option value='{$val['user_id']}'>{$val['mobile']}</option>";
            }
        }
        exit;
    }

    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where("first_leader = 1")->select();
        return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送系统消息
     * @author dyr
     * @time  2016/09/01
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $text = I('post.text');//内容
        $type = I('post.type', 0);//个体or全体
        $admin_id = session('admin_id');
        $users = I('post.user/a');//个体id
        $message = array(
            'admin_id' => $admin_id,
            'message' => $text,
            'category' => 0,
            'send_time' => time()
        );

        if ($type == 1) {
            //全体用户系统消息
            $message['type'] = 1;
            M('Message')->add($message);
        } else {
            //个体消息
            $message['type'] = 0;
            if (!empty($users)) {
                $create_message_id = M('Message')->add($message);
                foreach ($users as $key) {
                    M('user_message')->add(array('user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email = I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if ($email) {
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        return $this->fetch();
    }

    public function get_withdrawals_list($status = '')
    {
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = I('create_time');
        $create_time = str_replace("+", " ", $create_time);
        $create_time2 = $create_time ? $create_time : date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));
        $create_time3 = explode(' - ', $create_time2);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        $where['w.create_time'] = array(array('gt', strtotime(strtotime($create_time3[0])), array('lt', strtotime($create_time3[1]))));
        $status = empty($status) ? I('status') : $status;
        if (empty($status) || $status === '0') {
            $where['w.status'] = array('lt', 1);
        }
        if ($status === '0' || $status > 0) {
            $where['w.status'] = $status;
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['w.bank_card'] = array('like', '%' . $bank_card . '%');
        $export = I('export');
        if ($export == 1) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('create_time', $create_time2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("withdrawals");
        $model->where('id =' . $_GET['id'])->delete();
        $return_arr = array('status' => 1, 'msg' => '操作成功', 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $model = M("withdrawals");
        $withdrawals = $model->find($id);
        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();
        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];

        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        return $this->fetch();
    }

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update()
    {
        $id = I('id/a');
        $data['status'] = $status = I('status');
        $data['remark'] = I('remark');
        if ($status == 1) $data['check_time'] = time();
        if ($status != 1) $data['refuse_time'] = time();
        $r = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->update($data);
        if ($r) {
            $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
        }
    }

    // 用户申请提现
    public function transfer()
    {
        $id = I('selected/a');
        if (empty($id)) $this->error('请至少选择一条记录');
        $atype = I('atype');
        if (is_array($id)) {
            $withdrawals = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->select();
        } else {
            $withdrawals = M('withdrawals')->where(array('id' => $id))->select();
        }
        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach ($withdrawals as $val) {
            $user = M('users')->where(array('user_id' => $val['user_id']))->find();
            if ($user['user_money'] < $val['money']) {
                $data = array('status' => -2, 'remark' => '账户余额不足');
                M('withdrawals')->where(array('id' => $val['id']))->save($data);
                $this->error('账户余额不足');
            } else {
                $rdata = array('type' => 1, 'money' => $val['money'], 'log_type_id' => $val['id'], 'user_id' => $val['user_id']);
                if ($atype == 'online') {
                    header("Content-type: text/html; charset=utf-8");
                    exit("功能正在开发中。。。");
                } else {
                    accountLog($val['user_id'], ($val['money'] * -1), 0, "管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id' => $val['id']))->save(array('status' => 2, 'pay_time' => time()));
                    expenseLog($rdata);//支出记录日志
                }
            }
        }
        if ($alipay['batch_num'] > 0) {
            //支付宝在线批量付款
            include_once PLUGIN_PATH . "payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!", U('remittance'), 3);
    }

    /**
     *  转账汇款记录
     */
    public function remittance()
    {
        $status = I('status', 1);
        $this->assign('status', $status);
        $this->get_withdrawals_list($status);
        return $this->fetch();
    }

    /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList()
    {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList()
    {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule()
    {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }

    //<!-- 白胡子 -->

    /**
     * 账户资金记录
     */
    public function water()
    {
        $user_id = I('get.id');

        //返币记录
        //获取记录总数
        $count = M('water_coin_log')->where(array('user_id' => $user_id))->count();

        $page = new Page($count);
        $list = db('water_coin_log')
            ->where(['user_id' => $user_id])
            ->order('create_time desc')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s', $val['create_time']) : '0000-00-00 00:00:00';
            $_list[] = $_t;
        }
        $this->assign('page', $page->show());
        $this->assign('lists', $_list);

        //消费记录
        //获取记录总数
        $count_order = M('exchange_order')->where(array('user_id' => $user_id))->count();

        $page_order = new Page($count_order);
        $list_order = db('exchange_order')
            ->where(['user_id' => $user_id])
            ->order('create_time desc')
            ->limit($page_order->firstRow . ',' . $page_order->listRows)
            ->select();

        $_list_order = array();
        foreach ($list_order as $key => $val) {
            $_t = $val;
            $_t['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s', $val['create_time']) : '0000-00-00 00:00:00';
            $_list_order[] = $_t;
        }
        //p($_list_order);
        $this->assign('page_order', $page_order->show());
        $this->assign('lists_order', $_list_order);
        return $this->fetch();
    }


    //大区/总代 权限看到的直推关系图
    public function tree_zhitui()
    {

        //获取session底下的 admin_id
        if ($_SESSION['admin_id']) {
            $admin_id = $_SESSION['admin_id'];
            //根据admin_id找到user_id
            $user_id = Db('admin')->where('admin_id', $admin_id)->value('user_id');

            if ($user_id) {
                $user_data = db('users')->field('user_id,nickname')->where('user_id', $user_id)->find();
                //根据user_id找到pid
                $res = db('users')->field('user_id,nickname')->where('pid', $user_id)->select();

                $this->assign('list', $res);
                $this->assign('user_data', $user_data);
                return $this->fetch();
            } else {
                $this->error('该用户没有关联user_id');
            }
        }

    }


    //点击树状图的子集查询 直推人数
    public function xiaji_tree()
    {

        $input = input();
        if ($input['user_id']) {
            $res = db('users')->field('user_id,nickname')->where('pid', $input['user_id'])->select();
        } else {
            $res = "";
        }

        return $res;


    }


    //点击树状图的子集查询  大区/总代 权限看到的直推关系图
    public function tree_guanli()
    {

        //获取session底下的 admin_id
        if ($_SESSION['admin_id']) {
            $admin_id = $_SESSION['admin_id'];
            //根据admin_id找到user_id
            $user_id = Db('admin')->where('admin_id', $admin_id)->value('user_id');

            if ($user_id) {
                $user_data = db('users')->field('user_id,nickname,user_type')->where('user_id', $user_id)->find();

                $res = Db::table('tp_n_user_management')
                    ->alias('a')
                    ->join('users b', 'a.user_id = b.user_id')
                    ->where('a.management_id', $user_id)
                    ->field('a.management_id,b.user_id,b.user_type,b.nickname')
                    ->select();

                $this->assign('list', $res);
                $this->assign('user_data', $user_data);

                return $this->fetch();
            } else {
                $this->error('该用户没有关联user_id');
            }
        }

    }


    //大区/总代 权限看到的直推关系图
    public function xiaji_tree_guanli()
    {
        $input = input();
        if ($input['user_id']) {

            $res = Db::table('tp_n_user_management')
                ->alias('a')
                ->join('users b', 'a.user_id = b.user_id')
                ->where('a.management_id', $input['user_id'])
                ->field('b.user_id,b.user_type,b.nickname')
                ->select();

            $list = array();
            foreach ($res as $k => $v) {
                $_t = $v;
                if ($v['user_type'] == 0) {
                    $_t['user_type_name'] = '会员';

                }
                if ($v['user_type'] == 1) {
                    $_t['user_type_name'] = '健康大使';

                }

                $list[] = $_t;

            }


        } else {
            $list = "";
        }
        return $list;

    }

    /*
     *资金明细
     * 
     * */
    public function amountLog()
    {

        $input = input('');
        $user_id = $input['id'];

        $where = array();
        $where['user_id'] = ['>', '0'];
        $where['user_id'] = $user_id;

        $count = M('n_amount_log')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_amount_log')->where($where)
            ->order('id desc')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        if ($lists) {
            foreach ($lists as $k => $v) {
                //获得佣金的用户信息
                $get_user = M('users')->where('user_id', $v['user_id'])->find();
                $lists[$k]['get_user'] = $get_user;
                //输出佣金的用户
                $obj_user = M('users')->where('user_id', $v['obj_user_id'])->find();
                $lists[$k]['obj_user'] = $obj_user;

                if ($v['reward_type'] == '0') {
                    $lists[$k]['reward_type_name'] = '不参与';

                }
                if ($v['reward_type'] == '1') {
                    $lists[$k]['reward_type_name'] = '分销奖';

                }
                if ($v['reward_type'] == '2') {
                    $lists[$k]['reward_type_name'] = '管理奖';

                }
                if ($v['reward_type'] == '3') {
                    $lists[$k]['reward_type_name'] = '上荐奖';

                }

                if ($v['type'] == '1') {
                    $lists[$k]['type_name'] = '马克币';

                }

                if ($v['type'] == '2') {
                    $lists[$k]['type_name'] = '功德';

                }

                if ($v['type'] == '3') {
                    $lists[$k]['type_name'] = '佣金';

                }

                //关联订单
                $obj = json_decode($v['obj'], true);
                $lists[$k]['order_sn'] = '';
                if ($obj['order_id']) {
                    $order = M('order')->where('order_id', $obj['order_id'])->find();
                    $lists[$k]['order_sn'] = $order['order_sn'];
                }

            }
        }


        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    //设置为特殊账户（所有通过申请的健康大使都会归这个账户统一分配）
    public function superAccount()
    {
        $userId = input('user_id');

        if (empty($userId)) {
            $this->error('用户ID为空');
        }

        $userInfo = db('users')->where('user_id', $userId)->find();

        if ($userInfo['user_type'] !== 3) {
            $this->error('非大区无法设置为特殊账户');
        }

        //将原特殊账户更新为普通大区
        $oldSuper = db('users')->where('super_account', '1')->find();

        if ($oldSuper) {
            if ($oldSuper['user_id'] == $userId) {
                $this->error('所设置特殊和原有特殊账户一致');
            }
            //将原有特殊账户的所有下级转移至更改后的特殊账户
//            $upList = db('n_user_management')->where('management_id', $oldSuper['user_id'])->update(['management_id' => $userId]);
//            dump($upList);die;
//            if ($upList === false) {
//                $this->error('转移原有用户出错');
//            }

            $upOld = db('users')->where('super_account', '1')->update(['super_account' => '0']);
            if ($upOld === false) {
                $this->error('设置出错');
            }

        }

        $data = array(
            'super_account' => 1,
        );

        $upUser = db('users')->where('user_id', $userId)->update($data);

        if ($upUser === false) {
            $this->error('设置失败');
        } else {
            $this->success("设置成功!", U('index'), 3);
        }
    }


    /*
     * 用户银行卡信息列表
     *
     * */
    public function bankList()
    {
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['name']) {
                $where['name'] = array('like', "%$input[name]%");
            }

            if ($input['user_id'] && $input['user_id']) {
                $where['user_id'] = $input['user_id'];
            }

            if ($input['start_time'] && $input['end_time']) {
                $where['create_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }
        }


        $count = M('n_user_bank')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_user_bank')->where($where)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($lists as $k => $v) {
            $user = db('users')->where('user_id', $v['user_id'])->find();
            $lists[$k]['nickname'] = $user['nickname'];

            //银行名称
            $bank = db('n_bank')->where('id', $v['bank_id'])->find();
            $lists[$k]['bank_name'] = $bank['name'];
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }


    /*
     * 币种流水列表
     *
     * */
    public function amountList()
    {
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['user_id'] && $input['user_id']) {
                $where['user_id'] = $input['user_id'];
            }

            if ($input['start_time'] && $input['end_time']) {
                $where['create_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }

            if ($input['desc']) {
                $where['desc'] = array('like', "%$input[desc]%");
            }

        }

        $count = M('n_amount_log')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_amount_log')->where($where)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($lists as $k => $v) {
            $user = db('users')->where('user_id', $v['user_id'])->find();
            $lists[$k]['nickname'] = $user['nickname'];
        }


        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    /**
     *
     * 佣金流水表
     * $where 查询条件
     * where条件参考文章:https://www.cnblogs.com/ccw869476711/p/10838061.html
     *
     * */
    public function commissionList(){


        //统计平台历史产生的佣金（总和）
        $countCommission = Db::name('n_amount_log')
            ->where('money',['egt',0])
            ->sum('money');

        //用户已提现佣金（总和）
        $withdraw_money = Db::name('n_withdraw')
            ->where('status',2)
            ->sum('withdraw_money');

        //用户待返佣金（总和）
        $countAll = Db::name('users')
            ->sum('wait_money');

        //统计平台历史产生的佣金1（总和）
        $total_wait_money= Db::name('users')
//            ->where('money',['egt',0])
            ->sum('total_wait_money');

        $this->assign('countCommission',$countCommission);
        $this->assign('withdraw_money',$withdraw_money);
        $this->assign('countAll',$countAll);
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['user_id'] && $input['user_id']) {
                $where['user_id'] = $input['user_id'];
            }

            if ($input['start_time'] && $input['end_time']) {
                $where['create_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }

            if ($input['desc']) {
                $where['desc'] = array('like', "%$input[desc]%");
            }
            //会员身份
            if($input['user_type']){
                $users = db('users')->field('user_id')->where('user_type', $input['user_type'])->select();
                if($users){
                    $where['user_id']=['in',$users];
                }
//                dump($where);die;
            }
            //会员昵称
            if($input['nickname']){
                $users = db('users')->field('user_id')->where('nickname', $input['nickname'])->select();
                if($users){
                    $where['user_id']=['in',$users];
                }

            }
            //会员手机号
            if($input['mobile']){
                $users = db('users')->field('user_id')->where('mobile', $input['mobile'])->select();
                if($users){
                    $where['user_id']=['in',$users];
                }

            }
            //事项类型（1-收入 2-支出）
            if($input['type']){
                $where['money'] = array('egt',0);   // money >= 0;
            }

        }

        $count = M('n_amount_log')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_amount_log')->where($where)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($lists as $k => $v) {
            $user = db('users')->where('user_id', $v['user_id'])->find();
            $lists[$k]['mobile'] = $user['mobile'];
            $lists[$k]['user_type'] = userType($user['user_type']) ;
            $lists[$k]['nickname'] = $user['nickname'];
        }

//        dump($lists);die;
        $this->assign('page', $page->show());
        $this->assign('countCommission', $countCommission);
        $this->assign('countAll', $countAll);
        $this->assign('withdraw_money', $withdraw_money);
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }


    public function ajaxcommissionList(){

        //统计平台历史产生的佣金（总和）
        $countCommission = Db::name('n_amount_log')
            ->where('money',['egt',0])
            ->sum('money');

        //用户已提现佣金（总和）
        $withdraw_money = Db::name('n_withdraw')
            ->where('status',2)
            ->sum('withdraw_money');

        //用户待返佣金（总和）
        $wait_money = Db::name('users')
            ->sum('wait_money');
        $timegap = I('timegap');
        if ($timegap) {
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        } else {
            //@new 新后台UI参数
            $begin = strtotime(I('start_time'));
            $end = strtotime(I('end_time'));
        }
        // 搜索条件
        $where = array();
        //1用户id
        I('user_id') ? $where['user_id'] = I('user_id') : false;
        //2会员身份
        if(I('user_type')){

            $users = db('users')->field('user_id')->where('user_type', I('user_type'))->select();
            if($users){
                $arr = [];
                foreach($users as $v){
                    $arr[]=$v['user_id'];
                }
                $where['user_id']=['in',$arr];
            }
        }
        //3事项类型（1-收入 2-支出）
        if(I('type')==1){
            $where['money'] = array('egt',0);   // money >= 0;
        }elseif(I('type')==2){
            $where['money'] = array('lt',0);   // money < 0;
        }
        //4会员昵称
        if(I('nickname')){
            $nickname = I('nickname');
            $users = db('users')->field('user_id')
                ->where('nickname','like',"%{$nickname}%")
//                ->where('nickname', I('nickname'))
                ->select();
            if($users){
                $arr = [];
                foreach($users as $v){
                    $arr[]=$v['user_id'];
                }
                $where['user_id']=['in',$arr];
            }

        }
        //5会员手机号
        if(I('mobile')){
            $mobile = I('mobile');
            $users = db('users')->field('user_id')
                ->where('mobile','like',"%{$mobile}%")
//                ->where('mobile', I('mobile'))
                ->select();
            if($users){
                $arr = [];
                foreach($users as $v){
                    $arr[]=$v['user_id'];
                }
                $where['user_id']=['in',$arr];
            }

        }
        //6时间
        if ($begin && $end) {
            $where['create_time'] = array('between', [$begin,$end]);
        }



        $count = M('n_amount_log')->where($where)->count();
        $page = new AjaxPage($count, 10);
        $lists = M('n_amount_log')->where($where)->order('create_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($lists as $k => $v) {
            $user = db('users')->where('user_id', $v['user_id'])->find();
            $lists[$k]['mobile'] = $user['mobile'];
            $lists[$k]['user_type'] = userType($user['user_type']) ;
            $lists[$k]['nickname'] = $user['nickname'];
        }
//        return $lists;
//        dump($lists);die;
//        dump($wait_money);die;
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('countCommission', $countCommission);
        $this->assign('waitMoney', $wait_money );
        $this->assign('withdraw_money', $withdraw_money);
        $this->assign('lists', $lists);

        return $this->fetch();
    }


    /**
     * 佣金报表导出
     * $where 查询条件
     *
     *
    */
    /**
     * 用户列表导出
     * 根据筛选条件：注册时间、手机号、昵称、id
     */
    public function export_commission()
    {
        $timegap = I('timegap');
        if ($timegap) {
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        } else {
            //@new 新后台UI参数
            $begin = strtotime(I('start_time'));
            $end = strtotime(I('end_time'));
        }
        // 搜索条件
        $where = array();
        //1用户id
        I('user_id') ? $where['user_id'] = I('user_id') : false;
        //2会员身份
        if(I('user_type')){

            $users = db('users')->field('user_id')->where('user_type', I('user_type'))->select();
            if($users){
                $arr = [];
                foreach($users as $v){
                    $arr[]=$v['user_id'];
                }
                $where['user_id']=['in',$arr];
            }
        }
        //3事项类型（1-收入 2-支出）
        if(I('type')==1){
            $where['money'] = array('egt',0);   // money >= 0;
        }elseif(I('type')==2){
            $where['money'] = array('lt',0);   // money < 0;
        }
        //4会员昵称
        if(I('nickname')){
            $nickname = I('nickname');
            $users = db('users')->field('user_id')
                ->where('nickname','like',"%{$nickname}%")
//                ->where('nickname', I('nickname'))
                ->select();
            if($users){
                $arr = [];
                foreach($users as $v){
                    $arr[]=$v['user_id'];
                }
                $where['user_id']=['in',$arr];
            }

        }
        //5会员手机号
        if(I('mobile')){
            $mobile = I('mobile');
            $users = db('users')->field('user_id')
                ->where('mobile','like',"%{$mobile}%")
//                ->where('mobile', I('mobile'))
                ->select();
            if($users){
                $arr = [];
                foreach($users as $v){
                    $arr[]=$v['user_id'];
                }
                $where['user_id']=['in',$arr];
            }

        }
        //6时间
        if ($begin && $end) {
            $where['create_time'] = array('between', [$begin,$end]);
        }


        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">身份</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">事项类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">时间</td>';

        $strTable .= '</tr>';
        $count = M('n_amount_log')->where($where)->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $userList = M('n_amount_log')->where($where)->order('user_id')->limit($start . ',' . $end)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $user = db('users')->where('user_id,mobile,nickname', $v['user_id'])->find();
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . (userType($user['user_type'])) . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $user['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $user['nickname'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . ($val['money'] >=0 ? "收入": "支出") . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . null. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['create_time']) . '</td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'commission_' . $i);
        exit();
    }


    public function buyVip()
    {
        //获取身份卡图片
        $goods_config = M('n_goods_config')
            ->where('key', 'user_vip_pic')
            ->find();
        if (IS_POST) {
            $data['value'] = input('img_path');
            $up = M('n_goods_config')
                ->where('key', 'user_vip_pic')
                ->update($data);
            if ($up !== false) {
                $this->success('添加成功', 'buyVip');
            }
        }

        $this->assign('pic', $goods_config['value']);
        return $this->fetch();

    }

    public function qrcoder()
    {
        //获取1号客服
        $service1 = M('n_goods_config')
            ->where('key', 'service1_pic')
            ->find();

        //获取2号客服
        $service2 = M('n_goods_config')
            ->where('key', 'service2_pic')
            ->find();

        if (IS_POST) {
            $data['value'] = input('original_img');

            $up = M('n_goods_config')
                ->where('key', 'service1_pic')
                ->update($data);

            if ($up) {
                $data2['value'] = input('tui_original_img');
                $up2 = M('n_goods_config')
                    ->where('key', 'service2_pic')
                    ->update($data2);
            }

            if ($up2 !== false) {
                $this->success('添加成功', 'qrcoder');
            }
        }

        $this->assign('pic', $service1['value']);
        $this->assign('pic2', $service2['value']);
        return $this->fetch();

    }


    //查看团队列表
    public function teamList2()
    {

        if(input('user_id')){

            $userId = input('user_id');
            //当前用户下级
            $userList = M('n_management')->where('management_id', $userId)->select();

        }elseif(input('mobile')){

            $mobile = input('mobile');
            $userInfo = M('users')->where('mobile',$mobile)->find();
            $userId = $userInfo['user_id'];
            //当前用户下级
            $userList = M('n_management')->where('management_id', $userId)->select();

        } else{

            $adminInfo = M('admin')->where('admin_id', session('admin_id'))->find();
            $userId = $adminInfo['user_id'];

            //当前用户下级
            $userList = M('n_management')->where('management_id', $userId)->select();

        }

        //用户信息
        $user = M('users')->where('user_id', $userId)->find();
        $orderVip1 = M('order_vip')->where('user_id',$user['user_id'])->where('pay_status',1)->find();
        if(!empty($orderVip1)){
            $user['become_vip_time'] = $orderVip1['pay_time'];
        }else{
            $user['become_vip_time'] = null;
        }
        $start = 0;
        //统计总数量
        $num = $this->countNum($userId, $start);




        foreach ($userList as &$v) {
            $userInfo = M('users')->where('user_id', $v['user_id'])->find();
            $v['real_name'] = $userInfo['real_name'];
            $v['user_type'] = $userInfo['user_type'];

            $orderVip = M('order_vip')->where('user_id',$v['user_id'])->where('pay_status',1)->find();
            if(!empty($orderVip)){
                $v['become_vip_time'] = $orderVip['pay_time'];
            }else{
                $v['become_vip_time'] = null;
            }

        }

        $this->assign('num', $num);
        $this->assign('list', $userList);
        $this->assign('userInfo', $user);

        return $this->fetch();
    }

    //查看团队列表数据
    public function ajaxTeamList()
    {
        $input = input();
//        dump($input);die;
        if ($input['user_id']) {

            $res = Db::table('tp_n_management')
                ->alias('a')
                ->join('users b', 'a.user_id = b.user_id')
                ->where('a.management_id', $input['user_id'])
                ->field('b.user_id,b.user_id,b.user_type,b.real_name,b.become_vip_time')
                ->select();

            $list = array();
            foreach ($res as $k => $v) {
                $_t = $v;
                $_t['time'] = date("Y-m-d", $v['become_vip_time']);
                if ($v['user_type'] == 0) {
                    $_t['user_type_name'] = '普通消费者粉丝';

                }
                if ($v['user_type'] == 1) {
                    $_t['user_type_name'] = '会员';

                }

                if ($v['user_type'] == 2) {
                    $_t['user_type_name'] = '代理';

                }
                $orderVip = M('order_vip')->where('user_id',$v['user_id'])->where('pay_status',1)->find();
                if(!empty($orderVip)){
                    $_t['become_vip_time'] = date("Y-m-d H:i:s",$orderVip['pay_time']);
                }else{
                    $_t['become_vip_time'] =  null;
                }

                $list[] = $_t;
            }

        } else {
            $list = "";
        }
        return $list;
    }

    //递归统计数量
    public function countNum($pid, &$num)
    {
        $countNext = M('n_management')->where('management_id', $pid)->field('id,user_id')->select();
        if (!empty($countNext)) {
            foreach ($countNext as $v) {
                $num++;
                $child = $this->countNum($v['user_id'], $num);
            }
        }
        return $num;
    }

    /*
     * liaoyanqing
     * 查看购买身份卡记录列表
     * 
     * */
    public function vipIndex()
    {
        $input = input('');
        $where = array();
        if ($input['id']) {
            //查询条件
            if ($input['start_time'] && $input['end_time']) {
                $where['add_time'] = array('between', array(strtotime($input['start_time']), strtotime($input['end_time'])));
            }

            if ($input['user_id']) {
                $where['user_id'] = $input['user_id'];
            }
        }

        $count = M('order_vip')
            ->where($where)
            ->count();

        $page = new Page($count);
        $lists = M('order_vip')
            ->where($where)
            ->order('add_time desc')
            ->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($lists as $k => $v) {
            $userInfo = M('users')->where('user_id', $v['user_id'])->find();
            $lists[$k]['userInfo'] = $userInfo;
        }
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);
        return $this->fetch();
    }


}