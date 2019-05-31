<?php

namespace app\mobile\controller;

use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;
use app\common\model\Users;
use think\Config;
use think\Controller;
use think\exception\DbException;
use think\exception\HttpResponseException;
use think\Log;
use think\Request;
use think\Response;
use think\Session;
use think\View;

class MobileBase extends Controller
{
    public $session_id;
    public $weixin_config;
    public $cateTrre = array();

    protected $tpshop_config = [];

    /**
     * 不需要微信浏览器的控制器
     *
     * @var array
     */
    protected $noNeedWxControllerList = [
        'Payment',
        'PaymentNew',
    ];

    public function _initialize()
    {
        // 判断当前用户是否手机
        if (isMobile())
            cookie('is_mobile', '1', 3600);
        else
            cookie('is_mobile', '0', 3600);

        // 公众号二维码
        $wx_qr = M('wx_user')->cache(true)->value('qr');
        $this->assign('wx_qr', $wx_qr);

        // 获取微信配置
        $signPackage = getwxconfig();
        $this->assign('signPackage', $signPackage);

        // 临时 模拟微信浏览器
        if (!$this->isWXBrowser() || $this->isWXBrowser()) {
//        if (!$this->isWXBrowser()){
            // 非微信浏览器 或者 微信浏览器
            $input = input();
            $recommendId = isset($input['rec_id']) ? $input['rec_id'] : 0;  //扫码分享的推荐人id
            session('recommendId', $recommendId);  //扫码分享的推荐人id

            //Log::error('input-'.json_encode($input));

            // 临时 模拟登录
            $userId = (int)$this->request->get('_user_id');

            $userId or $userId = session('user_id');

            try {
                $userId and $user = Users::get($userId);
            } catch (DbException $e) {
            }

            if ($user['openid']) {
                $_SESSION['openid'] = $user['openid'];
                session('user_id', $user['user_id']);

                $user['subscribe'] = 1;
                $data['result'] = $wxuser = $user;

                session('subscribe', $wxuser['subscribe']);
                setcookie('subscribe', $wxuser['subscribe']);

                session('user', $data['result']);
                setcookie('user_id', $data['result']['user_id'], null, '/');
                setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
                setcookie('uname', $data['result']['nickname'], null, '/');

                // 登录后将购物车的商品的 user_id 改为当前登录的id
                M('cart')->where("session_id", $this->session_id)->save(array('user_id' => $data['result']['user_id']));
            } else {
                if (!in_array($this->request->controller(), $this->noNeedWxControllerList)) {
                    //拒绝访问
                    if (!session('user_id')) {
                        $this->notWXBrowserError();
                    }
                }

            }

        } else {
            // 微信浏览器访问
            $input = input();
            $recommendId = isset($input['rec_id']) ? $input['rec_id'] : 0;  //扫码分享的推荐人id
            session('recommendId', $recommendId);  //扫码分享的推荐人id


            $this->assign('is_weixin_browser', 1);
            $user_temp = session('user');
            if ($user_temp['user_id']) {
                $user = M('users')->where("user_id", $user_temp['user_id'])->find();
                if (!$user) {
                    $_SESSION['openid'] = '';
                    session('user', null);
                }
            }

            if (!$_SESSION['openid']) {
                // 没有openid

                $this->weixin_config = M('wx_user')->find(); //获取微信配置
                $this->assign('wechat_config', $this->weixin_config);

                if (is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1) {
                    // 微信公众号已接入

                    $wxuser = $this->GetOpenid(); //授权获取openid以及微信用户信息

                    session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号
                    session('openid', $wxuser['openid']);// 授权用户的openid
                    session('wxuser', $wxuser);          // 授权用户的信息
                    setcookie('subscribe', $wxuser['subscribe']);
                }
            }
        }
        $this->public_assign();
    }

    public function toLogin()
    {

        $is_bind_account = tpCache('basic.is_bind_account');

        if ($this->isWXBrowser() && $is_bind_account) {
            //微信浏览器, 调到绑定账号引导页面
            $this->redirect(U('User/bind_guide'));
        } else {
            $this->redirect(U('User/bind_guide'));
            // $this->redirect(U('User/login'));
        }
    }

    /**
     * 非微信浏览器时提示
     */
    public function notWXBrowserError()
    {
//        $code = 0;
//        $msg  = '请在微信客户端打开链接';
//        $data = '';
//        $url = Request::instance()->isAjax() ? '' : 'javascript:history.back(-1);';
//        $wait = 3;
//        $result = [
//            'code' => $code,
//            'msg'  => $msg,
//            'data' => $data,
//            'url'  => $url,
//            'wait' => $wait,
//        ];
//
//        $type = $this->getResponseType();
//        if ('html' == strtolower($type)) {
//            $result = View::instance(Config::get('template'), Config::get('view_replace_str'))
//                ->fetch('public:not_wx_browser', $result);
//        }
//        $response = Response::create($result, $type);
//        throw new HttpResponseException($response);


        $this->redirect('mobile/login/index');


    }


    /**
     * 保存公告变量到 smarty中 比如 导航
     */
    public function public_assign()
    {
        $first_login = session('first_login');
        $this->assign('first_login', $first_login);
        if (!$first_login && ACTION_NAME == 'login') {
            session('first_login', 1);
        }

        $tpshop_config = array();
        $tp_config = M('config')->cache(true, TPSHOP_CACHE_TIME)->select();
        foreach ($tp_config as $k => $v) {
            if ($v['name'] == 'hot_keywords') {
                $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }
            $tpshop_config[$v['inc_type'] . '_' . $v['name']] = $v['value'];
        }
        $this->tpshop_config = $tpshop_config;

        $goods_category_tree = get_goods_category_tree();
       /* echo '<pre>';
        dump($goods_category_tree);exit;*/
        $this->cateTrre = $goods_category_tree;
        $this->assign('goods_category_tree', $goods_category_tree);
        $brand_list = M('brand')->cache(true, TPSHOP_CACHE_TIME)->field('id,cat_id,logo,is_hot')->where("cat_id>0")->select();
        $this->assign('brand_list', $brand_list);
        $this->assign('tpshop_config', $tpshop_config);
        /** 修复首次进入微商城不显示用户昵称问题 **/
        $user_id = cookie('user_id');
        $uname = cookie('uname');
        if (empty($user_id) && ($users = session('user'))) {
            $user_id = $users['user_id'];
            $uname = $users['nickname'];
        }
        $this->assign('user_id', $user_id);
        $this->assign('uname', $uname);

    }

    // 网页授权登录获取 OpendId
    public function GetOpenid()
    {
        if ($_SESSION['openid'])
            return $_SESSION['openid'];
        //通过code获得openid
        if (!isset($_GET['code'])) {
            //触发微信返回code码
            //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            $baseUrl = urlencode($this->get_url());
            $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            // 跳转到微信授权页面 需要用户确认登录的页面 之后跳转到当前页面
            Header("Location: $url");
            exit();
        } else {
            //上面获取到code后这里跳转回来
            $code = $_GET['code'];
            $data = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid
            $data2 = $this->GetUserInfo($data['access_token'], $data['openid']);//获取微信用户信息
            $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
            $data['sex'] = $data2['sex'];
            $data['head_pic'] = $data2['headimgurl'];
            $data['subscribe'] = $data2['subscribe'];
            $data['oauth_child'] = 'mp';
            $_SESSION['openid'] = $data['openid'];
            $data['oauth'] = 'weixin';
            if (isset($data2['unionid'])) {
                $data['unionid'] = $data2['unionid'];
            }
            return $data;
        }
    }

    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url()
    {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
    }

    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
        //1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
        //2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);
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
        return $data;
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
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
//        $urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE" . "#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?" . $bizString;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["secret"] = $this->weixin_config['appsecret'];
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?" . $bizString;
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

    public function ajaxReturn($data)
    {
        exit(json_encode($data));
    }

    /**
     * 检测访问浏览器是否是微信
     *
     * @return bool
     */
    public function isWXBrowser()
    {
        return (bool)strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger');
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


}