<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-4
 * Time: 10:42
 */

namespace app\common\behavior;

use app\common;
use app\common\exception\PaymentCheckFailedException;
use app\common\model\AccountLog;
use app\common\model\Financial;
use app\common\model\Order;
use app\common\model\PaymentLog;
use app\common\logic\OrderLogic;
use app\common\model\Users;
use app\common\model\UserLevel;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Db;

class PaymentBehavior extends CommonBehavior {

    /**
     * 支付前
     *
     * @param array $params
     */
    public function payBefore(array $params) {
    }

    /**
     * 支付结果处理
     *
     * @param array $params
     * @throws PaymentCheckFailedException
     */
    public function payResultProcess(array $params) {
        // 增加已支付金额（单位元
        static::addPaymentLogPaidAmount($params['paymentLog'], $params['paidAmount']);

        // 检查已支付金额
        // static::checkPaidAmount($params['paymentLog']);
    }

    /**
     * 支付完成后
     *
     * @param array $params
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function payAfter(array $params) {
        // 修改支付单状态
        static::changePaymentLogStatus($params['paymentLog'], PaymentLog::STATUS_PAID);

        // 支付完成处理
        static::paySuccessProcess($params['paymentLog']);
    }

    /**
     * 商城订单order 支付完成后
     *
     * @param array $params
     * @throws DbException
     */
    public function oldPayAfter(array $params) {

        $params['order']['order_id'] and $params['order'] = Order::get($params['order']['order_id']);
        $params['order']['user_id'] and $params['orderUser'] = Users::get($params['order']['user_id']);
        // FIXME: 订单和下单用户应由参数传入

        // 标识用户已消费情况
        static::markUserPurchased($params['orderUser']);
        // FIXME: 可能订单完成后才算已消费过

        // 商品有分享人时 保存邀请人到下单用户
        //1、根据订单id查询order_goods表是否有分享人的id
        //2、有分享人id，根据user_id查看user表 看他是否有上级id
        //3、没有上级id绑定分享人id，
        //4、有上级判断是否消费过
        //5、没有消费过更改上级id 
  /*      $share_user_id=db('order_goods')->where('order_id',$params['order']['order_id'])->value('share_user_id');
        if($share_user_id)
        {
            $user_data=db('users')->field('first_leader,is_purchased')->where('user_id',$params['order']['user_id'])->find();

            //没有上级id，绑定上级id
            if(empty($user_data['first_leader']))
            {
                $update=db('users')->where('user_id',$params['order']['user_id'])->update(['first_leader'=>$share_user_id]);
            }

            //有上级id，并且没有消费过
            if($user_data['first_leader'] && $user_data['is_purchased']=='0')
            {
                $update=db('users')->where('user_id',$params['order']['user_id'])->update(['first_leader'=>$share_user_id]);
            }
        }*/

        // 商城购物分销
        static::distribute($params['order'], $params['orderUser']);

        $params['orderUser']->save();

        //梅克伦支付成功逻辑
        $orderLogin=new OrderLogic();
        $orderLoginReturn=$orderLogin->callBackDealOrder($params['order']['order_id']);
        //END梅克伦支付成功逻辑

    }

    public static function addPaymentLogPaidAmount(PaymentLog $paymentLog, $paidAmount) {
        static::updatePaymentLog($paymentLog, [
            'pay_amount' => ['exp', 'pay_amount + ' . $paidAmount],
        ]);
    }

    /**
     * 检查已支付金额
     *
     * @param PaymentLog $paymentLog
     * @throws PaymentCheckFailedException
     */
    public static function checkPaidAmount(PaymentLog $paymentLog) {
        $totalAmount = $paymentLog['amount'];
        $paidAmount = $paymentLog['pay_amount'];

        if ($paidAmount < $totalAmount) {
            // 支付金额不足
            throw new PaymentCheckFailedException();
        }
    }

    /**
     * 修改支付单状态
     *
     * @param PaymentLog $paymentLog
     * @param int $status
     */
    public static function changePaymentLogStatus(PaymentLog $paymentLog, $status) {
        static::updatePaymentLog($paymentLog, [
            'status' => $status,
        ], 'setStatus');
    }

    /**
     * 支付完成处理
     *
     * @param PaymentLog $paymentLog
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public static function paySuccessProcess(PaymentLog $paymentLog) {
        // 额外数据
        $extraData = $paymentLog['extra'];

        switch ($extraData[PaymentLog::EXTRA_PAY_REASON]) {

            case PaymentLog::PAY_FOR_FINANCIAL:
                // 投资理财支付单

                $financialId = $extraData[PaymentLog::EXTRA_FINANCIAL_ID];
                $financialId and $financial = Financial::get($financialId);
                if (!$financial) {
                    Log::alert('未找到理财单' . PHP_EOL . json_encode($paymentLog, JSON_PRETTY_PRINT));
                    break;
                }

                // 修改本金状态
                $financial['amount_status'] = Financial::AMOUNT_STATUS_PAID;

                if ($financial['status'] === Financial::STATUS_NOT_STARTED) {
                    // 开始理财
                    $financial->startManaging();
                }
                $financial->save();
                break;

            case PaymentLog::PAY_FOR_DEPOSIT:
                // 押金支付单

                $userId = $extraData[PaymentLog::EXTRA_USER_ID];
                $levelId = $extraData[PaymentLog::EXTRA_LEVEL_ID];

                $levelId and $level = UserLevel::get($levelId);
                if (!$level) {
                    Log::alert('未找到用户等级' . PHP_EOL . json_encode($paymentLog, JSON_PRETTY_PRINT));
                    break;
                }
                $deposit = $level['amount'];

                //修改用户等级和押金
                $user = Users::get($userId);
                if (!$user) {
                    Log::alert('未找到用户' . PHP_EOL . json_encode($paymentLog, JSON_PRETTY_PRINT));
                    break;
                }
                $user->save([
                    'level'   => $levelId,
                    'deposit' => $deposit,
                    'is_purchased' => 1,
                ]);
                break;
        }
    }

    /**
     * 商城购物分销
     * 只奖励一层上级，奖励到余额
     * 上级用户的等级和身份决定奖励金额
     *
     * @param Order $order
     * @param Users $user
     * @throws DbException
     */
    public static function distribute(Order $order, Users $user) {

        $config = tpCache('distribut');

        if (!$config['switch']) {
            // 分销关闭
            return;
        }

        $user['first_leader'] and $leader = Users::get($user['first_leader']);

        if (!$leader) {
            // 没有邀请人
            return;
        }

        // 查找最大的分销比率
        $userLevel = UserLevel::get($leader['level'], [], true);
        $distributeRate = (float) $userLevel['distribution'];

        if ($leader->isShareHolder()) {
            // 股东身份
            $distributeRate = max($distributeRate, (float) $config['shareholder_rate']);
        }
        if ($leader->isShareHolder()) {
            // 业务员身份
            $distributeRate = max($distributeRate, (float) $config['sales_rate']);
        }

        if (!$distributeRate) {
            Log::alert([__METHOD__ => '没有分销比率']);
            return;
        }

        $amount = floor($order['order_amount'] * $distributeRate) / 100;

        $leader->save([
            'user_money' => ['exp', 'user_money + ' . $amount],
        ]);

        AccountLog::create([
            'user_id'    => $leader['user_id'],
            'user_money' => $amount,
            'desc'       => '团队商城购物分销',
        ]);
    }

    /**
     * 标识用户已消费情况
     *
     * @param Users $user
     * @param bool $status
     */
    public static function markUserPurchased(Users $user, $status = true) {
        if ($user->isPurchased() != $status) {
            $user['is_purchased'] = $status ? 1 : 0;
        }
    }

    /**
     * 更新支付单数据
     *
     * @param PaymentLog $paymentLog
     * @param array $data
     * @param string $scene 验证场景
     */
    public static function updatePaymentLog(PaymentLog $paymentLog, array $data, $scene = '') {
        if ($scene) {
            // TODO: 数据验证
        }
        $paymentLog->save($data);
    }
}