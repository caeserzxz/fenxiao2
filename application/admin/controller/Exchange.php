<?php
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use app\admin\model\Goods;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

//兑水活动
class Exchange extends Base {

    //兑水商品列表
    public function exchangeList(){

        $where = ' 1 = 1 '; // 搜索条件
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (goods_name like '%$key_word%')" ;
        }
        $count = db('exchange')->alias('e')
            ->join('goods g','e.goods_id = g.goods_id','left')
            ->where($where)->count();

        $Page = $pager = new Page($count,10);
        //$order_str = "'{$_POST['orderby1']} {$_POST['orderby2']}'";
        $list = db('exchange')
            ->alias('e')
            ->join('goods g','e.goods_id = g.goods_id','left')
            ->join('exchange_activity ea','e.activity_id = ea.id','left')
            ->where($where)
            ->order('e.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->field('e.*,g.goods_id,g.goods_name,g.original_img,ea.title')
            ->select();
        $show  = $Page->show();
        //p($list);
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['update_time'] = $val['update_time'] != 0 ? date('Y-m-d H:i:s',$val['update_time']):'0000-00-00 00:00:00';
            $_list[] = $_t;
        }
        $this->assign('cur_page',$Page->nowPage);
        $this->assign('pager',$pager);
        $this->assign('exchangeList', $_list);
        $this->assign('show', $show);
        return $this->fetch();
    }

    /**
     * 添加修改编辑  兑水商品
     */
    public  function addEditExchange(){
        $id = I('id');
        if(IS_POST)
        {
            $data = I('post.');
            $validate = Loader::validate('Exchange');
            if(!$validate->batch()->check($data)){
                $return = ['status'=>0,'msg'=>'操作失败','result'=>$validate->getError()];
                $this->ajaxReturn($return);
            }
            $level_id = db('exchange_activity')->where(['id'=>$data['activity_id']])->value('level_id');
            if($id){
                $data['level_id'] = $level_id;
                $data['update_time'] = time();
                M("exchange")->update($data);
            }else{
                $data['level_id'] = $level_id;
                $data['create_time'] = time();
                $data['update_time'] = time();
                M("exchange")->insert($data);
            }
            $this->ajaxReturn(['status'=>1,'msg'=>'操作成功','result'=>'']);
        }

        $activity = db('exchange_activity')->field('id,title')->select();
        $this->assign('activity',$activity);
        $exchange = M("exchange")->find($id);
        if(!empty($exchange)){
            $info = db('goods')->where(['goods_id'=>$exchange['goods_id']])->find();
        }
        $this->assign('info',$info);
        $this->assign('exchange',$exchange);
        return $this->fetch('_exchange');
    }

    //删除兑水商品
    public function delExchange()
    {
        $ids = I('post.ids','');
        empty($ids) && $this->ajaxReturn(['status' => -1,'msg' => '非法操作！']);
        $exchange_ids = rtrim($ids,",");
        $res=Db::name('exchange')->whereIn('id',$exchange_ids)->delete();
        if($res){
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/Exchange/exchangeList")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }

    //兑水活动列表
    public function activityList(){

        $where = ' 1 = 1 '; // 搜索条件
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (title like '%$key_word%')" ;
        }
        $count = db('exchange_activity')->alias('ea')->join('user_level ul','ea.level_id = ul.level_id','left')->where($where)->count();

        $Page = $pager = new Page($count,10);
        //$order_str = "'{$_POST['orderby1']} {$_POST['orderby2']}'";
        $list = db('exchange_activity')
            ->alias('ea')
            ->join('user_level ul','ea.level_id = ul.level_id','left')
            ->where($where)
            ->order('ea.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->field('ea.*,ul.level_id,level_name')
            ->select();
        $show  = $Page->show();
        //p($list);
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['update_time'] = $val['update_time'] != 0 ? date('Y-m-d H:i:s',$val['update_time']):'0000-00-00 00:00:00';
            $_list[] = $_t;
        }
        $this->assign('cur_page',$Page->nowPage);
        $this->assign('pager',$pager);
        $this->assign('exchangeList', $_list);
        $this->assign('show', $show);
        return $this->fetch();
    }

    /**
     * 添加修改编辑  兑水活动
     */
    public  function addEditActivity(){
        $id = I('id');
        if(IS_POST)
        {
            $data = I('post.');
            $validate = Loader::validate('Activity');
            if(!$validate->batch()->check($data)){
                $return = ['status'=>0,'msg'=>'操作失败','result'=>$validate->getError()];
                $this->ajaxReturn($return);
            }
            if($id){
                $data['update_time'] = time();
                M("exchange_activity")->update($data);
            }else{
                $data['create_time'] = time();
                $data['update_time'] = time();
                M("exchange_activity")->insert($data);
            }
            $this->ajaxReturn(['status'=>1,'msg'=>'操作成功','result'=>'']);
        }

        $user_level = db('user_level')->field('level_id,level_name')->select();
        $this->assign('user_level',$user_level);
        $exchange = M("exchange_activity")->find($id);

        $this->assign('exchange',$exchange);
        return $this->fetch('_activity');
    }

    //删除兑水活动
    public function delActivity()
    {
        $ids = I('post.ids','');
        empty($ids) && $this->ajaxReturn(['status' => -1,'msg' => '非法操作！']);
        $exchange_ids = rtrim($ids,",");
        $count_ids = M("exchange")->whereIn('activity_id',$exchange_ids)->group('activity_id')->getField('activity_id',true);
        if($count_ids){
            $count_ids = implode(',',$count_ids);
            $this->ajaxReturn(['status' => -1,'msg' => "ID为【{$count_ids}】的活动，清空活动商品后才可以删除!"]);
        }
        $res=Db::name('exchange_activity')->whereIn('id',$exchange_ids)->delete();
        if($res){
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/Exchange/activityList")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }

    public function search_goods()
    {
        $goods_id = input('goods_id');
        $intro = input('intro');
        $cat_id = input('cat_id');
        $brand_id = input('brand_id');
        $keywords = input('keywords');
        $prom_id = input('prom_id');
        $tpl = input('tpl', 'search_goods');
        $where = ['is_on_sale' => 1, 'store_count' => ['gt', 0],'is_virtual'=>0,'exchange_integral'=>0];
        $prom_type = input('prom_type/d');
        $is_exchange = input('is_exchange');
        if($goods_id){
            $where['goods_id'] = ['<>',$goods_id];
        }
        if($intro){
            $where[$intro] = 1;
        }
        if($cat_id){
            $grandson_ids = getCatGrandson($cat_id);
            $where['cat_id'] = ['in',implode(',', $grandson_ids)];
        }
        if ($brand_id) {
            $where['brand_id'] = $brand_id;
        }
        if($keywords){
            $where['goods_name|keywords'] = ['like','%'.$keywords.'%'];
        }
        if($is_exchange == 1){
            $where['is_exchange'] = 1;
        }
        $Goods = new Goods();
        $count = $Goods->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if($prom_type == 3){
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => 3])->whereor('prom_type', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if(in_array($prom_type,[1,2,6])){
                //抢购，团购
                $query->where('prom_type','in' ,[0,$prom_type])->where('prom_type',0);
            }else{
                $query->where('prom_type',0);
            }
        })->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->with('specGoodsPrice')->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if($prom_type == 3){
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => 3])->whereor('prom_type', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if(in_array($prom_type,[1,2,6])){
                //抢购，团购
                $query->where('prom_type','in' ,[0,$prom_type]);
            }else{
                $query->where('prom_type',0);
            }
        })->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $GoodsLogic = new GoodsLogic;
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);
        return $this->fetch($tpl);
    }

}