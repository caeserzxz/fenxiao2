<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 18-1-30
 * Time: 15:20
 */

namespace app\common\exception;
use think\Exception;

/**
 * 轻度异常，不用记录，消息发送到前端
 *
 * Class NoticeException
 *
 * @package app\common\exception
 */
class NoticeException extends Exception {

}