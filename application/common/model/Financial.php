<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-5-23
 * Time: 15:14
 */

namespace app\common\model;

use app\common\behavior\FinancialBehavior;
use DateTime;
use think\Hook;
use think\Log;
use traits\model\SoftDelete;

class Financial extends CommonModel {

    use SoftDelete;

    // 理财状态
    const STATUS_NOT_STARTED = 1;
    const STATUS_STARTED = 2;
    const STATUS_CANCELED = 3;
    const STATUS_FINISHED = 4;

    public static $statusList = [
        self::STATUS_NOT_STARTED => '未开始',
        self::STATUS_STARTED     => '理财中',
        self::STATUS_CANCELED    => '已取消',
        self::STATUS_FINISHED    => '已完成',
    ];

    // 本金状态
    const AMOUNT_STATUS_UNPAID = 1;
    const AMOUNT_STATUS_PAID = 2;
    const AMOUNT_STATUS_REQUEST_REFUNDED = 3;
    const AMOUNT_STATUS_REFUNDED = 4;

    public static $amountStatusList = [
        self::AMOUNT_STATUS_UNPAID           => '未支付',
        self::AMOUNT_STATUS_PAID             => '已支付',
        self::AMOUNT_STATUS_REQUEST_REFUNDED => '申请返还',
        self::AMOUNT_STATUS_REFUNDED         => '已返还',
    ];

    public function user() {
        return $this->hasOne('Users', 'user_id', 'user_id');
    }

    protected $type = [
        'start_time'        => 'datetime',
        'expected_end_time' => 'datetime',
        'end_time'          => 'datetime',
        'cancel_time'       => 'datetime',
        'refund_time'       => 'datetime',

        'create_time' => 'datetime',
        'delete_time' => 'datetime',
    ];

    /**
     * 生成格式化输出文本
     *
     * @param string $dateFormat
     * @return $this
     */
    public function reformat($dateFormat = 'Y-m-d') {
        $this['regular_year'] = floor($this['regular_month'] / 12);
        $this['regular_text'] = $this['regular_year'] ? $this['regular_year'] . '年' : '';
        $this['regular_text'] .= $this['regular_month'] % 12 ? $this['regular_month'] % 12 . '月' : '';

        $this['status_text'] = $this['status'] ? static::$statusList[$this['status']] : '';
        $this['amount_status_text'] = $this['amount_status'] ? static::$amountStatusList[$this['amount_status']] : '';

        $this['start_time_text'] = $this['start_time'] ? date($dateFormat, strtotime($this['start_time'])) : '';
        $this['expected_end_time_text'] = $this['expected_end_time'] ? date($dateFormat, strtotime($this['expected_end_time'])) : '';
        $this['end_time_text'] = $this['end_time'] ? date($dateFormat, strtotime($this['end_time'])) : '';
        $this['cancel_time_text'] = $this['cancel_time'] ? date($dateFormat, strtotime($this['cancel_time'])) : '';
        $this['refund_time_text'] = $this['refund_time'] ? date($dateFormat, strtotime($this['refund_time'])) : '';

        return $this;
    }

    /**
     * 获取完成时的月份数
     *
     * @return int
     */
    public function getFinishMonth() {
        $startTime = new DateTime($this['start_time']);
        $endTime = new DateTime($this['end_time']);

        // 计算时间差距
        return FinancialBehavior::getIntervalMonth($startTime, $endTime);
    }

    /**
     * 获取取消时的月份数
     *
     * @return int
     */
    public function getCancelMonth() {
        $startTime = new DateTime($this['start_time']);
        $endTime = new DateTime($this['cancel_time']);

        // 计算时间差距
        return FinancialBehavior::getIntervalMonth($startTime, $endTime);
    }

    /**
     * 开始理财
     * 更新状态，计算时间
     *
     * @return Financial
     */
    public function startManaging() {
        $regularMonth = $this['regular_month'];
        $startTime = time();
        $expectedEndTime = strtotime("+${regularMonth} month");

        $this['status'] = static::STATUS_STARTED;
        $this['start_time'] = $startTime;
        $this['expected_end_time'] = $expectedEndTime;

        $params = [
            'financial' => $this,
        ];
        Hook::listen('financialStartAfter', $params);

        return $this;
    }

    /**
     * 取消理财
     *
     * @return Financial
     * @throws \think\exception\DbException
     */
    public function cancelManaging() {
        $this['status'] = static::STATUS_CANCELED;
        $this['cancel_time'] = time();

        if ($this['user'] && $this['user'] instanceof Users) {
            $user = Users::get($this['user_id']);
        }

        $params = [
            'financial' => $this,
            'user'      => $user,
        ];
        Hook::listen('financialEndAfter', $params);

        return $this;
    }

    /**
     * 结束理财
     *
     * @return Financial
     * @throws \think\exception\DbException
     */
    public function endManaging() {
        $this['status'] = static::STATUS_FINISHED;
        $this['end_time'] = time();

        if ($this['user'] && $this['user'] instanceof Users) {
            $user = Users::get($this['user_id']);
            // FIXME: 通过关系获取模型
        }

        $params = [
            'financial' => $this,
            'user'      => $user,
        ];
        Hook::listen('financialEndAfter', $params);

        return $this;
    }

    /**
     * 退还本金
     * 仅申请
     */
    public function refund() {
        $this['amount_status'] = static::AMOUNT_STATUS_REQUEST_REFUNDED;

        return $this;
    }

    /**
     * 复制理财单
     * 如已支付 不用重复支付
     *
     * @param array $data
     * @return Financial
     */
    public function copy($data = []) {
        $originData = $this->toArray();

        // 重置数据
        $resetData = [
            'status'     => static::STATUS_NOT_STARTED,
            'user_money' => 0,
            'pay_points' => 0,
        ];
        $data = array_merge($originData, $resetData, $data);

        // 筛选字段
        $allowFieldList = [
            'user_id',
            'status',
            'amount_status',
            'amount',
            'regular_month',
            'user_money',
            'pay_points',
        ];
        $data = array_filter($data, function($value, $index) use ($allowFieldList) {
            return $value !== null && in_array($index, $allowFieldList);
        }, ARRAY_FILTER_USE_BOTH);

        return static::create($data);
    }
}