<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-7
 * Time: 11:43
 */

namespace app\common\command;

use app\common\model\Financial;
use PDO;
use think\Config;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\db\Query;
use think\Exception;
use think\Hook;
use think\Log;

/**
 * 检查理财单
 * 结算完成的理财单
 *
 * @package app\common\command
 */
class CheckoutFinancial extends CommonCommand {

    /**
     * 配置指令
     *
     * @return void
     */
    protected function configure() {
        $this->setName('checkout-financial')->setDescription('检查理财单');
        // $this->addOption('--test-option', '-t', Option::VALUE_REQUIRED, '选项测试');
        // $this->addArgument('testArgument', Argument::REQUIRED, '参数测试');

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

            Db::startTrans();

            // 获取已到周期的理财单
            $finishedFinancialList = Financial::all([
                'status'        => Financial::STATUS_STARTED,
                'regular_month' => ['exp', '<= abs(timestampDiff(month, start_time, now()))'],
            ]);

            foreach ($finishedFinancialList as $item) {
                $item->endManaging();
                $item->save();
            }

            Db::commit();

            $timeString = date(DATE_ATOM);
            $output->writeln("<info>检查理财单完成 时间: ${timeString}</info>");

            return 0;

        } catch (Exception $e) {
            Db::rollback();
            Log::error((string) $e);
            $output->writeln($this->getName() . ': ' . $e->getMessage());

            return 1;
        }
    }
}