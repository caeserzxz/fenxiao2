<?php

namespace app\common\logic;

use app\common\util\WechatUtil;
use app\common\logic\UsersLogic;
use think\Image;
use think\Log;
use app\common\model\Users;
use think\db;
class WechatLogic
{
    private $wx_user;
    private $wechatObj;

    public function __construct()
    {
        

        $this->wx_user = M('wx_user')->find();
        if ($this->wx_user['wait_access'] == 0) {

            exit($_GET["echostr"]);
        }
        
        $this->wechatObj = new WechatUtil($this->wx_user);
    
    }

    /**
     * 处理接收推送消息
     */
    public function handleMessage()
    {
        $wechatObj = $this->wechatObj;
        

        $msg = $wechatObj->handleMessage();

        Log::alert(['msg '=>json_encode($msg)]);
        if (!$msg) {
            exit($wechatObj->getError());
        }

        if ($msg['MsgType'] == 'event') {
            if ($msg['Event'] == 'subscribe') {
                $ret = $this->handleSubscribeEvent($msg);//首次关注事件
                //$this->ajaxReturn($ret);
            } elseif ($msg['Event'] == 'SCAN') { 
                $ret = $this->handleSCAN($msg);//已关注扫码事件
            } elseif ($msg['Event'] == 'CLICK') {
                $this->handleClickEvent($msg);//点击事件
            } elseif ($msg['Event'] == 'LOCATION') {
                $this->handleClickEvent($msg);////上报地理位置（LOCATION）
            } elseif ($msg['Event'] == 'unsubscribe') {
                $this->handleClickEvent($msg);//取消关注事件
            } elseif ($msg['Event'] == 'verify_expired') {
                $this->handleClickEvent($msg);//点击事件
            } 

        } elseif ($msg['MsgType'] == 'text') {

            
            //Log::alert(['MsgType :'=>json_encode($msg)]);
            
            $this->handleTextMsg($msg);//用户输入文本
        }
        //默认回复
        $this->replyDefault($msg);
    }

    /**
     * 处理关注事件
     * @param array $msg
     * @return type
     */
    private function handleSubscribeEvent($msg)
    {
        $openid = $msg['FromUserName'];
        Log::alert(['首次绑定openid '=>json_encode($openid)]);
        if (!$openid) {
             return ['status' => -1, 'msg' => "openid无效"];
        }

        if ($msg['MsgType'] != 'event' || $msg['Event'] != 'subscribe') {
            return ['status' => 1, 'msg' => "不是关注事件"];
        }

        $logic = new UsersLogic();
        
        //根据openid 查找user表是否有数据
        $wechatObj = $this->wechatObj;
        if (false === ($wxdata = $wechatObj->getFanInfo($openid))) 
        {
            //Log::alert(['首次绑定获取用户信息wxdata '=>json_encode($wxdata)]);
            return ['status' => -1, 'msg' => $wechatObj->getError()];
        }
        //上级id
        $wxdata['first_leader'] = substr($msg['EventKey'], strlen('qrscene_'));
        //Log::alert(['获取首次上级id '=>$wxdata['first_leader']]);
        $wxdata['oauth']='weixin';
        $wxdata['head_pic']=$wxdata['headimgurl'];
        $wxdata['oauth_child']= 'mp';
        $data = $logic->thirdLogin($wxdata);
        //Log::alert(['绑定返回的信息data '=> json_encode($data)]);

        $text=db('wx_firstreply')->where('id',1)->value('text');
        Log::alert(['首次关注回复text '=> json_encode($text)]);
        if(empty($text))
        {
            $text = "欢迎你来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile';
        }
        // $wechatObj->sendMsg($openid, "欢迎你来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile');
        $wechatObj->sendMsg($openid, $text);
        exit;
        // if (!($user = M('users')->where('openid', $openid)->find())) 
        // {
        //     if (false === ($wxdata = $wechatObj->getFanInfo($openid))) 
        //     {
        //         Log::alert(['首次绑定获取用户信息wxdata '=>json_encode($wxdata)]);
        //         return ['status' => -1, 'msg' => $wechatObj->getError()];
        //     }
        //     Log::alert(['首次绑定获取用户信息wxdata '=>json_encode($wxdata)]);
        //     $wxdata['oauth']='weixin';
        //     $data = $logic->thirdLogin($wxdata);
        //     Log::alert(['绑定返回的信息data '=> json_encode($data)]);
        //     // $user = [
        //     //     'openid'    => $openid,
        //     //     'head_pic'  => $wxdata['headimgurl'],
        //     //     'nickname'  => $wxdata['nickname'] ?: '微信用户',
        //     //     'sex'       => $wxdata['sex'] ?: 0,
        //     //     'subscribe' => $wxdata['subscribe'],
        //     //     'oauth'     => 'weixin',
        //     //     'reg_time'  => time(),
        //     //     'token'     => md5(time().mt_rand(1,99999)),
        //     //     'password'  => '',
        //     //     'is_distribut' => 1,
        //     // ];
        //     // isset($wxdata['unionid']) && $user['unionid'] = $wxdata['unionid'];

        //     // // 由场景值获取分销一级id
        //     // if (!empty($msg['EventKey'])) 
        //     // {
        //     //     //拿上级id
        //     //     $user['first_leader'] = substr($msg['EventKey'], strlen('qrscene_'));
        //     //     if ($user['first_leader']) 
        //     //     {   
        //     //         //拿上级id查询user表是否存在这个用户
        //     //         $pid_data = M('users')->where('user_id', $user['first_leader'])->find();
        //     //         Log::alert(['查找首次关注的他的上级记录pid_data'=>json_encode($pid_data)]);
        //     //         if ($pid_data) 
        //     //         {   
                        
        //     //             //他上线分销的下线人数要加1
        //     //             $rest=M('users')->where('user_id', $user['first_leader'])->setInc('underling_number');
        //     //             Log::alert(['查找首次关注的他的上级underling_number+1 '=>json_encode($rest)]);   
        //     //         }
        //     //     } 
        //     //     else 
        //     //     {
        //     //         //默认上级为厂家0
        //     //         $user['first_leader'] = 0;
        //     //     }
        //     // }
        //     // //添加新用户数据
        //     // $ret = M('users')->insert($user);
        //     // Log::alert(['首次关注添加数据返回ret '=> json_encode($ret)]);
        //     // if (!$ret) {
        //     //     return ['status' => -1, 'msg' => "保存数据出错"];
        //     // }

        //     //推送信息内容
        //     //查表找出首次关注回复的内容
        //     // $Content =  "你好,欢迎来到绿源龙硒泉商城!";
        //     // $ToUserName = $msg['ToUserName'];
        //     // $FromUserName = $msg['FromUserName'];
        //     // //$CreateTime= time();
        //     // return $this->$wechatObj->createReplyMsgOfText($FromUserName, $ToUserName, $Content);
        //     $text=db('wx_firstreply')->where('id',1)->value('text');
        //     Log::alert(['首次关注回复text '=> json_encode($text)]);
        //     if(empty($text))
        //     {
        //         $text = "欢迎你来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile';
        //     }
        //     // $wechatObj->sendMsg($openid, "欢迎你来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile');
        //     $wechatObj->sendMsg($openid, $text);
        //     exit;
        // }
        // $wechatObj->sendMsg($openid, "欢迎你来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile');
        // exit;
    }


    /**
     * 已关注事件推送
     * @param array $msg
     * @return type
     */
    private function handleSCAN($msg)
    {
        //Log::alert(['handleSCAN '=>json_encode($msg)]);
        $openid = $msg['FromUserName'];
        if (!$openid) 
        {
             return ['status' => -1, 'msg' => "openid无效"];
        }

        if ($msg['MsgType'] != 'event' || $msg['Event'] != 'SCAN') 
        {
            return ['status' => 1, 'msg' => "不是用户已关注时的事件推送"];
        }
        //Log::alert(['openid '=>json_encode($openid)]);
        $wechatObj = $this->wechatObj;

        //根据openid查询该用户是否有上级
        $user = M('users')->field('pid,user_id')->where('openid','like','%'.$openid)->find();
        Log::alert(['user :'=>json_encode($user)]);

        if(!empty($user['user_id']))
        {
            $text = "绑定上级失败! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile';
            $wechatObj->sendMsg($openid, $text);
            exit;
        }
        //查询users表查看用户消费过东西么,有消费过的话跳过更新绑定新的pid
         $pay_data = M('users')->where('user_id',$user['user_id'])->where('is_purchased',1)->find();
         Log::alert(['pay_data :'=>json_encode($pay_data)]);
        $Users=Users::get($user['user_id']);
        $is_purchased=$Users->isPurchased();
        Log::alert(['is_purchased :'=>json_encode($is_purchased)]);

        if($is_purchased)
        {
            $text=db('wx_firstreply')->where('id',1)->value('text');
            //Log::alert(['首次关注回复text '=>json_encode($text)]);
            if(empty($text))
            {
                $text = "欢迎你来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile';
            }

            $wechatObj->sendMsg($openid, $text);
            exit;

        }


        // 没买过东西，由场景值获取分销一级id
        if (!empty($msg['EventKey']))
        {
            //拿上级id
            $where['first_leader'] = $msg['EventKey'];
            Log::alert(['上级id： '=>json_encode($where['first_leader'])]);
            if($user['user_id']==$where['first_leader'])
            {
                $text = "不能绑定自己为上级ID! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile';
                $wechatObj->sendMsg($openid, $text);
                exit;
            }
            if ($where['first_leader'])
            {
                //上级id存在数据
                $pid_data = M('users')->where('user_id', $where['first_leader'])->find();
                if ($pid_data)
                {

                    //他上线分销的下线人数要加1
                    M('users')->where('user_id', $where['first_leader'])->setInc('underling_number');

                    //原来的上级 下线人数减一
                    M('users')->where('user_id', $user['first_leader'])->setDec('underling_number');

                }
            }
            else
            {
                $where['first_leader'] = 0;
            }

            //更新上级id
            $update=M('users')->where('openid', $openid)->update($where);
            Log::alert(['更新update的结果 '=>json_encode($update)]);
        }
        
        
           
        $text=db('wx_firstreply')->where('id',1)->value('text');
        Log::alert(['首次关注回复text '=>json_encode($text)]);
        if(empty($text))
        {
            $text = "欢迎你来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile';
        }

        $wechatObj->sendMsg($openid, $text);
        exit;

        //推送信息内容
        //查表找出首次关注回复的内容
        // $Content =  "你好,欢迎来到绿源龙硒泉商城!";

        // $ToUserName = $msg['ToUserName'];
        // $FromUserName = $msg['FromUserName'];
        // //$CreateTime= time();
        // return $this->$wechatObj->createReplyMsgOfText($FromUserName, $ToUserName, $Content);
        // $wechatObj->sendMsg($openid, "欢迎您来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile');
        // exit;
    }



    /**
     * 处理点击事件
     * @param type $msg
     */
    private function handleClickEvent($msg)
    {
        $eventKey = $msg['EventKey'];
        $distribut = tpCache('distribut');

        //分销二维码图片
        if ($distribut['qrcode_menu_word'] && $eventKey == $distribut['qrcode_menu_word']) {
            $this->replyMyQrcode($msg);
        }

        //其他处理
        $this->handleTextMsg($msg);
    }

    /**
     * 回复我的二维码
     */
    private function replyMyQrcode($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = $this->wechatObj;

        if (!($user = M('users')->where('openid', $fromUsername)->find())) {
            $content = '请进入商城: '.SITE_URL.' , 再获取二维码哦';
            $reply = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
            exit($reply);
        }

        //获取缓存的图片id
        $distribut = tpCache('distribut');
        $mediaId = $this->getCacheQrcodeMedia($user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        if (!$mediaId) {
            $mediaId = $this->createQrcodeMedia($msg, $user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        }

        //回复图片消息
        $reply = $wechatObj->createReplyMsgOfImage($toUsername, $fromUsername, $mediaId);
        exit($reply);
    }

    private function createQrcodeMedia($msg, $userId, $headPic, $qrBackImg)
    {
        $wechatObj = $this->wechatObj;

        //创建二维码关注url
        $qrCode = $wechatObj->createTempQrcode(2592000, $userId);
        if (!(is_array($qrCode) && $qrCode['url'])) {
            $this->replyError($msg, '创建二维码失败');
        }

        //创建分销二维码图片
        $shareImg = $this->createShareQrCode('.'.$qrBackImg, $qrCode['url'], $headPic);
        if (!$shareImg) {
            $this->replyError($msg, '生成图片失败');
        }

        //上传二维码图片
        if (!($mediaInfo = $wechatObj->uploadTempMaterial($shareImg, 'image'))) {
            @unlink($shareImg);
            $this->replyError($msg, '上传图片失败');
        }
        @unlink($shareImg);

        $this->setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo);

        return $mediaInfo['media_id'];
    }

    private function getCacheQrcodeMedia($userId, $headPic, $qrBackImg)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        $config = cache($mediaIdCache);
        if (!$config) {
            return false;
        }

        //$config = json_decode($config);
        //有效期3天（259200s）,提前5小时(18000s)过期
        if (!(is_array($config) && $config['media_id'] && ($config['created_at'] + 259200 - 18000) > time())) {
            return false;
        }

        return $config['media_id'];
    }

    private function setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        cache($mediaIdCache, $mediaInfo);
    }

    /**
     * 处理点击推送事件
     * @param array $msg
     */
    private function handleTextMsg($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $keyword      = trim($msg['Content']);

        //点击菜单拉取消息时的事件推送
        /*
         * 1、click：点击推事件
         * 用户点击click类型按钮后，微信服务器会通过消息接口推送消息类型为event的结构给开发者（参考消息接口指南）
         * 并且带上按钮中开发者填写的key值，开发者可以通过自定义的key值与用户进行交互；
         */
        if ($msg['MsgType'] == 'event' && $msg['Event'] == 'CLICK') {
            $keyword = trim($msg['EventKey']);
        }

        if (empty($keyword)) {
            return false;
        }

        //分销二维码图片
        $distribut = tpCache('distribut');
        if ($distribut['qrcode_input_word'] && $distribut['qrcode_input_word'] == $msg['Content']) {
            $this->replyMyQrcode($msg);
        }

        // 图文回复
        $wx_img = M('wx_img')->where("keyword", "like", "%$keyword%")->find();
        if( $wx_img) {
            $articles[0] = [
                'title'       => $wx_img['title'],
                'description' => $wx_img['desc'],
                'picurl'      => $wx_img['pic'],
                'url'         => $wx_img['url']
            ];
            $resultStr = $this->wechatObj->createReplyMsgOfNews($toUsername, $fromUsername, $articles);
            exit($resultStr);
        }


        // 文本回复
        $wx_text = M('wx_text')->where("keyword", "like", "%$keyword%")->find();
        if ($wx_text) {
            $resultStr = $this->wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $wx_text['text']);
            exit($resultStr);
        }
    }

    /**
     * 默认回复
     * @param type $msg
     */
    private function replyDefault($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $content = '欢迎来到绿源龙硒泉!';
        $resultStr = $this->wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
        exit($resultStr);
    }

    private function replyError($msg, $extraMsg = '')
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = $this->wechatObj;

        if ($wechatObj->isDedug()) {
            $content = '错误信息：';
            $content .= $wechatObj->getError() ?: '';
            $content .= $extraMsg ?: '';
        } elseif ($extraMsg) {
            $content = '系统信息：'.$extraMsg;
        } else {
            $content = '系统正在处理...';
        }

        $resultStr = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
        exit($resultStr);
    }

    /**
     * 创建分享二维码图片
     * @param type $backImg 背景大图片
     * @param type $qrText  二维码文本:关注入口
     * @param type $headPic 头像路径
     * @return 图片路径
     */
    private function createShareQrCode($backImg, $qrText, $headPic)
    {
        if (!is_file($backImg) || !$headPic || !$qrText) {
            return false;
        }

        vendor('phpqrcode.phpqrcode');
        vendor('topthink.think-image.src.Image');

        $qr_code_path = './public/upload/qr_code/';
        !file_exists($qr_code_path) && mkdir($qr_code_path, 0777, true);

        /* 生成二维码 */
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        \QRcode::png($qrText, $qr_code_file, QR_ECLEVEL_M);

        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        $QR_height = $QR->height();


        /* 添加背景图 */
        if ($backImg && is_file($backImg)) {
            $back =Image::open($backImg);
            $backWidth = $back->width();
            $backHeight = $back->height();

            //生成的图片大小以540*960为准
            if ($backWidth <= $backHeight) {
                $refWidth = 540;
                $refHeight = 960;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                } else {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                }
            } else {
                $refWidth = 960;
                $refHeight = 540;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                } else {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                }
            }

            $shortSize = $backWidth > $backHeight ? $backHeight : $backWidth;
            $QR_width = $shortSize / 2;
            $QR_height = $QR_width;
            $QR->thumb($QR_width, $QR_height, \think\Image::THUMB_CENTER)->save($qr_code_file, null, 100);
            $back->thumb($backWidth, $backHeight, \think\Image::THUMB_CENTER)
                ->water($qr_code_file, \think\Image::WATER_CENTER, 90)->save($qr_code_file, null, 100);
            $QR = $back;
        }

        /* 添加头像 */
        if ($headPic) {
            //如果是网络头像
            if (strpos($headPic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $headPic);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path.time().rand(1, 10000).'.png';
                    file_put_contents($head_pic_path, $file_content);
                    $headPic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($headPic)) {
                $logo = Image::open($headPic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 5;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path.time().rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->water($logo_file, \think\Image::WATER_CENTER);
                unlink($logo_file);
            }
            if ($head_pic_path) {
                unlink($head_pic_path);
            }
        }

        //加上有效时间
        $valid_date = date('Y.m.d', strtotime('+30 days'));
        $QR = $QR->text('有效时间 '.$valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 16, '#FFFFFF', Image::WATER_SOUTH)->save($qr_code_file);

        return $qr_code_file;
    }
}