<?php
/**
 * Created by PhpStorm.
 * User: Wzy
 * Date: 2018/4/12
 * Time: 10:22
 */

namespace app\api\controller;

use app\common\logic\UsersLogic;
use My\DataReturn;
use app\common\model\AdPosition;
use think\Page;
use think\Exception;

class Index extends Base{

	//首页图片
	public function indexapi(){
				try{
							$ad_img = M('ad')->cache(true,TPSHOP_CACHE_TIME)->where('pid',32)->field('ad_link,ad_code')->limit(5)->select();
			        foreach ($ad_img as $key => $val) {
			            $ad_img[$key]['ad_img'] = request()->domain() . $val['ad_code'];
			        }
							$index['bannerlist'] = $ad_img;//轮播图

							// AdPosition::with('ad')->where('position_id','in',[51318,51319,51320,51321])->select();
							$ad_position =M('AdPosition')
									->alias('p')
									->cache(true,TPSHOP_CACHE_TIME)
			            ->field('a.ad_code,a.ad_link,p.position_name')
			            ->join('ad a','a.pid = p.position_id','LEFT')
			            ->where('p.position_id','in',[51318])
			            ->find();
					        // foreach ($ad_position as $key => $val) {
					            $ad_position['ad_img'] = request()->domain() . $val['ad_code'];
					        // }
							 $index['ad_position'] = $ad_position;//热门专场大图

							 $ad_most_position =M('AdPosition')
									->alias('p')
									->cache(true,TPSHOP_CACHE_TIME)
			            ->field('a.ad_code,a.ad_link,p.position_name')
			            ->join('ad a','a.pid = p.position_id','LEFT')
			            ->where('p.position_id','in',[51321])
			            ->find();
					        // foreach ($ad_most_position as $key => $val) {
					            $ad_most_position['ad_img'] = request()->domain() . $val['ad_code'];
					        // }
							 $index['ad_most_position'] = $ad_most_position;//热门专场最大图


			         $most_ad_img = M('AdPosition')
			         		->alias('p')
									->cache(true,TPSHOP_CACHE_TIME)
			            ->field('a.ad_code,a.ad_link,p.position_name')
			            ->join('ad a','a.pid = p.position_id','LEFT')
			            ->where('p.position_id','in',51319)
			            ->select();
					        foreach ($most_ad_img as $key => $val) {
					            $most_ad_img[$key]['ad_img'] = request()->domain() . $val['ad_code'];
					        }
			            // request()->domain();
			          $index['most_ad_img'] = $most_ad_img;//热门专场大图右边小图

			          //热门专场大图下面
			          $lower_ad_img = M('AdPosition')
			         		->alias('p')
									->cache(true,TPSHOP_CACHE_TIME)
			            ->field('a.ad_code,a.ad_link,p.position_name')
			            ->join('ad a','a.pid = p.position_id','LEFT')
			            ->where('p.position_id','in',51320)
			            ->select();
					        foreach ($lower_ad_img as $key => $val) {
					            $lower_ad_img[$key]['ad_img'] = request()->domain() . $val['ad_code'];
					        }
			            // request()->domain();
			          $index['lower_ad_img'] = $lower_ad_img;//热门专场大图下面

			            // dump($ad_position);die;


			        // dump($index['favourite_goods']);
			        DataReturn::returnJson('200','获取数据成功！',$index);
			        // DataReturn::returnJson(200,'获取数据成功',$this->viewAssign());
				}catch(\Exception  $e){
						 DataReturn::returnJson('400','获取数据失败！');
				}
	}

	//发现好货
	public function index_good_goods(){
		try{
			$count = M('goods')->where("is_recommend=1 and is_on_sale=1")->count();// 查询满足要求的总记录数
      $pagesize = C('PAGESIZE');  //每页显示数
      $pages = I('pages') ? I('pages') : 1;
      $page = new Page($count, $pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
      $goods['lists'] = M('goods')->where("is_recommend=1 and is_on_sale=1")->field('goods_id,goods_name,shop_price,original_img')->limit(20)->page($pages, $pagesize)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

			foreach ($goods['lists'] as $key => $val) {
	            $goods['lists'][$key]['original_img'] = request()->domain() . $val['original_img'];
	    }
			// dump($goods);die;
			DataReturn::returnJson('200','获取数据成功！',$goods);
		}catch(\Exception  $e){
						 DataReturn::returnJson('400','获取数据失败！');
		}
	}

	public function test()
	{
//		session_start();
		dump(session(''));
		var_dump($_SESSION);

	}
	public function test1()
	{
		$_SESSION['open'] = 'myopen123';
		session('openid','session_openid');
		var_dump($_SESSION);
	}

}