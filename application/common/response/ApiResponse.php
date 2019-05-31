<?php

namespace app\common\response;

use think\response\Json;

class ApiResponse extends Json {

    /**
     * 构造函数
     *
     * @access public
     * @param mixed $data 输出数据
     * @param string $status 操作状态
     * @param int $code
     * @param array $header
     * @param array $options 输出参数
     */
    public function __construct($data = '', $status = 'success', $code = 200, array $header = [], $options = []) {
        parent::__construct($data, $code, $header, $options);

        $this->data($data, $status);
    }

    /**
     * 输出数据设置
     *
     * @access public
     * @param mixed $data 输出数据
     * @param string $status 操作状态
     * @return $this
     */
    public function data($data, $status = 'success') {

        switch ($status) {

            case 'error':
                $message = $data;
                $this->data = [
                    'status' => $status,
                    'message' => $message,
                ];
                break;

            default:
                $this->data = [
                    'data' => $data,
                    'status' => $status,
                ];
                break;
        }

        return $this;
    }
}