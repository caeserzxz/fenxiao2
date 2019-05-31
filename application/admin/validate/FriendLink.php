<?php

namespace app\admin\validate;

use think\Validate;

/**
 * Description of Article
 *
 * @author Administrator
 */
class FriendLink extends Validate
{
    //验证规则
    protected $rule = [
        'link_name'  => 'require|chsAlphaNum',
        'link_url'   => 'require|url',
        'orderby'    => 'require|number',
        'link_id'    => 'require',
    ];

    //错误消息
    protected $message = [
        'link_name.require'      => '链接名称不能为空',
        'link_name.chsAlphaNum'  => '链接名称只能是汉字，字母，数字',
        'link_url.require'       => '链接地址不能为空',
        'link_url.url'           => '链接地址格式错误',
        'orderby.require'        => '不能为空',
        'orderby.number'         => '排序必须是数字',
    ];

    //验证场景
    protected $scene = [
        'add'  => ['link_name', 'link_url', 'orderby'],
        'edit' => ['link_name', 'link_url', 'orderby'],
        'del'  => ['link_id']
    ];
}
