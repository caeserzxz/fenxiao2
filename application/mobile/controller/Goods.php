<?php

namespace app\mobile\controller;

use app\common\logic\GoodsLogic;
use app\common\logic\GoodsPromFactory;
use app\common\model\SpecGoodsPrice;
use think\AjaxPage;
use think\Controller;
use think\image\Exception;
use think\Page;
use think\Db;
use think\Log;

class Goods extends Controller
{
    public function index()
    {
//        dump(getwxconfig());

        // $this->show('<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} body{ background: #fff; font-family: "微软雅黑"; color: #333;font-size:24px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.8em; font-size: 36px } a,a:hover,{color:blue;}</style><div style="padding: 24px 48px;"> <h1>:)</h1><p>欢迎使用 <b>ThinkPHP</b>！</p><br/>版本 V{$Think.version}</div><script type="text/javascript" src="http://ad.topthink.com/public/static/client.js"></script><thinkad id="ad_55e75dfae343f5a1"></thinkad><script type="text/javascript" src="http://tajs.qq.com/stats?sId=9347272" charset="UTF-8"></script>','utf-8');
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList()
    {
        $goods_category_tree = get_goods_category_tree();


        $this->cateTrre = $goods_category_tree;
        $this->assign('goods_category_tree', $goods_category_tree);
        return $this->fetch();
    }

    /**
     * 商品列表页
     */
    public function goodsList()
    {
        $filter_param = array(); // 筛选数组
        $id = I('id/d', 1); // 当前分类id
        $brand_id = I('brand_id/d', 0);
        $spec = I('spec', 0); // 规格
        $attr = I('attr', ''); // 属性
        $sort = I('sort', 'goods_id'); // 排序
        $sort_asc = I('sort_asc', 'asc'); // 排序
        $price = I('price', ''); // 价钱
        $start_price = trim(I('start_price', '0')); // 输入框价钱
        $end_price = trim(I('end_price', '0')); // 输入框价钱
        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入筛选条件中
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
        $spec && ($filter_param['spec'] = $spec); //加入筛选条件中
        $attr && ($filter_param['attr'] = $attr); //加入筛选条件中
        $price && ($filter_param['price'] = $price); //加入筛选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 筛选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson($id);
        $goods_where = ['is_on_sale' => 1, 'exchange_integral' => 0, 'cat_id' => ['in', $cat_id_arr]];
        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField("goods_id", true);

        // 过滤筛选的结果集里面找商品
        if ($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price);  // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1);     // 获取多个筛选条件的结果 的交集
        }
        if ($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_2); // 获取多个筛选条件的结果 的交集
        }
        if ($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_3); // 获取多个筛选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if ($sel) {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel, $cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_4);
        }

        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'goodsList'); // 获取显示的筛选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'goodsList'); // 筛选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'goodsList'); // 获取指定分类下的筛选品牌
        $filter_spec = $goodsLogic->get_filter_spec($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选规格
        $filter_attr = $goodsLogic->get_filter_attr($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选属性

        $count = count($filter_goods_id);
        $page = new Page($count, C('PAGESIZE'));
        if ($count > 0) {
            $goods_list = M('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow . ',' . $page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if ($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list', $goods_list);
//        dump($goods_list);die;
        $this->assign('goods_category', $goods_category);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 筛选菜单
        $this->assign('filter_spec', $filter_spec);  // 筛选规格
        $this->assign('filter_attr', $filter_attr);  // 筛选属性
        $this->assign('filter_brand', $filter_brand);// 列表页筛选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 筛选的价格期间
        $this->assign('goodsCate', $goodsCate);
        $this->assign('cateArr', $cateArr);
        $this->assign('filter_param', $filter_param); // 筛选条件
        $this->assign('cat_id', $id);
        $this->assign('page', $page);// 赋值分页输出
        $this->assign('web_title', '商品列表');
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        C('TOKEN_ON', false);
        if (input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxGoodsList()
    {
        $where = '';

        $cat_id = I("id/d", 0); // 所选择的商品分类id
        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " WHERE cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }

        $result = DB::query("select count(1) as count from __PREFIX__goods $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count, 10);

        $order = " order by goods_id desc"; // 排序
        $limit = " limit " . $page->firstRow . ',' . $page->listRows;
        $list = DB::query("select *  from __PREFIX__goods $where $order $limit");

        $this->assign('lists', $list);
        $html = $this->fetch('ajaxGoodsList'); //return $this->fetch('ajax_goods_list');
        exit($html);
    }

    /**
     * 商品详情页
     */
    public function goodsInfo()
    {
        C('TOKEN_ON', true);

        // 分配用户id以分享
        $user_id = session('user.user_id');
        $this->assign('user_id', $user_id);

        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id/d");
        /* $data=$this->spec($goods_id);

         print_r($data);exit;*/
        $share_id = I("get.share_id/d");
        Log::alert(['获取分享链接上的用户id ' => json_encode($share_id)]);
        if ($share_id) {
            //判断是否关注了 0没关注
            if (session('subscribe') == 0) {
                Log::alert(['该用户的关注状态 ' => json_encode(session('subscribe'))]);
                //二维码链接
                //$qr_url ='/public/upload/weixin/2018/06-26/27e0446162e11de2fbe10cb65be11e3a.jpg';
                $qr_url = M('users')->field('param_code')->where('user_id', $share_id)->find();
                $this->assign('qr_url', $qr_url['param_code']);
            } else {
                $this->assign('qr_url', '');
            }
        }

        $goodsModel = new \app\common\model\Goods();
        $goods = $goodsModel::get($goods_id);

        //前端显示文案：商品价格已含税X元，商品所需进口税X元
        $detailDesc = '';
        $baoyou = 0;      //满多少包邮


        //获取当月开始日期和结束日期
        $m = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')));
        $t = date('t', strtotime($m)); //该月共多少天

        $month_start = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y'))); //该月的开始日期
        $month_end = date('Y-m-d', mktime(0, 0, 0, date('m'), $t, date('Y')));       //该月的结束日期

        //计算月销量
        $month_sales_num = 0;
        $month_order_goods = M('order_goods')
            ->where('goods_id', $goods_id)
            ->select();
        if ($month_order_goods) {
            //判断该订单是否在当月和已付款
            foreach ($month_order_goods as $k => $v) {
                $month_order = M('order')
                    ->where('order_id', $v['order_id'])
                    ->where('pay_time', '>=', strtotime($month_start))
                    ->where('pay_time', '<=', strtotime($month_end))
                    ->find();

                if ($month_order) {
                    $month_sales_num += $v['goods_num'];
                }
            }
        }

        //判断如果是身份产品，只能直接购买
        $identity = 0;    //0不是，1是总代身份产品
        $canBuyIdentity = 0;  //仅会员身份可以购买总代产品

        if (empty($goods) || ($goods['is_on_sale'] == 0) || ($goods['is_virtual'] == 1 && $goods['virtual_indate'] <= time())) {
            $this->error('此商品不存在或者已下架');
        }
        $goodsPromFactory = new GoodsPromFactory();
        if (!empty($goods['prom_id']) && $goodsPromFactory->checkPromType($goods['prom_type'])) {
            $goodsPromLogic = $goodsPromFactory->makeModule($goods, null);//这里会自动更新商品活动状态，所以商品需要重新查询
            $goods = $goodsPromLogic->getGoodsInfo();//上面更新商品信息后需要查询
        }
        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }
        if ($goods['brand_id']) {
            $brnad = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);
        $spec_goods_price = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count,item_id"); // 规格 对应 价格 库存表
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('spec_goods_price', json_encode($spec_goods_price, true)); // 规格 对应 价格 库存表
        $goods['sale_num'] = M('order_goods')->where(['goods_id' => $goods_id, 'is_send' => 1])->count();
        //当前用户收藏
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id" => $goods_id, "user_id" => $user_id))->count();
        $goods_collect_count = M('goods_collect')->where(array("goods_id" => $goods_id))->count(); //商品收藏数

        //分享描述
        $desc = '';
        foreach ($filter_spec as $key => $val) {
            foreach ($val as $v) {
                $desc .= $v['item'] . '  ';
            }
        }

        //商品评论数
        $count = M('comment')
            ->where('goods_id', $goods_id)
            ->count();

        //print_r($goods);exit;
        $this->assign('month_sales_num', $month_sales_num);   //月销量
        $this->assign('baoyou', $baoyou);   //满x包邮
        $this->assign('count', $count);   //商品评论数
        //$this->assign('message', $message);   //商品仓文案
        $this->assign('detailDesc', $detailDesc);   //商品含税文案

        $this->assign('identity', $identity);   //判断总代身份产品
        $this->assign('canBuyIdentity', $canBuyIdentity);   //判断是会员身份才能购买
        $this->assign('desc', $desc);
        $this->assign('share_id', $share_id);
        $request = \think\Request::instance();
        $action = $request->action();
        $this->assign('action', $action);
        $this->assign('collect', $collect);
        $this->assign('commentStatistics', $commentStatistics);//评论概览
        $this->assign('goods_attribute', $goods_attribute);//属性值
        $this->assign('goods_attr_list', $goods_attr_list);//属性列表
        $this->assign('filter_spec', $filter_spec);//规格参数
        $this->assign('goods_images_list', $goods_images_list);//商品缩略图
        $this->assign('goods', $goods->toArray());
//        dump($goods->toArray());die;
        $point_rate = tpCache('shopping.point_rate');
        $this->assign('goods_collect_count', $goods_collect_count); //商品收藏人数
        $this->assign('point_rate', $point_rate);


        //判断，根据不同类型的商品，加载不同的页面
        if ($goods['g_type'] == '0' || $goods['g_type'] == '2' || $goods['g_type'] == '3'|| $goods['g_type'] == '4') {
            //普通商品
            return $this->fetch();
        } elseif ($goods['g_type'] == '1') {
            //外链商品
            return $this->fetch('urlGoodsInfo');
        }

    }

    public function activity()
    {
        $goods_id = input('goods_id/d');//商品id
        $item_id = input('item_id/d');//规格id
        $goods_num = input('goods_num/d');//欲购买的商品数量
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id, '', true);
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            //这里会自动更新商品活动状态，所以商品需要重新查询
            if ($item_id) {
                $specGoodsPrice = SpecGoodsPrice::get($item_id, '', true);
                $goodsPromLogic = $goodsPromFactory->makeModule($goods, $specGoodsPrice);
            } else {
                $goodsPromLogic = $goodsPromFactory->makeModule($goods, null);
            }
            //检查活动是否有效
            if ($goodsPromLogic->checkActivityIsAble()) {
                $goods = $goodsPromLogic->getActivityGoodsInfo();
                $goods['activity_is_on'] = 1;
                $this->ajaxReturn(['status' => 1, 'msg' => '该商品参与活动', 'result' => ['goods' => $goods]]);
            } else {
                if (!empty($goods['price_ladder'])) {
                    $goodsLogic = new GoodsLogic();
                    $price_ladder = unserialize($goods['price_ladder']);
                    $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
                }
                $goods['activity_is_on'] = 0;
                $this->ajaxReturn(['status' => 1, 'msg' => '该商品没有参与活动', 'result' => ['goods' => $goods]]);
            }
        }
        if (!empty($goods['price_ladder'])) {
            $goodsLogic = new GoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '该商品没有参与活动', 'result' => ['goods' => $goods]]);
    }

    public function ajaxReturn($data)
    {
        exit(json_encode($data));
    }

    /*
     * 商品评论
     */
    public function comment()
    {
        $goods_id = I("goods_id/d", 0);
        $this->assign('goods_id', $goods_id);
        return $this->fetch();
    }

    /*
     * ajax获取商品评论
     */
    public function ajaxComment()
    {
        $where = array();
        $goods_id = I("goods_id/d", 0);
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        /*  if ($commentType == 5) {
              $where = array(
                  'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''], 'is_show' => 1
              );
          } else {
              $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
              $where = array('is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
          }*/
        $count = M('comment')
            ->where('goods_id', $goods_id)
            ->count();

        $page_count = C('PAGESIZE');
        $page = new AjaxPage($count, $page_count);
        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            //->limit($page->firstRow . ',' . $page->listRows)
            ->limit(0, 1)//只显示一条评论
            ->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $list[$k]['service_rank'] = star($v['service_rank']); // 评分
            $replyList[$v['comment_id']] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
        }
        $this->assign('goods_id', $goods_id);//商品id
        $this->assign('commentlist', $list);// 商品评论
        $this->assign('commentType', $commentType);// 1 全部 2好评 3 中评 4差评 5晒图
        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('count', $count);//总条数
        $this->assign('page_count', $page_count);//页数
        $this->assign('current_count', $page_count * I('p'));//当前条
        $this->assign('p', I('p'));//页数
        return $this->fetch();
    }

    /*
     * 获取商品评论页面，所有评论
     *
     * */
    public function goodcomment()
    {
        $input = input();
        $goods_id = $input['goods_id'];


        //商品评论
        $count = M('comment')
            ->where(array(
                'goods_id' => $goods_id,
                'is_show' => 1,
            ))
            ->count();
        $page = new Page($count);
        $lists = M('comment')
            ->where(array(
                'goods_id' => $goods_id,
                'is_show' => 1,
            ))
            ->order('comment_id desc')
            ->limit($page->firstRow . ',' . $page->listRows)->select();

        foreach ($lists as $k => $v) {
            $user = M('users')->where('user_id', $v['user_id'])->find();
            $lists[$k]['userInfo'] = $user;
            $lists[$k]['img'] = unserialize($v['img']); // 晒单图片
        }

        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    /*
     * 获取商品规格
     */
    public function goodsAttr()
    {
        $goods_id = I("get.goods_id/d", 0);
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list', $goods_attr_list);
        $this->assign('goods_attribute', $goods_attribute);
        return $this->fetch();
    }



    /**
     * 商品搜索列表页
     */
    public function search()
    {
        $filter_param = array(); // 筛选数组
        $id = I('get.id/d', 0); // 当前分类id
        $brand_id = I('brand_id/d', 0);
        $sort = I('sort', 'goods_id'); // 排序
        $sort_asc = I('sort_asc', 'asc'); // 排序
        $price = I('price', ''); // 价钱
        $start_price = trim(I('start_price', '0')); // 输入框价钱
        $end_price = trim(I('end_price', '0')); // 输入框价钱
        if ($start_price && $end_price) $price = $start_price . '-' . $end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入筛选条件中
        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
        $price && ($filter_param['price'] = $price); //加入筛选条件中
        $q = urldecode(trim(I('q', ''))); // 关键字搜索
        $q && ($_GET['q'] = $filter_param['q'] = $q); //加入筛选条件中
        $qtype = I('qtype', '');
        $where = array('is_on_sale' => 1);
        $where['exchange_integral'] = 0;//不检索积分商品
        if ($qtype) {
            $filter_param['qtype'] = $qtype;
            $where[$qtype] = 1;
        }
        if ($q) $where['goods_name'] = array('like', '%' . $q . '%');

        $goodsLogic = new GoodsLogic();
        $filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id", true);

        // 过滤筛选的结果集里面找商品
        if ($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个筛选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if ($sel) {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel);
            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_4);
        }

        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'search'); // 获取显示的筛选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'search'); // 筛选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'search'); // 获取指定分类下的筛选品牌

        $count = count($filter_goods_id);
        $page = new Page($count, 12);
        if ($count > 0) {
            $goods_list = M('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow . ',' . $page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if ($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list', $goods_list);
        $this->assign('goods_category', $goods_category);
        $this->assign('goods_images', $goods_images);  // 相册图片
        $this->assign('filter_menu', $filter_menu);  // 筛选菜单
        $this->assign('filter_brand', $filter_brand);// 列表页筛选属性 - 商品品牌
        $this->assign('filter_price', $filter_price);// 筛选的价格期间
        $this->assign('filter_param', $filter_param); // 筛选条件
        $this->assign('page', $page);// 赋值分页输出
        $this->assign('web_title', '商品搜索');
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        C('TOKEN_ON', false);
        if (input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品搜索列表页
     */
    public function ajaxSearch()
    {
        $this->assign('web_title', '商品搜索');
        return $this->fetch();
    }

    /**
     * 品牌街
     */
    public function brandstreet()
    {
        $getnum = 9;   //取出数量
        $goods = M('goods')->where(array('is_recommend' => 1, 'is_on_sale' => 1))->page(1, $getnum)->cache(true, TPSHOP_CACHE_TIME)->select(); //推荐商品
        for ($i = 0; $i < ($getnum / 3); $i++) {
            //3条记录为一组
            $recommend_goods[] = array_slice($goods, $i * 3, 3);
        }
        $where = array(
            'is_hot' => 1,  //1为推荐品牌
        );
        $count = M('brand')->where($where)->count(); // 查询满足要求的总记录数
        $Page = new Page($count, 20);
        $brand_list = M('brand')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('sort desc')->select();
        $this->assign('recommend_goods', $recommend_goods);  //品牌列表
        $this->assign('brand_list', $brand_list);            //推荐商品
        $this->assign('listRows', $Page->listRows);
        if (I('is_ajax')) {
            return $this->fetch('ajaxBrandstreet');
        }
        return $this->fetch();
    }

    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods($goods_id)
    {
        $goods_id = I('goods_id/d');
        $goodsLogic = new GoodsLogic();

        $user = session('user');
        $result = $goodsLogic->collect_goods($user['user_id'], $goods_id);
        exit(json_encode($result));
    }


    /**
     * 商品规格
     * @param int $id 商品id
     * @return ApiResponse
     * @throws JsonException
     */
    public function spec($id = 0)
    {

        $goodsLogic = new GoodsLogic();

        //该商品的规格列表(前端遍历显示的)
        $filter_spec = $goodsLogic->get_spec($id);

        //组合选择的时候  对应的商品价格
        $spec_goods_price = M('spec_goods_price')->where("goods_id", $id)->getField("key,price,store_count,item_id"); // 规格 对应 价格 库存表

        //该商品的销量
        $sale_num = M('order_goods')->where(['goods_id' => $id, 'is_send' => 1])->count();

        $specImg = [];//点击某个商品规格对应的图片

        //存储键名
        foreach ($filter_spec as $key => &$val) {

            foreach ($val as $k => $v) {
                $specImg[$v['item_id']] = $v['src'];
            }
        }

        $data = [
            'sale_num' => $sale_num,//商品销量
            'goods_spec' => $filter_spec,//商品规格,显示使用
            'spec_combine_price' => $spec_goods_price,//规格组合的时候,对应的价格
            'specImg' => $specImg,
        ];

        exit(json_encode($data));
    }


}