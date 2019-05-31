<?php

namespace payment\weixin;

use app\common\exception\AlreadyPaidException;
use app\common\exception\PaymentCheckFailedException;
use app\common\model\PaymentLog;
use think\Db;
use think\Exception;
use think\Hook;
use think\Log;
use WxPayApi;
use WxPayNotify;
use WxPayOrderQuery;

require_once("lib/WxPay.Api.php");
require_once("lib/WxPay.Notify.php");

// ini_set('date.timezone', 'Asia/Shanghai');

class PayNotifyCallBack extends WxPayNotify {

    /**
     * 处理通知
     *
     * @param array $data
     * @param string $msg
     * @return bool 是否已处理完成
     */
    public function NotifyProcess($data, &$msg) {
        try {

            if (!$data || $data["return_code"] !== "SUCCESS") {
                throw new Exception('支付通知失败');
            }
            if (!$data['transaction_id']) {
                throw new Exception('缺少事务id');
            }

            // 查询支付单
            $data['out_trade_no'] and $paymentLog = PaymentLog::get(['trade_no' => $data['out_trade_no']]);

            if (!$paymentLog) {
                throw new Exception('支付单不存在');
            }
            if ($paymentLog['status'] === PaymentLog::STATUS_PAID) {
                // 可能是因为回复平台通知的失败，导致平台重复通知
                throw new AlreadyPaidException('支付单已支付');
            }

            // 附加数据
            // $attach = $data['attach'];

            // 已付金额
            $paidAmount = floor($data['total_fee']) / 100;

            Db::startTrans();

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

            Db::commit();

            return true;

        } catch (AlreadyPaidException $e) {
            // 已支付
            Log::alert($e->getMessage());

            return true;

        } catch (PaymentCheckFailedException $e) {
            // 支付验证失败
            Log::error((string) $e);

            return true;

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);

            return false;
        }
    }
}
