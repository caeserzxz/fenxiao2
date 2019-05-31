<?php

namespace app\common\logic;

use think\model;

/**
 * Class UserAddressModel
 * @package Home\Model
 */
class UserAddressLogic extends model
{
    protected $tableName = 'user_address';

    /**
     * 获取用户自提点
     * @time 2016/08/23
     * @author
     * @param $user_id
     * @return mixed
     */
    public function getUserPickup($user_id)
    {
        $user_pickup_where = array(
            'ua.user_id' => $user_id,
            'ua.is_pickup' => 1
        );
        $user_pickup_list = M('user_address')
            ->alias('ua')
            ->field('ua.*,r1.name AS province_name,r2.name AS city_name,r3.name AS district_name')
            ->join('__REGION__ r1','r1.id = ua.province','LEFT')
            ->join('__REGION__ r2','r2.id = ua.city','LEFT')
            ->join('__REGION__ r3', 'r3.id = ua.district','LEFT')
            ->where($user_pickup_where)
            ->find();
        return $user_pickup_list;
    }

}