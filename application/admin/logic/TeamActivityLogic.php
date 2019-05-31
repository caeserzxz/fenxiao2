<?php

namespace app\admin\logic;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\model\TeamFollow;
use think\Db;
use think\Model;

/**
 * 拼团活动逻辑类
 */
class TeamActivityLogic extends Model
{
    protected $team;//拼团模型
    protected $teamFound;//团长模型
    public function setTeam($team){
        $this->team = $team;
    }
    public function setTeamFound($teamFound){
        $this->teamFound = $teamFound;
    }
    /**
     * 抽奖
     * @return array
     * @throws \think\Exception
     */
    public function lottery(){
		header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }

    /**
     * 拼团退款
     * @return array
     * @throws \think\Exception
     */
    public function refundFound(){
        if(empty($this->teamFound)){
            return ['status'=>0,'msg'=>'找不到拼单','result'=>''];
        }
        if(empty($this->teamFound->order)){
            return ['status'=>0,'msg'=>'找不到拼单的订单','result'=>''];
        }
        if($this->teamFound->status != 3){
            return ['status'=>0,'msg'=>'拼单状态不符合退款需求','result'=>''];
        }
        if($this->teamFound->order->pay_status == 0){
            return ['status'=>0,'msg'=>'拼单订单状态不符合退款需求','result'=>''];
        }
        $teamOrderId = [];//拼团Order_id集合
        array_push($teamOrderId,$this->teamFound->order_id);
        $teamFollow = $this->teamFound->teamFollow()->where(['status'=>1])->select();//拼单成功的会员
        if($teamFollow){
            $followOrderId = get_arr_column($teamFollow,'order_id');//会员拼单成功的order_id
            $teamOrderId = array_merge($teamOrderId,$followOrderId);
        }
        $orderRefund = Db::name('order')->where('order_id', 'IN', $teamOrderId)->update(['order_status' => 3]);//订单取消,平台后台处理退款
        $orderLogic = new OrderLogic();
        $TeamOrderList = Db::name('order')->where('order_id', 'IN', $teamOrderId)->select();
        if($TeamOrderList){
            foreach($TeamOrderList as $orderKey => $orderVal){
                $orderLogic->orderActionLog($orderVal['order_id'], '取消订单', '拼团退款');
            }
        }
        if($orderRefund !== false){
            return ['status'=>1,'msg'=>'拼团退款已提交至平台，坐等审核','result'=>''];
        }else{
            return ['status'=>0,'msg'=>'拼团退款失败','result'=>''];
        }
    }


}