<?php
namespace app\admin\controller;
use think\Log;
use app\admin\service\BusinessPay;
use think\Exception;
use think\Page;
use think\Db;

//退押申请
class Deposit extends Base {

//申请列表
    public function applyList(){

        $where = ' 1 = 1 '; // 搜索条件
        $where .= 'and status != 3';
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (u.nickname like '%$key_word%')" ;
        }

        $count = db('apply_deposit')->alias('ad')
            ->join('users u','ad.user_id = u.user_id','left')
            ->where($where)->count();

        $Page = $pager = new Page($count,10);
        //$order_str = "'{$_POST['orderby1']} {$_POST['orderby2']}'";
        $list = db('apply_deposit')
            ->alias('ad')
            ->join('users u','ad.user_id = u.user_id','left')
            ->where($where)
            ->order('ad.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->field('ad.*,u.nickname,u.head_pic,u.deposit')
            ->select();
        $show  = $Page->show();
        //p($list);
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s',$val['create_time']):'0000-00-00 00:00:00';
            $_t['examine_time'] = $val['examine_time'] != 0 ? date('Y-m-d H:i:s',$val['examine_time']):'0000-00-00 00:00:00';
            $_list[] = $_t;
        }

        $this->assign('cur_page',$Page->nowPage);
        $this->assign('pager',$pager);
        $this->assign('applyList', $_list);
        $this->assign('show', $show);
        return $this->fetch();
    }

    //详情
    public function detail(){

        $id = input('id');
        if(!$id){
            $return = ['status'=>0,'msg'=>'操作失败','url'=>U("Admin/Deposit/applyList")];
            $this->ajaxReturn($return);
        }
        $detail = db('apply_deposit')
            ->alias('ad')
            ->join('users u','ad.user_id = u.user_id','left')
            ->join('user_level ul','u.level = ul.level_id','left')
            ->where(['id'=>$id])
            ->order('ad.id desc')
            ->field('ad.*,u.nickname,u.head_pic,u.user_money,u.pay_points,u.water_coin,u.openid,u.last_login,ul.level_name')
            ->find();

        //p($detail);
        $this->assign('detail', $detail);
        return $this->fetch();
    }

    //审核
    public function status(){
        $id = input('id');
        $status = input('status');
        if(empty($id)) returnJson(-1,'缺少id');
        if($status != 1 && $status != 2) returnJson(-1,'缺少参数');
        if($status == 1) {
            $user_id = db('apply_deposit')->where(['id'=>$id])->value('user_id');
            db('users')->where(['user_id'=>$user_id])->update(['level'=>3]);
        }
        $res = db('apply_deposit')->where(['id'=>$id])->update(['status'=>$status,'examine_time'=>time()]);
        if($res) returnJson(1,'操作成功');
        returnJson(-1,'操作失败');
    }

    //企业打款
    public function payment($ids, $type){

        if($type != 'pay') returnJson(-1,'非法操作！');
        empty($ids) && returnJson(-1,'非法操作！');

        try{
            $apply_ids = rtrim($ids,",");
            $apply_ids = explode(',',$apply_ids);
            $pay_array = array();
            $where['status'] = ['in',[1,4]];

            Db::startTrans();

            foreach ($apply_ids as $key => $val){
                $where['id'] = $val;
                $return = db('apply_deposit')->where($where)->update(['status'=>3]);
                $pay_array[] = array('id'=>$val,'ok'=>$return);
            }

            if($pay_array && is_array($pay_array)){
                $count = count($pay_array);
                $is_ok = array();
                $ok = 0;
                foreach ($pay_array as $key => $val){

                    if($val['ok'] === 1){
                        $deposit_list = db('apply_deposit')
                            ->alias('ad')
                            ->join('users u','ad.user_id = u.user_id','left')
                            ->where(['ad.id'=>$val['id']])
                            ->field('ad.*,u.deposit,u.openid')
                            ->find();

                        if(!empty($deposit_list['openid']) && (int)$deposit_list['deposit'] >= 1){

//                             $obj = new BusinessPay();
//                             $order_sn = time().mt_rand(1000,9999); //订单号
//                             $deposit = $deposit_list['deposit'] * 100; //金额
//                             $res = $obj->PayToUser($deposit_list['openid'],(int)$deposit,$order_sn);

                            // 临时 模拟打款成功
                            $res['flag'] = true;

                            if($res['flag'] === true){
                                db('users')->where(['user_id'=>$deposit_list['user_id']])->update(['deposit'=>0]);
                                db('apply_deposit')->where(['id'=>$val['id']])->update(['detail'=>'打款成功']);

                                $is_ok[] = $this->is_ok($val['id'], 1);
                                $ok += 1;
                                //$this->ajaxReturn(['status' => 1,'msg' => '打款成功','url'=>U("Admin/Deposit/applyList")]);
                            }else{
                                db('apply_deposit')->where(['id'=>$val['id']])->update(['status'=>4,'detail'=>$res['msg']]);
                                $is_ok[] = $this->is_ok($val['id']);
                            }
                        }else{
                            returnJson(-1,'缺少openid或者没有押金');
                        }
                    }
                }
            }

            Db::commit();

            if($count == $ok) {
                returnJson(1,'打款成功');
            }elseif(!empty($is_ok)){
                $str_id = '';
                foreach ($is_ok as $key => $val){
                    if($val['complete'] == 0){
                        $str_id .= $val['id'].' , ';
                    }
                }
                returnJson(-1,'ID：'.rtrim($str_id," , ").' 的申请打款失败');
            }
        }catch (Exception $e){
            Db::rollback();
            Log::error((string) $e);
            returnJson(-1,'打款失败');
        }

    }

    public function is_ok($id, $complete = 0){
        return array('id'=>$id,'complete'=>$complete);
    }

    //删除申请
    public function delApply(){
        $ids = I('post.ids','');
        empty($ids) && $this->ajaxReturn(['status' => -1,'msg' => '非法操作！']);
        $apply_ids = rtrim($ids,",");
        $res=Db::name('apply_deposit')->whereIn('id',$apply_ids)->delete();
        if($res){
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/Deposit/applyList")]);
        }
        $this->ajaxReturn(['status' => -1,'msg' => '操作失败','data'  =>'']);
    }

    //已完成
    public function completeList(){
        $where = ' 1 = 1 '; // 搜索条件
        $where .= 'and status = 3';
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (u.nickname like '%$key_word%')" ;
        }

        $count = db('apply_deposit')->alias('ad')->join('users u','ad.user_id = u.user_id','left')->where($where)->count();
        $Page = $pager = new Page($count,10);
        //$order_str = "'{$_POST['orderby1']} {$_POST['orderby2']}'";
        $list = db('apply_deposit')
            ->alias('ad')
            ->join('users u','ad.user_id = u.user_id','left')
            ->where($where)
            ->order('ad.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->field('ad.*,u.nickname,u.head_pic,u.deposit')
            ->select();
        $show  = $Page->show();
        //p($list);
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s',$val['create_time']):'0000-00-00 00:00:00';
            $_t['examine_time'] = $val['examine_time'] != 0 ? date('Y-m-d H:i:s',$val['examine_time']):'0000-00-00 00:00:00';
            $_list[] = $_t;
        }

        $this->assign('cur_page',$Page->nowPage);
        $this->assign('pager',$pager);
        $this->assign('applyList', $_list);
        $this->assign('show', $show);
        return $this->fetch();
    }

    //全部列表
    public function applyAll(){

        $where = ' 1 = 1 '; // 搜索条件
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (u.nickname like '%$key_word%')" ;
        }

        $count = db('apply_deposit')->alias('ad')->join('users u','ad.user_id = u.user_id','left')->where($where)->count();
        $Page = $pager = new Page($count,10);
        //$order_str = "'{$_POST['orderby1']} {$_POST['orderby2']}'";
        $list = db('apply_deposit')
            ->alias('ad')
            ->join('users u','ad.user_id = u.user_id','left')
            ->where($where)
            ->order('ad.id desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->field('ad.*,u.nickname,u.head_pic,u.deposit')
            ->select();
        $show  = $Page->show();
        //p($list);
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['create_time'] = $val['create_time'] != 0 ? date('Y-m-d H:i:s',$val['create_time']):'0000-00-00 00:00:00';
            $_t['examine_time'] = $val['examine_time'] != 0 ? date('Y-m-d H:i:s',$val['examine_time']):'0000-00-00 00:00:00';
            $_list[] = $_t;
        }

        $this->assign('cur_page',$Page->nowPage);
        $this->assign('pager',$pager);
        $this->assign('applyList', $_list);
        $this->assign('show', $show);
        return $this->fetch();
    }

}