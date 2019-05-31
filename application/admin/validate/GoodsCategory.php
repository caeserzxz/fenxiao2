<?php

namespace app\admin\validate;
use think\Validate;
class GoodsCategory extends Validate {
    // 验证规则
    protected $rule = [
        ['name','require','分类名称必须填写'],
       /* ['sort_order', 'number', '排序必须为数字'],*/
    ];
}
