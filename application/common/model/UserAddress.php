<?php

namespace app\common\model;
use think\Model;
use think\Db;
class UserAddress extends Model {

    //获取省-市-区
    public function getRegionTextAttr($value,$data)
    {
        $pro = $data['province'];
        $city = $data['city'];
        $district = $data['district'];

        //获取省市区
        $region = Db::name('region')->whereIn('id',[$pro,$city,$district])->column('name');

        //格式化代码
        $format = isset($data['format_region']) ? $data['format_region'] : ' ';

        return implode($format,$region);
    }
}
