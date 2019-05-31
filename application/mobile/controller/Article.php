<?php

namespace app\mobile\controller;

use think\Db;

class Article extends MobileBase
{
    //文章详情页
    public function articleDetail()
    {
        $articleId = I('article_id');
        //文章内容
        $getArticle = M('article')->where('article_id', $articleId)->find();
        $getArticle['content'] = htmlspecialchars_decode($getArticle['content']);

        //专业推荐
        $recommend = M('goods')
            ->where('goods_id', 'in', $getArticle['goods_ids'])
            ->select();
        //加上保税仓香港仓的标识
        if ($recommend) {
            foreach ($recommend as $k => $v) {
                if ($v['hk_bs_good'] == '0' && $v['is_identity'] == '1') {
                    $recommend[$k]['goods_name'] = $v['goods_name'];
                }
                if ($v['hk_bs_good'] == '1') {
                    $recommend[$k]['goods_name'] = $v['goods_name'];
                }
                if ($v['hk_bs_good'] == '2') {
                    $recommend[$k]['goods_name'] =  $v['goods_name'];
                }
            }
        }


        //精品推荐
        $fineRecommend =M('goods')
                ->where('is_hot', 1)
                ->where('is_on_sale', 1)
                ->order('sort asc')
                //->limit(20)
                ->cache(true,TPSHOP_CACHE_TIME)
                ->select();//首页热卖商品
        //加上保税仓香港仓的标识
        if ($fineRecommend) {
            foreach ($fineRecommend as $k => $v) {
                if ($v['hk_bs_good'] == '0' && $v['is_identity'] == '1') {
                    $fineRecommend[$k]['goods_name'] = $v['goods_name'];
                }
                if ($v['hk_bs_good'] == '1') {
                    $fineRecommend[$k]['goods_name'] = $v['goods_name'];
                }
                if ($v['hk_bs_good'] == '2') {
                    $fineRecommend[$k]['goods_name'] = $v['goods_name'];
                }
            }
        }

        $this->assign('fineRecommend', $fineRecommend);
        $this->assign('recommend', $recommend);
        $this->assign("article", $getArticle);
        return $this->fetch();
    }


}