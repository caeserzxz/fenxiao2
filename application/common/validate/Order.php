<?php

namespace app\common\validate;

use think\Validate;

/**
 * Description of Article
 *
 * @author Administrator
 */
class Order extends Validate
{
    //验证规则
    protected $rule = [
        'order_id' => 'require',
    ];

    //错误消息
    protected $message = [
        'order_id.require'    => '订单id不能为空',
    ];

    //验证场景
    protected $scene = [
        'del'  => ['order_id' =>  'require|checkDelOrder'],
    ];

    protected function checkDelOrder($value)
    {
        $data = M('order')->field('deleted, order_status')->where('order_id', $value)->find();
        //halt($data);
        if (!$data) {
            return '订单不存在';
        } elseif ($data['deleted']) {
            return '订单已经删除过了';
        } elseif (in_array($data['order_status'], [0, 1])) { //待确认，已确认
            return '订单未完成，不能删除';
        }

        return true;
    }
}
