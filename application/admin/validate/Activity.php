<?php
namespace app\admin\validate;
use think\Validate;
class Activity extends Validate
{
    // 验证规则
    protected $rule = [
        ['title', 'require'],
        ['level_id','require'],
        ['activity_img','require'],
    ];
    //错误信息
    protected $message  = [
        'title.require'      => '标题必须',
        'level_id.require'         => '等级类型必须',
        'activity_img.require'         => '活动图片必须',
    ];
    //验证场景
    protected $scene = [
        'edit'  =>  [
            'title'    =>'require',
            'level_id'        =>'require',
            'activity_img'        =>'require',
        ],
    ];
}