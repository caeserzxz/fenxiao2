<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-5
 * Time: 16:19
 */

namespace app\common\behavior;

use app\common\model\AccountLog;
use app\common\model\Financial;
use app\common\model\Users;
use DateTime;
use think\Log;

class FinancialBehavior extends CommonBehavior {

    /**
     * 开始理财后
     *
     * @param array $params
     */
    public function financialStartAfter(array $params) {
    }

    /**
     * 结束理财后
     *
     * @param array $params
     */
    public function financialEndAfter(array $params) {
        // 结算利息
        static::settleInterest($params['financial'], $params['user']);

        // 自动退还押金
        static::financialRefund($params['financial']);
    }

    /**
     * 退还押金后
     *
     * @param array $params
     */
    public function financialRefundAfter(array $params) {

    }

    /**
     * 结算利息
     *
     * @param Financial $financial
     * @param Users $user
     * @param null|int $intervalMonth
     */
    public static function settleInterest(Financial $financial, Users $user, $intervalMonth = null) {
        if (!is_int($intervalMonth)) {
            // 获取理财月份数
            if ($financial['status'] === Financial::STATUS_CANCELED) {
                $intervalMonth = $financial->getCancelMonth();
            } else {
                $intervalMonth = $financial->getFinishMonth();
            }
        }

        $config = tpCache('financial');
        $config['shopping.point_rate'] = tpCache('shopping.point_rate');

        // 计算利息
        $info = static::getSettleInterestInfo($financial['amount'], $intervalMonth, $config);

        // 发放利息
        $user->save([
            'user_money' => ['exp', 'user_money + ' . $info['money']],
            'pay_points' => ['exp', 'pay_points + ' . $info['point']],
        ]);

        // 记录资金变动
        AccountLog::create([
            'user_id'    => $user['user_id'],
            'user_money' => $info['money'],
            'pay_points' => $info['point'],
            'desc'       => '理财利息',
        ]);

        // 保存结算利息
        $financial['user_money'] = ['exp', 'user_money + ' . $info['money']];
        $financial['pay_points'] = ['exp', 'pay_points + ' . $info['point']];
    }

    /**
     * 退回理财本金
     *
     * @param Financial $financial
     */
    public static function financialRefund(Financial $financial) {

        if ($financial['amount_status'] !== Financial::AMOUNT_STATUS_PAID) {
            // 未支付本金
            return;
        }

        switch ($financial['status']) {

            // case Financial::STATUS_FINISHED:
            //     // 订单已完成
            //     // 生成新的相同理财单，立即开始
            //     $newFinancial = $financial->copy();
            //     $newFinancial->startManaging();
            //     $newFinancial->save();
            //     break;

            default:
                // 退款
                $financial->refund();
                break;
        }
    }

    /**
     * 计算利息
     *
     * @param float $amount 理财金额
     * @param int $intervalMonth 理财月份
     * @param array $config 相关配置
     * @return array
     */
    public static function getSettleInterestInfo($amount, $intervalMonth, array $config) {

        // 处理配置
        $config['year_interest_rate'] = (float) $config['year_interest_rate'];
        $config['month_interest_rate'] = (float) $config['month_interest_rate'];
        $config['interest_user_money_rate'] = (float) $config['interest_user_money_rate'];
        $config['shopping.point_rate'] = (float) $config['shopping.point_rate'];
        $config['interest_pay_points_rate'] = (float) $config['interest_pay_points_rate'];

        // 利率
        if ($intervalMonth >= 12) {
            $interestRate = $config['year_interest_rate'];
        } else {
            $interestRate = $config['month_interest_rate'];
        }

        // 总利息
        $interestAmount = $amount * $intervalMonth * $interestRate / 100;

        // 获得佣金
        $money = floor($interestAmount * $config['interest_user_money_rate']) / 100;

        // 获得积分
        $point = floor($interestAmount * $config['shopping.point_rate'] * $config['interest_pay_points_rate'] / 100);

        return [
            'interval_month'  => $intervalMonth,
            'interest_rate'   => $interestRate,
            'interest_amount' => $interestAmount,
            'money'           => $money,
            'point'           => $point,
        ];
    }

    /**
     * 计算时间的月差距
     *
     * @param DateTime $startTime 开始时间
     * @param DateTime $endTime 结束时间
     * @return int 时间差距，单位 月
     */
    public static function getIntervalMonth(DateTime $startTime, DateTime $endTime) {
        $interval = $startTime->diff($endTime);
        return ((int) $interval->format('%y') * 12) + (int) $interval->format('%m');
    }

    /**
     * 修改支付单状态
     *
     * @param Financial $financial
     * @param int $status
     */
    public static function changePaymentLogStatus(Financial $financial, $status) {
        static::updateFinancial($financial, [
            'status' => $status,
        ], 'setStatus');
    }

    /**
     * 更新支付单数据
     *
     * @param Financial $financial
     * @param array $data
     * @param string $scene 验证场景
     */
    public static function updateFinancial(Financial $financial, array $data, $scene = '') {
        if ($scene) {
            // TODO: 数据验证
        }
        $financial->save($data);
    }
}