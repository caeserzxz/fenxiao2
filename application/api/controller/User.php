<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/12
 * Time: 10:19
 */
namespace app\api\controller;
use app\common\logic\UsersLogic;
use app\common\model\Users;
use My\DataReturn;
use think\Exception;
use think\Page;
use app\common\logic\DistributLogic;
use app\common\logic\SearchWordLogic;
use app\home\controller\check_validate_code;

class User extends Base{

    //需要检查登录的页面
    public function __construct()
    {
        parent::__construct();
        //$this->user_id = 2581;
        //不需验证登录的方法
         $nologin = [];

         if(!in_array(ACTION_NAME,$nologin))
         {
             $this->checkLogin();
             // DistributLogic::rebateDivide($this->user_id); //初始佣金分佣
         }
    }

    public function index()
    {
        //$this->user_id;
        $user_id = $this->user_id;
        $users = M('users')->where('user_id', $user_id)->field('head_pic,nickname,user_money,distribut_money,pay_points')->select();
        DataReturn::returnBase64Json(200,'校验成功');
    }

    //‘我的’首页
    public function my_index(){

        try{
            //$this->user_id;
            //$prefix = request()->domain();图片前缀
            $user_id = $this->user_id;
            $users = M('users')->where('user_id',$user_id)->field('sex,birthday,email,mobile,address_id,head_pic,nickname,user_money,distribut_money,pay_points')->find();
            //查优惠卷
            // $users['coupon'] = M('coupon_list')->where('status',1)->where('uid',$user_id)->count();
            $users['coupon'] = M('coupon_list')
                ->alias('cl')
                ->join('coupon c','c.id = cl.cid','LEFT')
                ->where(['c.status'=>1 , 'cl.uid'=>$user_id])
                ->count();
            if(!$users){
                throw new Exception("系统繁忙，稍后再试！");

                // echo  M('coupon_list')->getlastsql();
            }
            // dump($users);die;
            DataReturn::returnJson('200','获取数据成功！',$users);
        }catch(\Exception  $e){
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    // ‘我的’我的收藏
    public function collect_list(){

        try{
            //$this->user_id;
            $prefix = request()->domain();//图片前缀
            $user_id = $this->user_id;
            // dump($user_id);
            // $aaa  = M('aaa')->data('text',$user_id)->insert();
            $count = M('goods_collect')->where('user_id',$user_id)->count();// 查询满足要求的总记录数
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            $collect_list = M('goods_collect')->where('user_id',$user_id)->field('goods_id')->limit($start , $pagesize)->select();
            $_list = [];
            $_collect_list = [];
            if($collect_list){
                foreach ($collect_list as $key => $val) {
                    $details = M('goods')->where('goods_id',$val['goods_id'])->field('original_img,goods_name,shop_price,goods_id')->find();
                    if(!empty($details)){
                        $_t['goods_name'] = $details['goods_name'];
                        $_t['shop_price'] = $details['shop_price'];
                        $_t['goods_id'] = $details['goods_id'];
                        $_t['original_img'] = $prefix . $details['original_img'];
                        $_collect_list[] = $_t;
                    }
                }
            }else{
                throw new Exception("系统繁忙，稍后再试！");
            }
            $_list['lists'] = $_collect_list;
            DataReturn::returnJson('200','获取数据成功！',$_list);
        }catch(\Exception  $e){
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //‘我的’我的收藏->删除
    public function collect_list_del(){
        try{
            $goods_id = I('goods_id');
            if($goods_id){
                $where = [];
                $where['goods_id'] = $goods_id;
                $where['user_id'] = $this->user_id;
                $del = M('goods_collect')->where($where)->delete();
                if($del){
                    // Cache::rm('TPSHOP_CACHE_TIME');
                    DataReturn::returnJson('200','删除数据成功！');
               } else {
                throw new Exception("删除失败！");
               }

            }else{
                throw new Exception("系统繁忙，稍后再试！");
            }
        }catch(\Exception $e){
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //‘我的’我的分享
    public function share_list(){
        try{
            $prefix = request()->domain();//图片前缀
            $user_id = $this->user_id;
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            $user = M('share')->where(['user_id'=>$user_id])->limit($start , $pagesize)->field('goods_id,original_img,goods_name,shop_price')->select();
            // dump($user);
            foreach ($user as $key => $val) {
                    $user[$key]['original_img'] = $prefix. $val['original_img'];
            }

            $data['lists'] = $user;
            DataReturn::returnJson('200','获取数据成功！',$data);
        }catch(\Exception $e){
            DataReturn::returnJson('400',$e->getMessage());
        }

    }

    //‘我的’我的分享->清空
    public function share_empty(){
        try{
            $user_id = $this->user_id;
            $res = M('share')->where(['user_id' => $user_id])->delete();
            DataReturn::returnJson('200','删除数据成功！');
        }catch(\Exception $e){
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //‘我的’我的评论
    public function zpevaluate_list(){
        try {
            $user_id = $this->user_id;
            if($user_id){
                    $pagesize = C('PAGESIZE');  //每页显示数
                    $pages = I('pages') ? I('pages') : 1;
                    $start =  ($pages-1) * $pagesize;
                    $user = M('comment')->where(['user_id'=>$user_id])->limit($start , $pagesize)->field('user_id,goods_id,deliver_rank,goods_rank,service_rank,img,content')->select();
                    $_user = array();
                    foreach ($user as $key => $val) {

                        $_u = $val;
                        $_u['head_pic'] = M('users')->where(['user_id'=>$val['user_id']])->value('head_pic');
                        $_u['nickname'] = M('users')->where(['user_id'=>$val['user_id']])->value('nickname');
                        $rank = ($val['deliver_rank'] + $val['goods_rank'] + $val['service_rank']) / 3;
                        $_rank = round($rank,0);
                        // dump($_rank);

                        if($_rank == 0){
                            $_u['starts_img'] = request()->domain() . "/public/images/start/stars0.gif";
                        }elseif($_rank == 1){
                            $_u['starts_img'] = request()->domain() . "/public/images/start/stars1.gif";
                        }elseif($_rank == 2){
                            $_u['starts_img'] = request()->domain() . "/public/images/start/stars2.gif";
                        }elseif($_rank == 3){
                            $_u['starts_img'] = request()->domain() . "/public/images/start/stars3.gif";
                        }elseif($_rank == 4){
                            $_u['starts_img'] = request()->domain() . "/public/images/start/stars4.gif";
                        }else{
                            $_u['starts_img'] = request()->domain() . "/public/images/start/stars5.gif";
                        }
                        // dump($_u['image']);
                        $_imglist= [];
                        $_imgarray = unserialize($val['img']); // 晒单图片
                        if($_imgarray){
                            for ($i=0; $i < count($_imgarray ); $i++) {
                                $_c = request()->domain() .$_imgarray[$i];
                                $_imglist[] =$_c;
                            }
                        }

                        $_u['img'] =  $_imglist;
                        $_u['add_time'] = $val['add_time'] != 0 ? date('Y-m-d H:i:s', $val['add_time']) : '0000-00-00 00:00:00';
                        // $_u['star_images'] = star($_u['rank']);
                        $_u['original_img'] = request()->domain() . (M('goods')->where(['goods_id'=>$val['goods_id']])->value('original_img'));
                        $_u['goods_name'] = M('goods')->where(['goods_id'=>$val['goods_id']])->value('goods_name');
                        $_u['shop_price'] = M('goods')->where(['goods_id'=>$val['goods_id']])->value('shop_price');
                        // $_u['spec_key_name'] = M('order_goods')->where(['goods_id'=>$val['goods_id']])->where(['order_id'=>$val['order_id']])->value('spec_key_name');
                        $_user[] = $_u;

                    }
                }else{
                    throw new Exception("系统繁忙，稍后再试！");
                }
                $return = [];
                $return['lists'] = $_user;
                DataReturn::returnJson('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }


    // ‘我的’我的分销
    public function distribution_list(){
        try {
            $user_id = $this->user_id;
            $users = M('users')->where(['user_id'=>$user_id])->field('head_pic,nickname,distribut_money,pay_points,first_leader,second_leader,third_leader')->find();
            $users['head_pic'] = request()->domain() . $users['head_pic'];//头像处理
            $users['first'] = M('users')->where(['first_leader'=>$user_id])->count('first_leader');//子用户第一层
            $users['second'] = M('users')->where(['second_leader'=>$user_id])->count('second_leader');//子用户第二层
            $users['third'] = M('users')->where(['third_leader'=>$user_id])->count('third_leader');//子用户第三层
            $users['count'] = $users['first']+$users['second']+$users['third'];

            $list = M('users')->field('user_id')->whereOR(['first_leader'=>$user_id])->whereOR(['second_leader'=>$user_id])->whereOR(['third_leader'=>$user_id])->select();

            $user_arr = array_column($list, 'user_id');
            $users['purchase']=M('order')->where('user_id','IN',$user_arr)->where('order_status','in',['2','4'])->where('shipping_status&pay_status',1)->count();
            // echo M('order')->getlastsql();die;
            $return = [];
            $return['lists'] = $users;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    // ‘我的’我的分销->我的粉丝
    public function my_fans(){
        try {
            $prefix = request()->domain();//图片前缀
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            $user_id = $this->user_id;
            $type_value = I('type_value') ? I('type_value') : 4;
            $account = I('account');

             $where = [];
             if($account){
                if(!preg_match("/[^\d-., ]/",$account)){
                    $where['user_id'] = $account;
                }else {
                    $where['nickname'] = ['like','%'.$account.'%'];
                }
             }
            if($type_value == 4){
                $user = M('users')->limit($start , $pagesize)->where($where)->where('first_leader|second_leader|third_leader',$user_id)->field('user_id')->select();
                // echo M('users')->getlastsql();die;
            }elseif($type_value == 1){
                $user = M('users')->limit($start , $pagesize)->where('first_leader',$user_id)->where($where)->field('user_id')->select();
            }elseif($type_value == 2){
                $user = M('users')->limit($start , $pagesize)->where(['second_leader'=>$user_id])->where($where)->field('user_id')->select();
            }elseif($type_value == 3){
               $user = M('users')->limit($start , $pagesize)->where(['third_leader'=>$user_id])->where($where)->field('user_id')->select();
            }
            $_user = array();

            /** @var Users[] $user */
            foreach ($user as $key => $value) {
                $_l = $value;
                $head_pic = M('users')->where('user_id',$value['user_id'])->value('head_pic');
                if($head_pic){
                    $_l['head_pic'] = $prefix.$head_pic;
                }else{
                    $_l['head_pic'] = api_img_url();

                }

                // $memberorder = M('order')->where(['user_id'=>$value['user_id']])->where('order_status','in',['2','4'])->where('shipping_status&pay_status',1)->field(['order_id'])->find();

                if ($value->isPurchased()) {
                    $_l['memberorder']= 1;

                } else {
                    $_l['memberorder']= 0;
                }

                $_user[] = $_l;
            }
            $return = [];
            $return['lists'] = $_user;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }


    // 粉丝详细信息
    public function vermicelli_details(){
        try {
            $user_id = I('user_id');
            $prefix = request()->domain();//图片前缀
            $user = M('users')->where('user_id',$user_id)->field('head_pic,nickname,reg_time,distribut_money,pay_points,first_leader,second_leader,third_leader')->find();
            if(!$user){
                throw new Exception("没有此用户");
            }
            $user['head_pic'] = $prefix . $user['head_pic'];
            $user['reg_time'] = $user['reg_time'] != 0 ? date('Y.m.d', $user['reg_time']) : '0000.00.00';

            if($this->user_id == $user['first_leader']){
                $user['layer'] = "第一层";
            }elseif($this->user_id == $user['second_leader']){
                $user['layer'] = "第二层";
            }elseif($this->user_id == $user['third_leader']){
                $user['layer'] = "第三层";
            }else{
                $user['layer'] = "不是本用户下级";
            }
            $all_fans = M('users')->where('first_leader|second_leader|third_leader',$user_id)->count();
            $user['all_fans'] = $all_fans;

            $one_fans = M('users')->where('first_leader',$user_id)->count();
            $user['one_fans'] = $one_fans;

            $two_fans = M('users')->where('second_leader',$user_id)->count();
            $user['two_fans'] = $two_fans;

            $three_fans = M('users')->where('third_leader',$user_id)->count();
            $user['three_fans'] = $three_fans;

            $return = [];
            $return['lists'] = $user;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }


    // 个人资料
    public function user_info(){
        try {
            $_user = M('users')
            ->field('user_id,email,sex,birthday,user_money,frozen_money,distribut_money,underling_number,pay_points,address_id,reg_time,last_login,last_ip,qq,mobile,mobile_validated,oauth,unionid,head_pic,province,city,district,nickname,email_validated,level,discount,total_amount,is_lock,is_distribut,first_leader,second_leader,third_leader')
            ->where('user_id',$this->user_id)
            ->find();
            $_user['reg_time'] = date('Y-m-d H:i:s', $_user['reg_time']);
            $_user['birthday'] = date('Y-m-d', $_user['birthday']);
            $sex_text =[0=>'保密',1=>'男',2=>'女'];
            $_user['sexname'] =$sex_text[$_user['sex']] ;
            if(!$_user['email']){
                $_user['email'] = '暂无绑定邮箱';
            }

            $_user['coupon'] = M('coupon_list')
                ->alias('cl')
                ->join('coupon c','c.id = cl.cid','LEFT')
                ->where(['c.status'=>1 , 'cl.uid'=>$this->user_id])
                ->count();
            // 待支付
            $_user['waitpay'] = M('order')->where('user_id',$this->user_id)->where('pay_status',0)->where('order_status',0)->where('pay_code', NEQ ,"cod")->count();
            // 待发货
            $_user['waitsend'] = M('order')->where('user_id',$this->user_id)->where('shipping_status',0)->where('order_status','in',['0','1'])->where('pay_status=1 or pay_code = "cod"')->count();
                // echo M('order')->getlastsql();die;
            // 待收货
            $_user['waitreceive'] = M('order')->where('user_id',$this->user_id)->where('shipping_status',1)->where('order_status',1)->count();
            // 待评价
            $_user['waitccomment'] = M('order')->where('user_id',$this->user_id)->where('shipping_status',1)->where('order_status',2)->count();
            //退换货
            $_user['cahnge_goods'] = M('return_goods')->where('user_id',$this->user_id)->where('type','in',['0','1'])->count();


            $return = [];
            $return['lists'] = $_user;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
            // DataReturn::returnJson('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //个人资料-》修改
    public function modify_user_nickname(){
        try {
            $data = [];
            $nickname = I('nickname');
            $sex = I('sex');
            $birthday = I('birthday');
            $email = I('email');
            $mobile = I('mobile');
            if($nickname){
                $data['nickname'] = $nickname;
            }
            if($birthday){
                $data['birthday'] = strtotime($birthday);
            }
            if(in_array($sex,[0,1,2]))
            {
                $data['sex'] = $sex;
            }
            if($email){
                $yz = M('users')->where('user_id',$this->user_id)->where('email',$email)->count();
                if($yz ==0){
                    $data['email'] = $email;
                }else{
                    throw new Exception("此邮箱已被使用");
                }
            }
            if($mobile){
                $yz = M('users')->where('user_id',$this->user_id)->where('mobile',$mobile)->count();
                 if (preg_match('/^1\d{9}$/',$mobile))
                 {
                   throw new Exception('您的手机号码不正确');
                 }
                if($yz ==0){
                    $data['mobile'] = $mobile;
                }else{
                    throw new Exception("此手机号已被使用");
                }
            }
            $res = M('users')->where('user_id',$this->user_id)->update($data);
            if($res){
                DataReturn::returnJson('200','修改成功！',$return);
            }else{
                throw new Exception("修改失败！");
            }
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }

    }

    // 个人资料-》收货地址
    public function user_address(){
        try {
            $address = M('user_address')->where('user_id',$this->user_id)->order('is_default desc')->field('consignee,address_id,mobile,province,city,district,address,is_default')->select();
            $_address = array();
            foreach ($address as $key => $val) {
                $_a = $val;
                $_a['province'] = M('region')->where('id',$val['province'])->value('name');
                $_a['province_id'] = $val['province'];
                $_a['city'] = M('region')->where('id',$val['city'])->value('name');
                $_a['city_id'] = $val['city'];
                $_a['district'] = M('region')->where('id',$val['district'])->value('name');
                $_a['district_id'] = $val['district'];
                $_address[] = $_a;
            }
            $return = [];
            $return['lists'] = $_address;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //设置为默认地址
    public function default_user_address(){
        try {
            $address_id = I('address_id');
            if($address_id){
                $list = M('user_address')->where('user_id',$this->user_id)->update(['is_default'=>0]);
                $res = M('user_address')->where('address_id',$address_id)->update(['is_default'=>1]);
                DataReturn::returnJson('200','设置成功！');
            }else{
                throw new Exception("系统繁忙，请稍后再试！");
            }
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    // 个人资料-》删除收货地址
    public function delete_user_address(){
        try {
            $address_id = I('address_id');
            if($address_id){
                $res = M('user_address')->where('address_id',$address_id)->delete();
                if($res){
                    DataReturn::returnJson('200','删除数据成功！',$return);
                }else{
                    throw new Exception("删除失败！");
                }
            }else{
                throw new Exception("系统繁忙，请稍后再试！");
            }
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    // 个人资料-》修改收货地址
    public function modify_user_address(){
        try {
            $data = [];
            $address_id = I('address_id');
            $consignee = I('consignee');
            $mobile = I('mobile');
            $province = I('province');
            $city = I('city');
            $district = I('district');
            $address = I('address');
            $is_default = I('is_default');
            $data['consignee'] = $consignee;
            if(preg_match("/^1[34578]{1}\d{9}$/",$mobile)){
                $data['mobile'] = $mobile;
            }else{
                throw new Exception("手机号格式不正确！");
            }
            $data['province'] = $province;
            $data['city'] = $city;
            $data['district'] = $district;
            $data['address'] = $address;
            $data['is_default'] = $is_default;
            $default_no = 0;
            $default = 0;
            if($address_id){
               $res = M('user_address')->where('address_id',$address_id)->update($data);
                // echo M('user_address')->getlastsql();
               // dump($res);
               if($is_default == 1){
                    $default_no = M('user_address')->where('user_id',$this->user_id)->save(['is_default'=>0]);
                    $default = M('user_address')->where('user_id',$this->user_id)->where('address_id',$address_id)->save(['is_default'=>1]);
                }
                if($res || $default_no || $default){
                    DataReturn::returnJson('200','修改数据成功！');
                }else{
                    throw new Exception("暂无数据修改");
                }
            }else{
                throw new Exception("请传入address_id！");
            }

        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    // 个人资料-》添加收货地址
    public function add_user_address(){
        try {
            $data = [];
            $user_id = $this->user_id;
            $consignee = I('consignee');
            $province = I('province');
            $city = I('city');
            $district = I('district');
            $address = I('address');
            $mobile = I('mobile');
            $is_default = I('is_default');
            $data['user_id'] = $user_id;
            $data['consignee'] = $consignee;
            $res['province'] = M('region')->where('id',$province)->value('id');
            if($res['province']){
                $data['province'] = $province;
            }else{
                throw new Exception("省份id错误");
            }
            $res['city'] = M('region')->where('id',$city)->value('id');
            if($res['city']){
                $data['city'] = $city;
            }else{
                throw new Exception("市id错误");
            }
            $res['district'] = M('region')->where('id',$district)->value('id');
            if($res['district']){
                $data['district'] = $district;
            }else{
                throw new Exception("区id错误");
            }
            $data['address'] = $address;
            if(preg_match("/^1[34578]{1}\d{9}$/",$mobile)){
                $data['mobile'] = $mobile;
            }else{
                throw new Exception("手机号格式不正确！");
            }
            $data['is_default'] = $is_default;
            $res = M('user_address')->insertGetId($data);
            if($res){
                if($is_default == 1){
                    M('user_address')->where('user_id',$this->user_id)->save(['is_default'=>0]);
                    M('user_address')->where('user_id',$this->user_id)->where('address_id',$res)->save(['is_default'=>1]);
                }
            }else{
                throw new Exception("添加收货地址失败！");
            }
            DataReturn::returnJson('200','修改数据成功！');
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //账户与安全
    public function account_safe(){
        try {
            $res['mobile'] = M('users')->where('user_id',$this->user_id)->value('mobile');
            if($res){
                $res['is_binding'] = '已绑定';
            }else{
                $res['is_binding'] = '未绑定';
            }
            $return = [];
            $return['lists'] = $res;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    // 修改登录密码
    public function password_update(){
        try {
            $old_password =encrypt(I('old_password'));
            $new_password_one = I('new_password_one');
            $new_password_two = I('new_password_two');
            if($new_password_one == $new_password_two){
                $password['password'] =encrypt($new_password_one);
            }else{
                throw new Exception("两次密码输入错误！");
            }
            $res = M('users')->where('password',$old_password)->where('user_id',$this->user_id)->count();
            if($res){
                $info = M('users')->where('user_id',$this->user_id)->update($password);
                DataReturn::returnJson('200','修改密码成功！');
            }else{
                throw new Exception("原始密码输入错误！");
            }
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //余额总收支总收支
    public function account_type(){
        $tabselectdata = [];
        $tabselectdata[] = ['id' => 1,'name' => '收入', 'value'=>1];
        $tabselectdata[] = ['id' => 2,'name' => '支出', 'value'=>2];
        $plus_count = M('account_log')->where('user_id',$this->user_id)->where('user_money','>',' 0')->where('pay_points',0)->sum('user_money');// 总收入
        $plus_count = $plus_count ? $plus_count : 0;
        $minus_count = M('account_log')->where('user_id',$this->user_id)->where('user_money','<',' 0')->where('pay_points',0)->sum('user_money');// 总支出
        $minus_count = $minus_count ? $minus_count : 0;

        $return['minus_count'] = $minus_count;
        $return['plus_count'] = $plus_count;
        $return['tabselectdata'] = $tabselectdata;

        DataReturn::returnJson('200','请求数据成功！',$return);
    }

    //余额明细
    public function account_list(){
        try {
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            $tabselect = I('tabselect');
            $field = 'log_id,user_money,change_time,order_sn,desc';
            $where = [];
            $where['user_id'] = $this->user_id;
            $where['pay_points'] = '0';
            switch ($tabselect) {
                case 1:
                   $where['user_money'] = ['>',"0"];// 收入
                    break;
                case 2:
                    $where['user_money'] = ['<',"0"];// 支出
                    break;
                default:
                    # code...
                    break;
            }
            $res = M('account_log')->where($where)->order('change_time desc')->limit($start , $pagesize)->field($field)->select();
            $_res = array();
            foreach ($res as $key => $val) {
                $_r = $val;
                if($val['user_money'] > 0){
                    $_r['income_and_expenditure'] = "收入";
                    $_r['text_red'] = 'text-red';
                    $_r['user_money'] = '+' . $val['user_money'] ;
                }else{
                    $_r['income_and_expenditure'] = "支出";
                    $_r['text_red'] = '';
                }
                $_r['change_time'] = $val['change_time'] != 0 ? date('Y-m-d H:i:s', $val['change_time']) : '0000-00-00 00:00:00';
                $_res[] = $_r;
            }

            $return = [];
            $return['lists'] = $_res;
            DataReturn::returnJson('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //积分总收支
    public function income_expenditure(){
        $tabselectdata = [];
        $tabselectdata[] = ['id' => 1,'name' => '收入', 'value'=>1];
        $tabselectdata[] = ['id' => 2,'name' => '支出', 'value'=>2];
        $plus_count = M('account_log')->where('user_id',$this->user_id)->where('pay_points','>',' 0')->where('user_money',0)->sum('pay_points');// 总收入
        $minus_count = M('account_log')->where('user_id',$this->user_id)->where('pay_points','<',' 0')->where('user_money',0)->sum('pay_points');// 总支出
        $plus_count = $plus_count ? $plus_count : 0;
        $minus_count = $minus_count ? $minus_count : 0;

        $return['minus_count'] = $minus_count;
        $return['plus_count'] = $plus_count;
        $return['tabselectdata'] = $tabselectdata;
        DataReturn::returnJson('200','请求数据成功！',$return);
    }

    //积分明细
    public function points_list(){
        try {
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            $tabselect = I('tabselect');
            $where = [];
            $where['user_id'] = $this->user_id;
            $where['user_money'] = '0';
            switch ($tabselect) {
                case 1:
                   $where['pay_points'] = ['>',"0"];// 收入
                    break;
                case 2:
                    $where['pay_points'] = ['<',"0"];// 支出
                    break;
                default:
                    # code...
                    break;
            }
            $res = M('account_log')->where($where)->order('change_time desc')->limit($start , $pagesize)->field('log_id,pay_points,change_time,order_sn,desc')->select();
            $_res = array();
            foreach ($res as $key => $val) {
                $_r = $val;
                if($val['pay_points'] > 0){
                    $_r['income_and_expenditure'] = "收入";
                    $_r['text_red'] = 'text-red';
                    $_r['pay_points'] = '+' . $val['pay_points'] ;
                }else{
                    $_r['income_and_expenditure'] = "支出";
                    $_r['text_red'] = '';
                }
                $_r['change_time'] = $val['change_time'] != 0 ? date('Y-m-d H:i:s', $val['change_time']) : '0000-00-00 00:00:00';
                $_res[] = $_r;
            }

            $return = [];
            $return['lists'] = $_res;
            DataReturn::returnJson('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //充值记录
    public function recharge_list(){
        try {
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            $res = M('recharge')->where('user_id',$this->user_id)->field('pay_name,ctime,account,pay_status,order_id,order_sn')->order('ctime desc')->limit($start , $pagesize)->select();
            $_res = array();
            foreach ($res as $key => $val) {
                $_r = $val;
                if($val['pay_status'] == 0){
                    $_r['pay_status'] = "待支付";
                }elseif($val['pay_status'] == 1){
                    $_r['pay_status'] = "充值成功";
                }else{
                    $_r['pay_status'] = "交易关闭";
                }
                $_r['ctime'] = $val['ctime'] != 0 ? date('Y-m-d H:i:s', $val['ctime']) : '0000-00-00 00:00:00';
                $_res[] = $_r;
            }

            $return = [];
            $return['lists'] = $_res;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //提现手续费
    public function servicecharge()
    {
        $user_money = M('users')->where('user_id',$this->user_id)->value('user_money'); //账户余额
        $rate  = M('config')->where('name','bill_charge')->value('value');
        $data['rate'] = $rate;
        $data['user_money'] = $user_money;
        $price = I('price');
        if ($price) {
           if ($rate == 0) {
                $money = 0;
            } else {
                $money = round($price * $rate * 0.01,2);
                if ($money < 0.01) {
                    $money = 0.01;
                }
            }
            $data['taxfee'] = $money;
            $data['amount'] = $money + $price;
        }
        DataReturn::returnJson(200,'成功',$data);

    }

    /**
     * 申请提现
     */
    public function withdrawals()
    {
        try {
            $data = I('post.');
            $data = DataReturn::baseFormat($data['data']);
            // dump($data);
            $data['user_id'] = $this->user_id;
            $user = M('users')->where('user_id',$this->user_id)->find(); //账户余额
            if (!$this->user_id) {
                throw new Exception('请先登录');
            }
            if (!$data['money'] || !$data['bank_name'] || !$data['bank_card'] || !$data['realname']) {
                throw new Exception('系统繁忙，请稍后再试！');
            }
            if(encrypt($data['paypwd']) != $user['paypwd']){
                throw new Exception('支付密码错误');
            }
            $data['create_time'] = time();
            $distribut_min = tpCache('basic.min'); // 最少提现额度
            if ($data['money'] < $distribut_min) {
                throw new Exception('每次最少提现额度' . $distribut_min);
            }
            $amount = $data['money'] + $data['taxfee'];
            if ($amount > $user_money) {
                throw new Exception('提现金额超过账户余额');
            }
            $withdrawal = M('withdrawals')->where(['user_id' => $this->user_id, 'status' => 0])->sum('money');
            if ($user_money < ($withdrawal + $amount)) {
                throw new Exception('您有提现申请待处理，本次提现余额不足');
            }
            $add = M('withdrawals')->add($data);
            if (!$add) {
                throw new Exception('提交失败,联系客服!');
            }
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
        DataReturn::returnJson('200','已提交申请');
    }
    //提现记录
    public function withdrawals_list(){
        try {
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            $res = M('withdrawals')->where('user_id',$this->user_id)->field('id,create_time,money,status')->order('create_time desc')->limit($start , $pagesize)->select();
            $_res = array();
            foreach ($res as $key => $val) {
                $_r = $val;
                $status = ['0'=>'申请中','1'=>'审核通过','2'=>'付款成功','3'=>'付款失败','-1'=>'审核失败','-2'=>'删除作废'];
                $_r['status'] = $status[$val['status']];
                $_r['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s', $val['create_time']) : '0000-00-00 00:00:00';
                $_res[] = $_r;
            }

            $return = [];
            $return['lists'] = $_res;
            DataReturn::returnBase64Json('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    // 优惠卷
    public function coupon_list(){
        try {
            $tabselect = I('tabselect');
            $pagesize = C('PAGESIZE');  //每页显示数
            $pages = I('pages') ? I('pages') : 1;
            $start =  ($pages-1) * $pagesize;
            // dump($tabselect);
            $where['uid'] = $this->user_id;
            if($tabselect == 0){//0未使用1已使用2已过期
                $where['cl.status'] = 0;
                $where['c.status'] = 1;
            }elseif($tabselect == 1){
                $where['cl.status'] = 1;
                $where['c.status'] = 1;
            }elseif($tabselect == 2){
                $where['cl.status'] = 2;
                $where['c.status'] = 1;
            }
            $res = M('coupon_list')
                ->alias('cl')
                ->join('coupon c','c.id = cl.cid','LEFT')
                ->where(['cl.uid'=>$this->user_id])
                ->field('c.money,c.condition,c.name,c.use_type,c.use_end_time,c.status')
                ->limit($start , $pagesize)
                ->order('cl.send_time desc')
                ->where($where)
                ->select();
            // echo M('coupon_list')->getlastsql();exit;
            // dump($res);die;
            $_res = array();
            foreach ($res as $key => $val) {
                $_r = $val;
                if($tabselect ==2){
                    $_r['expired'] = 'expired';
                }else{
                    $_r['expired'] = '';
                }
                if($tabselect ==1){
                    $_r['use_end_time'] = '已使用';
                }else{
                    $_r['use_end_time'] = $val['use_end_time'] != 0 ? '限' . date('Y-m-d H:i:s', $val['use_end_time']) . '前使用': '0000-00-00 00:00:00';
                }
                if($val['status'] == 0){
                    $_r['status'] = '无效优惠卷';
                }
                $_r['money'] = ceil($val['money']);
                // $_r['status'] = $val['status'];
                // dump(ceil($_r['money']));
                $_r['condition'] = '满'. $val['condition'] . '元使用';
                $_r['name'] = $val['name'];
                $use_type = ['0'=>'全店通用','1'=>'指定商品可用','2'=>'指定分类商品可用'];
                $_r['use_type'] = $use_type[$val['use_type']];
                $_res[] = $_r;
            }

            $return = [];
            $return['lists'] = $_res;
            DataReturn::returnJson('200','请求数据成功！',$return);
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }


    //用户分享商品
    public function share_goods(){
        try {
            $goods_id = I('goods_id');
            if($goods_id){
                $goods_integral = tpCache('basic.goods_integral'); // 会员分享赠送积分
                $count = M('share')->where(["user_id"=>$this->user_id,"goods_id"=>$goods_id])->count();
                if ($count > 0){
                    throw new Exception('商品已分享');
                }else{
                    $goods_info = M('goods')->where('goods_id',$goods_id)->field('goods_name,original_img,shop_price')->find();
                    $goods_info['goods_id']=$goods_id;
                    $goods_info['user_id']=$this->user_id;
                    $goods_info['share_t']=time();
                    $goods_info['integral']= $goods_integral;  //分享积分
                    $re = M('share')->insert($goods_info);
                    if($re){
                        M('users')->where(['user_id'=>$this->user_id])->setInc('pay_points',$goods_integral);
                        DataReturn::returnJson('200','分享成功');
                    }
                }
            }else{
               throw new Exception('系统繁忙,稍后再试！');
            }
        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }



    //快递插件
    public function shipping(){
        $data = M('plugin')->where(['type'=>'shipping','status'=>1])->field('code,name')->select();
        DataReturn::returnJson(200,'获取数据成功',$data);
    }
    //查快递
    public function check_express(){
        $shipping_code = I('shipping_code'); //快递公司
        $invoice_no = I('invoice_no'); //快递单号
        if (!$shipping_code || !$invoice_no) {
            DataReturn::returnJson(500,'系统出错');
        }
        // $mobel = new SearchWordLogic;
        // if(preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $shipping_code)<1){
        //     DataReturn::returnJson(500,'物流公司只能是中文');
        // }
        // $shipping_code = $mobel->getPinyinFull($shipping_code);
        $logistics = queryExpress($shipping_code, $invoice_no);
        if ($logistics['status'] == 200) {
            foreach ($logistics['data'] as $key => $value) {
                $time = strtotime($value['time']);
                $_t   =[
                    'specificdate' => date('Y.m.d', $time),
                    'timedivision' => date('H:i', $time),
                    'context'      => $value['context'],
                ];
                $list[] = $_t;
            }
            $status      = 200;
            $message     = '查询成功';
            $region_list = get_region_list();
            $data = [
                'shipping_name' => $delivery['shipping_name'],
                'invoice_no'    => $delivery['invoice_no'],
                'list'          => $list,
                'consignee'     => $order['consignee'],
                'address'       => $region_list[$order['province']] . $region_list[$order['city']] . $region_list[$order['district']] . $order['address'],
            ];
        } else {
            $message = $logistics['message'];
            $status  = 500;
            $data    = [
                'list' => '',
            ];
        }
        DataReturn::returnJson($status, $message, $data);
    }

    //手机发送验证码
    public function mobile_code(){
        try {
                $mobile = I('mobile');
                if($mobile == "")throw new Exception('手机号不能为空'.$resp['msg']);
                if(!check_mobile($mobile))throw new Exception('手机号格式不正确'.$resp['msg']);
                $session_id = session_id();
                // dump($session_id);die;
                $scene = 2;

                //发送短信验证码
                $res = checkEnableSendSms($scene);
                if($res['status'] != 1){
                    throw new Exception($res['msg']);

                }
                //判断是否存在验证码
                $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$session_id, 'status'=>1))->order('id DESC')->find();
                //获取时间配置
                $sms_time_out = tpCache('sms.sms_time_out');
                $sms_time_out = $sms_time_out ? $sms_time_out : 120;
                //120秒以内不可重复发送
                if($data && (time() - $data['add_time']) < $sms_time_out){
                    //$return_arr = array('status'=>-1,'msg'=>$sms_time_out.'秒内不允许重复发送');
                    throw new Exception($sms_time_out.'秒内不允许重复发送');

                }
                //随机一个验证码
                $code = rand(1000, 9999);
                $params['code'] =$code;

                //发送短信
                $resp = sendSms($scene , $mobile , $params, $session_id);
                // dump($resp);
                if($resp['status'] == 1){
                    //发送成功, 修改发送状态位成功
                    M('sms_log')->where(array('mobile'=>$mobile,'code'=>$code,'session_id'=>$session_id , 'status' => 0))->save(array('status' => 1));
                    //$return_arr = array('status'=>1,'msg'=>'发送成功,请注意查收');
                    $str_a = substr($mobile,0,3);
                    $str_b = substr($mobile,-4);
                    $datas = '我们向'.$str_a.'****'.$str_b.'发送了一个验证码';
                    DataReturn::returnJson(200,"发送成功,请注意查收",$datas);


            } else {
                throw new Exception('手机号格式错误！');
            }
        } catch (Exception $e) {
           DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //绑定手机
    public function binding_phone(){
        try {
            $code = I('code');
            $mobile = I('mobile');
            $session_id = session_id();
            // dump($session_id);die;
            if(check_mobile($mobile)){
                if(!empty($code)){
                    $res = M('sms_log')->where('code',$code)->where('mobile',$mobile)->where('session_id',$session_id)->find();
                        if($res){
                            $arr['mobile'] = $mobile;
                            $arr['mobile_validated'] = 1;
                            $data = M('users')->where('user_id',$this->user_id)->update($arr);
                            $del = M('sms_log')->where('code',$code)->where('mobile',$mobile)->where('session_id',$session_id)->delete();
                            if($data){
                                DataReturn::returnJson('200','验证成功');
                            }else{
                                throw new Exception('系统繁忙,稍后再试！');
                            }

                        }else{
                            throw new Exception('系统繁忙,稍后再试！');
                        }
                }else{
                   throw new Exception('验证码不能为空！');
                }
            }else{
               throw new Exception('手机号格式错误！');
            }

        } catch (Exception $e) {
            DataReturn::returnJson('400',$e->getMessage());
        }
    }

    //关联上下级
    public function contact_leader(){
        $parent_id = I('user_id/d');//上级id
        $user_id = $this->user_id;
        $users=M('users');
        $parent_info = $users->where(['user_id'=>$parent_id])->find();
        if($user_id==$parent_id)
            DataReturn::returnJson('400','不能成为自己的下级');
        if(empty($parent_info))
            DataReturn::returnJson('400','所绑定上级用户的信息有误');
        if($parent_info['first_leader']==$user_id)
            DataReturn::returnJson('400','您已是他的上级，不能绑定');
        $user_info = $users->where(['user_id'=>$user_id])->find();
        if($user_info['perpetual'] && $user_info['first_leader'])
            DataReturn::returnJson('400','您已经存在永久上级，不可以继续绑定');
        //判断有没有绑定手机号码或消费过
        if(!empty($user_info['mobile']) || $user_info['total_amount']>0){
            $perpetual=1;//永久上下级关系
        }else{
            $perpetual=0;//临时上下级关系
        }

        //如果是永久关系，他上级的下线人数要加1
        if($perpetual){
            $users->where(['user_id' => $parent_id])->setInc('underling_number');
        }
        //绑定上下级关系
        $result = $users->where(['user_id'=>$user_id])->update(['first_leader'=>$parent_id,'perpetual'=>$perpetual]);
        if($result!==false){
            DataReturn::returnJson('200','绑定成功');
        }else{
            DataReturn::returnJson('400','绑定失败');
        }
    }

    //获取上级用户信息
    public function leader_info(){
        $parent_id = I('user_id/d');//上级id
        $info = M('users')->field('nickname,head_pic')->where(['user_id'=>$parent_id])->find();
        if(empty($info))
            DataReturn::returnJson('400','用户不存在');
        $data['nickname']=$info['nickname'];
        $data['head_pic']=$info['head_pic'] ? request()->domain().$info['head_pic'] : '';
        DataReturn::returnJson('200','',$data);
    }

    //我的小程序码
    public function mycode(){
        $user_id=$this->user_id;
        $wxcode=M('users')->where(['user_id'=>$user_id])->value('wx_code');

        if(!empty($wxcode) && file_exists($wxcode)){
            DataReturn::returnJson('200','',['imageurl'=>request()->domain().'/'.$wxcode]);
        }else{
            $paymentPlugin = M('Plugin')->where("code='miniAppPay' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
            $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
            $appid = $config_value['appid']; // * APPID
            $appsecret = $config_value['appsecret']; // * appsecret
            $post_arr = [
                // 'page'  => 'pages/contact_leader/contact_leader',
                'scene' => 'user_id$'.$user_id,
            ];

            $jssdk = new JssdkLogic($appid,$appsecret);
            $base64=$jssdk->getwxacodeunlimit($post_arr);

            if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $img)){
                $type = $img[2];
            }else{
                DataReturn::returnJson('400','获取失败');
            }
            $file = 'public/wxcode/'.date('Ymd', time()).'/';
             //检查是否有该文件夹，如果没有就创建
            if (!file_exists($file)) {
                mkdir($file, 0777, true);
            }
            $imgpath = $file . md5(time()).'.'.$type;
            //将生成的小程序码存入相应文件夹下
            file_put_contents($imgpath,base64_decode(str_replace($img[1],'',$base64)));
            //写入数据库
            M('users')->where(['user_id'=>$user_id])->update(['wx_code'=>$imgpath]);
            DataReturn::returnJson('200','',['imageurl'=>request()->domain().'/'.$imgpath]);
        }
    }

}