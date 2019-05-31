<?php
/**
 * Created by PhpStorm.
 * User: 猿份哥
 * Date: 2018/6/6
 * Time: 10:10
 */

namespace app\admin\controller;

use app\common\model\ExchangeOrder;
use My\DataReturn;
use think\Db;
use think\Page;

/**
 * Class Change
 * @package app\admin\controller
 */
class Change extends Base {

    /**
     * 已兑水列表
     * @return mixed
     */
    public function index(){
        $cityInfo = [];
        $areaInfo = [];

        $where['e.is_delete'] = 0; // 默认搜索条件

        $key_word = I('key_word') ? trim(I('key_word')) : '';
        $is_deliver = I('is_deliver');

        $province = I('province');//省
        $city = I('city');//市
        $district = I('district');//区
        if($key_word)
        {
            $where['u.nickname|e.order_sn'] = ['like',"%$key_word%"];
        }
        if($is_deliver !== ''){
            $where['e.is_deliver'] = $is_deliver;
        }

        //三级联动
        if(!empty($province) && !empty($city) && !empty($district)){
            $where['a.province'] = $province;
            $where['a.city'] = $city;
            $where['a.district'] = $district;

            //查询
            $cityInfo =  M('region')->where(['parent_id'=>$province])->select();
            //获取订单地区
            $areaInfo =  M('region')->where(['parent_id'=>$city])->select();

        }else if(!empty($province) && !empty($city)){
            $where['a.province'] = $province;
            $where['a.city'] = $city;
            //查询
            $cityInfo =  M('region')->where(['parent_id'=>$province])->select();
        }else if(!empty($province)){
            $where['a.province'] = $province;
        }
//        halt($where);

        $join = [
            ['tp_users u','e.user_id = u.user_id'],
            ['tp_user_address a','e.address_id = a.address_id'],
        ];

        //计算总条数
        $count = Db::name('exchange_order')->alias('e')->where($where)->join($join)->count();
        $pageObj = new Page($count,15);

        //链表查表
        $orderInfo = Db::name('exchange_order')->alias('e')
            ->field(['e.*','u.nickname','u.head_pic','a.*'])
            ->where($where)
            ->limit($pageObj->firstRow.','.$pageObj->listRows)
            ->join($join)->order('e.id desc')
            ->select();

        $show  = $pageObj->show();//显示分页

        foreach ($orderInfo as $k=>&$v){
            $v['create_time'] = $v['create_time'] != 0 ? date('Y-m-d H:i:s',$v['create_time']) : '0000-00-00 00:00:00';
            $v['consignee_info'] = $v['consignee'].'  :  '.$v['mobile'];
            $v['address'] = getTotalAddress($v['province'],$v['city'],$v['district'],$v['twon'],$v['address']);
            if($v['is_deliver'] == 0){
                $v['deliver_status'] = '待发货';
            }elseif ($v['is_deliver'] == 1){
                $v['deliver_status'] = '已发货';
            }else{
                $v['deliver_status'] = '已收货';
            }
        }

        //改写搜素条件,解决模板不能识别‘’和0的问题
        if($is_deliver == ''){
            $is_deliver = 3;
        }

        //返回查询的条件
        $returnWhere = [
            'key_word'=>$key_word,
            'is_deliver'=>$is_deliver,
            'province'=>$province,
            'city'=>$city,
            'district'=>$district
        ];


        // 获取省份
        $provinceInfo = M('region')->where(array('parent_id'=>0,'level'=>1))->select();

        $this->assign('province',$provinceInfo);
        $this->assign('city',$cityInfo);
        $this->assign('district',$areaInfo);

        return $this->fetch('',compact('orderInfo','show','pageObj','returnWhere'));
    }

    /**
     * 兑水订单详情
     */
    public function detail(){
        $id = input('id');//对应的大订单id
        $join = [
            ['tp_users u','e.user_id = u.user_id'],
            ['tp_user_address a','e.address_id = a.address_id'],
        ];
        $order = Db::name('exchange_order')->alias('e')
            ->field(['e.*','u.nickname','u.head_pic','u.user_id','u.email as user_email','u.mobile as phone','a.*'])
            ->where(['e.id'=>$id])
            ->join($join)->find();
        $order['fulladdress'] = getTotalAddress($order['province'],$order['city'],$order['district'],$order['twon'],$order['address']);

        //查询快递公司
        $order['deliver_name'] = Db::name('plugin')->field(['name'])->where(['status'=>1,'type'=>'shipping','code'=>$order['shopping_type']])->value('name');

        //一共消费的总水币
        $total_coin = $order['total_coin'];

        //根据大订单id去查询小订单
        $join = [
            ['tp_goods g','e.goods_id = g.goods_id']
        ];
        $splitOrder = Db::name('exchange_split')->alias('e')
            ->join($join)
            ->field(['g.original_img','g.goods_name','e.*'])
            ->where(['e.order_id'=>$order['id']])
            ->select();
        foreach ($splitOrder as $k=>&$v){
            $v['original_img'] = SITE_URL.$v['original_img'];
        }

        return $this->fetch('',compact('order','splitOrder','total_coin'));

    }

    /**
     * 删除指定订单
     */
    public function delOrder(){
        $id = input('ids');//对应的大订单id
        $res = Db::name('exchange_order')->where(['id'=>$id])->update([
           'delete_time'=>time(),
            'is_delete'=>1
        ]);
        if($res){
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Change/index")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }

    /**
     * 发货
     */
    public function deliver(){

        if(request()->isPost()){
            $deliverData = input();
            //获取当前物流状态
            $is_deliver = Db::name('exchange_order')->where(['id'=>$deliverData['order_id']])->value('is_deliver');
            $msg = $is_deliver?'修改成功':'发货成功';

            $res = Db::name('exchange_order')->where(['id'=>$deliverData['order_id']])->update([
                'is_deliver'=>1,
                'shopping_type'=>$deliverData['shipping'],
                'deliver_sn'=>$deliverData['deliver_sn'],
                'delivery_time'=>time()
            ]);

            if($res){
                $this->ajaxReturn(['status' => 1,'msg' => $msg,'url'=>U("Change/index")]);
            }
            $this->ajaxReturn(['status' => -1,'msg' => '发货失败','data'  =>'']);

        }
        $id = input('id');
        $join = [
            ['tp_user_address a','e.address_id = a.address_id']
        ];
        $order = Db::name('exchange_order')->alias('e')
            ->field(['e.*','a.*'])
            ->where(['e.id'=>$id])
            ->join($join)->find();
        $order['fulladdress'] = getTotalAddress($order['province'],$order['city'],$order['district'],$order['twon'],$order['address']);

        //获取配送方式
        $shipping_list = Db::name('plugin')->field(['name','code'])->where(['status'=>1,'type'=>'shipping'])->select();

        return $this->fetch('',compact('order','shipping_list'));
    }


    /**
     * 批量删除操作
     * @return array
     */
    public function delAllOrder()
    {
        $ids = rtrim(input('ids'));
        empty($ids) && $this->ajaxReturn(['status' => -1,'msg' => '非法操作！']);
        $res = Db::name('exchange_order')->whereIn('id',$ids)->update([
            'delete_time'=>time(),
            'is_delete'=>1
        ]);
        if($res){
            $this->ajaxReturn(['status' => 1,'msg' => '删除成功','url'=>U("Change/index")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '删除失败','data'  =>'']);
    }

    /**
     * 导出交换订单
     */
    public function exportExchageOrder(){
        $ids = input('ids');
        if(!empty($ids)){
            $ids = rtrim($ids,',');
        }
        $join = [
            ['tp_users u','e.user_id = u.user_id'],
            ['tp_user_address a','e.address_id = a.address_id'],
        ];

        $orderInfo = Db::name('exchange_order')->alias('e')
            ->field(['e.*','u.nickname','u.head_pic','a.*'])
            ->where('id','in',$ids)
            ->join($join)->order('e.id desc')
            ->select();

        //拼接组成excel数据
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">下单人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货地址</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">总数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">消费总水币</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">兑水时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">配送状态</td>';
        $strTable .= '</tr>';
        if(is_array($orderInfo)){
            $region	= get_region_list();
            foreach($orderInfo as $k=>$val){

                if($val['is_deliver'] == 0){
                    $val['deliver_status'] = '待发货';
                }elseif ($val['is_deliver'] == 1){
                    $val['deliver_status'] = '已发货';
                }else{
                    $val['deliver_status'] = '已收货';
                }

                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['id'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['nickname'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['consignee'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'."{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}".' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['goods_num'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_coin'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['create_time']).'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['deliver_status'].'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'兑水订单');
        exit($strTable);
    }

    /**
     * @猿份哥
     * 修改订单状态
     */
    public function alterStatus(){
        if(request()->isPost()){
            $order_id = input('order_id');
            $res = ExchangeOrder::update(['is_deliver'=>2],['id'=>$order_id]);
            if($res){
                DataReturn::returnJson(1,'收货成功');
            }else{
                DataReturn::returnJson(0,'系统繁忙,请稍后再试...');
            }

        }else{
            DataReturn::returnJson(-1,'非法请求',[]);
        }
    }
}