<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-1-30
 * Time: 14:58
 */

namespace app\common\validate;

use think\Validate;

class TokenValidate extends Validate {

    public static function checkToken($data, $rule = '__token__') {

        if (is_string($data)) {
            $data = [$rule => $data];
        }
        return self::make()->token(null, $rule, $data);
    }
}