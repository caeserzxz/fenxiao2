<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-5-31
 * Time: 14:53
 */

namespace app\common\model;

class PaymentLog extends CommonModel {

    // 支付状态
    const STATUS_UNPAID = 1;// 未支付
    const STATUS_PAID = 2;// 已支付

    // 附加数据
    const EXTRA_PAY_REASON = 'pay_reason';// 支付原因
    const EXTRA_FINANCIAL_ID = 'financial_id';// 理财单id
    const EXTRA_LEVEL_ID = 'level_id';// 会员等级ID
    const EXTRA_USER_ID = 'user_id';// 会员ID

    // 支付原因
    const PAY_FOR_DEPOSIT = 1;// 押金（送水活动
    const PAY_FOR_FINANCIAL = 2;// 投资理财

    public function user() {
        return $this->hasOne('Users', 'user_id', 'user_id');
    }

    protected $type = [
        'extra'    => 'json',
        'pay_time' => 'datetime',

        'create_time' => 'datetime',
        'delete_time' => 'datetime',
    ];
}