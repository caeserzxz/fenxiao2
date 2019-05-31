<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-5-23
 * Time: 15:15
 */

namespace app\common\model;

use think\Loader;
use think\Model;

class CommonModel extends Model {

    protected $pk = 'id';

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $deleteTime = 'delete_time';
    protected $type = [
        'create_time' => 'datetime',
        'delete_time' => 'datetime',
    ];
    protected $hidden = [
        'create_time',
        'delete_time',
    ];

    /**
     * 获取列表
     *
     * @param array|\Closure $condition 条件 或者闭包
     * @param int $page 页码
     * @param string|array $orderBy 排序字段
     * @param int $pageSize 一页的项目数
     * @param array $defaultCondition 默认条件
     * @param array $defaultField 默认查询字段
     * @return \PDOStatement|\think\Collection|static[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getList($condition = [], $page = 1, $orderBy = '', $pageSize = 20, $defaultCondition = [], $defaultField = []) {

        // 排序分页
        $query = (new static)->db()
            ->page($page, $pageSize);

        if ($orderBy) {
            if (is_array($orderBy)) {
                $query->order($orderBy[0], $orderBy[1]);
            } else {
                $query->order($orderBy);
            }
        }

        if ($condition instanceof \Closure) {
            // 使用闭包
            call_user_func($condition, $query, $defaultCondition, $defaultField);

        } else {

            $condition = array_merge($defaultCondition, $condition);
            $field = $defaultField;

            // 合并参数
            $query->where($condition)->field($field);
        }

        $result = $query->select();

        return is_array($result) ? $result : [];
    }

    /**
     * 统计行数
     *
     * @param array|\Closure $condition 条件 或 闭包
     * @return int 行数
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getCount($condition = []) {
        return (int) static::aggregate($condition, 'count(1)')[0]['count(1)'];
    }

    /**
     * 获取聚合，用来统计
     *
     * @param array|\Closure $condition 条件 或 闭包
     * @param array $field
     * @param string $groupBy
     * @return \think\Collection|static[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function aggregate($condition = [], $field = ['count(1)' => 'count'], $groupBy = '') {
        $model = new static;
        $query = $model->db();
        $query->alias(Loader::parseName($model->name));

        if ($condition instanceof \Closure) {
            // 使用闭包
            call_user_func($condition, $query);

        } else {
            $query->where($condition)->field($field);
            $groupBy and $query->group($groupBy);
        }

        $result = $query->select();

        return is_array($result) ? $result : [];
    }

}