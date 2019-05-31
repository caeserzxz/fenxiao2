<?php

namespace app\mobile\controller;
use Think\Db;
use app\common\logic\UsersLogic;
use \think\Request;
class Share extends MobileBase {

    //显示个人推广二维码
    public function index(){
        $img  = Db::name('n_goods_config')
            ->where('key','qecode_breakground')
            ->find();
        //通过session获取用户Id
        $userSession = session('user');
        $getId = $userSession['user_id'];
        //获取当前登录的个人信息
        $user = M("users")->where('user_id',$getId)->find();
        $savePath = APP_PATH . '/../public/qrcode/';
        $webPath = '/../public/qrcode/';
        $qrData = "http://".$_SERVER['HTTP_HOST'].'/mobile/login/register?rec_id='.$user['user_id'];
        $qrLevel = 'H';
        $qrSize = '8';
        $savePrefix = 'qrcode';
        $new = new UsersLogic();
        if($filename =$new->createQRcode($savePath, $qrData, $qrLevel, $qrSize, $savePrefix)){
            $pic = $webPath . $filename;
        }
        $bie_name = basename($pic);
        $pic  =substr($pic, 2);

        $pic = $this->huahua($pic,$bie_name);

        $this->assign('pic',$pic);
        $this->assign('user',$user);
        $this->assign('img',$img);
        return $this->fetch();
    }
    public  function huahua($erweima,$bie_name){
        $pathstr = './public/upload/qrcode/'.$bie_name;
        if(file_exists($pathstr)){
            return $bie_name;
        }
        $im = './public/upload/ditu/ditu.png';
//        $erweima = I('erweima');

        $config = array(
            'image'=>array(
                array(
                    'url'=>"$erweima",     //二维码资源
                    'stream'=>0,
                    'left'=>250,
                    'top'=>600,
                    'right'=>0,
                    'bottom'=>0,
                    'width'=>250,
                    'height'=>250,
                    'opacity'=>100
                )
            ),
            'text'=> array(
            ),
            'background'=>$im,         //背景图
        );

        $filename = 'public/upload/qrcode/'.$bie_name;
        return  $this->createPoster($config,$filename);
        exit;
    }

    function createPoster($config=array(),$filename=""){
        header("Content-type: text/html; charset=utf-8");
        //如果要看报什么错，可以先注释调这个header

        if(empty($filename))
            header("content-type: image/png");
        $imageDefault = array(
            'left'=>0,
            'top'=>0,
            'right'=>0,
            'bottom'=>0,
            'width'=>100,
            'height'=>100,
            'opacity'=>100
        );
        $textDefault = array(
            'left'=>0,
            'top'=>0,
            'fontSize'=>32,       //字号
            'fontColor'=>'0,0,0', //字体颜色
            'angle'=>0,
        );
        $background = $config['background'];//海报最底层得背景
        //背景方法

        $backgroundInfo = getimagesize($background);
        $backgroundFun = 'imagecreatefrom'.image_type_to_extension($backgroundInfo[2], false);
        $background = $backgroundFun($background);
        $backgroundWidth = imagesx($background);  //背景宽度
        $backgroundHeight = imagesy($background);  //背景高度
        $imageRes = imageCreatetruecolor($backgroundWidth,$backgroundHeight);
        $color = imagecolorallocate($imageRes, 255, 255, 255);
        imagefill($imageRes, 0, 0, $color);
        // imageColorTransparent($imageRes, $color);  //颜色透明
        imagecopyresampled($imageRes,$background,0,0,0,0,imagesx($background),imagesy($background),imagesx($background),imagesy($background));

        if(!empty($config['image'])){
            foreach ($config['image'] as $key => $val) {
                $val = array_merge($imageDefault,$val);
                $info = getimagesize($val['url']);

                $function = 'imagecreatefrom'.image_type_to_extension($info[2], false);
                if($val['stream']){   //如果传的是字符串图像流
                    $info = getimagesizefromstring($val['url']);
                    $function = 'imagecreatefromstring';
                }
                $res = $function($val['url']);
                $resWidth = $info[0];
                $resHeight = $info[1];
                //建立画板 ，缩放图片至指定尺寸

                $canvas=imagecreatetruecolor($val['width'], $val['height']);
                imagefill($canvas, 0, 0, $color);
                //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
                imagecopyresampled($canvas, $res, 0, 0, 0, 0, $val['width'], $val['height'],$resWidth,$resHeight);
                $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']) - $val['width']:$val['left'];
                $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']) - $val['height']:$val['top'];
                //放置图像
                imagecopymerge($imageRes,$canvas, $val['left'],$val['top'],$val['right'],$val['bottom'],$val['width'],$val['height'],$val['opacity']);//左，上，右，下，宽度，高度，透明度
            }
        }

        //处理文字
        if(!empty($config['text'])){
            foreach ($config['text'] as $key => $val) {
                $val = array_merge($textDefault,$val);

                list($R,$G,$B) = explode(',', $val['fontColor']);
                $fontColor = imagecolorallocate($imageRes, $R, $G, $B);
                $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']):$val['left'];
                $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']):$val['top'];

                imagettftext($imageRes,$val['fontSize'],$val['angle'],$val['left'],$val['top'],$fontColor,"./wryahei.ttf",$val['text']);
            }
        }

        //生成图片
        if(!empty($filename)){
            $res = imagejpeg ($imageRes,$filename,90); //保存到本地
            imagedestroy($imageRes);
            if(!$res) return false;
            return $filename;
        }else{
            imagejpeg ($imageRes);     //在浏览器上显示
            imagedestroy($imageRes);
        }
    }
}