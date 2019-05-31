<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-7
 * Time: 10:34
 */

namespace app\common\command;

use app\common\behavior\UserBehavior;
use app\common\logic\UsersUpLevel;
use PDO;
use think\Config;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Hook;
use think\Log;
use app\common\logic\RewardLogic;

/**
 * 自动收货
 *
 * @package app\common\command
 */
class GetOrder extends CommonCommand {

    /**
     * 配置指令
     *
     * @return void
     */
    protected function configure() {
        $this->setName('get_order')->setDescription('自动收货');

        // 不自动提交
        Config::set('database.params', [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET autocommit = 0',
        ]);
    }

    /**
     * 执行指令
     *
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output) {
        try {
            // 不自动提交
            Config::set('database.params', [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET autocommit = 0',
            ]);

            Db::startTrans();
            // 发货后满多少天自动收货确认
            $auto_confirm_date = tpCache('shopping.auto_confirm_date');
            $auto_confirm_date = $auto_confirm_date * (60 * 60 * 24); // 7天的时间
            $time = time() - $auto_confirm_date; // 比如7天以前的可用自动确认收货
            $order_id_arr = M('order')->where("order_status = 1 and shipping_status = 1 and shipping_time < $time")->getField('order_id', true);
            foreach ($order_id_arr as $k => $v) {
                confirm_order($v);
                //确认收货成功，才开始计算分佣
                //订单信息
                $orderInfo = M('order')->where('order_id', $v)->find();
                    //将分佣更新到余额
                    $levelLogic = new UsersUpLevel();
                    $levelLogic->receivingGoods( $orderInfo['order_id'] );

            }

            Db::commit();

            $timeString = date(DATE_ATOM);
            $output->writeln("<info>自动收货完成 时间: ${timeString}</info>");

            return 0;

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);
            $output->writeln($this->getName() . ': ' . $e->getMessage());

            return 1;
        }
    }
}