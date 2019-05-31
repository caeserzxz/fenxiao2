<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-8
 * Time: 13:50
 */

namespace app\common\model;

use think\Model;

class WaterCoinLog extends Model {

    public static function create($data = [], $field = null) {
        $model = new static();

        if ($field) {
            $model->allowField($field);
        }

        $data['create_time'] or $data['create_time'] = time();

        $model->isUpdate(false)->save($data);

        return $model;
    }
}