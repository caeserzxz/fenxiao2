<?php

namespace app\mobile\controller;

use app\common\logic\UsersLogic;
use app\common\logic\RewardLogic;
use app\common\model\Users;
use think\Controller;
use think\Db;
use app\common\logic\MakebiLogic;
use app\common\aliyunsdk\api_demo\SmsDemoAli;
use think\Session;
use think\Log;

class Login extends Controller
{


    public function index()
    {
        return $this->fetch();
    }

    /*
     * 停止访问
     *
     * */
    public function close()
    {
        //判断首页是否能够开启访问
        $config = M('n_goods_config')
            ->where('key', 'is_show_system')
            ->find();
        if ($config['value'] == '1') {
            $this->redirect('mobile/index/index');
        } else {
            return $this->fetch();
        }

    }


    //手机登录
    public function mobile_login()
    {
        return $this->fetch();
    }


    //获取手机验证码
    public function captcha()
    {


        $input = input();

        //1、校验user表手机号用户是否存在
        $user_data=db('users')->where('mobile',$input['mobile'])->find();
//        if(!$user_data)
//        {
//            return array('status' => 500, 'msg' => '手机用户不存在', 'result' => '');
//        }
        if($user_data)
        {
            return array('status' => 500, 'msg' => '手机用户已存在', 'result' => '');
        }
//        生成验证码
        $captcha = mt_rand(100000, 999999);
//        dump($captcha);
//        $captcha = 1111;
        //发送验证码接口（阿里云短信）
        ####################

        set_time_limit(0);
        header('Content-Type: text/plain; charset=utf-8');
        $SmsDemoAli= new SmsDemoAli;
        $result_data=$SmsDemoAli->sendSms($input['mobile'],$captcha);

        if($result_data->Message != OK)
        {
            return array('status' => 500, 'msg' => '发送失败', 'result' => "");
        }

        #################################
        //聚合数据短信
        #################################
//        $url = "http://v.juhe.cn/sms/send";
//        $params = array(
//            'key'   => 'f17d1ac1e5250f3a29b7badd80ffb093', //您申请的APPKEY
//            'mobile'    => $input['mobile'], //接受短信的用户手机号码
//            'tpl_id'    => '151146', //您申请的短信模板ID，根据实际情况修改
//            'tpl_value' =>urlencode("#code#=").$captcha //您设置的模板变量，根据实际情况修改
//        );
//
//        $paramstring = http_build_query($params);
//        $content = $this->juheCurl($url, $paramstring);
//        $result = json_decode($content, true);
//        if ($result) {
//        } else {
//            return array('status' => 500, 'msg' => '发送失败', 'result' => "");
//            //请求异常
//        }
        ################################




        //验证码入库
        $res = db('n_mobile_captcha')->insert([
            'mobile' => $input['mobile'],
            'expire_in' => (time() + 1200),
            'captcha' => $captcha,
            'create_time' => time(),
        ]);

        return array('status' => 200, 'msg' => '发送成功', 'result' => $captcha);

    }


    //登录
    public function mobile_login_check()
    {
        //1、获取手机号跟验证码
        $input = input();
//
//        //2、校验验证码表的手机号跟验证码是否是准确的， 是否过期了
//        $mobile_captcha = db('n_mobile_captcha')->where('mobile', $input['mobile'])->order('id desc')->find();
//
//        if ($mobile_captcha['expire_in'] < time()) {
//            return array('status' => 500, 'msg' => '验证码已过期', 'result' => '');
//        }
//
//        if ($mobile_captcha['captcha'] != $input['code']) {
//            return array('status' => 500, 'msg' => '验证码不正确', 'result' => '');
//        }
//
//        if ($mobile_captcha['mobile'] != $input['mobile']) {
//            return array('status' => 500, 'msg' => '手机号码有误', 'result' => '');
//        }
        //3、校验user表手机号用户是否存在
        $user_data = db('users')
            ->where('mobile', $input['mobile'])
//            ->where('password', md5($input['password']))
            ->find();
        if (!$user_data) {
            return array('status' => 500, 'msg' => '账户或密码出错', 'result' => '');
        }

        //判断用户是否被冻结
        if ($user_data['is_lock'] == '1') {
            return array('status' => 500, 'msg' => '用户被冻结', 'result' => '');
        }

        session('user', $user_data);
        session('user_id', $user_data['user_id']);
        session('openid', $user_data['openid']);

        //5、跳转到首页
        return array('status' => 200, 'msg' => '登录成功', 'result' => $user_data['user_id']);
    }


    //通过code值换取access_token
    public function getAccessTokenUrl($code)
    {
        $weiconfig = M('wx_user')->find(); //获取微信配置

        $access_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $weiconfig['app_appid'] . '&secret=' . $weiconfig['app_appsecret'] . '&code=' . $code . '&grant_type=authorization_code';
        return $this->sendToWeChat($access_token_url);
    }


    /**
     * Curl Get方式发送数据
     * @param $url
     * @param null $data
     * @param bool $bool
     * @return mixed
     */
    function sendToWeChat($url, $data = null, $bool = true)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        $result = $output;
        if ($bool)
            $result = json_decode($output, true);
        return $result;
    }

    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v) {
            if ($k != "sign") {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     *
     * 通过access_token openid 从工作平台获取UserInfo
     * @return openid
     */
    public function GetUserInfo($access_token, $openid)
    {
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token, $openid);
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回
        $data = json_decode($res, true);
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        if (!isset($data['unionid'])) {
            $access_token2 = $this->get_access_token();//获取基础支持的access_token
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token2&openid=$openid";
            $subscribe_info = httpRequest($url, 'GET');
            $subscribe_info = json_decode($subscribe_info, true);
            $data['subscribe'] = $subscribe_info['subscribe'];
        }
        return $data;
    }

    public function get_access_token()
    {
        //判断是否过了缓存期
        $expire_time = $this->weixin_config['web_expires'];
        if ($expire_time > time()) {
            return $this->weixin_config['web_access_token'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->weixin_config[appid]}&secret={$this->weixin_config[appsecret]}";
        $return = httpRequest($url, 'GET');
        $return = json_decode($return, 1);
        $web_expires = time() + 7140; // 提前60秒过期
        M('wx_user')->where(array('id' => $this->weixin_config['id']))->save(array('web_access_token' => $return['access_token'], 'web_expires' => $web_expires));
        return $return['access_token'];
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token, $openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?" . $bizString;
    }

    /*
     * 加载注册页面
     * 
     * */
    public function register()
    {


        $input = input();
        $recommendId = isset($input['rec_id']) ? $input['rec_id'] : 0;  //扫码分享的推荐人id

        $in = session('in');
        if($in=='android'){

        }

        if(is_weixin()){
            //授权获取用户信息
            $weiconfig = M('wx_user')->find(); //获取微信配置
            $appId = $weiconfig['appid'];
            $appsecret = $weiconfig['appsecret'];

            if (!isset($_GET['code'])){
                $redirect_uri = 'http://'.$_SERVER["SERVER_NAME"].'/Mobile/Login/register?rec_id='. $recommendId ;
                # 获取code
                $code =$this->getCode($redirect_uri,'userinfo',$appId);
            }else{
                $code = $_GET['code'];
                //获取网页授权access_token
                $tokenUrl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appId}&secret={$appsecret}&code=" . $code . "&grant_type=authorization_code";
                $tokenUrlArr = $this->http_curl($tokenUrl,'get');
                //获取用户的基本信息
                $userInfo = $this->GetUserInfo($tokenUrlArr['access_token'],$tokenUrlArr['openid']);
                if($userInfo){
                    $userInfo['head_pic'] = $userInfo['headimgurl'];
                    $userInfo['oauth'] = 'weixin';
                    session('wxuser',$userInfo);
                }
            }
        }


        $this->assign('recommendId', $recommendId);
        return $this->fetch();
    }

    //app授权
    public function appauth(){
         $code = I('code');
        //通过code值获取用户的token跟unionid ，openid
        $data1=$this->getAccessTokenUrl($code);

        $data2 = $this->GetUserInfo($data1['access_token'],$data1['openid']);//获取微信用户信息
         return  $data2['nickname'];
        $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
        $data['sex'] = $data2['sex'];
        $data['head_pic'] = $data2['headimgurl'];
        $data['subscribe'] = $data2['subscribe'];
        $data['oauth_child'] = 'mp';
//        $_SESSION['openid'] = $data['openid'];
        $data['oauth'] = 'app';
        $data['app_openid'] = $data1['openid'];
        if(isset($data2['unionid'])){
            $data['unionid'] = $data2['unionid'];
        }
        session('wxuser',$data);
        return  $data;

    }

    /*
     * 处理注册
     * 
     * */
    public function dealRegister()
    {
        //获取微信公众号授权登录用户信息（有就存，没有就当是app提交注册处理）
        $openid = session('openid');
        $wxuser = session('wxuser');

        //    Log::error('_mobile_reg_' . json_encode($wxuser));

        $input = input();

        if (isset($input['mobile'])) {
            //1、校验验证码表的手机号跟验证码是否是准确的， 是否过期了
            $mobile_captcha = db('n_mobile_captcha')->where('mobile', $input['mobile'])->order('id desc')->find();

            if (!$mobile_captcha) {
                return array('status' => 500, 'msg' => '手机号有误', 'result' => '');
            }

            if ($mobile_captcha['captcha'] != $input['code']) {
                return array('status' => 500, 'msg' => '验证码不正确', 'result' => '');
            }

            if ($mobile_captcha['expire_in'] < time()) {
                return array('status' => 500, 'msg' => '验证码已过期', 'result' => '');
            }

            if (trim($input['password']) != trim($input['confirm_password'])) {
                return array('status' => 500, 'msg' => '两次登录密码输入不一样', 'result' => '');
            }

            //判断手机号码是否已经存在

            $members = db('users')->where('mobile', $input['mobile'])->find();
            if ($members) {
                return array('status' => 500, 'msg' => '该手机号码已被注册', 'result' => '');
            }

            //邀请码不能为空
            //第一个用户不需要邀请码
            $usersEnpty = M('users')->count();
            if (!empty($usersEnpty)) {
                if (!$input['pid']) {
                    return array('status' => 500, 'msg' => '推荐人ID不能为空', 'result' => '');
                }
            }


            //判断邀请人是否存在
            if(!empty($input['pid'])){
                $pidUser = M('users')
                    ->where('user_id', $input['pid'])
                    ->find();
                if (!$pidUser) {
                    return array('status' => 500, 'msg' => '推荐人不存在', 'result' => '');
                }
            }


            //注册
            $userData = array();
            $userData['real_name'] = $input['real_name'];
            $userData['mobile'] = $input['mobile'];
            $userData['password'] = md5($input['password']);
            $userData['pid'] = $input['pid'];
            $userData['pid2'] = $pidUser['pid'];


            $userData['openid'] = $wxuser ? $wxuser['openid'] : '';
//            $userData['unionid'] = $wxuser ? $wxuser['unionid'] : '';
            $userData['nickname'] = $wxuser ? $wxuser['nickname'] : $input['real_name'];
            $userData['sex'] = $wxuser ? $wxuser['sex'] : '';
            $userData['head_pic'] = $wxuser ? $this->filterEmoji($wxuser['head_pic']) : '';
            $userData['oauth'] = $wxuser['oauth'] ?  $wxuser['oauth'] : 'app';

            $userData['reg_time'] = time();


            $res = db('users')->insertGetId($userData); //注册并返回用户id

            //写入用户关系表
            if ($pidUser && $res) {
                //确认推荐人身份层级
                $isManage = M('n_management')->where('management_id', $pidUser['user_id'])->find();

                $managementData = array();
                if (empty($isManage)) {
                    $managementData['level'] = 0;
                } else {
                    $managementData['level'] = $isManage['level'] + 1;
                }

                $managementData['user_id'] = $res;
                $managementData['management_id'] = $pidUser['user_id'];
                $managementData['create_time'] = time();
                //写入用户关系表
                $management = M('n_management')->insert($managementData);

                //第一个人作为admin的user_id
                if (empty($usersEnpty)) {
                    $whereAdmin['user_name'] = 'admin';
                    $dataAdmin['user_id'] = $res;
                    $upAdmin = M('admin')->where($whereAdmin)->update($dataAdmin);
                }
            }


            if ($res) {
                //登录才存用户session
                /*  session('user_id', $res);
                  session('openid', $wxuser['openid']);*/
                return array('status' => 200, 'msg' => '注册成功', 'result' => '');
            } else {
                return array('status' => 500, 'msg' => '注册失败', 'result' => '');
            }
        } else {
            return array('status' => 500, 'msg' => '请输入手机号码', 'result' => '');
        }

    }

    /*
     * 重置登录密码页面
     * 
     * */
    public function forget()
    {
        return $this->fetch();
    }


    /*
     * 处理重置登录密码
     * 
     * */
    public function dealForget()
    {
        $input = input();

        if (isset($input['mobile'])) {
            //1、校验验证码表的手机号跟验证码是否是准确的， 是否过期了
            $mobile_captcha = db('n_mobile_captcha')->where('mobile', $input['mobile'])->order('id desc')->find();

            if (!$mobile_captcha) {
                return array('status' => 500, 'msg' => '手机号有误', 'result' => '');
            }

            if ($mobile_captcha['captcha'] != $input['code']) {
                return array('status' => 500, 'msg' => '验证码不正确', 'result' => '');
            }

            if ($mobile_captcha['expire_in'] < time()) {
                return array('status' => 500, 'msg' => '验证码已过期', 'result' => '');
            }

            if (trim($input['password']) != trim($input['confirm_password'])) {
                return array('status' => 500, 'msg' => '两次登录密码输入不一样', 'result' => '');
            }

            //判断手机号码是否已经存在
            $members = db('users')->where('mobile', $input['mobile'])->find();
            if (!$members) {
                return array('status' => 500, 'msg' => '手机号不存在', 'result' => '');
            }


            //重置登录密码
            $userData = array();
            $userData['password'] = md5($input['password']);

            $res = db('users')->where('user_id', $members['user_id'])->update($userData);

            $find = M('admin')->where('user_id', $members['user_id'])->find();
            if (!empty($find)) {
                //子后台登录密码同时改变
                $r = M('admin')->where('user_id', $members['user_id'])->update($userData);
            }


            if ($res) {
                return array('status' => 200, 'msg' => '重置成功', 'result' => '');
            } else {
                return array('status' => 500, 'msg' => '重置失败', 'result' => '');
            }
        } else {
            return array('status' => 500, 'msg' => '请输入手机号码', 'result' => '');
        }
    }


    /*
     * 退出登录
     * 
     * */
    public function loginout()
    {
        session_unset();
        session(null);//退出清空session
        session::clear();
        $this->redirect(U('User/index'));
    }


    /*
     * 下载APP页面
     *
     * */
    public function uploadapp()
    {
        $data['msg']='';
        if ($this->isWXBrowser()) {
            $data['msg']='请点击右上角用浏览器打开本页面，再重新点击下载';
        }

        $data['url']='http://'.$_SERVER['SERVER_NAME'];
        return $data;
    }

    public function isWXBrowser()
    {
        return (bool)strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger');
    }

    /**
     *
     **/
    public function arrayExcel(array $tableTitle)
    {
//    public function arrayExcel(array $tableTitle,array $tableContent,array $fileName){


        //表头
        $title = "";
        foreach ($tableTitle as &$v) {
            $title .= '<td style="text-align:center;font-size:12px;width:120px;">' . $v . '</td>';
        }
        dump($title);
        die;

    }

    /**
     * 导出excel
     * @param $strTable    表格内容
     * @param $filename 文件名
     */
    public function downloadExcel($strTable, $filename)
    {
        header("Content-type: application/vnd.ms-excel");
        header("Content-Type: application/force-download");
        header("Content-Disposition: attachment; filename=" . $filename . "_" . date('Y-m-d') . ".xls");
        header('Expires:0');
        header('Pragma:public');
        echo '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . $strTable . '</html>';
    }

    function strtoascii($str)
    {
        $str = mb_convert_encoding($str, 'GB2312');
//注意：我默认当前我们的php文件环境是UTF-8，如果是GBK的话mb_convert_encoding操作就不需要
        $change_after = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $temp_str = dechex(ord($str[$i]));
            $change_after .= $temp_str[1] . $temp_str[0];
        }
        return strtoupper($change_after);
    }

//B:将ascii转为字符串(中文同样实用)
    function asciitostr($sacii)
    {
        $asc_arr = str_split(strtolower($sacii), 2);
        $str = '';
        for ($i = 0; $i < count($asc_arr); $i++) {
            $str .= chr(hexdec($asc_arr[$i][1] . $asc_arr[$i][0]));
        }
        return mb_convert_encoding($str, 'UTF-8', 'GB2312');
//注意：我默认当前我们的php文件环境是UTF-8，如果是GBK的话mb_convert_encoding操作就不需要
    }

    function filterEmoji($str)
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);

        return $str;
    }

    /**
     * 把用户输入的文本转义（主要针对特殊符号和emoji表情）
     */
    function userTextEncode($str)
    {
        if (!is_string($str)) return $str;
        if (!$str || $str == 'undefined') return '';

        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i", function ($str) {
            return addslashes($str[0]);
        }, $text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
        return json_decode($text);
    }

    /**
     * 解码用户输入的转义
     */
    function userTextDecode($str)
    {
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback('/\\\\\\\\/i', function ($str) {
            return '\\';
        }, $text); //将两条斜杠变成一条，其他不动
        return json_decode($text);
    }

    /*
   * 下载安装包
   * */
    public function uploadAppBt()
    {
        $data['url'] = 'http://' . $_SERVER['SERVER_NAME'];
        return $data ;
    }

    /*
    * 下载安装包
    * */
    public function downApp()
    {
        $idarr = array(58,59,60,61,62,63,64);
        $list = M('n_goods_config')->whereIn('id',$idarr)->select();
        $this->assign('list', $list);
        //$url = 'http://' . $_SERVER['SERVER_NAME'].'/YG优购商城 .apk';
        //return $url ;
        return $this->fetch('downApp');
    }

    /**
     * 聚合数据
     * 请求接口返回内容
     * @param  string $url [请求的URL地址]
     * @param  string $params [请求的参数]
     * @param  int $ipost [是否采用POST形式]
     * @return  string
     */
    function juheCurl($url, $params = false, $ispost = 0)
    {
        $httpInfo = array();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JuheData');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($ispost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url.'?'.$params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }

    //授权登录,获取用户信息
    public  function oauthUserInfo(){
        $weiconfig = M('wx_user')->find(); //获取微信配置
        $appId = $weiconfig['appid'];
        $appsecret = $weiconfig['appsecret'];

        if (!isset($_GET['code'])){
            $redirect_uri = 'http://'.$_SERVER["SERVER_NAME"].'/Mobile/Login/oauthUserInfo';
            # 获取code
            $code =$this->getCode($redirect_uri,'userinfo',$appId);
        }else{
            $code = $_GET['code'];
            //获取网页授权access_token
            $tokenUrl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appId}&secret={$appsecret}&code=" . $code . "&grant_type=authorization_code";
            $tokenUrlArr = $this->http_curl($tokenUrl,'get');
            //获取用户的基本信息
            $userInfo = $this->GetUserInfo($tokenUrlArr['access_token'],$tokenUrlArr['openid']);
            dump($userInfo);
            if($userInfo){
                dump(111);
                session('wxuser',$userInfo);
            }

            return '';

        }
    }

    /*
    * 网页授权 获取code
    * */
    public function getCode($redirect_uri = '', $snsapi = 'base',$appId){
        $redirect_uri = urlencode($redirect_uri);
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appId}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_{$snsapi}&state=1#wechat_redirect";
        header("Location: {$url}");
        exit ;
    }

    /**
     ** @desc 封装 curl 的调用接口，post的请求方式
     **/
    function http_curl($url,$type='get',$res='json',$arr=''){
        //初始化curl
        $ch = curl_init();
        //设置curl的参数
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // post数据
        if($type=='post'){
            curl_setopt($ch, CURLOPT_POST, 1);
            // post的变量
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
        }
        //采集
        $output = curl_exec($ch);
        curl_close($ch);

        if($res=='json'){
            return json_decode($output,true);
        }
    }

    /*
         * 梅克伦绑定手机号
         *
         * */
    public function bindPhone()
    {
        $input = input('');
        $phone = $input['phone'];
        $returnData = array();
        $oldPhone = M('users')->where('mobile', $phone)->find();
        if ($oldPhone) {

            $returnData['status'] = '0';
            $returnData['msg'] = '该手机号码已被优先绑定';
        } else {
            $data = array();
            $data['mobile'] = $phone;
            $data['mobile_validated'] = 1;

            $Rt = M('users')->where('user_id', $this->user_id)->update($data);


            $returnData['status'] = '1';
            $returnData['msg'] = '绑定成功';
        }

        return $returnData;
    }
    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {

        if (IS_POST) {
            $wxuser = session('wxuser');
            $data = I('post.');
//            $userLogic = new UsersLogic();
            $user['mobile'] = $data['mobile'];
            $user['password'] = md5($data['password']);
            $new_user = M('users')->where('mobile',$user['mobile'])->find();

            if($new_user){
                if($new_user['openid']){
                    return $this->error("绑定失败,失败原因:此账号已绑定其他微信账号");
                }
               if($new_user['password']!=$user['password']){
                   return $this->error("绑定失败,失败原因:密码错误");
               }
               if(empty($wxuser)){
                   return $this->error("绑定失败,失败原因:微信参数失效");
               }
               $map['openid'] = $wxuser['openid'];
               $map['nickname'] = $wxuser['nickname'];
               $res = M('users')->where('mobile',$user['mobile'])->save($map);
               if($res){
                   $new_user = M('users')->where('mobile',$user['mobile'])->find();
                   session('user',$new_user);
                   session('openid',$new_user['openid']);
                   return $this->success("绑定成功", U('Mobile/User/index'));
               }else{
                   return $this->error("绑定失败,请联系管理员");
               }
            }else{
                return $this->error("绑定失败,失败原因:用户不存在");
            }
//            $res = $userLogic->oauth_bind_new($user);
//            if ($res['status'] == 1) {
//                //绑定成功, 重新关联上下级
//                $map['first_leader'] = cookie('first_leader');  //推荐人id
//                // 如果找到他老爸还要找他爷爷他祖父等
//                if ($map['first_leader']) {
//                    $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
//                    if ($first_leader) {
//                        $map['second_leader'] = $first_leader['first_leader'];
//                        $map['third_leader'] = $first_leader['second_leader'];
//                    }
//                    //他上线分销的下线人数要加1
//                    M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
//                    M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
//                    M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
//                } else {
//                    $map['first_leader'] = 0;
//                }
//                $ruser = $res['result'];
//                M('Users')->where('user_id', $ruser['user_id'])->save($map);
//
//                $res['url'] = urldecode(I('post.referurl'));
//                $res['result']['nickname'] = empty($res['result']['nickname']) ? $res['result']['mobile'] : $res['result']['nickname'];
//                setcookie('user_id', $res['result']['user_id'], null, '/');
//                setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
//                setcookie('uname', urlencode($res['result']['nickname']), null, '/');
//                setcookie('head_pic', urlencode($res['result']['head_pic']), null, '/');
//                setcookie('cn', 0, time() - 3600, '/');
//                //获取公众号openid,并保持到session的user中
//                $oauth_users = M('OauthUsers')->where(['user_id' => $res['result']['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
//                $oauth_users && $res['result']['open_id'] = $oauth_users['open_id'];
//                session('user', $res['result']);
//                $cartLogic = new CartLogic();
//                $cartLogic->setUserId($res['result']['user_id']);
//                $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
//                $userlogic = new OrderLogic();//登录后将超时未支付订单给取消掉
//                $userlogic->setUserId($res['result']['user_id']);
//                $userlogic->abolishOrder();
//                return $this->success("绑定成功", U('Mobile/User/index'));
//            } else {
//                return $this->error("绑定失败,失败原因:" . $res['msg']);
//            }
        } else {
            return $this->fetch();
        }
    }
    /**
     *  注册
     */
    public function reg()
    {

//        if ($this->user_id > 0) {
//            $this->redirect(U('Mobile/User/index'));
//        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname = I('post.nickname', '');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $user_id = I('user_id');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();

            //是否开启注册验证码机制
            if (check_mobile($username)) {
                if ($reg_sms_enable) {
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if ($check_code['status'] != 1) {
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            //是否开启注册邮箱验证码机制
            if (check_email($username)) {
                if ($reg_smtp_enable) {
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if ($check_code['status'] != 1) {
                        $this->ajaxReturn($check_code);
                    }
                }
            }

            $invite = I('invite');
            if (!empty($invite)) {
                $invite = get_user_info($invite, 2);//根据手机号查找邀请人
            } else {
                $invite = array();
            }
            //存储管理关系
            if (!empty($user_id)) {
                $user2 = M("users")->where('user_id', $user_id)->find();
                if (!empty($user2['pid'])) {
                    $user_id2 = $user2['pid'];
                }
                //添加管理关系
                $getUser1 = M("users")->where('user_id', $user_id)->find();
                //如果拥有管理权限，写入，没有找他们的父类
                if ($getUser1['user_type'] == 2 || $getUser1['user_type'] == 3) {
                    $logic->management($user_id);
                } elseif (!empty($getUser1['pid'])) {
                    $logic->management($getUser1['pid']);
                }
            }

            if ($is_bind_account && session("third_oauth")) { //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite, $user_id, $user_id2, $nickname, $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            } else {
                $data = $logic->reg($username, $password, $password2, 0, $invite, $user_id, $user_id2);
            }


            if ($data['status'] != 1) $this->ajaxReturn($data);

            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id' => $data['result']['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable', $reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable', $reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        $this->assign('user_id', I('user_id'));//推荐人
        return $this->fetch();
    }

    public function bind_guide()
    {
        $data = session('third_oauth');
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);

        return $this->fetch();
    }
}
