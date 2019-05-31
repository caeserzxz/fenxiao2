<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-5-22
 * Time: 18:01
 */

namespace app\admin\controller;

use app\common\exception\NoticeException;
use app\common\model;
use app\common\model\AccountLog;
use app\common\model\Users;
use app\common\response\ApiResponse;
use PDO;
use think\AjaxPage;
use think\Config;
use think\Db;
use think\db\Query;
use think\Exception;
use think\Hook;
use think\Log;
use think\Page;

class Financial extends Base {

    public function _initialize() {
        parent::_initialize();

        // 不自动提交
        Config::set('database.params', [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET autocommit = 0',
        ]);
    }

    public static $timeTypeList = [
        'start_time'  => '理财开始时间',
        'cancel_time' => '取消理财时间',
        'end_time'    => '理财结束时间',
    ];

    /**
     * 进行中
     *
     * @return mixed
     */
    public function index() {
        $this->assign('timeTypeList', static::$timeTypeList);

        return $this->fetch();
    }

    /**
     * 等待退款列表
     *
     * @return mixed
     */
    public function waitingRefund() {
        $this->assign('timeTypeList', static::$timeTypeList);

        return $this->fetch();
    }

    /**
     * 已完成
     *
     * @return mixed
     */
    public function finished() {
        $this->assign('timeTypeList', static::$timeTypeList);

        return $this->fetch();
    }

    /**
     * 已结束
     *
     * @return mixed
     */
    public function canceled() {
        $this->assign('timeTypeList', static::$timeTypeList);

        return $this->fetch();
    }

    /**
     * 全部
     *
     * @return mixed
     */
    public function allList() {
        $this->assign('timeTypeList', static::$timeTypeList);

        return $this->fetch();
    }

    /**
     * 通过退款申请
     *
     * @return mixed
     */
    public function grantRefund() {
        try {
            $id = $this->request->get('id');

            $item = model\Financial::get($id, ['user']);

            if (!$item) {
                throw new Exception();
            }
            if ($item['amount_status'] !== model\Financial::AMOUNT_STATUS_REQUEST_REFUNDED) {
                throw new Exception();
            }

            /** @var Users $user */
            $user = $item['user'];

            Db::startTrans();

            // 退还本金到余额
            $user->save([
                'user_money' => ['exp', 'user_money + ' . $item['amount']],
            ]);

            // 记录资金变动
            AccountLog::create([
                'user_id'    => $user['user_id'],
                'user_money' => $item['amount'],
                'desc'       => '理财本金退还',
            ]);

            $item['amount_status'] = model\Financial::AMOUNT_STATUS_REFUNDED;
            $item['refund_time'] = time();

            $params = [
                'financial' => $item,
            ];
            Hook::listen('financialRefundAfter', $params);

            $item->save();

            Db::commit();

            $this->success('退款成功');

            return null;

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

    public function details() {
        try {
            $id = $this->request->get('id');

            $item = model\Financial::get($id, ['user']);
            $item->reformat();

            $this->assign('item', $item);

            return $this->fetch();

        } catch (Exception $e) {
            Log::error((string) $e);
            $this->error('操作失败');

            return null;
        }
    }

    public function getList() {
        try {
            $page = $this->request->get('p');

            $timeType = $this->request->get('time_type');
            $startTime = $this->request->get('start_time');
            $endTime = $this->request->get('end_time');
            $money = $this->request->get('money');
            //dump($money);die;
            $orderField = $this->request->get('order_field') ?: 'id';
            $orderDirection = $this->request->get('order_direction') ?: 'desc';

            $type = $this->request->param('t');
            $listName = $type;

            $where = [];
            $itemProcessor = null;

            switch ($type) {

                case 'waitingRefund':
                    $where = [
                        'amount_status' => model\Financial::AMOUNT_STATUS_REQUEST_REFUNDED,
                    ];
                    break;

                case 'finished':
                    $where = [
                        'status' => model\Financial::STATUS_FINISHED,
                    ];
                    break;

                case 'canceled':
                    $where = [
                        'status' => model\Financial::STATUS_CANCELED,
                    ];
                    break;

                case 'index':
                    $where = [
                        'status' => model\Financial::STATUS_STARTED,
                    ];
                    $listName = '';
                    break;

                default:

                    break;
            }

            // 时间范围筛选
            if (in_array($timeType, array_keys(static::$timeTypeList))) {
                $field = "unix_timestamp({$timeType})";

                $startTime and $startTime = strtotime($startTime);
                if ($startTime) {
                    $endTime and $endTime = strtotime($endTime) or $endTime = $startTime;
                    $endTime += 86400;
                    $where[$field] = ['between', [$startTime, $endTime]];
                }
                if($money)
                {
                    $where['financial.amount']= $money;
                }
            }

            //dump($where);die;
            
            $list = model\Financial::getList(function(Query $query) use ($where) {
                $query->alias('financial')
                    ->join('users', 'users.user_id = financial.user_id')
                    ->join('user_level', 'users.level = user_level.level_id')
                    ->where($where)
                    ->field([
                        'financial.*',

                        'users.nickname'        => 'user_nickname',
                        'user_level.level_name' => 'user_level_name',
                        'users.mobile'          => 'user_mobile',
                    ]);
            }, $page, [$orderField, $orderDirection]);
            
            foreach ($list as $item) {
                $item->reformat();
                is_callable($itemProcessor) and $itemProcessor($item);
            }

            $count = model\Financial::getCount($where);
            $Page = new AjaxPage($count, 20);

            $this->assign('list', $list);
            $this->assign('page', $Page->show());

            return $this->fetch('getlist' . ($listName ? '_' . $listName : ''));

        } catch (Exception $e) {
            Log::error((string) $e);
            $this->error('操作失败');

            return null;
        }
    }

    /**
     * 操作
     */
    public function doOperation() {

    }
}