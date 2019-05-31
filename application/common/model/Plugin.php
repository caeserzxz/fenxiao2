<?php

namespace app\common\model;

use think\Model;

class Plugin extends Model {

    // 特殊支付方式代码
    const PAYMENT_CODE_COD = 'cod';// 货到付款
    const PAYMENT_CODE_MONEY_PAY = 'money_pay';// 余额支付

    protected $type = [
        'config'       => 'serialize',
        'config_value' => 'serialize',
    ];
}
