<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use app\admin\logic\OrderLogic;
use app\admin\logic\SearchWordLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

class Goods extends Base
{

    /**
     *  商品分类列表
     */
    public function categoryList()
    {
        $GoodsLogic = new GoodsLogic();
        $cat_list = $GoodsLogic->goods_cat_list();
        $this->assign('cat_list', $cat_list);
        return $this->fetch();
    }

    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'),
     * select * from tp_goods_category where id = 393
     * select * from tp_goods_category where parent_id = 393
     * update tp_goods_category  set parent_id_path = concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
     * insert into `tp_goods_category` (`parent_id`,`name`) values
     * ('393','时尚饰品'),
     */
    public function addEditCategory()
    {

//        dump(input(''));die;

        $GoodsLogic = new GoodsLogic();
        if (IS_GET) {
            $goods_category_info = D('GoodsCategory')->where('id=' . I('GET.id', 0))->find();
            $level_cat = $GoodsLogic->find_parent_cat($goods_category_info['id']); // 获取分类默认选中的下拉框

            $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
            $this->assign('level_cat', $level_cat);
            $this->assign('cat_list', $cat_list);
            $this->assign('goods_category_info', $goods_category_info);
            return $this->fetch('_category');
            exit;
        }

        $GoodsCategory = D('GoodsCategory'); //

        $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if (I('is_ajax') == 1) {
            // 数据验证
            $validate = \think\Loader::validate('GoodsCategory');
            if (!$validate->batch()->check(input('post.'))) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            } else {

                $GoodsCategory->data(input('post.'), true); // 收集数据
                $GoodsCategory->parent_id = I('parent_id_1');
                input('parent_id_2') && ($GoodsCategory->parent_id = input('parent_id_2'));
                //编辑判断
                if ($type == 2) {
                    $children_where = array(
                        'parent_id_path' => array('like', '%_' . I('id') . "_%")
                    );
                    $children = M('goods_category')->where($children_where)->max('level');
                    if (I('parent_id_1')) {
                        $parent_level = M('goods_category')->where(array('id' => I('parent_id_1')))->getField('level', false);
                        if (($parent_level + $children) > 4) {
                            $return_arr = array(
                                'status' => -1,
                                'msg' => $parent_level . '商品分类最多为三级' . $children,
                                'data' => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                    if (I('parent_id_2')) {
                        $parent_level = M('goods_category')->where(array('id' => I('parent_id_2')))->getField('level', false);
                        if (($parent_level + $children) > 4) {
                            $return_arr = array(
                                'status' => -1,
                                'msg' => '商品分类最多为三级',
                                'data' => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                }

                //查找同级分类是否有重复分类
                $par_id = ($GoodsCategory->parent_id > 0) ? $GoodsCategory->parent_id : 0;
                $same_cate = M('GoodsCategory')->where(['parent_id' => $par_id, 'name' => $GoodsCategory['name']])->find();
                if ($same_cate) {
                    $return_arr = array(
                        'status' => 0,
                        'msg' => '同级已有相同分类存在',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }

                if ($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id) {
                    //  编辑
                    $return_arr = array(
                        'status' => 0,
                        'msg' => '上级分类不能为自己',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }

                if ($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id) {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg' => '上级分类不能为自己',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }
              /*  if ($GoodsCategory->commission_rate > 100) {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg' => '分佣比例不得超过100%',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }*/

                if ($type == 2) {
                    $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库
                    $GoodsLogic->refresh_cat(I('id'));
                } else {
                    $GoodsCategory->save(); // 写入数据到数据库
                    $insert_id = $GoodsCategory->getLastInsID();
                    $GoodsLogic->refresh_cat($insert_id);
                }
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => U('Admin/Goods/categoryList')),
                );
                $this->ajaxReturn($return_arr);

            }
        }

    }

    /**
     * 获取商品分类 的筛选规格 复选框
     */
    public function ajaxGetSpecList()
    {
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsCategory')->where("id = " . $_REQUEST['category_id'])->getField('filter_spec');
        $filter_spec_arr = explode(',', $filter_spec);
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'], $filter_spec_arr);
        $str = $str ? $str : '没有可筛选的商品规格';
        exit($str);
    }

    /**
     * 获取商品分类 的筛选属性 复选框
     */
    public function ajaxGetAttrList()
    {
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsCategory')->where("id = " . $_REQUEST['category_id'])->getField('filter_attr');
        $filter_attr_arr = explode(',', $filter_attr);
        $str = $GoodsLogic->GetAttrCheckboxList($_REQUEST['type_id'], $filter_attr_arr);
        $str = $str ? $str : '没有可筛选的商品属性';
        exit($str);
    }

    /**
     * 删除分类
     */
    public function delGoodsCategory()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => "非法操作！", 'data' => '']);
        // 判断子分类
        $count = Db::name("goods_category")->where("parent_id = {$ids}")->count("id");
        $count > 0 && $this->ajaxReturn(['status' => -1, 'msg' => '该分类下还有分类不得删除!']);
        // 判断是否存在商品
        $goods_count = Db::name('Goods')->where("cat_id = {$ids}")->count('1');
        $goods_count > 0 && $this->ajaxReturn(['status' => -1, 'msg' => '该分类下有商品不得删除!']);
        // 删除分类
        DB::name('goods_category')->where('id', $ids)->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/Goods/categoryList')]);
    }


    /**
     *  商品列表
     */
    public function goodsList()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);
        return $this->fetch();
    }

    /**
     *  商品列表
     */
    public function ajaxGoodsList()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . " = 1";
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }

        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " and cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }

        $count = M('Goods')->where($where)->count();
        $Page = new AjaxPage($count, 20);
        /**  搜索条件下 分页赋值
         * foreach($condition as $key=>$val) {
         * $Page->parameter[$key]   =   urlencode($val);
         * }
         */
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList', $catList);
        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }


    public function stock_list()
    {
        $model = M('stock_log');
        $map = array();
        $mtype = I('mtype');
        if ($mtype == 1) {
            $map['stock'] = array('gt', 0);
        }
        if ($mtype == -1) {
            $map['stock'] = array('lt', 0);
        }
        $goods_name = I('goods_name');
        if ($goods_name) {
            $map['goods_name'] = array('like', "%$goods_name%");
        }
        $ctime = urldecode(I('ctime'));
        if ($ctime) {
            $gap = explode(' - ', $ctime);
            $this->assign('start_time', $gap[0]);
            $this->assign('end_time', $gap[1]);
            $this->assign('ctime', $gap[0] . ' - ' . $gap[1]);
            $map['ctime'] = array(array('gt', strtotime($gap[0])), array('lt', strtotime($gap[1])));
        }
        $count = $model->where($map)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('pager', $Page);
        $this->assign('page', $show);// 赋值分页输出
        $stock_list = $model->where($map)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('stock_list', $stock_list);
        return $this->fetch();
    }

    /*
     * 库存日志
     *
     * */
    public function ku_list()
    {
        $input = input('');
        $where = array();

        $count = M('n_goods_ku_log')->where($where)->count();

        $page = new Page($count);
        $lists = M('n_goods_ku_log')->where($where)->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        if ($lists) {
            foreach ($lists as $k => $v) {
                //管理员名称
                $adminInfo = M('admin')->where('admin_id', $v['admin_id'])->find();
                $lists[$k]['admin_name'] = $adminInfo ? $adminInfo['user_name'] : '未知管理员';

                //商品名称
                $goodsInfo = M('goods')->where('goods_id', $v['goods_id'])->find();
                $lists[$k]['goods_name'] = $goodsInfo ? $goodsInfo['goods_name'] : null;
            }
        }


        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    /**
     * 添加修改商品(普通商品)
     */
    public function addEditGoods()
    {

        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\Goods();
        $goods_id = I('id');
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
            $goods_id = I('id');
            $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
            // 数据验证
            $is_distribut = input('is_distribut');
            $virtual_indate = input('post.virtual_indate');//虚拟商品有效期
            $return_url = $is_distribut > 0 ? U('Admin/Distribut/goods_list') : U('Admin/Goods/goodsList');
            $data = input('post.');

            if(!isset($data['g_type']) || $data['g_type'] < '0'){
                $return_arr = array(
                    'status' => -0,
                    'msg' => '请选择商品类型，提交失败',
                    'data' => array(),
                );
                $this->ajaxReturn($return_arr);
            }

            if ($data['g_type'] == '2' && $data['provider_id'] > '0') {
                $return_arr = array(
                    'status' => -0,
                    'msg' => '兑换商品不能归属于供应商，提交失败',
                    'data' => array(),
                );
                $this->ajaxReturn($return_arr);
            }

            $validate = \think\Loader::validate('Goods');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -0,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $data['virtual_indate'] = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            //$data['exchange_integral'] = ($data['is_virtual'] == 1) ? 0 : $data['exchange_integral'];

            if (empty($data['integral']) && empty($data['shop_price'])) {
                $return_arr = array(
                    'msg' => '本店售价和积分不能同时为空！',
                    'status' => -0,
                    'data' => array('url' => $return_url)
                );
                $this->ajaxReturn($return_arr);
            }


            //设置了积分
            if (!empty($data['integral'])) {
                if (!empty($data['shop_price']) && $data['shop_price'] >= 0.01) {
                    $data['sale_type'] = 1;
                } else {
                    $data['sale_type'] = 2;
                }
            } else {
                $data['sale_type'] = 0;
            }

            $Goods->data($data, true); // 收集数据
            $Goods->on_time = time(); // 上架时间
            I('cat_id_2') && ($Goods->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($Goods->cat_id = I('cat_id_3'));
            I('extend_cat_id_2') && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
            $Goods->spec_type = $Goods->goods_type;
            $price_ladder = array();
            if ($Goods->ladder_amount[0] > 0) {
                foreach ($Goods->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($Goods->ladder_amount[$key]);
                    $price_ladder[$key]['price'] = floatval($Goods->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $Goods->shop_price) {
                    $return_arr = array(
                        'msg' => '价格阶梯最大金额不能大于商品原价！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                if ($price_ladder[0]['amount'] <= 0 || $price_ladder[0]['price'] <= 0) {
                    $return_arr = array(
                        'msg' => '您没有输入有效的价格阶梯！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                $Goods->price_ladder = serialize($price_ladder);
            } else {
                $Goods->price_ladder = '';
            }
            if ($type == 2) {
                $goodsInfo = M('goods')->where('goods_id', $goods_id)->find();
                if ($goodsInfo['store_count'] != $data['store_count']) {
                    $kuData = array();
                    $kuData['admin_id'] = $_SESSION['admin_id'];
                    $kuData['goods_id'] = $goodsInfo['goods_id'];
                    $kuData['old_num'] = $goodsInfo['store_count'];
                    $kuData['num'] = $data['store_count'];
                    $kuData['create_time'] = time();

                    $cuRt = M('n_goods_ku_log')->add($kuData);
                }

                $Goods->isUpdate(true)->save(); // 写入数据到数据库
                // 修改商品后购物车的商品价格也修改一下
                M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                    'market_price' => I('market_price'), //市场价
                    'goods_price' => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price'), // 会员折扣价
                ));
            } else {
                $Goods->save(); // 写入数据到数据库
                $goods_id = $insert_id = $Goods->getLastInsID();

                //插入，直接保存
                $kuData = array();
                $kuData['admin_id'] = $_SESSION['admin_id'];
                $kuData['goods_id'] = $goods_id;
                $kuData['old_num'] = 0;
                $kuData['num'] = $data['store_count'];
                $kuData['create_time'] = time();

                $cuRt = M('n_goods_ku_log')->add($kuData);
            }
            $Goods->afterSave($goods_id);
            $GoodsLogic->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
            );

            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = M('Goods')->where('goods_id=' . I('GET.id', 0))->find();
        if ($goodsInfo['price_ladder']) {
            $goodsInfo['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }
        $level_cat = $GoodsLogic->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        $level_cat2 = $GoodsLogic->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $brandList = $GoodsLogic->getSortBrands();
        $goodsType = M("GoodsType")->select();
        $suppliersList = M("suppliers")->select();
        $plugin_shipping = M('plugin')->where(array('type' => array('eq', 'shipping')))->select();//插件物流
        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);

        //关联供应商
        $providerList=M('n_provider')->select();

        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsImages")->where('goods_id =' . I('GET.id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $this->assign('goods_id', $goods_id);
        $this->assign('providerList', $providerList);
        return $this->fetch('_goods');
    }


    /**
     * 商品类型  用于设置商品的属性
     */
    public function goodsTypeList()
    {
        $model = M("GoodsType");
        $count = $model->count();
        $Page = $pager = new Page($count, 14);
        $show = $Page->show();
        $goodsTypeList = $model->order("id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('pager', $pager);
        $this->assign('show', $show);
        $this->assign('goodsTypeList', $goodsTypeList);
        return $this->fetch('goodsTypeList');
    }

    /**
     * 添加修改编辑  商品属性类型
     */
    public function addEditGoodsType()
    {
        $id = $this->request->param('id', 0);
        $model = M("GoodsType");
        if (IS_POST) {
            $data = $this->request->post();
            if ($id)
                DB::name('GoodsType')->update($data);
            else
                DB::name('GoodsType')->insert($data);

            $this->success("操作成功!!!", U('Admin/Goods/goodsTypeList'));
            exit;
        }
        $goodsType = $model->find($id);
        $this->assign('goodsType', $goodsType);
        return $this->fetch('_goodsType');
    }

    /**
     * 商品属性列表
     */
    public function goodsAttributeList()
    {
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList', $goodsTypeList);
        return $this->fetch();
    }

    /**
     *  商品属性列表
     */
    public function ajaxGoodsAttributeList()
    {
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件
        I('type_id') && $where = "$where and type_id = " . I('type_id');
        // 关键词搜索
        $model = M('GoodsAttribute');
        $count = $model->where($where)->count();
        $Page = new AjaxPage($count, 13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $goodsTypeList = M("GoodsType")->getField('id,name');
        $attr_input_type = array(0 => '手工录入', 1 => ' 从列表中选择', 2 => ' 多行文本框');
        $this->assign('attr_input_type', $attr_input_type);
        $this->assign('goodsTypeList', $goodsTypeList);
        $this->assign('goodsAttributeList', $goodsAttributeList);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品属性
     */
    public function addEditGoodsAttribute()
    {

        $model = D("GoodsAttribute");
        $type = I('attr_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        $attr_values = str_replace('_', '', I('attr_values')); // 替换特殊字符
        $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符
        $attr_values = trim($attr_values);

        $post_data = input('post.');
        $post_data['attr_values'] = $attr_values;

        if ((I('is_ajax') == 1) && IS_POST)//ajax提交验证
        {
            // 数据验证
            $validate = \think\Loader::validate('GoodsAttribute');
            if (!$validate->batch()->check($post_data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            } else {
                $model->data($post_data, true); // 收集数据

                if ($type == 2) {
                    $model->isUpdate(true)->save(); // 写入数据到数据库
                } else {
                    $model->save(); // 写入数据到数据库
                    $insert_id = $model->getLastInsID();
                }
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => U('Admin/Goods/goodsAttributeList')),
                );
                $this->ajaxReturn($return_arr);
            }
        }
        // 点击过来编辑时
        $attr_id = I('attr_id/d', 0);
        $goodsTypeList = M("GoodsType")->select();
        $goodsAttribute = $model->find($attr_id);
        $this->assign('goodsTypeList', $goodsTypeList);
        $this->assign('goodsAttribute', $goodsAttribute);
        return $this->fetch('_goodsAttribute');
    }

    /**
     * 更改指定表的指定字段
     */
    public function updateField()
    {
        $primary = array(
            'goods' => 'goods_id',
            'goods_category' => 'id',
            'brand' => 'id',
            'goods_attribute' => 'attr_id',
            'ad' => 'ad_id',
        );
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];
        $model->save();
        $return_arr = array(
            'status' => 1,
            'msg' => '操作成功',
            'data' => array('url' => U('Admin/Goods/goodsAttributeList')),
        );
        $this->ajaxReturn($return_arr);
    }

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput()
    {
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'], $_REQUEST['type_id']);
        exit($str);
    }

    /**
     * 删除商品
     */
    public function delGoods()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => "非法操作！", 'data' => '']);
        $goods_ids = rtrim($ids, ",");
        // 判断此商品是否有订单
        $ordergoods_count = Db::name('OrderGoods')->whereIn('goods_id', $goods_ids)->group('goods_id')->getField('goods_id', true);
        if ($ordergoods_count) {
            $goods_count_ids = implode(',', $ordergoods_count);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$goods_count_ids}】的商品有订单,不得删除!", 'data' => '']);
        }
        // 商品团购
        $groupBuy_goods = M('group_buy')->whereIn('goods_id', $goods_ids)->group('goods_id')->getField('goods_id', true);
        if ($groupBuy_goods) {
            $groupBuy_goods_ids = implode(',', $groupBuy_goods);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$groupBuy_goods_ids}】的商品有团购,不得删除!", 'data' => '']);
        }
        // 删除此商品
        M("Goods")->whereIn('goods_id', $goods_ids)->delete();  //商品表
        M("cart")->whereIn('goods_id', $goods_ids)->delete();  // 购物车
        M("comment")->whereIn('goods_id', $goods_ids)->delete();  //商品评论
        M("goods_consult")->whereIn('goods_id', $goods_ids)->delete();  //商品咨询
        M("goods_images")->whereIn('goods_id', $goods_ids)->delete();  //商品相册
        M("spec_goods_price")->whereIn('goods_id', $goods_ids)->delete();  //商品规格
        M("spec_image")->whereIn('goods_id', $goods_ids)->delete();  //商品规格图片
        M("goods_attr")->whereIn('goods_id', $goods_ids)->delete();  //商品属性
        M("goods_collect")->whereIn('goods_id', $goods_ids)->delete();  //商品收藏

        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U("Admin/goods/goodsList")]);
    }

    /**
     * 删除商品类型
     */
    public function delGoodsType()
    {
        // 判断 商品规格
        $id = $this->request->param('id');
        $count = M("Spec")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品规格不得删除!', U('Admin/Goods/goodsTypeList'));
        // 判断 商品属性
        $count = M("GoodsAttribute")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品属性不得删除!', U('Admin/Goods/goodsTypeList'));
        // 删除分类
        M('GoodsType')->where("id = {$id}")->delete();
        $this->success("操作成功!!!", U('Admin/Goods/goodsTypeList'));
    }

    /**
     * 删除商品属性
     */
    public function delGoodsAttribute()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => "非法操作！"]);
        $attrBute_ids = rtrim($ids, ",");
        // 判断 有无商品使用该属性
        $count_ids = Db::name("GoodsAttr")->whereIn('attr_id', $attrBute_ids)->group('attr_id')->getField('attr_id', true);
        if ($count_ids) {
            $count_ids = implode(',', $count_ids);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$count_ids}】的属性有商品正在使用,不得删除!"]);
        }
        // 删除 属性
        M('GoodsAttribute')->whereIn('attr_id', $attrBute_ids)->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => "操作成功!", 'url' => U('Admin/Goods/goodsAttributeList')]);
    }

    /**
     * 删除商品规格
     */
    public function delGoodsSpec()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => "非法操作！"]);
        $aspec_ids = rtrim($ids, ",");
        // 判断 商品规格项
        $count_ids = M("SpecItem")->whereIn('spec_id', $aspec_ids)->group('spec_id')->getField('spec_id', true);
        if ($count_ids) {
            $count_ids = implode(',', $count_ids);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$count_ids}】规格，清空规格项后才可以删除!"]);
        }
        // 删除分类
        M('Spec')->whereIn('id', $aspec_ids)->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => "操作成功!!!", 'url' => U('Admin/Goods/specList')]);
    }

    /**
     * 品牌列表
     */
    public function brandList()
    {
        $model = M("Brand");
        $where = "";
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";
        $count = $model->where($where)->count();
        $Page = $pager = new Page($count, 10);
        $brandList = $model->where($where)->order("`sort` asc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
        $this->assign('pager', $pager);
        $this->assign('show', $show);
        $this->assign('brandList', $brandList);
        return $this->fetch('brandList');
    }

    /**
     * 添加修改编辑  商品品牌
     */
    public function addEditBrand()
    {
        $id = I('id');
        if (IS_POST) {
            $data = I('post.');
            $brandVilidate = Loader::validate('Brand');
            if (!$brandVilidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '操作失败', 'result' => $brandVilidate->getError()];
                $this->ajaxReturn($return);
            }
            if ($id) {
                M("Brand")->update($data);
            } else {
                M("Brand")->insert($data);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result' => '']);
        }
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
        $brand = M("Brand")->find($id);
        $this->assign('brand', $brand);
        return $this->fetch('_brand');
    }

    /**
     * 删除品牌
     */
    public function delBrand()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        $brind_ids = rtrim($ids, ",");
        // 判断此品牌是否有商品在使用
        $goods_count = Db::name('Goods')->whereIn("brand_id", $brind_ids)->group('brand_id')->getField('brand_id', true);
        $use_brind_ids = implode(',', $goods_count);
        if ($goods_count) {
            $this->ajaxReturn(['status' => -1, 'msg' => 'ID为【' . $use_brind_ids . '】的品牌有商品在用不得删除!', 'data' => '']);
        }
        $res = Db::name('Brand')->whereIn('id', $brind_ids)->delete();
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U("Admin/goods/brandList")]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '操作失败', 'data' => '']);
    }

    /**
     * 商品规格列表
     */
    public function specList()
    {
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList', $goodsTypeList);
        return $this->fetch();
    }


    /**
     *  商品规格列表
     */
    public function ajaxSpecList()
    {
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件
        I('type_id') && $where = "$where and type_id = " . I('type_id');
        // 关键词搜索
        $model = D('spec');
        $count = $model->where($where)->count();
        $Page = new AjaxPage($count, 13);
        $show = $Page->show();
        $specList = $model->where($where)->order('`type_id` desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $GoodsLogic = new GoodsLogic();
        foreach ($specList as $k => $v) {       // 获取规格项
            $arr = $GoodsLogic->getSpecItem($v['id']);
            $specList[$k]['spec_item'] = implode(' , ', $arr);
        }

        $this->assign('specList', $specList);
        $this->assign('page', $show);// 赋值分页输出
        $goodsTypeList = M("GoodsType")->select(); // 规格分类
        $goodsTypeList = convert_arr_key($goodsTypeList, 'id');
        $this->assign('goodsTypeList', $goodsTypeList);
        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品规格
     */
    public function addEditSpec()
    {

        $model = D("spec");
        $id = I('id/d', 0);
        if ((I('is_ajax') == 1) && IS_POST)//ajax提交验证
        {
            // 数据验证
            $validate = \think\Loader::validate('Spec');
            $post_data = I('post.');
            $scene = $id > 0 ? 'edit' : 'add';
            if (!$validate->scene($scene)->batch()->check($post_data)) {  //验证数据
                $error = $validate->getError();
                $error_msg = array_values($error);
                $this->ajaxReturn(['status' => -1, 'msg' => $error_msg[0], 'data' => $error]);
            }
            $model->data($post_data, true); // 收集数据
            if ($scene == 'edit') {
                $model->isUpdate(true)->save(); // 写入数据到数据库
                $model->afterSave(I('id'));
            } else {
                $model->save(); // 写入数据到数据库
                $insert_id = $model->getLastInsID();
                $model->afterSave($insert_id);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/Goods/specList')]);
        }
        // 点击过来编辑时
        $spec = DB::name("spec")->find($id);
        $GoodsLogic = new GoodsLogic();
        $items = $GoodsLogic->getSpecItem($id);
        $spec[items] = implode(PHP_EOL, $items);
        $this->assign('spec', $spec);

        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList', $goodsTypeList);
        return $this->fetch('_spec');
    }


    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect()
    {
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;
        $GoodsLogic = new GoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('Spec')->where("type_id = " . I('get.spec_type/d'))->order('`order` desc')->select();
        foreach ($specList as $k => $v)
            $specList[$k]['spec_item'] = M('SpecItem')->where("spec_id = " . $v['id'])->order('id')->getField('id,item'); // 获取规格项

        $items_id = M('SpecGoodsPrice')->where('goods_id = ' . $goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);

        // 获取商品规格图片
        if ($goods_id) {
            $specImageList = M('SpecImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');
        }

        
        $this->assign('specImageList', $specImageList);

        $this->assign('items_ids', $items_ids);
        $this->assign('specList', $specList);
        return $this->fetch('ajax_spec_select');
    }

    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */
    public function ajaxGetSpecInput()
    {
        $GoodsLogic = new GoodsLogic();
        $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
        $str = $GoodsLogic->getSpecInput($goods_id, I('post.spec_arr/a', [[]]));

        exit($str);
    }

    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename', '');
        M('goods_images')->where("image_url = '$path'")->delete();
    }

    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord()
    {
        $searchWordLogic = new SearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord();
        $this->success('成功初始化' . $successNum . '个搜索关键词');
    }

    /**
     * 初始化地址json文件
     */
    public function initLocationJsonJs()
    {
        $goodsLogic = new GoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        file_put_contents(ROOT_PATH . "public/js/locationJson.js", "var locationJsonInfoDyr = " . json_encode($region_list, JSON_UNESCAPED_UNICODE) . ';');
        $this->success('初始化地区json.js成功。文件位置为' . ROOT_PATH . "public/js/locationJson.js");
    }

    //供应商发货首页
    public function goods_delivery_list(){
        return $this->fetch('goods_delivery_list');
    }

    //返回相应供应商列表数据
    public function goods_ajaxdelivery(){
//        $providerInfo = db('n_provider')->where('admin_id',session('admin_id'))->find();
        $input = input('');
        $condition = array();

        //时间筛选
        if(isset($input['start_time'])&&$input['start_time']){
            $startTime = strtotime($input['start_time']);
            $condition['add_time'] = ['>=',$startTime];
        }
        if(isset($input['end_time'])&&$input['end_time']){
            $endTime = strtotime($input['end_time']);
            $condition['add_time'] = ['<=',$endTime];
        }
        if(isset($input['start_time'])&&isset($input['end_time'])&&$input['start_time']&&$input['end_time']){
            $startTime = strtotime($input['start_time']);
            $endTime = strtotime($input['end_time']);
            $condition['add_time'] = ['between time',[$startTime,$endTime]];
        }


//        $shipping_status = I('shipping_status');
//        $condition['shipping_status'] = empty($shipping_status) ? array('neq', 1) : $shipping_status;
//        $condition['order_status'] = array('in', '1,2,4');

//        $count = M('order')->where($condition)->count();
        $count = M('order_goods')->where($condition)->count();
        $sum = M('order_goods')->where($condition)->sum('goods_num');

        $Page = new AjaxPage($count, 10);
        //搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            if (!is_array($val)) {
                $Page->parameter[$key] = urlencode($val);
            }
        }
        $show = $Page->show();
        $orderList = M('order')
//            ->alias('a')
//            ->join('tp_order b','b.order_id=a.order_id')
//            ->where('a.provider_id',$providerInfo['id'])
            ->where('pay_status=1')
            ->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)
//            ->field('a.*,b.*')
//            ->fetchSql()
            ->order('add_time DESC')->select();
//        dump($orderList);die;

        //获取订单相关信息
        foreach($orderList as $key => &$v){
            $orderInfo = db('order')->where('order_id',$v['order_id'])->find();
            $v['order_sn']= $orderInfo['order_sn'];
            $v['consignee']= $orderInfo['consignee'];
            $v['mobile']= $orderInfo['mobile'];
            $v['order_add_time']= $orderInfo['add_time'];
        }
//        dump($orderList);die;

        //导出excl
//        if(isset($input['submit'])){
//            $orderLogic = new OrderLogic();
//            //列表表头文案
//            $rows = array(
//                '商品订单ID', '商品订单编号', '商品名称', '下单时间', '收货人', '联系电话'
//            );
//
//            //列表表头字段取值
//            $exList = array(
//                'rec_id', 'order_goods_sn', 'goods_name', 'add_time', 'consignee', 'mobile'
//            );
//            $strTable = $orderLogic->exportList($rows, $exList, $orderList);
//
//            $orderLogic->downloadExcel($strTable, '门店商家排队表');
//            exit();
//        }

        $this->assign('orderList', $orderList);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('count', $count);
        $this->assign('sum', $sum);
        return $this->fetch('goods_ajaxdelivery');
    }

    //发货详情页
    public function goods_delivery_info(){
        $orderId = I('order_id');

        $orderInfo = M('order')->where('order_id',$orderId)->find();

        $orderGoodsAll = M('order_goods')->where('order_id', $orderId)->select();

        $orderGoodsInfo = M('order_goods')->where('order_id', $orderId)->find();
        $order = M('order')->where('order_id',$orderGoodsInfo['order_id'])->find();
        //收货人信息
        $orderGoodsInfo['order_sn'] = $order['order_sn'];
        $orderGoodsInfo['order_add_time'] = $order['add_time'];
        $orderGoodsInfo['consignee'] = $order['consignee'];
        $province = db('region')->where('id',$order['province'])->find();
        $orderGoodsInfo['province'] = $province['name'];
        $city = db('region')->where('id',$order['city'])->find();
        $orderGoodsInfo['city'] = $city['name'];
        $orderGoodsInfo['address'] = $order['address'];
        $orderGoodsInfo['mobile'] = $order['mobile'];
        $orderGoodsInfo['email'] = $order['email'];
        $orderGoodsInfo['user_note'] = $order['user_note'];
        $orderGoodsInfo['zipcode'] = $order['zipcode'];



        $list = array();
        foreach($orderGoodsAll as $v){
            $order = M('order')->where('order_id',$v['order_id'])->find();
            //收货人信息
            $v['order_sn'] = $order['order_sn'];
            $v['order_add_time'] = $order['add_time'];
            $v['consignee'] = $order['consignee'];
            $province = db('region')->where('id',$order['province'])->find();
            $v['province'] = $province['name'];
            $city = db('region')->where('id',$order['city'])->find();
            $v['city'] = $city['name'];
            $v['address'] = $order['address'];
            $v['mobile'] = $order['mobile'];
            $v['email'] = $order['email'];
            $v['user_note'] = $order['user_note'];
            $v['zipcode'] = $order['zipcode'];

            $list[] = $v;
        }

        $shipping_list = M('plugin')->where(array('status' => 1, 'type' => 'shipping'))->select();

        //返回配置好的物流公司配置
        $expressList = M('n_express')->select();

//        dump($orderGoodsInfo);die;

        $this->assign('orderGoodsInfo', $orderGoodsInfo);
        $this->assign('orderInfo', $orderInfo);
        $this->assign('orderGoods', $list);
        $this->assign('shipping_list', $shipping_list);
        $this->assign('expressList', $expressList);
        return $this->fetch();

    }
    //查看发货详情页
    public function goods_delivery_info2(){
        $orderId = I('order_id');

        $orderInfo = M('order')->where('order_id',$orderId)->find();
//        dump($orderInfo);die;

        $orderGoodsAll = M('order_goods')->where('order_id', $orderId)->select();

        $orderGoodsInfo = M('order_goods')->where('order_id', $orderId)->find();
        $order = M('order')->where('order_id',$orderGoodsInfo['order_id'])->find();
        //收货人信息
        $orderGoodsInfo['order_sn'] = $order['order_sn'];
        $orderGoodsInfo['order_add_time'] = $order['add_time'];
        $orderGoodsInfo['consignee'] = $order['consignee'];
        $province = db('region')->where('id',$order['province'])->find();
        $orderGoodsInfo['province'] = $province['name'];
        $city = db('region')->where('id',$order['city'])->find();
        $orderGoodsInfo['city'] = $city['name'];
        $orderGoodsInfo['address'] = $order['address'];
        $orderGoodsInfo['mobile'] = $order['mobile'];
        $orderGoodsInfo['email'] = $order['email'];
        $orderGoodsInfo['user_note'] = $order['user_note'];
        $orderGoodsInfo['zipcode'] = $order['zipcode'];

        $list = array();
        foreach($orderGoodsAll as $v){
            $order = M('order')->where('order_id',$v['order_id'])->find();
            //收货人信息
            $v['order_sn'] = $order['order_sn'];
            $v['order_add_time'] = $order['add_time'];
            $v['consignee'] = $order['consignee'];
            $province = db('region')->where('id',$order['province'])->find();
            $v['province'] = $province['name'];
            $city = db('region')->where('id',$order['city'])->find();
            $v['city'] = $city['name'];
            $v['address'] = $order['address'];
            $v['mobile'] = $order['mobile'];
            $v['email'] = $order['email'];
            $v['user_note'] = $order['user_note'];
            $v['zipcode'] = $order['zipcode'];

            $list[] = $v;
        }

        $shipping_list = M('plugin')->where(array('status' => 1, 'type' => 'shipping'))->select();

        //返回配置好的物流公司配置
        $expressList = M('n_express')->select();


        $this->assign('orderGoodsAll', $orderGoodsInfo);
        $this->assign('orderInfo', $orderInfo);
        $this->assign('orderGoods', $list);
        $this->assign('shipping_list', $shipping_list);
        $this->assign('expressList', $expressList);
        return $this->fetch();

    }

    /**
     * 生成发货单
     */
    public function goods_deliveryHandle()
    {
        $data = I('post.');
//        dump($data);die;

        //判断是否有传物流公司
        if (!$data['express_id']) {
            $this->error('请选择物流公司和填写物流单号');
        }

        //物流信息
        $express = M('n_express')->where('id',$data['express_id'])->find();
        $updateGoods['send_number']=$data['invoice_no'];
        $updateGoods['shipping_name']=$express['name'];
        $updateGoods['shipping_code']=$express['code'];
        $updateGoods['note']=$data['note'];
        $updateGoods['order_status'] =1;
        $updateGoods['shipping_status'] =1;

        $updateOrder['order_status'] = 1;
        $updateOrder['shipping_status'] = 1;

//        dump($data['goods']);die;

        foreach($data['goods'] as $v){
            $goodsInfo =  M('order_goods')->where('rec_id',$v)->find();
            $upOrder = db('order')->where('order_id', $goodsInfo['order_id'])->update($updateOrder);

            $res = db('order_goods')->where('rec_id',$v)->update($updateGoods);

        }


        if ($res) {
            $this->success('操作成功', U('Admin/goods/goods_delivery_list'));
        } else {
            $this->success('操作失败', U('Admin/goods/goods_delivery_list'));
        }
    }

    public function export_goods_order(){
        $input = input('');
//        dump($input);die;
        $condition = array();
        $providerInfo = db('n_provider')->where('admin_id',session('admin_id'))->find();

        //时间筛选
        if(isset($input['start_time'])&&$input['start_time']){
            $startTime = strtotime($input['start_time']);
            $condition['add_time'] = ['>=',$startTime];
        }
        if(isset($input['end_time'])&&$input['end_time']){
            $endTime = strtotime($input['end_time']);
            $condition['add_time'] = ['<=',$endTime];
        }
        if(isset($input['start_time'])&&isset($input['end_time'])&&$input['start_time']&&$input['end_time']){
            $startTime = strtotime($input['start_time']);
            $endTime = strtotime($input['end_time']);
            $condition['add_time'] = ['between time',[$startTime,$endTime]];
        }

        $orderList = M('order_goods')->where('provider_id',$providerInfo['id'])->where($condition)->order('add_time DESC')->select();

        //获取订单相关信息
        foreach($orderList as $key => &$v){
            $orderInfo = db('order')->where('order_id',$v['order_id'])->find();
            $v['order_sn']= $orderInfo['order_sn'];
            $v['consignee']= $orderInfo['consignee'];
            $v['mobile']= $orderInfo['mobile'];
            $v['order_add_time']= $orderInfo['add_time'];
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">商品订单ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">商品订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">下单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">联系电话</td>';
        $strTable .= '</tr>';
        $count = M('users')->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {

            if (is_array($orderList)) {
                foreach ($orderList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['rec_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['order_goods_sn'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_name'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['add_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['consignee'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '</tr>';
                }
                unset($orderList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'goods_order' . $i);
        exit();

    }

}