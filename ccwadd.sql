

-- ----------------------------
-- Table structure for `tp_n_integral_log`
-- ----------------------------
DROP TABLE IF EXISTS `tp_n_integral_log`;
CREATE TABLE `tp_n_integral_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '表id',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `money` decimal(12,2) DEFAULT NULL COMMENT '积分(正数收入，负数支出)',
  `number` varbinary(200) DEFAULT NULL COMMENT '流水号',
  `desc` varchar(255) DEFAULT NULL COMMENT '描述',
  `obj` text COMMENT '附加数据，json记录',
  `create_time` int(11) DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='积分流水表';

-- ----------------------------
-- Records of tp_n_integral_log
-- ----------------------------



-- ----------------------------
-- Table structure for `tp_n_integral_order`
-- ----------------------------
CREATE TABLE `tp_n_integral_order` (
  `order_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT COMMENT '订单id',
  `order_sn` varchar(20) NOT NULL DEFAULT '' COMMENT '订单编号',
  `user_id` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '用户id',
  `order_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '订单状态0:待发货1:待发货(后台确认订单)2:待评价3已取消4已完成5已作废',
  `shipping_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '发货状态0未发货1已发货2部分发货',
  `pay_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '支付状态0未支付1已支付;3已同意退款；4已拒绝退款',
  `consignee` varchar(60) NOT NULL DEFAULT '' COMMENT '收货人',
  `country` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '国家',
  `province` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '省份',
  `city` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '城市',
  `district` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '县区',
  `twon` int(11) DEFAULT '0' COMMENT '乡镇',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '地址',
  `zipcode` varchar(60) NOT NULL DEFAULT '' COMMENT '邮政编码',
  `mobile` varchar(60) NOT NULL DEFAULT '' COMMENT '手机',
  `email` varchar(60) NOT NULL DEFAULT '' COMMENT '邮件',
  `shipping_code` varchar(32) NOT NULL DEFAULT '' COMMENT '物流code',
  `shipping_name` varchar(120) NOT NULL DEFAULT '' COMMENT '物流名称',
  `pay_code` varchar(32) NOT NULL DEFAULT '' COMMENT '支付code',
  `pay_name` varchar(120) NOT NULL DEFAULT '' COMMENT '支付方式名称',
  `invoice_title` varchar(256) DEFAULT '' COMMENT '发票抬头',
  `goods_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '商品总价',
  `shipping_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '邮费',
  `user_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '使用余额',
  `coupon_price` decimal(10,2) DEFAULT '0.00' COMMENT '优惠券抵扣',
  `integral` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '使用积分',
  `integral_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '使用积分抵多少钱',
  `order_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '应付款金额(最终实际付出金额，但由于这个项目特殊，实际付款金额是要扣减去金豆/云豆抵扣金额)',
  `total_amount` decimal(10,2) DEFAULT '0.00' COMMENT '订单总价',
  `add_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '下单时间',
  `shipping_time` int(11) DEFAULT '0' COMMENT '最后新发货时间',
  `confirm_time` int(10) DEFAULT '0' COMMENT '收货确认时间',
  `pay_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '支付时间',
  `transaction_id` varchar(255) DEFAULT NULL COMMENT '第三方平台交易流水号',
  `order_prom_id` smallint(6) NOT NULL DEFAULT '0' COMMENT '活动id',
  `order_prom_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '活动优惠金额',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '价格调整',
  `user_note` varchar(255) NOT NULL DEFAULT '' COMMENT '用户备注',
  `admin_note` varchar(255) DEFAULT '' COMMENT '管理员备注',
  `parent_sn` varchar(100) DEFAULT NULL COMMENT '父单单号',
  `paid_money` decimal(10,2) DEFAULT '0.00' COMMENT '订金',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用户假删除标识,1:删除,0未删除',
  `order_type` tinyint(4) DEFAULT '0' COMMENT '0普通订单',
  `send_number` varchar(100) DEFAULT NULL COMMENT '快递单号',
  `refund_time` int(11) DEFAULT NULL COMMENT '用户取消订单后，后台处理退款单的操作时间',
  `is_delete` varchar(255) DEFAULT '1' COMMENT '订单伪删除；1：正常；2：已删除',
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `order_sn` (`order_sn`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `add_time` (`add_time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='积分兑换订单表';

-- ----------------------------
-- Records of tp_n_integral_order
-- ----------------------------
































