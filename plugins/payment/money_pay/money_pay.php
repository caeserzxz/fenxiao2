<?php

namespace payment\money_pay;

use app\common\exception\AlreadyPaidException;
use app\common\exception\PayFailedException;
use app\common\model\AccountLog;
use app\common\model\PaymentLog;
use app\common\model\Users;
use think\Db;
use think\Exception;
use think\Hook;

/**
 * 用户余额支付
 * 用于payment_log
 */
class money_pay {

    /**
     * 支付
     *
     * @param array $options
     * @throws \think\exception\DbException
     * @throws AlreadyPaidException
     * @throws PayFailedException
     * @throws Exception
     */
    function pay($options = []) {

        $amount = $options['amount'];

        Db::startTrans();

        // 获取支付单
        $options['trade_no'] and $paymentLog = PaymentLog::get(['trade_no' => $options['trade_no']], 'user');
        if (!$paymentLog) {
            throw new Exception('支付单不存在');
        }
        if ($paymentLog['status'] === PaymentLog::STATUS_PAID) {
            // 可能是因为回复平台通知的失败，导致平台重复通知
            throw new AlreadyPaidException('支付单已支付');
        }

        // 获取用户
        /** @var Users $user */
        $user = $paymentLog['user'];
        if (!$user) {
            throw new Exception('用户不存在');
        }
        if ($user['user_money'] < $amount) {
            throw new PayFailedException('用户佣金不足');
        }

        // 扣除余额
        $user->save([
            'user_money' => ['exp', 'user_money - ' . $amount],
        ]);

        $data = [];
        $paidAmount = $amount;

        // 处理支付结果
        $params = [
            'payResult'  => $data,
            'paymentLog' => $paymentLog,
            'paidAmount' => $paidAmount,
        ];
        Hook::listen('payResultProcess', $params);

        // 支付完成
        $params = [
            'payResult'  => $data,
            'paymentLog' => $paymentLog,
        ];
        Hook::listen('payAfter', $params);

        $paymentLog->save();

        AccountLog::create([
            'user_id'    => $user['user_id'],
            'user_money' => -$amount,
            'desc'       => '佣金支付',
        ]);

        Db::commit();
    }

    public static function response() {
    }

    public static function respond2() {
    }

    /**
     * 获取支付结果
     *
     * @param array $options
     * @return bool
     */
    public function getPayResult(array $options) {
        return (int) $options['paymentLog']['status'] === PaymentLog::STATUS_PAID;
    }
}