<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-5-31
 * Time: 18:05
 */

namespace app\mobile\controller;

use app\common\exception\NoticeException;
use app\common\exception\PayFailedException;
use app\common\exception\PaymentNotFoundException;
use app\common\logic\DistributLogic;
use app\common\logic\OrderLogic;
use app\common\model;
use app\common\model\Plugin;
use app\common\response\ApiResponse;
use PDO;
use think\Config;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\Hook;
use think\Log;

class PaymentNew extends MobileBase {

    /**
     * @var model\Users
     */
    protected $user;

    /**
     * @var model\PaymentLog
     */
    protected $paymentLog;

    /**
     * 支付插件，支付回调时有效
     *
     * @var object
     */
    protected $paymentPlugin;

    /**
     * 支付类，支付回调时有效
     *
     * @var object
     */
    protected $paymentApi;

    /**
     * @var array 允许的支付方式(plugin.code) 必须已有可用插件 并且 兼容支付单(payment_log)
     */
    protected $allowPaymentTypeList = [
        'weixin',// 微信支付
        Plugin::PAYMENT_CODE_MONEY_PAY,// 余额支付
    ];

    public function _initialize() {
        parent::_initialize();

        // 不自动提交
        Config::set('database.params', [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET autocommit = 0',
        ]);

        $user = session('user');
        try {
            $user['user_id'] and $this->user = model\Users::get($user['user_id']);
        } catch (DbException $e) {
        }

        if (!$this->user) {
            // 不用登录的方法
            $publicActionList = [
                'notifyUrl',
                'returnUrl',
            ];
            if (!in_array(ACTION_NAME, $publicActionList)) {
                $this->toLogin();
            }

        } else {
            session('user', $this->user->toArray());
        }

        $paymentLogId = (int) $this->request->param('pid');

        if (!$paymentLogId) {
            try {
                // 可能是支付平台返回，通过支付类获取支付单

                $paymentCode = $this->request->param('pay_code');
                if (!$paymentCode) {
                    throw new PaymentNotFoundException('缺少$paymentCode');
                }

                // 获取支付插件
                $paymentPlugin = static::getPaymentPlugin($paymentCode);
                if (!$paymentPlugin) {
                    throw new PaymentNotFoundException('找不到支付插件: ' . $paymentCode);
                }

                // 获取支付api
                $paymentApi = static::getPaymentApi($paymentCode, $paymentPlugin['config_value']);
                if (!$paymentApi) {
                    throw new PaymentNotFoundException('找不到支付api: ' . $paymentCode);
                }

                $this->paymentApi = $paymentApi;

            } catch (PaymentNotFoundException $e) {
                Log::alert($e->getMessage());

                $this->error('操作失败');

            } catch (Exception $e) {
                Log::error((string) $e);

                $this->error('操作失败');
            }

        } else {
            // 获取支付单
            try {
                $paymentLog = model\PaymentLog::get([
                    'id'      => $paymentLogId,
                    'user_id' => $this->user['user_id'],
                ]);
            } catch (DbException $e) {
            }
            if (!$paymentLog) {
                $this->error('操作失败');
            }
            $this->paymentLog = $paymentLog;
        }
    }

    public function choosePaymentType() {
        try {
            $this->assign('title', '选择支付方式');

            $paymentPluginList = model\Plugin::all([
                'type'   => 'payment',
                'status' => 1,
                'scene'  => ['in', [0, 1]],
                'code'   => ['in', $this->allowPaymentTypeList],
            ]);

            $list = [];
            foreach ($paymentPluginList as $item) {
                $item->hidden([
                    'author',
                    'version',
                    'config',
                    'config_value',
                    'status',
                    'type',
                    'scene',
                ]);
                $list[] = $item->toArray();
            }

            $this->assign('pid', $this->paymentLog['id']);
            $this->assign('amount', $this->paymentLog['amount']);
            $this->assign('payment_type_list_json', json_encode($list));

            return $this->fetch(__FUNCTION__);

        } catch (NoticeException $e) {
            Db::rollback();
            $this->error($e->getMessage());

            return null;

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);
            $this->error('操作失败');

            return null;
        }
    }

    public function pay() {
        try {
            $this->assign('title', '支付');

            if ($this->paymentLog['status'] === model\PaymentLog::STATUS_PAID) {
                // 已完成支付
                $this->redirect('payResult', ['pid' => $this->paymentLog['id']]);
            }

            // 保存支付方式
            $paymentCode = $this->request->param('payment_code');
            $paymentCode and $this->paymentLog['payment_code'] = $paymentCode;

            try {
                if (!$this->paymentLog['payment_code']) {
                    // 未选择支付方式
                    throw new PaymentNotFoundException();
                }
                if (!in_array($this->paymentLog['payment_code'], $this->allowPaymentTypeList)) {
                    // 支付方式无效
                    throw new PaymentNotFoundException();
                }

                // 获取支付方式
                $paymentPlugin = static::getPaymentPlugin($this->paymentLog['payment_code']);

                $options = $paymentPlugin['config_value'];

                // 获取支付api (e.g. \payment\alipay\alipay
                $paymentApi = static::getPaymentApi($this->paymentLog['payment_code'], $options);

            } catch (PaymentNotFoundException $e) {
                // 未找到支付方式，跳转到选择支付方式
                if ($message = $e->getMessage()) {
                    $this->error($message, U('choosePaymentType', ['pid' => $this->paymentLog['id']]));
                }
                $this->redirect(U('choosePaymentType', ['pid' => $this->paymentLog['id']]));
            }

            // 生成交易单号
            $tradeNo = (new OrderLogic)->get_order_sn();

            $options['trade_no'] = $tradeNo;
            $options['body'] = $this->tpshop_config['shop_info_store_name'] . '-充值';
            $options['amount'] = $this->paymentLog['amount'];
            $options['open_id'] = $this->user['openid'];
            $options['notify_url'] = U('notifyUrl', ['pay_code' => $paymentPlugin['code']], true, true);
            $options['return_url'] = U('returnUrl', ['pay_code' => $paymentPlugin['code']], true, true);

            Db::startTrans();

            $this->paymentLog['trade_no'] = $tradeNo;
            $this->paymentLog->save();

            $params = [
                'paymentLog'    => $this->paymentLog,
                'paymentPlugin' => $paymentPlugin,
                'paymentApi'    => $paymentApi,
                'options'       => $options,
            ];
            Hook::listen('payBefore', $params);

            Db::commit();

            if ($paymentPlugin['code'] === 'weixin' && $this->isWXBrowser()) {
                // 微信JS支付

                // 临时
                $options['amount'] = 0.01;

                $options['go_url'] = U('payResult', ['pid' => $this->paymentLog['id']]);
                $options['back_url'] = U('choosePaymentType', ['pid' => $this->paymentLog['id']]);

                $html = $paymentApi->getJSAPI($options);

                return $html;

            } else if ($paymentPlugin['code'] === 'miniAppPay' && $this->isWXBrowser()) {
                $html = $paymentApi->getJSAPI($options);

                return $html;

            } else if ($paymentPlugin['code'] === Plugin::PAYMENT_CODE_MONEY_PAY) {
                // 余额支付
                try {
                    $paymentApi->pay($options);

                } catch (PayFailedException $e) {
                    Db::rollback();

                    $this->error($e->getMessage() ?: '支付失败，请更换支付方式', U('choosePaymentType', ['pid' => $this->paymentLog['id']]));
                }

                $this->redirect('payResult', ['pid' => $this->paymentLog['id']]);

            } else {
                $code_html = $paymentApi->get_code($options);
            }
            $this->assign('code_html', $code_html);
            $this->assign('pay_result_url', U('payResult', ['pid' => $this->paymentLog['id']]));

            return $this->fetch(__FUNCTION__);

        } catch (NoticeException $e) {
            Db::rollback();
            $this->error($e->getMessage());

            return null;

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);
            $this->error('操作失败');

            return null;
        }
    }

    /**
     * @param string $paymentCode
     * @return model\Plugin
     * @throws DbException
     * @throws PaymentNotFoundException
     */
    public static function getPaymentPlugin($paymentCode) {
        if (!$paymentCode) {
            // 没有选择支付方式
            throw new PaymentNotFoundException();
        }
        $paymentPlugin = model\Plugin::get([
            'type'   => 'payment',
            'status' => 1,
            'scene'  => ['in', [0, 1]],
            'code'   => $paymentCode,
        ]);
        if (!$paymentPlugin) {
            throw new PaymentNotFoundException('此支付方式已失效');
        }

        return $paymentPlugin;
    }

    /**
     * 获取支付api
     *
     * @param string $paymentCode
     * @param array $options
     * @return object
     * @throws PaymentNotFoundException
     */
    public static function getPaymentApi($paymentCode, $options = []) {
        $paymentApiClass = static::getPaymentApiClass($paymentCode);

        // 必须要有api类
        if (!$paymentApiClass) {
            throw new PaymentNotFoundException('此支付方式已失效');
        }

        return new $paymentApiClass($options);
    }

    /**
     * 获取支付api类名
     *
     * @param string $paymentCode
     * @return null|string
     */
    public static function getPaymentApiClass($paymentCode) {
        $paymentApiClass = '\\payment\\' . $paymentCode . '\\' . $paymentCode;

        if (!class_exists($paymentApiClass)) {
            return null;
        }

        return $paymentApiClass;
    }

    /**
     * 异步通知
     */
    public function notifyUrl() {
        try {
            $paymentApi = $this->paymentApi;
            if (!$paymentApi) {
                throw new Exception('缺少支付api');
            }
            $result = $paymentApi::response();
            exit($result);

        } catch (Exception $e) {
            Log::error((string) $e);

            $this->error('支付失败');
        }
    }

    /**
     * 同步跳转
     */
    public function returnUrl() {
        try {
            $paymentApi = $this->paymentApi;
            if (!$paymentApi) {
                throw new Exception('缺少支付api');
            }
            $result = $paymentApi::response2();

            if (!$result['payment_log']['id']) {
                throw new Exception('缺少支付单id');
            }
            $this->redirect('payResult', ['pid' => $result['payment_log']['id']]);

        } catch (Exception $e) {
            Log::error((string) $e);

            $this->error('支付失败');
        }
    }

    public function payResult() {
        try {
            // 支付原因决定跳转页面
            switch ($this->paymentLog['extra']['pay_reason']) {

                case model\PaymentLog::PAY_FOR_FINANCIAL:
                    $redirectUrl = U('financial/index');
                    break;

                case model\PaymentLog::PAY_FOR_DEPOSIT:
                    $redirectUrl = U('exchange/index');
                    break;

                default:
                    $redirectUrl = U('user/index');
                    break;
            }

            $paymentPlugin = static::getPaymentPlugin($this->paymentLog['payment_code']);
            if (!$paymentPlugin) {
                throw new PaymentNotFoundException();
            }

            $paymentApi = static::getPaymentApi($this->paymentLog['payment_code'], $paymentPlugin['config_value']);
            if (!$paymentApi) {
                throw new PaymentNotFoundException();
            }

            $options = [
                'paymentLog' => $this->paymentLog,
            ];
            $result = $paymentApi->getPayResult($options);
            if (!$result) {
                throw new Exception();
            }

            $this->success('订单已支付成功', $redirectUrl);

        } catch (PaymentNotFoundException $e) {
            // 未找到支付方式，仅验证支付单的状态
            $result = $this->paymentLog['status'] === model\PaymentLog::STATUS_PAID;

            if ($result) {
                $this->success('订单已支付成功', $redirectUrl);
            } else {
                $this->error('订单支付失败', $redirectUrl);
            }

        } catch (Exception $e) {
            Log::error((string) $e);

            $this->error('订单支付失败', $redirectUrl);
        }
    }

    /**
     * 获取支付记录
     *
     * @return ApiResponse
     */
    public function getPaymentLog() {
        try {

            return new ApiResponse($this->paymentLog);

        } catch (Exception $e) {

            if ($e instanceof NoticeException) {
                $message = $e->getMessage();
            } else {
                Log::error((string) $e);
                $message = '操作失败';
            }

            return new ApiResponse($message, 'error');
        }
    }
}