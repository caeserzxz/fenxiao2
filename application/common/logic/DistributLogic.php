<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/10
 * Time: 9:13
 */
namespace app\common\logic;

use app\common\model\Order;
use app\common\model\Users;
use think\Db;
use think\image\Exception;
use think\Model;

class DistributLogic extends Model{
    private $distribut_price = 0;
    private $contact_order = '';

    //产生分佣和记录-未分成
    public function rebateLog($order){

        $order_info = Order::with('OrderGoods.goods')->where('order_id',$order['order_id'])->find();

        $goods_list = $order_info->order_goods;

        $this->contact_order = $order_info;

        $pattern = tpCache('pattern'); //分佣模式0按商品设置的1按订单比例
        $rate    = tpCache('order_rate'); //订单设置的比例

        $distribut_price = 0;//初始化

        if($pattern)
        {
            $distribut_price = $order['goods_price'] * $rate; //分佣金额
        }else
        {
            foreach($goods_list as $k=>$v)
            {
                //判断该产品是否有分佣
                $commision = $v['goods']['commission'];

                if(!$commision) continue;
                $distribut_price += $commision;
            }
        }

        $this->distribut_price = $distribut_price;

        $user = Users::get($order['user_id']);

        Db::startTrans();

        try{
            $this->userDistributDo($user['first_leader'],1,$order);
            $this->userDistributDo($user['second_leader'],2,$order);
            $this->userDistributDo($user['third_leader'],3,$order);
            Db::commit();
        }catch(\Exception $e)
        {
            Db::rollback();
        }

    }

    /**
     * 用户分佣分成数据更新
     * !已废弃!
     *
     * @param $user_id
     */
    public static function rebateDivide($user_id)
    {
        //由于分佣分成不知道，不确定有多少条数，只能通过数据分批处理
        M('rebate_log')->where(['user_id'=>$user_id,'status'=>2])->chunk(20,function($user){
            $user_logic = new UsersLogic();
            $id_arr = [] ; //需更新的id

            foreach($user as $log)
            {
                if( (time() - $log['confirm']) >= tpCache('distribut.date') * 86400){
                    $rebate_log = ['desc'=>'分佣获得余额','order_sn'=>$log['order_sn'],'order_id'=>$log['order_id']];
                    $user_logic->setAccountOrPoints($log['user_id'],'account',$log['money'],$rebate_log);
                    //更新用户所累积的分佣金额
                    $user = Users::get($log['user_id']);
                    $user->distribut_money += $log['money'];
                    $user->save();
                    $id_arr[] = $log['id'];
                }
            }
            if(count($id_arr))
                M('rebate_log')->where('id','in',$id_arr)->save(['confirm_time'=>time(),'status'=>3]);

            unset($user_logic);
        });
    }



    //用户产生分佣
    private function userDistributDo($user_id,$level,$order)
    {

        //根据每一层的用户做处理
        $user_info = Users::get($user_id);
        $distribut_price = $this->distribut_price;
        if(empty($user_info) || !$user_info['is_distribut'] || !$distribut_price) return ;

        switch($level)
        {
            case 1:
                $price = $distribut_price *  tpCache('distribut.first_rate');

                break;
            case 2:
                $price = $distribut_price *  tpCache('distribut.second_rate');
                break;
            case 3:
                $price = $distribut_price *  tpCache('distribut.third_rate');
                break;
        }

        //格式化价格
        $price = sprintf('%.2f',$price/100);

        $buy_user_info = Users::get($order['user_id']);

        //处理分成记录
        $data['user_id'] = $user_id;
        $data['buy_user_id'] = $order['user_id'];
        $data['nickname'] = $buy_user_info->nickname;
        $data['order_sn'] = $order['order_sn'];
        $data['order_id'] = $order['order_id'];
        $data['goods_price'] = $order['total_amount'];
        $data['money'] = $price;
        $data['level'] = $user_info['distribut_level'];
        $data['create_time'] = time();
        $data['status'] = $this->contact_order->pay_status;

        $log = db('rebate_log')->insert($data);

        if(!$log)
            throw new \think\Exception('处理分销记录出错');


    }

}