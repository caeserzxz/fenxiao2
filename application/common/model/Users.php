<?php

namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;

class Users extends Model {

    protected $pk = 'user_id';

    public function userLevel() {
        return $this->hasOne('UserLevel', 'level', 'level_id');
    }

    /**
     * 是否是会员
     *
     * @return bool
     */
    public function isMember() {
        $level = (int) $this->getData('level');
        return $level === UserLevel::LEVEL_MEMBER || $level === UserLevel::LEVEL_SENIOR_MEMBER;
    }

    /**
     * 是否是股东
     * 有正在进行的理财的就是股东
     *
     * @return bool
     */
    public function isShareHolder() {
        try {
            $aggregate = Financial::aggregate([
                'user_id' => $this['user_id'],
                'status'  => Financial::STATUS_STARTED,
            ]);
        } catch (Exception $e) {
        }
        if (!$aggregate) {
            return false;
        }
        return (bool) $aggregate[0]['count'];
    }

    /**
     * 是否是业务员
     * 后台分配
     *
     * @return bool
     */
    public function isSales() {
        return (bool) $this->getData('is_sales');
    }

    /**
     * 是否消费过
     * 已购买过商品或者已交过押金
     *
     * @return bool
     */
    public function isPurchased() {
        return (bool) $this->getData('is_purchased');
    }
}
