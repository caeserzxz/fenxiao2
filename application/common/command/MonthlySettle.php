<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-7
 * Time: 10:34
 */

namespace app\common\command;

use app\common\behavior\UserBehavior;
use PDO;
use think\Config;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Hook;
use think\Log;

/**
 * 月结
 *
 * @package app\common\command
 */
class MonthlySettle extends CommonCommand {

    /**
     * 配置指令
     *
     * @return void
     */
    protected function configure() {
        $this->setName('settle-water-coin')->setDescription('结算并发放水币和返佣');

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

            // 结算水币
            UserBehavior::settleWaterCoin();

            // 结算返佣
            UserBehavior::settleRebate();

            Db::commit();

            $timeString = date(DATE_ATOM);
            $output->writeln("<info>月结完成 时间: ${timeString}</info>");

            return 0;

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);
            $output->writeln($this->getName() . ': ' . $e->getMessage());

            return 1;
        }
    }
}