<?php

namespace app\admin\validate;
use think\Validate;
class Order extends Validate {

    // 验证规则
    protected $rule = [
        ['consignee','require','收货人称必须填写'],
        ['address', 'require', '地址必须填写'],
    ];
}
