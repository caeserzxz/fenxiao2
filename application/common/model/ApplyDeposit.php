<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-8
 * Time: 16:25
 */

namespace app\common\model;

class ApplyDeposit extends CommonModel {

    const STATUS_REQUESTED_REFUNDED = 0;
    const STATUS_REFUND_GRANTED = 1;
    const STATUS_REFUND_REJECTED = 2;
    const STATUS_SUCCEEDED = 3;
    const STATUS_FAILED = 4;

    public static $statusList = [
        self::STATUS_REQUESTED_REFUNDED => '申请审核',
        self::STATUS_REFUND_GRANTED     => '审核通过',
        self::STATUS_REFUND_REJECTED    => '审核拒绝',
        self::STATUS_SUCCEEDED          => '打款成功',
        self::STATUS_FAILED             => '打款失败',
    ];
}