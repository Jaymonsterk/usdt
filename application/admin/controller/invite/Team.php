<?php

namespace app\admin\controller\invite;

use app\common\controller\Backend;

/**
 * 用户提现记录管理
 *
 * @icon fa fa-circle-o
 */
class Team extends Backend
{
    
    /**
     * Team模型对象
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 我的团队
     * @return [type] [description]
     */
    public function index()
    {

        $uid = 1;
        $userinfo = db('user');
        $myteam = mytime_oids($uid);
        $user = $userinfo->where('id',$uid)->find();
        $user['mysons'] = $myteam;
        $this->assign('mysons',$user);
        return $this->fetch();
    }


    /**
     * 某个代理商的业绩
     * @return [type] [description]
     */
    public function yeji()
    {
        $userinfo = db('userinfo');
        $price_log = db('price_log');
        $uid = input('uid');
        if(!$uid){
            $this->error('参数错误！');
        }

        $_user = $userinfo->where('uid',$uid)->find();
        if(!$_user){
            $this->error('暂无用户！');
        }



        //搜索条件
        $data = input('param.');

        if(isset($data['starttime']) && !empty($data['starttime'])){
            if(!isset($data['endtime']) || empty($data['endtime'])){
                $data['endtime'] = date('Y-m-d H:i:s',time());
            }
            $getdata['starttime'] = $data['starttime'];
            $getdata['endtime'] = $data['endtime'];
        }else{
            $getdata['starttime'] = date('Y-m-d',time()).' 00:00:00';
            $getdata['endtime'] = date('Y-m-d',time()).' 23:59:59';
        }

        $map['time'] = array('between time',array($getdata['starttime'],$getdata['endtime']));
        $map['uid'] = $uid;
        /*
        //红利收益
        $map['title'] = '对冲';
        $hl_account = $price_log->where($map)->sum('account');
        if(!$hl_account) $hl_account = 0;
        //佣金收益
        $map['title'] = '客户手续费';
        $yj_account = $price_log->where($map)->sum('account');
        if(!$yj_account) $yj_account = 0;
        dump($yj_account);
        */

        $_map['buytime'] = array('between time',array($getdata['starttime'],$getdata['endtime']));
        $uids = myuids($uid);
        $_map['uid']  = array('IN',$uids);
        $all_sxfee = db('order')->where($_map)->sum('sx_fee');
        if(!$all_sxfee) $all_sxfee = 0;
        $all_ploss = db('order')->where($_map)->sum('ploss');
        if(!$all_ploss) $all_ploss = 0;

        $this->assign('_user',$_user);
        $this->assign('getdata',$getdata);
        $this->assign('all_sxfee',$all_sxfee);
        $this->assign('all_ploss',$all_ploss);
        /*
        $this->assign('hl_account',$hl_account);
        $this->assign('yj_account',$yj_account);
        */
        return $this->fetch();
    }

}
