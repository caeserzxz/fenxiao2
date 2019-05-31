<?php

/**
 * 用户余额支付
 */
class money_pay {

    /**
     * 支付
     *
     * @param $order
     * @throws \app\common\exception\PayFailedException
     * @throws \think\exception\DbException
     */
    function pay($order) {

        $amount = $order['order_amount'];

        $order['user_id'] and $user = \app\common\model\Users::get($order['user_id']);

        if (!$user) {
            \think\Log::error((string) new \think\Exception('用户不存在'));
            throw new \app\common\exception\PayFailedException();
        }
        if ($user['user_money'] < $amount) {
            throw new \app\common\exception\PayFailedException('用户佣金不足');
        }

        \think\Db::startTrans();

        // 扣除余额
        $user->save([
            'user_money' => ['exp', 'user_money - ' . $amount],
        ]);

        $order_sn = $order['order_sn'];

        if (strlen($order_sn) > 18) {
            // 去除多余字符串（时间戳后缀
            $order_sn = substr($order_sn, 0, 18);
        }

        // 修改订单支付状态
        try {
            $result = update_pay_status($order_sn);

        } catch (\think\Exception $e) {
            \think\Log::error((string) new \think\Exception('修改订单支付状态 失败', 0, $e));
        }

        \app\common\model\AccountLog::create([
            'user_id'    => $user['user_id'],
            'user_money' => -$amount,
            'desc'       => '佣金支付',
        ]);

        \think\Db::commit();
    }

    function response() {
    }

    function respond2() {
    }

    // 微信订单退款原路退回
    public function payment_refund($data){

    }
}