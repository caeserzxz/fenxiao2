<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-7
 * Time: 10:54
 */

namespace app\common\behavior;

use app\common\model\AccountLog;
use app\common\model\UserLevel;
use app\common\model\Users;
use app\common\model\WaterCoinLog;
use think\db\Query;
use think\Log;

class UserBehavior extends CommonBehavior {

    /**
     * 结算、发放水币
     * 用户等级 决定金额
     *
     * @throws \think\exception\DbException
     */
    public static function settleWaterCoin() {

        $levelList = UserLevel::all(null, [], true);

        foreach ($levelList as $level) {

            // 奖励的水币数量
            $amount = (int) $level['return_water_coin'];

            if (!$amount) {
                continue;
            }

            // 发放水币
            Users::update([
                'water_coin' => ['exp', 'water_coin + ' . $amount],
            ], [
                'level' => $level['level_id'],
            ]);

            $userList = Users::all(function(Query $query) use ($level) {
                $query->where([
                    'level' => $level['level_id'],
                ])->field(['user_id']);
            });

            foreach ($userList as $item) {
                $userId = $item['user_id'];
                if (!$userId) {
                    continue;
                }

                // 生成水币记录
                WaterCoinLog::create([
                    'user_id'    => $userId,
                    'water_coin' => $amount,
                ]);
            }
        }
    }

    /**
     * 结算、发放返佣（积分
     * 用户的等级和 直属下级的等级和数量 决定奖励金额
     * 业务员例外，无视用户等级
     *
     * @throws \think\exception\DbException
     */
    public static function settleRebate() {
        $levelList = UserLevel::all(null, [], true);

        // 分销设置
        $config = tpCache('distribut');

        foreach ($levelList as $level) {
            $levelId = (int) $level['level_id'];

            // 筛选出有奖励的下级等级
            if (!in_array($levelId, [
                UserLevel::LEVEL_MEMBER,
                UserLevel::LEVEL_SENIOR_MEMBER,
            ])) {
                continue;
            }

            // 查询用户，统计 此下级等级的数量
            $leaderList = Users::all(function(Query $query) use ($level) {
                $query->alias('leader')
                    ->join('users', 'users.first_leader is not null and 
                        users.first_leader != 0 and 
                        users.first_leader = leader.user_id')
                    ->where([
                        'users.level' => $level['level_id'],
                    ])
                    ->group('users.first_leader')
                    ->field([
                        'leader.user_id',
                        'leader.level',
                        'leader.is_sales',
                        'count(users.user_id)' => 'underling_number',
                    ])
                ;
            });

            foreach ($leaderList as $item) {

                if ($item->isSales()) {
                    // 用户是业务员，使用分销设置的积分

                    if ($levelId === UserLevel::LEVEL_MEMBER) {
                        $amount = (int) $config['salesman_push_member'];

                    } else if ($levelId === UserLevel::LEVEL_SENIOR_MEMBER) {
                        $amount = (int) $config['salesman_push_senior_member'];
                    }
                } else {

                    // 找出用户等级
                    foreach ($levelList as $level) {
                        if ($level['level_id'] == $item['level']) {
                            $leaderLevel = $level;
                            break;
                        }
                    }

                    if ($levelId === UserLevel::LEVEL_MEMBER) {
                        $amount = (int) $leaderLevel['member_points'];

                    } else if ($levelId === UserLevel::LEVEL_SENIOR_MEMBER) {
                        $amount = (int) $leaderLevel['senior_points'];
                    }
                }

                if (!$amount) {
                    continue;
                }

                $item->save([
                    'pay_points' => ['exp', 'pay_points + ' . $amount],
                ]);

                // 记录资金变动
                AccountLog::create([
                    'user_id'    => $item['user_id'],
                    'pay_points' => $amount,
                    'desc'       => '推荐会员/高级会员获得分佣',
                ]);
            }
        }
    }
}
