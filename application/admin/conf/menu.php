<?php

return array(
    'system' => array(
        'name' => '系统',
        'child' => array(
            array(
                'name' => '设置',
                'child' => array(
                    array('name' => '商城设置', 'act' => 'index', 'op' => 'System'),
                    /* array('name' => '身份奖励设置', 'act' => 'referral', 'op' => 'System'),*/
                    array('name' => '平台设置', 'act' => 'hkSet', 'op' => 'System'),
                    //array('name' => '常见问题设置', 'act' => 'questionList', 'op' => 'Question'),
                    array('name' => '顶部轮转图设置', 'act' => 'bannerList', 'op' => 'Banner'),
                    array('name' => '首页常用设置', 'act' => 'iconList', 'op' => 'Banner'),
                    array('name' => '有问必答', 'act' => 'explain', 'op' => 'System'),
                    array('name' => '购买身份图片配置', 'act' => 'buyVip', 'op' => 'user'),
                    array('name' => '客服二维码配置', 'act' => 'qrcoder', 'op' => 'user'),
                    //array('name'=>'支付方式','act'=>'index1','op'=>'System'),
                    array('name' => '地区&配送', 'act' => 'region', 'op' => 'Tools'),
                    array('name' => '物流公司设置', 'act' => 'expressList', 'op' => 'Express'),
                    array('name' => '二维码背景图配置', 'act' => 'upQrCodeBackground', 'op' => 'System'),
                     array('name' => '短信模板', 'act' => 'index', 'op' => 'SmsTemplate'),
                    //array('name'=>'接口对接','act'=>'index3','op'=>'System'),
                    //array('name'=>'验证码设置','act'=>'index4','op'=>'System'),
                    /*array('name' => '自定义导航栏', 'act' => 'navigationList', 'op' => 'System'),*/
                    /*array('name' => '友情链接', 'act' => 'linkList', 'op' => 'Article'),*/
                    array('name' => '清除缓存', 'act' => 'cleanCache', 'op' => 'System'),
                    //array('name' => '自提点', 'act' => 'index', 'op' => 'Pickup'),
                )
            ),
            array(
                'name' => '会员',
                'child' => array(
                    array('name' => '会员列表', 'act' => 'index', 'op' => 'User'),
                    array('name' => '购买身份卡列表', 'act' => 'vipIndex', 'op' => 'User'),
                    array('name' => '收益流水', 'act' => 'amountList', 'op' => 'User'),
//                    array('name' => '积分流水', 'act' => 'integralList', 'op' => 'User'),
                    array('name' => '佣金报表', 'act' => 'commissionList', 'op' => 'User'),
                    array('name' => '查看团队列表', 'act' => 'teamList2', 'op' => 'user'),
                    /*array('name' => '用户银行卡列表', 'act' => 'bankList', 'op' => 'User'),*/
                    /* array('name' => '业务员列表', 'act' => 'salesman', 'op' => 'User'),*/
                    /* array('name' => '身份列表', 'act' => 'identitylist', 'op' => 'User'),*/
                    array('name' => '会员等级', 'act' => 'levelList', 'op' => 'User'),
                    /*  array('name' => '充值记录', 'act' => 'recharge', 'op' => 'User'),*/
                    /*  array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'User'),*/
                    /*  array('name' => '汇款记录', 'act' => 'remittance', 'op' => 'User'),*/
                    //array('name' => '直推关系列表', 'act' => 'topRefferal', 'op' => 'User'),
                    //array('name' => '管理关系列表', 'act' => 'manageList', 'op' => 'User'),
                    //array('name' => '大区/总代直推树形图', 'act' => 'tree_zhitui', 'op' => 'User'),
                    //array('name' => '大区/总代管理树形图', 'act' => 'tree_guanli', 'op' => 'User'),

                    //array('name'=>'会员整合','act'=>'integrate','op'=>'User'),
                    //array('name'=>'会员签到','act'=>'signList','op'=>'User'),
                )
            ),
            /*   array(
                   'name' => '广告',
                   'child' => array(
                       array('name' => '广告列表', 'act' => 'adList', 'op' => 'Ad'),
                       array('name' => '广告位置', 'act' => 'positionList', 'op' => 'Ad'),
                   )
               ),*/
         /*   array(
                'name' => '文章',
                'child' => array(
                    array('name' => '文章列表', 'act' => 'articleList', 'op' => 'Article'),
                  /*  array('name' => '文章分类', 'act' => 'categoryList', 'op' => 'Article'),*/
                    //array('name' => '帮助管理', 'act'=>'help_list', 'op'=>'Article'),
                    //array('name'=>'友情链接','act'=>'linkList','op'=>'Article'),
                    //array('name' => '公告管理', 'act'=>'notice_list', 'op'=>'Article'),
                    //array('name' => '专题列表', 'act' => 'topicList', 'op' => 'Topic'),
             /*  )
            ),*/
            array(
                'name' => '消息',
                'child' => array(
                    array('name' => '发送系统消息', 'act' => 'noticeAdd', 'op' => 'Notice'),
                    array('name' => '系统消息列表', 'act' => 'noticeList', 'op' => 'Notice'),
                )
            ),

            array(
                'name' => '权限',
                'child' => array(
                    array('name' => '管理员列表', 'act' => 'index', 'op' => 'Admin'),
                    array('name' => '角色管理', 'act' => 'role', 'op' => 'Admin'),
                    array('name' => '权限资源列表', 'act' => 'right_list', 'op' => 'System'),
                    array('name' => '管理员日志', 'act' => 'log', 'op' => 'Admin'),
                    array('name' => '添加供应商', 'act' => 'provider', 'op' => 'Admin'),
                  /*  array('name' => '供应商列表', 'act' => 'supplier', 'op' => 'Admin'),*/
                )
            ),

            /*array('name' => '模板','child'=>array(
                //	array('name' => '模板设置', 'act'=>'templateList', 'op'=>'Template'),
                    //array('name' => '手机首页', 'act'=>'mobile_index', 'op'=>'Template'),
            )),*/
            /*	array('name' => '数据','child'=>array(
                        array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools'),
                        array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools'),
                        //array('name' => 'ecshop数据导入', 'act'=>'ecshop', 'op'=>'Tools'),
                        //array('name' => '淘宝csv导入', 'act'=>'taobao', 'op'=>'Tools'),
                        //array('name' => 'SQL查询', 'act'=>'log', 'op'=>'Admin'),
                ))*/
        )
    ),

    'shop' => array(
        'name' => '商城',
        'child' => array(
            array(
                'name' => '商品', 'child' => array(
                array('name' => '商品列表', 'act' => 'goodsList', 'op' => 'Goods'),
                array('name' => '商品分类', 'act' => 'categoryList', 'op' => 'Goods'),
                /* array('name' => '库存日志', 'act' => 'stock_list', 'op' => 'Goods'),*/
                //array('name' => '库存日志', 'act' => 'ku_list', 'op' => 'Goods'),
                array('name' => '商品模型', 'act' => 'goodsTypeList', 'op' => 'Goods'),
                array('name' => '商品规格', 'act' => 'specList', 'op' => 'Goods'),
                //array('name' => '品牌列表', 'act' => 'brandList', 'op' => 'Goods'),
                /*array('name' => '商品属性', 'act' => 'goodsAttributeList', 'op' => 'Goods'),*/
                array('name' => '评论列表', 'act' => 'index', 'op' => 'Comment'),
                array('name' => '商品发货管理', 'act' => 'goods_delivery_list', 'op' => 'Goods'),
                //array('name' => '商品咨询', 'act' => 'ask_list', 'op' => 'Comment'),

            )
            ),
            array(
                'name' => '订单',
                'child' => array(
                    array('name' => '订单列表', 'act' => 'index', 'op' => 'Order'),
                    /*  array('name' => '水币兑水记录表', 'act' => 'duishui', 'op' => 'Order'),
                      array('name' => '积分商城兑换表', 'act' => 'jifen', 'op' => 'Order'),*/
                    //array('name' => '虚拟订单', 'act'=>'virtual_list', 'op'=>'Order'),
                    array('name' => '发货单', 'act' => 'delivery_list', 'op' => 'Order'),
                    //array('name' => '退款单', 'act' => 'refund_order_list', 'op' => 'Order'),
                    /*array('name' => '退换货', 'act' => 'return_list', 'op' => 'Order'),*/
                    //array('name' => '售后申请', 'act' => 'return_list', 'op' => 'Order'),
                    //array('name' => '添加订单', 'act' => 'add_order', 'op' => 'Order'),
                    //array('name' => '订单日志', 'act' => 'order_log', 'op' => 'Order'),
                    //array('name' => '直推分佣流水', 'act' => 'order_amount_log', 'op' => 'Order'),
                    //array('name' => '发票管理','act'=>'index', 'op'=>'Invoice'),
                    //array('name' => '拼团列表','act'=>'team_list','op'=>'Team'),
                    // array('name' => '拼团订单','act'=>'order_list','op'=>'Team'),
                )
            ),
            array(
                'name' => '申请',
                'child' => array(
                    /*array('name' => '健康大使审核列表', 'act' => 'healthList', 'op' => 'Apply'),
                    array('name' => '总代审核列表', 'act' => 'agentList', 'op' => 'Apply'),
                    array('name' => '身份证审核列表', 'act' => 'idcardList', 'op' => 'Apply'),
                    array('name' => '合同列表', 'act' => 'hetongList', 'op' => 'Apply'),
                    array('name' => '佣金提现审核列表', 'act' => 'yongjinList', 'op' => 'Apply'),
                    array('name' => '功德提现发放明细', 'act' => 'gongdeList', 'op' => 'Apply'),
                    array('name' => '销售总额', 'act' => 'totalsales', 'op' => 'Apply'),
                    array('name' => '大区/总代佣金列表', 'act' => 'daqu_zongdai_yongjinList', 'op' => 'Apply'),
                    array('name' => '大区/总代销售总额', 'act' => 'daqu_zongdai_totalsales', 'op' => 'Apply'),
                    array('name' => '大区/总代功德发放', 'act' => 'daqu_zongdai_gongdeList', 'op' => 'Apply'),*/
                    //array('name' => '兑换商品申请列表', 'act' => 'exchangeList', 'op' => 'Apply'),
                    array('name' => '提现申请列表', 'act'   => 'cashWithdrawalList', 'op' => 'Apply'),
                    array('name' => '提现待审核列表', 'act' => 'waitWithdrawalList', 'op' => 'Apply'),
                    array('name' => '提现待转账列表', 'act' => 'waitTransferList', 'op' => 'Apply'),
                )
            ),
           /* array(
                'name' => '意见',
                'child' => array(
                    array('name' => '意见反馈列表', 'act' => 'AdviceList', 'op' => 'Advice'),
                )
            ),*/

            // array(
            //     'name'  => '促销',
            //     'child' => array(
            //         array('name' => '抢购管理', 'act' => 'flash_sale', 'op' => 'Promotion'),
            //         array('name' => '团购管理', 'act' => 'group_buy_list', 'op' => 'Promotion'),
            //         array('name' => '优惠促销', 'act' => 'prom_goods_list', 'op' => 'Promotion'),
            //         array('name' => '订单促销', 'act' => 'prom_order_list', 'op' => 'Promotion'),
            //         array('name' => '优惠券', 'act' => 'index', 'op' => 'Coupon'),
            //         //array('name' => '预售管理','act'=>'pre_sell_list', 'op'=>'Promotion'),
            //         //array('name' => '拼团管理','act'=>'index', 'op'=>'Team'),
            //     )
            // ),

            /*   array(
                   'name' => '分销',
                   'child' => array(
                       array('name' => '分销商品列表', 'act' => 'goods_list', 'op' => 'Distribut'),
                       array('name' => '分销商列表', 'act' => 'distributor_list', 'op' => 'Distribut'),
                       //array('name' => '分销关系', 'act' => 'tree', 'op' => 'Distribut'),
                       array('name' => '分销设置', 'act' => 'set', 'op' => 'Distribut'),
                       array('name' => '分成日志', 'act' => 'rebate_log', 'op' => 'Distribut'),
                   )
               ),*/

            array(
                'name' => '微信',
                'child' => array(
                    array('name' => '公众号配置', 'act' => 'index', 'op' => 'Wechat'),
                    /* array('name' => '微信菜单管理', 'act' => 'menu', 'op' => 'Wechat'),
                     array('name' => '关键字回复', 'act' => 'text', 'op' => 'Wechat'),*/

                    /*array('name' => '粉丝管理', 'act' => 'fans', 'op' => 'Wechat'),*/
                    //array('name' => '收到的消息', 'act' => 'text', 'op' => 'Wechat'),
                    //array('name' => '群发消息', 'act' => 'qunfa', 'op' => 'Wechat'),
                    /*array('name' => '首次关注回复', 'act' => 'firstreply', 'op' => 'Wechat'),*/
                    //array('name' => '收到消息', 'act' => 'text', 'op' => 'Wechat'),
                    //array('name' => '发送文本消息', 'act' => 'text', 'op' => 'Wechat'),
                    //array('name' => '图文回复', 'act'=>'img', 'op'=>'Wechat'),
                )
            ),

            /*  array(
                  'name' => '统计',
                  'child' => array(
                      array('name' => '销售概况', 'act' => 'index', 'op' => 'Report'),
                      array('name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'),
                      array('name' => '会员排行', 'act' => 'userTop', 'op' => 'Report'),
                      array('name' => '销售明细', 'act' => 'saleList', 'op' => 'Report'),
                      array('name' => '会员统计', 'act' => 'user', 'op' => 'Report'),
                      array('name' => '运营概览', 'act' => 'finance', 'op' => 'Report'),
                      array('name' => '平台支出记录', 'act' => 'expense_log', 'op' => 'Report'),
                  )
              ),*/
        )
    ),

    'water' => array(
        'name' => '送水',
        'child' => array(
            array(
                'name' => '兑水',
                'child' => array(
                    array('name' => '兑水活动列表', 'act' => 'activityList', 'op' => 'Exchange'),
                    array('name' => '兑水商品列表', 'act' => 'exchangeList', 'op' => 'Exchange'),
                    array('name' => '兑水订单列表', 'act' => 'index', 'op' => 'Change'),
                )
            ),
            array(
                'name' => '退押',
                'child' => array(
                    array('name' => '退押申请列表', 'act' => 'applyList', 'op' => 'Deposit'),
                    array('name' => '退押成功列表', 'act' => 'completeList', 'op' => 'Deposit'),
                    array('name' => '全部退押列表', 'act' => 'applyAll', 'op' => 'Deposit'),
                )
            ),
        ),
    ),

    'financial_management' => [
        'name' => '理财',
        'child' => [
            [
                'name' => '理财管理',
                'child' => [
                    ['name' => '理财中', 'op' => 'Financial', 'act' => 'index'],
                    ['name' => '支取申请', 'op' => 'Financial', 'act' => 'waitingRefund'],
                    ['name' => '已完成', 'op' => 'Financial', 'act' => 'finished'],
                    ['name' => '已结束', 'op' => 'Financial', 'act' => 'canceled'],
                    ['name' => '全部理财', 'op' => 'Financial', 'act' => 'allList'],
                ],
            ],
        ],
    ],

    'mobile' => array(
        'name' => '模板',
        'child' => array(
            array(
                'name' => '设置',
                'child' => array(
                    array('name' => '模板设置', 'act' => 'templateList', 'op' => 'Template'),
                    array('name' => '手机支付', 'act' => 'templateList', 'op' => 'Template'),
                    array('name' => '微信二维码', 'act' => 'templateList', 'op' => 'Template'),
                    array('name' => '第三方登录', 'act' => 'templateList', 'op' => 'Template'),
                    array('name' => '导航管理', 'act' => 'finance', 'op' => 'Report'),
                    array('name' => '广告管理', 'act' => 'finance', 'op' => 'Report'),
                    array('name' => '广告位管理', 'act' => 'finance', 'op' => 'Report'),
                )
            ),
        )
    ),

    'resource' => array(
        'name' => '插件',
        'child' => array(
            array(
                'name' => '云服务',
                'child' => array(
                    array('name' => '插件库', 'act' => 'index', 'op' => 'Plugin'),
                    //array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools'),
                    //array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools'),
                )
            ),
            /* array('name' => 'App','child' => array(
                 array('name' => '安卓APP管理', 'act'=>'index', 'op'=>'MobileApp'),
                 array('name' => '苹果APP管理', 'act'=>'ios_audit', 'op'=>'MobileApp'),
             ))*/
        )
    ),
);