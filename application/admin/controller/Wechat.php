<?php

namespace app\admin\controller;

use app\common\util\WechatUtil;
use think\Db;
use think\Page;
use think\Cache;
use think\Log;
class Wechat extends Base
{
    public function index(){

        //$wechat_list = M('wx_user')->select();
        //$this->assign('lists',$wechat_list);
        //return $this->fetch();
        $wx_user = M('wx_user')->find();
        header("Location:".U('Wechat/setting',['id'=>$wx_user['id']]));
        exit;
    }

    public function add(){
        $exist = M('wx_user')->select();
        if($exist[0]['id'] > 0){
            $this->error('只能添加一个公众号噢');
            exit;
        }
        if(IS_POST){
            $data = I('post.');
            $data['create_time'] = time();
            $data['token'] = get_rand_str(6,1,0);
            $row = DB::name('wx_user')->insertGetId($data);
            if($row){
                $this->success('添加成功',U('Admin/Wechat/setting',array('id'=>$row)));
            }else{
                $this->error('操作失败');
            }
            exit;
        }
        return $this->fetch();
    }

    public function del(){
        $id = I('get.id');
        $row = M('wx_user')->where(array('id'=>$id))->delete();
        if($row){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');

        }
    }
    public function setting()
    {
        $id = I('get.id');
        $wechat = M('wx_user')->where(array('id'=>$id))->find();
        if(empty($wechat)){
            return $this->error('请先在公众号配置添加公众号，才能进行微信菜单管理', U('Admin/Wechat/index'));
        }
        if(IS_POST){
            $post_data = input('post.');
            $post_data['web_expires'] = 0;

            $row = M('wx_user')->where(array('id'=>$id))->update($post_data);
            if($row){
                $wx_config = M('wx_user')->find(); //获取微信配置
                Cache::set('weixin_config',$wx_config,0);
                exit($this->success("修改成功"));
            }else{
                exit($this->error("修改失败"));
            }
        }
        //$apiurl = 'http://'.$_SERVER['HTTP_HOST'].'/index.php?m=api&c=Wechat&a=handleMessage';
        $apiurl = 'http://'.$_SERVER['HTTP_HOST'].'/index.php?m=Home&c=Weixin&a=index';

        $this->assign('wechat',$wechat);
        $this->assign('apiurl',$apiurl);

        return $this->fetch();
    }
    public function menu(){

        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能进行微信菜单管理', U('Admin/Wechat/index'));
        }
        if(IS_POST){
            $post_menu = input('post.menu/a');
            //查询数据库是否存在
            $menu_list = M('wx_menu')->where(array('token'=>$wechat['token']))->getField('id',true);
            foreach($post_menu as $k=>$v){
                $v['token'] = $wechat['token'];
               if(in_array($k,$menu_list)){
                   //更新
                   M('wx_menu')->where(array('id'=>$k))->save($v);
               }else{
                   //插入
                   M('wx_menu')->where(array('id'=>$k))->add($v);
               }
            }
            $this->success('操作成功,进入发布步骤',U('Admin/Wechat/pub_menu'));
            exit;
        }
        //获取最大ID
        //$max_id = M('wx_menu')->where(array('token'=>$wechat['token']))->field('max(id) as id')->find();
        $max_id = DB::query("SHOW TABLE STATUS WHERE NAME = '__PREFIX__wx_menu'");
        $max_id = $max_id[0]['auto_increment'] ? $max_id[0]['auto_increment'] : $max_id[0]['Auto_increment'];

        //获取父级菜单
        $p_menus = M('wx_menu')->where(array('token'=>$wechat['token'],'pid'=>0))->order('id ASC')->select();
        $p_menus = convert_arr_key($p_menus,'id');
        //获取二级菜单
        $c_menus = M('wx_menu')->where(array('token'=>$wechat['token'],'pid'=>array('gt',0)))->order('id ASC')->select();
        $c_menus = convert_arr_key($c_menus,'id');
        $this->assign('p_lists',$p_menus);
        $this->assign('c_lists',$c_menus);
        $this->assign('max_id',$max_id ? $max_id-1 : 0);
        return $this->fetch();
    }


    /*
     * 删除菜单
     */
    public function del_menu(){
        $id = I('get.id');
        if(!$id){
            exit('fail');
        }
        $row = M('wx_menu')->where(array('id'=>$id))->delete();
        $row && M('wx_menu')->where(array('pid'=>$id))->delete(); //删除子类
        if($row){
            exit('success');
        }else{
            exit('fail');
        }
    }

    /*
     * 生成微信菜单
     */
    public function pub_menu(){
        $menu = array();
        $menu['button'][] = array(
            'name'=>'测试',
            'type'=>'view',
            'url'=>'http://www.tp-shop.cn'
        );
        $menu['button'][] = array(
            'name'=>'测试',
            'sub_button'=>array(
                array(
                    "type"=> "scancode_waitmsg",
                    "name"=> "系统拍照发图",
                    "key"=> "rselfmenu_1_0",
                   "sub_button"=> array()
                 )
            )
        );

        //获取菜单
        $wechat = M('wx_user')->find();
        //dump($wechat);die;
        //获取父级菜单
        $p_menus = M('wx_menu')->where(array('token'=>$wechat['token'],'pid'=>0))->order('id ASC')->select();
        $p_menus = convert_arr_key($p_menus,'id');

        $post_str = $this->convert_menu($p_menus,$wechat['token']);
        // http post请求
        if(!count($p_menus) > 0){
           $this->error('没有菜单可发布',U('Wechat/menu'));
            exit;
        }
        $access_token = $this->get_access_token($wechat['appid'],$wechat['appsecret']);
        if(!$access_token){
            $this->error('获取access_token失败',U('Wechat/menu')); //  http://www.tpshop.com/index.php/Admin/Wechat/menu

            exit;
        }
        $url ="https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$access_token}";
        //        exit($post_str);
        $return = httpRequest($url,'POST',$post_str);
        $return = json_decode($return,1);
        //dump($return );die;
        if($return['errcode'] == 0){
            $this->success('菜单已成功生成',U('Wechat/menu'));
        }else{
            echo "错误代码;".$return['errcode'];
            exit;
        }
    }

    //菜单转换
    private function convert_menu($p_menus,$token){
        $key_map = array(
            'scancode_waitmsg'=>'rselfmenu_0_0',
            'scancode_push'=>'rselfmenu_0_1',
            'pic_sysphoto'=>'rselfmenu_1_0',
            'pic_photo_or_album'=>'rselfmenu_1_1',
            'pic_weixin'=>'rselfmenu_1_2',
            'location_select'=>'rselfmenu_2_0',
        );
        $new_arr = array();
        $count = 0;
        foreach($p_menus as $k => $v){
            $new_arr[$count]['name'] = $v['name'];

            //获取子菜单
            $c_menus = M('wx_menu')->where(array('token'=>$token,'pid'=>$k))->select();

            if($c_menus){
                foreach($c_menus as $kk=>$vv){
                    $add = array();
                    $add['name'] = $vv['name'];
                    $add['type'] = $vv['type'];
                    // click类型
                    if($add['type'] == 'click'){
                        $add['key'] = $vv['value'];
                    }elseif($add['type'] == 'view'){
                        $add['url'] = $vv['value'];
                    }else{
                        //$add['key'] = $key_map[$add['type']];
                        $add['key'] = $vv['value'];       //2016年9月29日01:28:37  QQ  海南大卫照明  367013672  提供
                    }
                    $add['sub_button'] = array();
                    if($add['name']){
                        $new_arr[$count]['sub_button'][] = $add;
                    }
                }
            }else{
                $new_arr[$count]['type'] = $v['type'];
                // click类型
                if($new_arr[$count]['type'] == 'click'){
                    $new_arr[$count]['key'] = $v['value'];
                }elseif($new_arr[$count]['type'] == 'view'){
                    //跳转URL类型
                    $new_arr[$count]['url'] = $v['value'];
                }else{
                    //其他事件类型
                    //$new_arr[$count]['key'] = $key_map[$v['type']];
                    $new_arr[$count]['key'] = $v['value'];  //2016年9月29日01:40:13
                }
            }
            $count++;
        }
       // return json_encode(array('button'=>$new_arr));
        return json_encode(array('button'=>$new_arr),JSON_UNESCAPED_UNICODE);
    }


    /**
     * 群发
     * @return [type] [description]
     */
    public function qunfa()
    {
        return $this->fetch();
    }

    /**
     *根据openid群发消息 
     * 
     */
    public function _qunfa()
    {
        $this->wx_user = M('wx_user')->find();
        $this->wechatObj = new WechatUtil($this->wx_user);
        $wechatObj = $this->wechatObj;
        $input=input();
        
        //dump($input);die;
        //查询所有的openid
        $openid=db('users')->field('openid')->select();
        $list=array();
        foreach ($openid as $key => $value) {
            
            $_t=$value['openid'];
   
            $list[] =$_t;
        }

        $list=json_encode($list);

        //dump($list);die;
        $result=$wechatObj->sendMsgToMass($list,$input['text']);
        if($result)
        {
            return 'success';
        }
        else
        {
            return false;
        }
        // if($input['is_on_sale']==1)
        // {
        //     //回复文本
        //     $post_str='{
        //         "touser":[$list],
        //         "msgtype": "text",
        //         "text": { "content": "这是一个测试"}
        //     }';
        // }

        // if($input['is_on_sale']==0)
        // {
        //     //回复图文

        //     $post_str='{
        //        "touser":[
        //         [$list],
        //        ],
        //        "mpnews":{
        //           "media_id":$input["mpnews"]
        //        },
        //         "msgtype":"mpnews"，
        //         "send_ignore_reprint":0
        //     }';
        // }
        // $wechat = M('wx_user')->find();
        // $access_token = $this->get_access_token($wechat['appid'],$wechat['appsecret']);
        // if(!$access_token){
        //     $this->error('获取access_token失败',U('Wechat/qunfa')); //  http://www.tpshop.com/index.php/Admin/Wechat/menu

        //     exit;
        // }
        // $url ="https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token={$access_token}";
        // //exit($post_str);
        // $return = httpRequest($url,'POST',$post_str);
        // $return = json_decode($return,1);
        // if($return['errcode'] == 0){
        //     return 'success';
        // }else{
        //     echo "错误代码;".$return['errcode'];
        //     exit;
        // }
    }



    /**
     *粉丝管理
     * 
     */
    public function fans()
    {
        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能进行文本回复管理', U('Admin/Wechat/index'));
        }

        $input=input();
        if($input['nick_name'])
        {
            $list=db('users')->where('nickname','like','%'.$input['nick_name'])->select();
            
            $this->assign('fans_lists',$list);
            return $this->fetch();
        }
       
        //查询粉丝数量
        $count = db('users')->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        //dump($count);die;
        //查询所有粉丝
        $fans_lists=db('users')->field('user_id,sex,birthday,address_id,last_login,qq,mobile,openid,unionid,head_pic,nickname')
                               ->where('is_del','<>','1')
                               ->limit($Page->firstRow.','.$Page->listRows)
                               ->select();
       
        
        $this->assign('pager',$Page);
        $this->assign('page',$show);
        $this->assign('fans_lists',$fans_lists);
        return $this->fetch();
    }


    /**
     *
     * 粉丝删除
     */
    public function fans_del()
    {
        $input=input();
        //dump($input);die;
        $res=db('users')->where('user_id',$input['user_id'])->update(['is_del'=>1]);
        if($res){
            $this->success("删除成功",U('Wechat/fans'));
        }else{
            $this->error("删除失败");
        }
        
    }

    /**
     * 粉丝编辑
     */
    public function fans_edit()
    {
        $input=input();
        Log::write('测试log  ','notice',true);
        if($input['status']==1)
        {
            //dump($input);die;
            $updata=[
                        'first_leader'=> $input['first_leader'],
                        'nickname'=> $input['nickname'],
                        'openid'  => $input['openid'],
                        'email'   => $input['email'],
                        'qq'      => $input['qq'],
                        'mobile'  => $input['mobile'],
                    ];

            $res=db('users')->where('user_id',$input['user_id'])->update($updata);
            if($res){
                $this->success("编辑成功",U('Wechat/fans'));
            }else{
                $this->error("编辑失败");
            }
        }
        $result=db('users')->where('user_id',$input['user_id'])->find();
        //省
        $province=db('region')->where('id',$result['province'])->value('name');
        //市
        $city=db('region')->where('id',$result['city'])->value('name');
        //区、街道
        $district=db('region')->where('id',$result['district'])->value('name');
        
        //dump($res);die;
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('district',$district);
        $this->assign('res',$result);
        return $this->fetch();
       
    }

    /**
     * 首次关注回复
     */
    public function firstreply()
    {
        $res=Db::name('wx_firstreply')->where('is_del','null')->select();
        $this->assign('res',$res);
        return $this->fetch();
    }


    /**
     * 添加首次关注回复
     */
    public function add_firstr_eply()
    {
        $input=input();
        //dump($input);die;

        
        // if(empty($input['kid']) && $input['text'])
        // {
        //     dump($input);die;
        //     $data['text']=$input['text'];
        //     $data['createtime']=time();

        //     $res=Db::name('wx_firstreply')->insert($data);
        //     if($res){
            
        //         $this->success("添加成功");
        //     }
        //     else
        //     {
        //         $this->error("添加失败");
        //     }
            
        // }

        //编辑
        if($input['edit'])
        {
            //$input['id'];
            //dump($input);die;
            if(!empty($input['kid']))
            {
                //dump($input);die;
                $where =['text'=>$input['text'],'updatetime'=>time()];
                
                $res=Db::name('wx_firstreply')->where('id',$input['id'])->update($where);
                if($res)
                {
                    
                    $this->success("更新成功");
                }
                else
                {
                    $this->error("更新失败");
                }
                
            }

            $res=Db::name('wx_firstreply')->where('id',$input['id'])->find();
            $this->assign('res',$res);
            return $this->fetch();

        }
        
        
        return $this->fetch();
    }

    /**
     *删除首次回复 
     */
    public function del_firstr_eply()
    {
        $input=input();
        //dump($input);die;
        $res=db('wx_firstreply')->where('id',$input['id'])->update(['is_del'=>1]);
        if($res){
            
            $this->success("删除成功");
        }else{
            $this->error("删除失败");
        }
    }



    /*
     * 文本回复
     */
    public function text(){
        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能进行文本回复管理', U('Admin/Wechat/index'));
        }
        $count = M('wx_keyword')->where(array('token'=>$wechat['token'],'type'=>'TEXT'))->count();
        $pager = new Page($count,10);
        $sql = "SELECT k.id,k.keyword,t.text FROM __PREFIX__wx_keyword k LEFT JOIN __PREFIX__wx_text AS t ON t.id = k.pid WHERE k.token = '{$wechat['token']}' AND type = 'TEXT' ORDER BY t.createtime DESC LIMIT {$pager->firstRow},{$pager->listRows}";
        $show = $pager->show();
        $lists = DB::query($sql);
        
        $this->assign('page',$show);
        $this->assign('lists',$lists);
        $this->assign('wechat',$wechat);

        return $this->fetch();
    }
    /*
     * 添加文本回复
     */
    public function add_text(){
        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能添加文本回复', U('Admin/Wechat/index'));
        }
        if(IS_POST){

            $edit = I('get.edit');
            $add['keyword'] =  I('post.keyword');
            $add['token'] =  $wechat['token'];
            $add['text'] = I('post.text');

            if(!$edit){
                //添加模式
                $add['createtime'] = time();

                $add['pid'] = DB::name('wx_text')->insertGetId($add);
                //dump($add);die;
                unset($add['text']);
                unset($add['createtime']);
                $add['type'] = 'TEXT';

                $row = M('wx_keyword')->add($add);
            }else{
                //编辑模式
                $id = I('post.kid');
                $model = M('wx_keyword')->where(array('id'=>$id));

                $data = $model->find();
                if($data){
                    $update = I('post.');
                    $update['type'] = 'TEXT';
                    M('wx_keyword')->where(array('id'=>$id))->save($update);
                    $row = M('wx_text')->where(array('id'=>$data['pid']))->save($add);

                }
            }
            $row ? $this->success("添加成功",U('Admin/Wechat/text')) : $this->error("添加失败",U('Admin/Wechat/text'));
            exit;
        }

        $id = I('get.id');

        if($id){
            $sql = "SELECT k.id,k.keyword,t.text FROM __PREFIX__wx_keyword k LEFT JOIN __PREFIX__wx_text AS t ON t.id = k.pid WHERE k.token = '{$wechat['token']}' AND k.id = {$id} AND k.type = 'TEXT'";
            $data = DB::query($sql);
            $this->assign('keyword',$data[0]);
        }

        return $this->fetch();
    }

    /*
     * 删除文本回复
     */
    public function del_text(){
        $id = I('get.id');
        $row = M('wx_keyword')->where(array('id'=>$id))->find();
        if($row){
            M('wx_keyword')->where(array('id'=>$id))->delete();
            M('wx_text')->where(array('id'=>$row['pid']))->delete();
            $this->success("删除成功");
        }else{
            $this->error("删除失败");
        }
    }
    /*
     * 图文列表
     */
    public function img(){
        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能进行图文回复管理', U('Admin/Wechat/index'));
        }
        $count = M('wx_keyword')->where(array('token'=>$wechat['token'],'type'=>'IMG'))->count();
        $pager = new Page($count,10);
        $sql = "SELECT k.id,k.keyword,i.title,i.url,i.pic,i.desc FROM __PREFIX__wx_keyword k LEFT JOIN __PREFIX__wx_img i ON i.id = k.pid WHERE k.token = '{$wechat['token']}' AND type = 'IMG' ORDER BY i.createtime DESC LIMIT {$pager->firstRow},{$pager->listRows}";
        $show = $pager->show();
        $lists = DB::query($sql);

        $this->assign('page',$show);
        $this->assign('lists',$lists);
        $this->assign('wechat',$wechat);
        return $this->fetch();
    }
    /*
     * 添加图文回复
     */
    public function add_img(){
        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能添加图文回复', U('Admin/Wechat/index'));
        }
        if(IS_POST){

            $add['keyword'] =  I('post.keyword');
            $add['token'] =  $wechat['token'];
            $add['title'] = I('post.title');
            $add['desc'] = I('post.desc');

            $add['pic'] = I('post.pic'); //封面图片
            if(!strstr($add['pic'],'http'))
                    $add['pic'] = SITE_URL.$add['pic'];

            $add['url'] = I('post.url');  // 商品地址 或 其他
            $add['goods_id'] = I('post.goods_id');
            $add['goods_name'] = I('post.goods_name'); //商品名字

            empty($add['keyword']) && $this->error("关键词不得为空");
            empty($add['title'])   && $this->error("标题不得为空");
            empty($add['url'])     && $this->error("url不得为空");
            empty($add['pic'])     && $this->error("封面图片不得为空");
            empty($add['desc'])    && $this->error("简介不得为空");

            $edit = I('get.edit');
            if(!$edit){
                //添加模式
                $add['createtime'] = time();

            if(!strstr($add['pic'],'http'))
                $add['pic'] = SITE_URL.$add['pic'];

                $wx_img_last_ins_id =  DB::name('wx_img')->insertGetId($add);
                $add['pid'] = $wx_img_last_ins_id;
                $add['type'] = 'IMG';
                $row = M('wx_keyword')->add($add);
            }else{
                //编辑模式
                $id = I('post.kid');
                $model = M('wx_keyword')->where(array('id'=>$id,'type'=>'IMG'));

                $data = $model->find();
                if($data){
                    $update = input('post.');
                    $update['type'] = 'IMG';
                    M('wx_keyword')->where(array('id'=>$id))->save($update);
                    $add['uptatetime'] = time();
                    $row = M('wx_img')->where(array('id'=>$data['pid']))->save($add);

                }
            }
            $row ? $this->success("添加成功",U('Admin/Wechat/img')) : $this->error("添加失败",U('Admin/Wechat/img'));
            exit;
        }

        $id = I('get.id');
        if($id){
            $sql = "SELECT k.id,k.keyword,i.title,i.url,i.pic,i.desc FROM __PREFIX__wx_keyword k LEFT JOIN __PREFIX__wx_img i ON i.id = k.pid WHERE k.token = '{$wechat['token']}' AND type = 'IMG' AND k.id = {$id}";
            $data = DB::query($sql);
            $this->assign('keyword',$data[0]);
        }
        return $this->fetch();


    }

    /*
     * 选择商品
     * //todo
     * //与wap端一起做
     */
    public function select_goods(){
        $url = 'http://'.$_SERVER['HTTP_HOST'];
        //http://www.tp-shop.cn/index.php?m=Home&c=Goods&a=info&id=

        $count = M('goods')->count();
        $pager = new Page($count,10);
        //$sql = "SELECT k.id,k.keyword,t.text FROM tp_wx_keyword k LEFT JOIN tp_wx_text AS t ON t.id = k.pid WHERE k.token = '{$wechat['token']}' AND type = 'TEXT' ORDER BY t.createtime DESC LIMIT {$pager->firstRow},{$pager->listRows}";
        $show = $pager->show();
        $sql = "SELECT goods_name,shop_price,
                CONCAT('{$url}/index.php?m=Home&c=Goods&a=info&id=',goods_id) AS goods_url,
                CONCAT('{$url}/',original_img) AS original_img
                 FROM __PREFIX__goods ORDER BY goods_id DESC LIMIT {$pager->firstRow},{$pager->listRows}";
        $lists = DB::query($sql);
        $this->assign('page',$show);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    /*
     * 删除图文回复
     */
    public function del_img(){
        $id = I('get.id');
        $row = M('wx_keyword')->where(array('id'=>$id))->find();
        if($row){
            M('wx_keyword')->where(array('id'=>$id))->delete();
            M('wx_img')->where(array('id'=>$row['pid']))->delete();
            $this->success("删除成功");
        }else{
            $this->error("删除失败");
        }
    }

    /*
     * 多图文消息列表
     */
    public function news(){
        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能进行多图文管理', U('Admin/Wechat/index'));
        }
        $count = M('wx_keyword')->where(array('token'=>$wechat['token'],'type'=>'NEWS'))->count();
        $pager = new Page($count,10);
        $sql = "SELECT k.id,k.keyword,k.pid,i.img_id FROM __PREFIX__wx_keyword k LEFT JOIN __PREFIX__wx_news i ON i.id = k.pid WHERE k.token = '{$wechat['token']}' AND type = 'NEWS' ORDER BY i.createtime DESC LIMIT {$pager->firstRow},{$pager->listRows}";
        $show = $pager->show();
        $lists = DB::query($sql);

        $this->assign('page',$show);
        $this->assign('lists',$lists);
        $this->assign('wechat',$wechat);
        return $this->fetch();
    }

    /*
     * 添加多图文
     */
    public function add_news(){
        $wechat = M('wx_user')->find();
        if(empty($wechat)){
            $this->error('请先在公众号配置添加公众号，才能添加多图文', U('Admin/Wechat/index'));
        }
        if(IS_POST){
            $arr = explode(',',I('post.img_id/s'));
            if($arr)
                array_pop($arr);
            if(count($arr) <= 1){
                $this->error("单图文请到图文回复设置",U('Admin/Wechat/news'));
                exit;
            }
            $add['keyword'] =  I('post.keyword');
            $add['token'] =  $wechat['token'];
            $add['img_id'] =  implode(',',$arr);

            //添加模式
                $add['createtime'] = time();
                $wx_news_last_ins_id = DB::name('wx_news')->insertGetId($add);
                $add['pid'] = $wx_news_last_ins_id;
                $add['type'] = 'NEWS';
                $row = M('wx_keyword')->add($add);
            $row ? $this->success("添加成功",U('Admin/Wechat/news')) : $this->error("添加失败",U('Admin/Wechat/news'));
            exit;
        }
        return $this->fetch();
    }
    /*
     * 删除多图文
     */
    public function del_news(){
        $id = I('get.id');
        $row = M('wx_keyword')->where(array('id'=>$id))->find();
        if($row){
            M('wx_keyword')->where(array('id'=>$id))->delete();
            M('wx_news')->where(array('id'=>$row['pid']))->delete();
            $this->success("删除成功");
        }else{
            $this->error("删除失败");
        }
    }
    /*
     * 预览多图文
     */
    public function preview(){
        $id = I('get.id');
        $news = M('wx_news')->where(array('id'=>$id))->find();
        $lists = M('wx_img')->where(array('id'=>array('in',$news['img_id'])))->select();
        $first = $lists[0];
        unset($lists[0]);
        $this->assign('first',$first);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function select(){
        $wechat = M('wx_user')->find();
        $count = M('wx_keyword')->where(array('token'=>$wechat['token'],'type'=>'IMG'))->count();
        $pager = new Page($count,10);
        $sql = "SELECT k.id,k.pid,k.keyword,i.title,i.url,i.pic,i.desc FROM __PREFIX__wx_keyword k LEFT JOIN __PREFIX__wx_img i ON i.id = k.pid WHERE k.token = '{$wechat['token']}' AND type = 'IMG' ORDER BY i.createtime DESC LIMIT {$pager->firstRow},{$pager->listRows}";
        $show = $pager->show();
        $lists = DB::query($sql);

        $this->assign('page',$show);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function get_access_token($appid,$appsecret){
        //判断是否过了缓存期
        $wechat = M('wx_user')->find();
        $expire_time = $wechat['web_expires'];
        if($expire_time > time()){
           return $wechat['web_access_token'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
        $return = httpRequest($url,'GET');
        $return = json_decode($return,1);
        $web_expires = time() + 7000; // 提前200秒过期

        if(isset($return['access_token'])){
            M('wx_user')->where(array('id'=>$wechat['id']))->save(array('web_access_token'=>$return['access_token'],'web_expires'=>$web_expires));
         }else{
             $this->error($return['errmsg']);//提示错误
        }

        return $return['access_token'];
    }

    public function nes(){
        return $this->fetch();
    }

    public function source(){
    	return $this->fetch();
    }
}