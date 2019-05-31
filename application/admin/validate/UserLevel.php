<?php
namespace app\admin\validate;
use think\Validate;
class UserLevel extends Validate
{
    // 验证规则
    protected $rule = [
        ['level_name', 'require|unique:user_level'],
//        ['amount','require|number'],
    ];
    //错误信息
    protected $message  = [
        'level_name.require'    => '名称必须',
        'level_name.unique'     => '已存在相同等级名称',
//        'amount.require'        => '充值额度必须',
//        'amount.number'         => '充值额度必须是数字',
//        'amount.unique'         => '已存在相同充值额度',
    ];
    //验证场景
    protected $scene = [
        'edit'  =>  [
            'level_name'    =>'require|unique:user_level,level_name^level_id',
//            'amount'        =>'require|number',
        ],
    ];
}