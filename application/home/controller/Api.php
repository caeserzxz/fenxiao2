<?php

namespace app\home\controller;
use app\common\logic\UsersLogic;
use think\Db;
use think\Session;
use think\Verify;
use think\Cookie;

class Api extends Base {
    public  $send_scene;

    public function _initialize() {
        parent::_initialize();
        session('user');
    }
    /*
     * 获取地区
     */
    public function getRegion(){
        $parent_id = I('get.parent_id/d');
        $selected = I('get.selected',0);
        $data = M('region')->where("parent_id",$parent_id)->select();
        $html = '';
        if($data){
            foreach($data as $h){
            	if($h['id'] == $selected){
            		$html .= "<option value='{$h['id']}' selected>{$h['name']}</option>";
            	}
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }


    public function getTwon(){
    	$parent_id = I('get.parent_id/d');
    	$data = M('region')->where("parent_id",$parent_id)->select();
    	$html = '';
    	if($data){
    		foreach($data as $h){
    			$html .= "<option value='{$h['id']}'>{$h['name']}</option>";
    		}
    	}
    	if(empty($html)){
    		echo '0';
    	}else{
    		echo $html;
    	}
    }

    /**
     * 获取省
     */
    public function getProvince()
    {
        $province = Db::name('region')->field('id,name')->where(array('level' => 1))->cache(true)->select();
        $res = array('status' => 1, 'msg' => '获取成功', 'result' => $province);
        exit(json_encode($res));
    }

    /**
     * 获取市或者区
     */
    public function getRegionByParentId()
    {
        $parent_id = input('parent_id');
        $res = array('status' => 0, 'msg' => '获取失败，参数错误', 'result' => '');
        if($parent_id){
            $region_list = Db::name('region')->field('id,name')->where(['parent_id'=>$parent_id])->select();
            $res = array('status' => 1, 'msg' => '获取成功', 'result' => $region_list);
        }
        exit(json_encode($res));
    }

    /*
     * 获取地区
     */
    public function get_category(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
            $list = M('goods_category')->where("parent_id", $parent_id)->select();

        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['name']}</option>";
        exit($html);
    }


    /**
     * 前端发送短信方法: APP/WAP/PC 共用发送方法
     */
    public function send_validate_code(){

        $this->send_scene = C('SEND_SCENE');

        $type = I('type');
        $scene = I('scene');    //发送短信验证码使用场景
        $mobile = I('mobile');
        $sender = I('send');
        $verify_code = I('verify_code');
        $mobile = !empty($mobile) ?  $mobile : $sender ;
        $session_id = I('unique_id' , session_id());
        session("scene" , $scene);

        //注册
        if($scene == 1 && !empty($verify_code)){
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_reg')) {
                ajaxReturn(array('status'=>-1,'msg'=>'图像验证码错误'));
            }
        }
        if($type == 'email'){
            //发送邮件验证码
            $logic = new UsersLogic();
            $res = $logic->send_email_code($sender);
            ajaxReturn($res);
        }else{
            //发送短信验证码
            $res = checkEnableSendSms($scene);
            if($res['status'] != 1){
                ajaxReturn($res);
            }
            //判断是否存在验证码
            $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$session_id, 'status'=>1))->order('id DESC')->find();
            //获取时间配置
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 120;
            //120秒以内不可重复发送
            if($data && (time() - $data['add_time']) < $sms_time_out){
                $return_arr = array('status'=>-1,'msg'=>$sms_time_out.'秒内不允许重复发送');
                ajaxReturn($return_arr);
            }
            //随机一个验证码
            $code = rand(1000, 9999);
            $params['code'] =$code;

            //发送短信
            $resp = sendSms($scene , $mobile , $params, $session_id);

            if($resp['status'] == 1){
                //发送成功, 修改发送状态位成功
                M('sms_log')->where(array('mobile'=>$mobile,'code'=>$code,'session_id'=>$session_id , 'status' => 0))->save(array('status' => 1));
                $return_arr = array('status'=>1,'msg'=>'发送成功,请注意查收');
            }else{
                $return_arr = array('status'=>-1,'msg'=>'发送失败'.$resp['msg']);
            }
            ajaxReturn($return_arr);
        }
    }

    /**
     * 验证短信验证码: APP/WAP/PC 共用发送方法
     */
    public function check_validate_code(){

        $code = I('post.code');
        $mobile = I('mobile');
        $send = I('send');
        $sender = empty($mobile) ? $send : $mobile;
        $type = I('type');
        $session_id = I('unique_id', session_id());
        $scene = I('scene', -1);

        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $sender, $type ,$session_id, $scene);
        ajaxReturn($res);
    }

    /**
     * 检测手机号是否已经存在
     */
    public function issetMobile()
    {
      $mobile = I("mobile",'0');
      $users = M('users')->where('mobile',$mobile)->find();
      if($users)
          exit ('1');
      else
          exit ('0');
    }

    public function issetMobileOrEmail()
    {
        $mobile = I("mobile",'0');
        $users = M('users')->where("email",$mobile)->whereOr('mobile',$mobile)->find();
        if($users)
            exit ('1');
        else
            exit ('0');
    }
    /**
     * 查询物流（快递100接口）
     * @parm $shipping_code 快递公司编码
     * @parm invoice_no 快递单编号
     */
    public function queryExpress()
    {
        $shipping_code = input('shipping_code');
        $invoice_no = input('invoice_no');

        if(empty($shipping_code) || empty($invoice_no)){
            return json(['status'=>0,'message'=>'参数有误','result'=>'']);
        }

        $post_data = array();
        $post_data["customer"] = 'F5035417204DC573771C918B8E128E0B';
        $key= 'DvDwxcyC4332' ;
        $post_data["param"] = '{"com":"'.$invoice_no.'","num":"'.$shipping_code.'"}';
//        $post_data["param"] = '{"com":"zhongtong","num":"75149673840234"}';
        $url='http://poll.kuaidi100.com/poll/query.do';
        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($post_data["sign"]);
        $o="";
        foreach ($post_data as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
        }

        $post_data=substr($o,0,-1);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);


        $result = curl_exec($ch);

        $data = str_replace("\"",'"',$result );
        $data = json_decode($data,true);


    }


    /**
     * 查询物流（快递鸟接口）
     * @parm $ShipperCode 快递公司编码
     * @parm $LogisticCode 物流编号
     */
    public function queryExpress2()
    {
        $ShipperCode = input('shipping_code');
        $LogisticCode = input('invoice_no');
        if(empty($ShipperCode) || empty($LogisticCode)){
            return json(['status'=>0,'message'=>'参数有误','result'=>'']);
        }
        return kuaidiniao("$ShipperCode","$LogisticCode");
    }

    /**
     * 检查订单状态
     */
    public function check_order_pay_status()
    {
        $order_id = I('order_id/d');
        if(empty($order_id)){
            $res = ['message'=>'参数错误','status'=>-1,'result'=>''];
            $this->AjaxReturn($res);
        }
        $order = M('order')->field('pay_status')->where(['order_id'=>$order_id])->find();
        if($order['pay_status'] != 0){
            $res = ['message'=>'已支付','status'=>1,'result'=>$order];
        }else{
            $res = ['message'=>'未支付','status'=>0,'result'=>$order];
        }
        $this->AjaxReturn($res);
    }

    /**
     * 广告位js
     */
    public function ad_show()
    {
        $pid = I('pid/d',1);
        $where = array(
            'pid'=>$pid,
            'enable'=>1,
            'start_time'=>array('lt',strtotime(date('Y-m-d H:00:00'))),
            'end_time'=>array('gt',strtotime(date('Y-m-d H:00:00'))),
        );
        $ad = D("ad")->where($where)->order("orderby desc")->cache(true,TPSHOP_CACHE_TIME)->find();
        $this->assign('ad',$ad);
        return $this->fetch();
    }
    /**
     *  搜索关键字
     * @return array
     */
    public function searchKey(){
        $searchKey = input('key');
        $searchKeyList = Db::name('search_word')
            ->where('keywords','like',$searchKey.'%')
            ->whereOr('pinyin_full','like',$searchKey.'%')
            ->whereOr('pinyin_simple','like',$searchKey.'%')
            ->limit(10)
            ->select();
        if($searchKeyList){
            return json(['status'=>1,'msg'=>'搜索成功','result'=>$searchKeyList]);
        }else{
            return json(['status'=>0,'msg'=>'没记录','result'=>$searchKeyList]);
        }
    }

    /**
     * 根据ip设置获取的地区来设置地区缓存
     */
    public function doCookieArea()
    {
//        $ip = '183.147.30.238';//测试ip
        $address = input('address/a',[]);
        if(empty($address) || empty($address['province'])){
            $this->setCookieArea();
            return;
        }
        $province_id = Db::name('region')->where(['level' => 1, 'name' => ['like', '%' . $address['province'] . '%']])->limit('1')->value('id');
        if(empty($province_id)){
            $this->setCookieArea();
            return;
        }
        if (empty($address['city'])) {
            $city_id = Db::name('region')->where(['level' => 2, 'parent_id' => $province_id])->limit('1')->order('id')->value('id');
        } else {
            $city_id = Db::name('region')->where(['level' => 2, 'parent_id' => $province_id, 'name' => ['like', '%' . $address['city'] . '%']])->limit('1')->value('id');
        }
        if (empty($address['district'])) {
            $district_id = Db::name('region')->where(['level' => 3, 'parent_id' => $city_id])->limit('1')->order('id')->value('id');
        } else {
            $district_id = Db::name('region')->where(['level' => 3, 'parent_id' => $city_id, 'name' => ['like', '%' . $address['district'] . '%']])->limit('1')->value('id');
        }
        $this->setCookieArea($province_id, $city_id, $district_id);
    }

    /**
     * 设置地区缓存
     * @param $province_id
     * @param $city_id
     * @param $district_id
     */
    private function setCookieArea($province_id = 1, $city_id = 2, $district_id = 3)
    {
        Cookie::set('province_id', $province_id);
        Cookie::set('city_id', $city_id);
        Cookie::set('district_id', $district_id);
    }

    //获取省市区
    public function getAllArea()
    {
        $return_data = cache('json_area');
        if(!$return_data)
        {
            $province = Db::name('region')->field('id,name')->where(array('level' => 1))->cache(true)->select();
            //获取市
            $city = Db::name('region')->field('id,name,parent_id')->where(array('level' => 2))->cache(true)->select();
            //区
            $dist = Db::name('region')->field('id,name,parent_id')->where(array('level' => 3))->cache(true)->select();


            foreach($province as $k => $v)
            {
                $_province['label'] = $v['name'];
                $_province['value'] = $v['id'];
                $city_arr = [];

                foreach($city as $k1=>$v1)
                {
                    if($v1['parent_id'] == $v['id'])
                    {
                        $_city['label'] = $v1['name'];
                        $_city['value'] = $v1['id'];

                        $dist_arr = [];
                        foreach($dist as $k2=>$v2)
                        {

                            if($v2['parent_id'] == $v1['id'])
                            {
                                $_dist['label'] = $v2['name'];
                                $_dist['value'] = $v2['id'];

                                $dist_arr[] = $_dist;
                            }
                        }

                        $_city['children'] = $dist_arr;
                        $city_arr[] = $_city;
                    }
                }


                $_province['children'] = $city_arr;

                $return_data[] = $_province;
            }
            $return_data =  json_encode($return_data);
            cache('json_area',$return_data);
        }


      echo $return_data;

    }


    //获取省市区
    public function getApiAllArea()
    {
        $return_data = cache('json_api_area');
        if(!$return_data)
        {
            $province = Db::name('region')->field('id,name')->where(array('level' => 1))->cache(true)->select();
            //获取市
            $city = Db::name('region')->field('id,name,parent_id')->where(array('level' => 2))->cache(true)->select();
            //区
            $dist = Db::name('region')->field('id,name,parent_id')->where(array('level' => 3))->cache(true)->select();


            foreach($province as $k => $v)
            {
                $_province['name'] = $v['name'];
                $_province['code'] = $v['id'];
                $city_arr = [];

                foreach($city as $k1=>$v1)
                {
                    if($v1['parent_id'] == $v['id'])
                    {
                        $_city['name'] = $v1['name'];
                        $_city['code'] = $v1['id'];

                        $dist_arr = [];
                        foreach($dist as $k2=>$v2)
                        {

                            if($v2['parent_id'] == $v1['id'])
                            {
                                $_dist['name'] = $v2['name'];
                                $_dist['code'] = $v2['id'];

                                $dist_arr[] = $_dist;
                            }
                        }

                        $_city['sub'] = $dist_arr;
                        $city_arr[] = $_city;
                    }
                }


                $_province['sub'] = $city_arr;

                $return_data[] = $_province;
            }
            $return_data =  json_encode($return_data);
            cache('json_api_area',$return_data);
        }


        echo $return_data;

    }


}