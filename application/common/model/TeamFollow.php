<?php

namespace app\common\model;

use think\Model;

class TeamFollow extends Model
{
    public function teamActivity()
    {
        return $this->hasOne('TeamActivity', 'team_id', 'team_id');
    }
    public function teamFound(){
        return $this->hasOne('TeamFound','found_id','found_id');
    }
    public function order(){
        return $this->hasOne('Order','order_id','order_id');
    }
    public function orderGoods(){
        return $this->hasOne('OrderGoods','order_id','order_id');
    }

    //状态描述
    public function getStatusDescAttr($value, $data)
    {
        $status = config('TEAM_FOLLOW_STATUS');
        return $status[$data['status']];
    }
}
