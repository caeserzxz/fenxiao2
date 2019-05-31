<?php
namespace app\admin\validate;
use think\Validate;
class Exchange extends Validate
{
    // 验证规则
    protected $rule = [
        ['goods_id', 'require'],
        ['water_coin','require|number'],
        ['activity_id','require'],
    ];
    //错误信息
    protected $message  = [
        'goods_id.require'      => '商品必须',
        'water_coin.require'        => '水币必须',
        'water_coin.number'         => '水币必须是数字',
        'activity_id.require'         => '活动类型必须',
    ];
    //验证场景
    protected $scene = [
        'edit'  =>  [
            'goods_id'    =>'require',
            'water_coin'        =>'require|number',
            'activity_id'        =>'require',
        ],
    ];
}