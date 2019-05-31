<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/13
 * Time: 9:46
 */

namespace app\api\controller;

use app\common\model\GoodsAttr;
use My\DataReturn;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsPromFactory;
use app\common\model\SpecGoodsPrice;
use think\AjaxPage;
use think\Db;
use think\Page;
class Goods extends Base
{

    /**
     * 分类列表显示
     */
    public function categoryList(){
        $goods_category_tree = get_goods_category_tree();
        DataReturn::returnJson(200,'查询成功',$goods_category_tree);
    }
    /**
     * 商品列表页
     */
    public function goodsList(){
        $filter_param = array(); // 筛选数组
        $id = I('id/d',1); // 当前分类id
        $brand_id = I('brand_id/d',0);
        $spec = I('spec',0); // 规格
        $attr = I('attr',''); // 属性
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入筛选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
        $spec  && ($filter_param['spec'] = $spec); //加入筛选条件中
        $attr  && ($filter_param['attr'] = $attr); //加入筛选条件中
        $price  && ($filter_param['price'] = $price); //加入筛选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 筛选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson ($id);
        $goods_where = ['is_on_sale' => 1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];
        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField("goods_id",true);

        // 过滤筛选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个筛选条件的结果 的交集
        }
        if($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个筛选条件的结果 的交集
        }
        if($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个筛选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel =I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel,$cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

        $count = count($filter_goods_id);
       // $page = new AjaxPage($count,C('PAGESIZE'));
        $goods_list = [];
        if($count > 0)
        {
            $input_pages = (!input('pages') ||  input('pages') < 0) ? 1 : input('pages');
            $pages = $input_pages -1 ;
            $start = $pages*C('PAGESIZE');
            $goods_list = M('goods')->where("goods_id","in", implode(',', $filter_goods_id))->field('goods_id,goods_name,shop_price,sales_sum')->order("$sort $sort_asc")->limit($start,C('PAGESIZE'))->select();
            //获取缩略图
            foreach($goods_list as $key=>$value)
            {
                $img_path = request()->domain() . goods_thum_images($value['goods_id'],400,400);
                $goods_list[$key]['goods_images'] = $img_path;
            }
        }

        $this->assign('lists',$goods_list);

        DataReturn::returnJson(200,'获取成功',$this->viewAssign());
    }
    /**
     * 商品详情页
     */
    public function goodsInfo(){
        //C('TOKEN_ON',true);
        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id/d");
        $goodsModel = new \app\common\model\Goods();

        $goods = $goodsModel::where('goods_id',$goods_id)
                ->field('goods_id,prom_id,prom_type,brand_id,goods_name,shop_price,market_price,is_on_sale,is_virtual,goods_content,goods_remark,original_img,sales_sum')
                ->find();
        $goods['original_img'] = $goods->origin_img_url;


        //查询商品是否合法
        if(empty($goods) || ($goods['is_on_sale'] == 0) || ($goods['is_virtual']==1 && $goods['virtual_indate'] <= time())){
             DataReturn::returnJson(0,'此商品不存在或者已下架');
        }

        //商品活动信息
        $goodsPromFactory = new GoodsPromFactory();
        if (!empty($goods['prom_id']) && $goodsPromFactory->checkPromType($goods['prom_type'])) {
            $goodsPromLogic = $goodsPromFactory->makeModule($goods, null);//这里会自动更新商品活动状态，所以商品需要重新查询
            $goods = $goodsPromLogic->getGoodsInfo();//上面更新商品信息后需要查询
        }

        //暂时不需要该处，用户浏览
        /*if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }*/

        //获取所属的分支
        if($goods['brand_id']){
            $brnad = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品 图册
        $goods_attr_list = GoodsAttr::with(['GoodsAttribute'=>function($query){
                return $query->field('attr_id,attr_name');
        }])->where("goods_id", $goods_id)->field('attr_value,attr_id')->select(); // 查询商品属性表

        $filter_spec = $goodsLogic->get_spec($goods_id);
        $spec_goods_price  = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count,item_id"); // 规格 对应 价格 库存表
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true)); // 规格 对应 价格 库存表
        $goods['sale_num'] = M('order_goods')->where(['goods_id'=>$goods_id,'is_send'=>1])->count();
        //当前用户收藏
        //$user_id = cookie('user_id');
        //$collect = M('goods_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
        //$goods_collect_count = M('goods_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数

        $new_filter_spec = [];
        $key_filter_spec = [];
        //存储键名
        foreach($filter_spec as $key =>$val){
            //修改图片路径
            $val = array_map(function($item){
                    $item['src'] = api_img_url($item['src']);
                    return $item;
                },$val);

            $new_filter_spec[] = $val;
            $key_filter_spec[] = $key;
        }

        //$this->assign('desc',$desc);
        //$request=  \think\Request::instance();
        //$action=$request->action();
        //$this->assign('action',$action);
        //$this->assign('collect',$collect);
        //$this->assign('commentStatistics',$commentStatistics);//评论概览
       // $this->assign('goods_attribute',$goods_attribute);//属性值
        $this->assign('goods_attr_list',$goods_attr_list);//属性列表
        $this->assign('filter_spec',$filter_spec);//规格参数
        $this->assign('new_filter_spec',$new_filter_spec);//规格参数
        $this->assign('key_filter_spec',$key_filter_spec);//规格参数

        $this->assign('goods_images_list',$goods_images_list);//商品缩略图
        $this->assign('spec_goods_price',$spec_goods_price);
        $this->assign('goods',$goods->toArray());
        //$point_rate = tpCache('shopping.point_rate');
        //$this->assign('goods_collect_count',$goods_collect_count); //商品收藏人数
        //$this->assign('point_rate', $point_rate);


        DataReturn::returnJson(200,'',$this->viewAssign());
    }

    /*获取活动配合商品详情*/
    public function activity(){
        $goods_id = input('goods_id/d');//商品id
        $item_id = input('item_id/d');//规格id
        $goods_num = input('goods_num/d');//欲购买的商品数量
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id,'',true);
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            //这里会自动更新商品活动状态，所以商品需要重新查询
            if($item_id){
                $specGoodsPrice = SpecGoodsPrice::get($item_id,'',true);
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,$specGoodsPrice);
            }else{
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,null);
            }
            //检查活动是否有效
            if($goodsPromLogic->checkActivityIsAble()){
                $goods = $goodsPromLogic->getActivityGoodsInfo();
                $goods['activity_is_on'] = 1;
                DataReturn::returnJson(200,'该商品参与活动',$goods);

                //$this->ajaxReturn(['status'=>1,'msg'=>'该商品参与活动','result'=>['goods'=>$goods]]);
            }else{
                if(!empty($goods['price_ladder'])){
                    $goodsLogic = new GoodsLogic();
                    $price_ladder = unserialize($goods['price_ladder']);
                    $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
                }
                $goods['activity_is_on'] = 0;
                DataReturn::returnJson(0,'该商品没有参与活动',$goods);
                //$this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
            }
        }
        if(!empty($goods['price_ladder'])){
            $goodsLogic = new GoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }
        $goods->original_img = $goods->origin_img_url;
        DataReturn::returnJson(0,'该商品没有参与活动',$goods);
        //$this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
    }

    /*ajax获取商品评论**/
    public function ajaxComment()
    {
        $goods_id = I("goods_id/d", 0);
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        if ($commentType == 5) {
            $where = array(
                'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''],'is_show'=>1
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        }
        $count = M('Comment')->where($where)->count();
        $page_count = C('PAGESIZE');
        $page = new AjaxPage($count, $page_count);
        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            $list[$k]['add_time'] = date('Y.m.d H:i:s',$list[$k]['add_time']);
            $_img = unserialize($v['img']); // 晒单图片
            $list[$k]['head_pic'] =  api_img_url($list[$k]['head_pic']);

            $arr_img = [];
            if($_img)
            {
                foreach($_img as $key => $value)
                {
                    $arr_img[] = request()->domain() . $value;
                }
            }

            $list[$k]['img'] = $arr_img;


            $list[$k]['service_rank'] =img_star($v['service_rank']); // 评分c
            $list[$k]['replyList'] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
        }
        $this->assign('goods_id', $goods_id);//商品id
        $this->assign('lists', $list);// 商品评论
        $this->assign('commentType', $commentType);// 1 全部 2好评 3 中评 4差评 5晒图
       // $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('count', $count);//总条数
        $this->assign('page_count', $page_count);//页数
        $this->assign('current_count', $page_count * I('p'));//当前条
        $this->assign('p', I('p'));//页数

        DataReturn::returnJson(200,'',$this->viewAssign());
        //return $this->fetch();
    }

    public function is_collect_goods()
    {
        $this->checkLogin();

        $id = I('get.id/d');

        $is_exit = M('goodsCollect')->where(['goods_id'=>$id,'user_id'=>$this->user_id])->find();

        if($is_exit)
            DataReturn::returnBase64Json(200,'该商品已收藏');

        DataReturn::returnBase64Json(500,'该商品没有收藏');

    }

    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods($goods_id){
        $this->checkLogin();

        $goods_id = I('goods_id/d');
        $goodsLogic = new GoodsLogic();
        $result = $goodsLogic->collect_goods($this->user_id,$goods_id);
        DataReturn::jsonResult($result,true);
    }
}