<?php

namespace app\admin\logic;

use think\Model;
use think\Db;

class UsersLogic extends Model
{

    /**
     * 获取指定用户信息
     * @param $uid int 用户UID
     * @param bool $relation 是否关联查询
     *
     * @return mixed 找到返回数组
     */
    public function detail($uid, $relation = true)
    {
        $user = M('users')->where(array('user_id' => $uid))->relation($relation)->find();
        return $user;
    }

    /**
     * 改变用户信息
     * @param int $uid
     * @param array $data
     * @return array
     */
    public function updateUser($uid = 0, $data = array())
    {
        $db_res = M('users')->where(array("user_id" => $uid))->data($data)->save();
        if ($db_res) {
            return array(1, "用户信息修改成功");
        } else {
            return array(0, "用户信息修改失败");
        }
    }


    /**
     * 添加用户
     * @param $user
     * @return array
     */
    public function addUser($user)
    {
		$user_count = Db::name('users')
				->where(function($query) use ($user){
					if ($user['email']) {
						$query->where('email',$user['email']);
					}
					if ($user['mobile']) {
						$query->whereOr('mobile',$user['mobile']);
					}
				})
				->count();
		if ($user_count > 0) {
			return array('status' => -1, 'msg' => '账号已存在');
		}
    	$user['password'] = encrypt($user['password']);
    	$user['reg_time'] = time();
    	$user_id = M('users')->add($user);
    	if(!$user_id){
    		return array('status'=>-1,'msg'=>'添加失败');
    	}else{
    		$pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
    		if($pay_points > 0)
    			accountLog($user_id, 0 , $pay_points , '会员注册赠送积分'); // 记录日志流水
    		return array('status'=>1,'msg'=>'添加成功');
    	}
    }


	/**
	 *  提现身份判断
	 *  $userId:
	 *      对象用户ID
	 *  $type:
	 *      1申请健康大使；
	 *      2申请总代；
	 *      3申请大区经理；
	 *      4签代理合同；
	 *      5提现
	 **/
	public function checkUser($userId,$type){
		//获取用户信息
		$userInfo = M('users')->where('user_id',$userId)->find();


		if(empty($userInfo)){
			return array(
					'status'=>0,
					'msg'=>"该用户不存在！",
			);
		}


		//获取身份证认证记录
		$idCard = M('n_user_idcard_apply')->where('user_id',$userId)->find();


		//获取代理合同
		$heTong = M('n_hetong')->where('user_id',$userId)->find();


		//获取银行卡信息
		$bankCard = M('n_user_bank')->where('user_id',$userId)->find();
//        dump($userId);
//        dump($idCard);
//        dump($heTong);
//        dump($bankCard);
//        die;

		if($type==1){
			if(empty($userInfo['mobile'])){
				return array(
						'status'=>0,
						'msg'=>"该用户还未进行手机认证",
				);
			}else{
				return array(
						'status'=>1
				);
			}
		}


		if($type==2||$type==3||$type==4){
			if(empty($userInfo['mobile'])){
				return array(
						'status'=>0,
						'msg'=>"该用户还未进行手机认证",
				);
			}
			if(empty($idCard['id_card'])){
				return array(
						'status'=>0,
						'msg'=>"该用户还未进行身份号码认证",
				);
			}
			if(empty($idCard['positive_path'])||empty($idCard['reverse_path'])){
				return array(
						'status'=>0,
						'msg'=>"该用户还未上传身份证证件照",
				);
			}
			if($idCard['status']==0){
				return array(
						'status'=>0,
						'msg'=>"身份证审核中",
				);
			}
			if($idCard['status']==2){
				return array(
						'status'=>0,
						'msg'=>"身份证认证不通过",
				);
			}
			else{
				return array(
						'status'=>1
				);
			}
		}


		if($type==5){
			if(empty($userInfo['mobile'])){
				return array(
						'status'=>0,
						'msg'=>"该用户还未进行手机认证",
				);
			}
			if(empty($idCard['id_card'])){
				return array(
						'status'=>0,
						'msg'=>"该用户还未进行身份号码认证",
				);
			}
			if(empty($idCard['positive_path'])||empty($idCard['reverse_path'])){
				return array(
						'status'=>0,
						'msg'=>"该用户还未上传身份证证件照",
				);
			}

			if($idCard['status']==0){
				return array(
						'status'=>0,
						'msg'=>"身份证审核中",
				);
			}
			if($idCard['status']==2){
				return array(
						'status'=>0,
						'msg'=>"身份证认证不通过",
				);
			}

			if(empty($heTong)){
				return array(
						'status'=>0,
						'msg'=>"该用户还未签代理合同",
				);
			}
			if(empty($bankCard)){
				return array(
						'status'=>0,
						'msg'=>"该用户还未绑定银行卡",
				);
			}
			else{
				return array(
						'status'=>1
				);
			}
		}


	}
}