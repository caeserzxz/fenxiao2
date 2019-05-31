<?php

namespace app\mobile\controller;

use app\common\logic\JssdkLogic;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use think\Controller;
use Think\Db;
use think\Page;
use think\Log;
use think\Session;

class Index extends Controller
{

    public $session_id;
    public $weixin_config;
    public $cateTrre = array();

    protected $tpshop_config = [];
    public function _initialize(){

        $in = I('in');
        if($in){
            Session('in',$in);
        }

        if($this->isWXBrowser()){
            $wxuser = session('wx_user');
            //没关注的用户每次进来都清空session
            Session::delete('wx_user');
            session('wx_user','');
//            Session::delete('user');
//            Session::delete('openid');
//            if($wxuser){
//                if($wxuser['subscribe']==0){
//                    Session::delete('wx_user');
//                }
//            }

            if (!session('wx_user')) {
                // 没有openid
                $this->weixin_config = M('wx_user')->find(); //获取微信配置
                $this->assign('wechat_config', $this->weixin_config);
                if (is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1) {
                    // 微信公众号已接入

                    //授权获取用户信息
                    $weiconfig = M('wx_user')->find(); //获取微信配置
                    $appId = $weiconfig['appid'];
                    $appsecret = $weiconfig['appsecret'];

                    if (!isset($_GET['code'])){
                        $redirect_uri = 'http://'.$_SERVER["SERVER_NAME"].'/Mobile/Index/index';
                        # 获取code
                        $code =$this->getCode($redirect_uri,'userinfo',$appId);

                    }else{
                        $code = $_GET['code'];

                        //获取网页授权access_token
                        $tokenUrl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appId}&secret={$appsecret}&code=" . $code . "&grant_type=authorization_code";
                        $tokenUrlArr = $this->http_curl($tokenUrl,'get');
                        //获取用户的基本信息
                        $userInfo = $this->GetUserInfo($tokenUrlArr['access_token'],$tokenUrlArr['openid']);
                        $wxuser =$userInfo;

                        if($userInfo){
                            $userInfo['head_pic'] = $userInfo['headimgurl'];
                            session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号
                            session('wx_user', $wxuser);          // 授权用户的信息
                            setcookie('subscribe', $wxuser['subscribe']);
                        }
                    }

                }
            }

        }
    }
    public function index()
    {

        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;
            // 微信Jssdk 操作类 用分享朋友圈 JS
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();
            print_r($signPackage);
        */


        //梅克伦判断没绑定手机号码，强制跳转到绑定页面
        /*  $user = session('user');
          $user = M('users')->where("user_id", $user['user_id'])->find();
          if (!$user['mobile']) {
              $this->redirect(U('User/setMobile'));
          }*/
        //判断首页是否能够开启访问
        $config = M('n_goods_config')
            ->where('key', 'is_show_system')
            ->find();
        if ($config['value'] == '1') {
            $this->redirect('mobile/login/close');
        }

        //获取首页商品搜索
        $input = input('');
        $indexGoodsWhere = array();
        if (isset($input['goods_name']) && !empty($input['goods_name'])) {
            $indexGoodsWhere['goods_name'] = array('like', "%$input[goods_name]%");
        }


        //新品轮播
        $new_goods = M('goods')
            ->where("is_new=1 and is_on_sale=1")
            ->where($indexGoodsWhere)
            ->order('sort asc')
            //->limit(0,5)
            //->cache(true, TPSHOP_CACHE_TIME)
            ->select();//首页热卖商品


        //除新品外的其余全部商品
        $index_goods = M('goods')
            ->where("is_new=0 and is_on_sale=1")
            ->where($indexGoodsWhere)
            ->order('sort asc')
            //->cache(true, TPSHOP_CACHE_TIME)
            ->select();//首页热卖商品


        $thems = M('goods_category')
            ->where('level=1')->order('sort_order')
            ->limit(9)
            //->cache(true, TPSHOP_CACHE_TIME)
            ->select();
        $this->assign('thems', $thems);
        $this->assign('index_goods', $index_goods);
        $this->assign('new_goods', $new_goods);
        $favourite_goods = M('goods')
            ->where("is_recommend=1 and is_on_sale=1")
            ->order('goods_id DESC')
            ->limit(20)
            //->cache(true, TPSHOP_CACHE_TIME)
            ->select();//首页推荐商品

        //秒杀商品
        $now_time = time();  //当前时间
        if (is_int($now_time / 7200)) {      //双整点时间，如：10:00, 12:00
            $start_time = $now_time;
        } else {
            $start_time = floor($now_time / 7200) * 7200; //取得前一个双整点时间
        }
        $end_time = $start_time + 7200;   //结束时间
        $flash_sale_list = M('goods')->alias('g')
            ->field('g.goods_id,f.price,s.item_id')
            ->join('flash_sale f', 'g.goods_id = f.goods_id', 'LEFT')
            ->join('__SPEC_GOODS_PRICE__ s', 's.prom_id = f.id AND g.goods_id = s.goods_id', 'LEFT')
            ->where("start_time = $start_time and end_time = $end_time")
            ->limit(3)->select();
        //banner
        $banner = M("n_banner")->where('is_show', 1)->select();

        //公告栏
        $getNotice = M("n_goods_config")->where('key', 'index_tips')->where('is_show', 1)->find();
        //首页图标分类
        $getArticle = M("n_icon")->where('is_show', 1)->limit(8)->select();
        foreach ($getArticle as &$c) {
            $c['url'] = U($c['url']);
        }

//        dump($getArticle);die;
        //商品分类栏
        $getClass = M('goods_category')->where('is_show', 1)->where('level', 1)->limit(10)->select();


        //首页轮播文章
        $getArticleBanner = M("article")->where('is_index', 1)->where('is_banner', 1)->limit(6)->select();

        //是否关注
        $wxuser = Session('wx_user');
        if(isset($wxuser['subscribe'])){
            if($wxuser['subscribe']==0&&$wxuser['openid']){
                $subscribe = 2;//未关注
            }else{
                $subscribe = 1;//已关注
            }
        }
        $this->assign('getClass', $getClass);
        $this->assign('articleBanner', $getArticleBanner);
        $this->assign('articleList', $getArticle);
        $this->assign('getNotice', $getNotice);
        $this->assign('banner', $banner);
        $this->assign('flash_sale_list', $flash_sale_list);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('favourite_goods', $favourite_goods);
        $this->assign('searchGoods', $input['goods_name']);
        $this->assign('subscribe',$subscribe);
        return $this->fetch();
    }

    public function _index_goods()
    {
        $where = array(     //条件
            'is_on_sale' => 1,
            'prom_type' => 0,
            'is_recommend' => 1,
        );
        $type = I('get.type');
        if ($type == 'new') {
            $order = 'shop_price';
        } elseif ($type == 'comment') {
            $order = 'sales_sum';
        } else {
            $order = 'goods_id';
        }
        $count = M('goods')->where($where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $p = I('p') ? I('p') : 1;
        $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page', $show);    // 赋值分页输出
        $list = M('goods')->where($where)->field(['goods_id', 'goods_name', 'shop_price', 'hk_bs_good'])->page($p, $pagesize)->order($order)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList()
    {
        return $this->fetch();
    }

    /**
     * 模板列表
     */
    public function mobanlist()
    {
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach ($arr as $key => $val) {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";
        }
    }

    /**
     * 商品列表页
     */
    public function goodsList()
    {
        $id = I('get.id/d', 0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    public function ajaxGetMore()
    {
        $p = I('p/d', 1);
        $where = ['is_recommend' => 1, 'is_on_sale' => 1, 'virtual_indate' => ['exp', ' = 0 OR virtual_indate > ' . time()]];
        $favourite_goods = Db::name('goods')->where($where)->order('goods_id DESC')->page($p, C('PAGESIZE'))->cache(true, TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('favourite_goods', $favourite_goods);
        return $this->fetch();
    }

    //微信Jssdk 操作类 用分享朋友圈 JS
    /* public function ajaxGetWxConfig()
     {
         $user = session('user');
         Log::alert(['获取用户信息user ' => json_encode($user)]);
         // $user['user_id'];
         $askUrl = I('askUrl');//分享URL
         Log::alert(['分享链接askUrl ' => json_encode($askUrl)]);
         $weixin_config = M('wx_user')->find(); //获取微信配置
         $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
         $signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
         if ($signPackage) {
             $this->ajaxReturn($signPackage, 'JSON');
         } else {
             return false;
         }
     }*/


    public function uploadApp()
    {
        //获取域名并返回
        $data = array();
        $data['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/Mobile/Login/uploadapp';

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
     * 检测访问浏览器是否是微信
     *
     * @return bool
     */
    public function isWXBrowser()
    {
        return (bool)strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger');
    }
}