<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-5-24
 * Time: 17:55
 */

namespace app\common\model;

use think\Model;

class UserLevel extends Model {

    const LEVEL_CONSUMER = 3;// 消费者
    const LEVEL_MEMBER = 1;// 会员
    const LEVEL_SENIOR_MEMBER = 2;// 高级会员

    protected $pk = 'level_id';

    public function users() {
        return $this->hasMany('Users', 'level_id', 'level');
    }
}