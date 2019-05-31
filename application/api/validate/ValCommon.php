<?php
/**
 * Created by PhpStorm.
 * User: Xgh
 * Date: 2018/4/12
 * Time: 17:33
 */

namespace app\api\validate;


use think\Validate;

class ValCommon extends Validate
{
    /**
     * 判断数据是否为空
     * @param $string
     * @return bool
     */
     public function checkNull($string)
    {
        if ($string == "") return true;
        return false;
    }

     public function checkMinLength($string, $length = 6)
    {
        if (mb_strlen($string) < $length) return true;
        return false;
    }

    /**
     * 判断数据是否为空
     * @param $string
     * @return bool
     */
     public function checkEmpty($string)
    {
        if (empty($string)) return true;
        return false;
    }

    //验证菜单名称是否正确
     public function checkMenuName($string)
    {
        $pattern = '/^([a-zA-Z])+[\/]([a-zA-Z])+$/';
        if (!preg_match($pattern, $string)) return true;
        return false;
    }

    /**
     * 验证余额
     * @param $string
     * @return bool
     */
     public function checkBalance($string)
    {
        $pattern = '/^[0-9]+(.[0-9]{1,2})?$/';
        if (!preg_match($pattern, $string)) return true;
        return false;
    }

    /**
     * 判断两个值是否相等
     * @param $string
     * @param $string2
     * @return bool
     */
     public function checkNoEqual($string, $string2)
    {
        if ($string != $string2) return true;
        return false;
    }
}