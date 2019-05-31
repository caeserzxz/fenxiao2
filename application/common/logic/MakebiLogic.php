<?php

namespace app\common\logic;

use app\common\model\PromGoods;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use think\Model;
use think\db;

/**
 * 马克币获取逻辑
 */
class MakebiLogic extends Model
{
    /**
     * 注册生成直推以及管理关系
     * 用户注册获取马克币
     * 分享者获得马克币
     *
     * $registerId(注册者Id)
     * $recommendId(推荐者Id)
     **/
    public function relation($registerId, $recommendId = '')
    {
        if (!empty($recommendId)) {
            $getRecommendInfo = M("users")->where('user_id', $recommendId)->find();
            //写入直推关系
            $this->push($registerId, $recommendId);
            //写入管理关系
            if ($getRecommendInfo['user_type'] == 1 || $getRecommendInfo['user_type'] == 2 || $getRecommendInfo['user_type'] == 3) {
                $this->management($registerId, $getRecommendInfo['user_id']);
            } else {
                //推荐人不具备管理身份，判断推荐人是否有管理者、存在则该管理人也是新注册用户的管理者。
                //如果推荐人既没有管理身份，也没有管理者。则新注册的用户也没有管理者
                $recUserManagement = M('n_user_management')->where('user_id', $getRecommendInfo['user_id'])->find();
                if ($recUserManagement) {
                    $this->management($registerId, $recUserManagement['management_id']);
                }
            }
            //分享者获取马克币
            $this->insertAmount('makebi_share', '用户邀请好友注册成功获取马克币', $registerId, $recommendId);
        }
        //用户注册获取马克币
        $result = $this->insertAmount('makebi_register', '用户注册获取马克币', $registerId, '');
        return $result;
    }


    //手机绑定获得马克币
    public function binding($userId)
    {
        $result = $this->insertAmount('makebi_bind_phone', '用户绑定手机号获取马克币', $userId, '');
        return $result;
    }


    //用户登录获取马克币
    public function login($userId)
    {
        $result = $this->insertAmount('makebi_login', '用户登录获取马克币', $userId, '');
        return $result;
    }


    //用户下载登录获取马克币
    public function uploadApp($userId)
    {
        $result = $this->insertAmount('makebi_upload', '用户下载app获取马克币', $userId, '');
        return $result;
    }


    //下载App获取马克币
    public function download($userId)
    {

    }

    //添加直推关系
    public function push($registerId, $pid)
    {
        $userId = $registerId;
        $pushData1 = array(
            'pid' => $pid
        );
        //写入一级直推关系
        M('users')->where('user_id', $userId)->update($pushData1);
        //写入二级直推关系
        $getParent1 = M("users")->where('user_id', $pid)->find();
        if (!empty($getParent1['pid'])) {
            $pushData2 = array(
                'pid2' => $getParent1['pid']
            );
            $update2 = M('users')->where('user_id', $userId)->update($pushData2);
            if ($update2) {
                return true;
            } else {
                return false;
            }
        }
    }


    //添加管理关系
    public function management($registerId, $managementId)
    {
        $userID = $registerId;
        $manageData = array(
            'user_id' => $userID,
            'management_id' => $managementId,
            'create_time' => time()
        );
        $result = M("n_user_management")->insert($manageData);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }


    //写入tp_n_amount_log表基类
    public function insertAmount($key, $desc, $registerId, $recommendId = '')
    {
        if (empty($recommendId)) {
            //没有推荐人的时候，获得马克币的是注册用户。所以 $userId = $registerId;
            $userId = $registerId;
            $amountData['user_id'] = $userId;
        } else {
            //有推荐人的时候，获得马克币的是推荐人。所以 $userId = $recommendId;
            $userId = $recommendId;
            $amountData['user_id'] = $recommendId;
            $amountData['obj_user_id'] = $registerId;
        }

        $getMakebiConfig = M("n_goods_config")->where('key', $key)->find();
        if ($getMakebiConfig['value'] > '0') {
            $amountData['money'] = $getMakebiConfig['value'];
            $amountData['reward_type'] = 0;
            $amountData['type'] = 1;
            $amountData['number'] = rand(10000, 99999) . time();
            $amountData['desc'] = $desc;
            $amountData['create_time'] = time();
            $amountData['status'] = 0;
            $insertAmountLog = M("n_amount_log")->insert($amountData);

            //记录用户马克币变动
            $user = M('users')->where('user_id', $userId)->find();
            $userUpdate = array();
            $userUpdate['total_makebi'] = $user['total_makebi'] + $amountData['money'];
            $userUpdate['makebi'] = $user['makebi'] + $amountData['money'];
            $userRt = M('users')->where('user_id', $user['user_id'])->update($userUpdate);

            if ($insertAmountLog) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }


    }


}