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






