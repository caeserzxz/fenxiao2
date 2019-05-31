<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-6-15
 * Time: 14:20
 */

namespace app\common\model;

class AccountLog extends CommonModel {

    protected $pk = 'log_id';

    public static function create($data = [], $field = null) {
        $model = new static();

        if ($field) {
            $model->allowField($field);
        }

        $data['change_time'] or $data['change_time'] = time();

        $model->isUpdate(false)->save($data);

        return $model;
    }
}