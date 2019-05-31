<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-5-28
 * Time: 9:36
 */

namespace app\mobile\controller;

use app\common\behavior\FinancialBehavior;
use app\common\exception\ManagementDeniedException;
use app\common\exception\NoticeException;
use app\common\exception\TokenInvalidException;
use app\common\logic\DistributLogic;
use app\common\logic\OrderLogic;
use app\common\model;
use app\common\response\ApiResponse;
use app\common\validate\TokenValidate;
use DateTime;
use PDO;
use think\Config;
use think\Db;
use think\db\Query;
use think\Exception;
use think\exception\DbException;
use think\Log;

class Financial extends MobileBase {

    /**
     * @var model\Users
     */
    protected $user;

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
            $publicActionList = [];
            if (!in_array(ACTION_NAME, $publicActionList)) {
                $this->toLogin();
            }

        } else {
            session('user', $this->user->toArray());
        }
    }

    /**
     * @return mixed
     */
    public function index() {
        try {
            $this->assign('title', '理财投资');

            // 检查用户能否理财
            static::checkUserManagementPermit($this->user);

            // 理财统计
            $aggregate = model\Financial::aggregate([
                'user_id' => $this->user['user_id'],
            ], [
                'sum(amount)' => 'total_amount',
                'count(1)'    => 'count',
            ]);
            if (!$aggregate[0]['count']) {
                // 没有理财过，跳转到开始理财页
                $this->redirect('startManagement');
            }
            $this->user['total_financial_amount'] = number_format($aggregate[0]['total_amount'], 2);

            // 用户等级
            $this->user['level'] and $userLevel = model\UserLevel::get($this->user['level']);

            if ($userLevel) {
                $this->user['user_level'] = $userLevel;
                switch ($userLevel['level_id']) {

                    case model\UserLevel::LEVEL_MEMBER:
                        $userLevel['icon'] = '/images/icon_zs.png';
                        break;

                    case model\UserLevel::LEVEL_SENIOR_MEMBER:
                        $userLevel['icon'] = '/images/icon_setvip.png';
                        break;

                    default:
                        $userLevel['icon'] = '';
                }
            }

            // 用户身份
            $this->user['is_sales'] = $this->user->isSales();
            $this->user['is_share_holder'] = $this->user->isShareHolder();
            $this->assign('user', $this->user->toArray());

            return $this->fetch(__FUNCTION__);

        } catch (ManagementDeniedException $e) {

            $iconUrl = 'images/nodata1.png';
            $this->assign('icon_url', $iconUrl);
            $this->assign('text', $e->getMessage());

            return $this->fetch('cantManagement');

        } catch (NoticeException $e) {
            $this->error($e->getMessage());

            return null;

        } catch (Exception $e) {
            Log::error((string) $e);
            $this->error('操作失败');

            return null;
        }
    }

    /**
     * 开始理财
     *
     * @return mixed
     */
    public function startManagement() {
        $this->assign('title', '理财投资');
        $article = db('article')->where(['article_id'=>31])->find();
        $this->assign('article', $article);
        return $this->fetch(__FUNCTION__);
    }

    /**
     * @return ApiResponse
     */
    public function getList() {
        try {
            $page = $this->request->param('p');

            $where = [
                'user_id' => $this->user['user_id'],
            ];

            $list = model\Financial::getList($where, $page, 'create_time desc');

            $config = tpCache('financial');

            foreach ($list as $item) {
                $item->reformat();

                // 操作权限
                $permit = [
                    'pay'    => false,// 支付本金
                    'cancel' => false,// 取消理财单
                ];

                if ($item['status'] === model\Financial::STATUS_STARTED &&
                    $item['amount_status'] === model\Financial::AMOUNT_STATUS_PAID) {
                    // 理财中 而且 已支付本金
                    $permit['cancel'] = true;

                } else if ($item['amount_status'] === model\Financial::AMOUNT_STATUS_UNPAID) {
                    // 未在理财中 而且 未支付本金
                    // 获取支付单
                    $extraPayReason = model\PaymentLog::EXTRA_PAY_REASON;
                    $extraFinancialId = model\PaymentLog::EXTRA_FINANCIAL_ID;
                    $paymentLog = model\PaymentLog::get([
                        "json_unquote(extra->'$.${extraPayReason}')"   => model\PaymentLog::PAY_FOR_FINANCIAL,
                        "json_unquote(extra->'$.${extraFinancialId}')" => $item['id'],
                    ]);
                    if ($paymentLog) {
                        $permit['pay'] = true;
                        $item['payment_log_id'] = $paymentLog['id'];
                    }
                }
                $item['permit'] = $permit;

                if ($item['regular_month'] >= 12) {
                    // 一年以上
                    $item['interest_rate'] = $config['year_interest_rate'];
                } else {
                    $item['interest_rate'] = $config['month_interest_rate'];
                }

                $item['status_text'] = model\Financial::$statusList[$item['status']];

                switch ($item['status']) {

                    case model\Financial::STATUS_STARTED:
                        $item['status_class'] = 'text-orange';
                        $item['status_icon'] = '/images/icon_time1.png';
                        break;

                    default:
                        $item['status_class'] = 'text-gray';
                        $item['status_icon'] = '/images/icon_time2.png';
                        break;
                }

                switch ($item['amount_status']) {

                    case model\Financial::AMOUNT_STATUS_UNPAID:
                        $item['amount_status_text'] = '本金未付';
                        $item['amount_status_class'] = 'text-orange';
                        break;

                    case model\Financial::AMOUNT_STATUS_REQUEST_REFUNDED:
                        $item['amount_status_text'] = '本金待返';
                        $item['amount_status_class'] = 'text-blue';
                        break;

                    case model\Financial::AMOUNT_STATUS_REFUNDED:
                        $item['amount_status_text'] = '本金已返';
                        $item['amount_status_class'] = 'text-gray';
                        break;

                    default:
                        $item['amount_status_text'] = '';
                        $item['amount_status_class'] = '';
                        break;
                }

                $item['amount_text'] = number_format($item['amount'], 2);
                $dateFormat = 'y/m/d';
                $item['start_time'] and $item['start_date_text'] = date($dateFormat, strtotime($item['start_time']));
                $item['expected_end_time'] and $item['expected_end_date_text'] = date($dateFormat, strtotime($item['expected_end_time']));
            }

            return new ApiResponse($list);

        } catch (NoticeException $e) {
            return new ApiResponse($e->getMessage(), 'error');

        } catch (Exception $e) {
            Log::error((string) $e);

            return new ApiResponse('操作失败', 'error');
        }
    }

    /**
     * 投资理财
     *
     * @return ApiResponse
     */
    public function doManagement() {
        try {
            if (!TokenValidate::checkToken($this->request->post())) {
                throw new TokenInvalidException('页面已过期，请刷新页面');
            }

            // 检查用户能否理财
            static::checkUserManagementPermit($this->user);

            $regularType = $this->request->post('regular_type');
            $amount = (float) $this->request->post('amount');

            // 处理理财周期
            if ($regularType === 'year') {
                // 固定一年
                $regularMonth = 12;

            } else if ($regularType === 'month') {
                $regularMonth = (int) $this->request->post('regular_month');

            } else {
                throw new Exception();
            }

            Db::startTrans();

            // 生成理财单
            $financial = model\Financial::create([
                'user_id'       => $this->user['user_id'],
                'status'        => model\Financial::STATUS_NOT_STARTED,
                'amount_status' => model\Financial::AMOUNT_STATUS_UNPAID,
                'amount'        => $amount,
                'regular_month' => $regularMonth,
            ]);

            $order_sn = (new OrderLogic)->get_order_sn();

            // 生成支付单
            $paymentLog = model\PaymentLog::create([
                'user_id'  => $this->user['user_id'],
                'status'   => model\PaymentLog::STATUS_UNPAID,
                'order_sn' => $order_sn,
                'amount'   => $amount,
                'extra'    => [
                    model\PaymentLog::EXTRA_PAY_REASON   => model\PaymentLog::PAY_FOR_FINANCIAL,
                    model\PaymentLog::EXTRA_FINANCIAL_ID => $financial['id'],
                ],
            ]);

            Db::commit();

            $this->request->token();

            return new ApiResponse(['pid' => $paymentLog['id']]);

        } catch (TokenInvalidException $e) {
            return new ApiResponse($e->getMessage(), 'error');

        } catch (ManagementDeniedException $e) {
            $this->request->token();

            $iconUrl = 'images/nodata1.png';
            $this->assign('icon_url', $iconUrl);
            $this->assign('text', $e->getMessage());

            return $this->fetch('cantManagement');

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);
            $this->request->token();

            return new ApiResponse('操作失败', 'error');
        }
    }

    /**
     * 计算利息
     *
     * @return ApiResponse
     */
    public function getSettleInterestInfo() {
        try {
            $id = (int) $this->request->param('id');

            $id and $financial = model\Financial::get([
                'id'      => $id,
                'user_id' => $this->user['user_id'],
            ]);
            if (!$financial) {
                throw new Exception();
            }

            $startTime = new DateTime($financial['start_time']);
            $endTime = new DateTime();

            $config = tpCache('financial');
            $config['shopping.point_rate'] = tpCache('shopping.point_rate');

            // 月份数
            $intervalMonth = FinancialBehavior::getIntervalMonth($startTime, $endTime);

            $info = FinancialBehavior::getSettleInterestInfo($financial['amount'], $intervalMonth, $config);

            $info = [
                'id'             => $financial['id'],
                'interval_month' => $info['interval_month'],
                'interest_rate'  => $info['interest_rate'],
                'money'          => $info['money'],
                'money_text'     => number_format($info['money'], 2),
                'point'          => $info['point'],
            ];

            return new ApiResponse($info);

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);

            return new ApiResponse('操作失败', 'error');
        }
    }

    /**
     * 取消理财
     *
     * @return ApiResponse
     */
    public function doCancel() {
        try {
            $id = (int) $this->request->param('id');

            $id and $financial = model\Financial::get([
                'id'      => $id,
                'user_id' => $this->user['user_id'],
            ]);
            if (!$financial) {
                throw new Exception();
            }

            if ($financial['status'] !== model\Financial::STATUS_STARTED) {
                throw new Exception();
            }

            $financial['user'] = $this->user;

            Db::startTrans();

            // 取消理财
            $financial->cancelManaging();
            $financial->save();

            Db::commit();

            return new ApiResponse();

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);

            return new ApiResponse('操作失败', 'error');
        }
    }

    /**
     * 检查用户是否能理财
     *
     * @param model\Users $user
     * @throws ManagementDeniedException
     * @return void
     * @throws DbException
     */
    public static function checkUserManagementPermit(model\Users $user) {

        if (!$user->isMember()) {
            // 非会员不能理财

            // 查询退押申请数量
            $aggregate = model\ApplyDeposit::aggregate([
                'user_id' => $user['user_id'],
                'status'  => model\ApplyDeposit::STATUS_REQUESTED_REFUNDED,
            ]);

            if ($aggregate[0]['count']) {
                // 退押金中，改变提示
                throw new ManagementDeniedException('亲，您有一笔押金正在申请退押中，不能进行理财投资哦~');
            }
            throw new ManagementDeniedException('亲，您还没参与送水活动，暂不能进行理财投资哦~');
        }
    }
}