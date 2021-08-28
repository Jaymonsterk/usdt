<?php

namespace app\api\logic;

use app\api\library\Common;
use app\api\library\Queue;
use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;

/**
 * Token接口
 */
class Stat
{

    private static $server = array();

    //获取所有未统计的用户
    public function getUserUids() {
        $date = date('Y-m-d',strtotime('-2 day'));
        $data = Db::name('user')->where('last_stat_date','<=',$date)->whereOr('last_stat_date',null)->field('id')->select();
        return $data;
    }

    //获取充值总额
    public function getIntokenAmount($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $amount = Db::name('intoken')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->sum('amount');
        return $amount;
    }

    //获取充值次数
    public function getIntokenNum($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $num = Db::name('intoken')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->count();
        return $num;
    }

    //获取提币总额
    public function getOuttokenAmount($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $amount = Db::name('outtoken')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->sum('num');
        return $amount;
    }

    //获取提币次数
    public function getOuttokenNum($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $num = Db::name('outtoken')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->count();
        return $num;
    }

    //获取订单次数
    public function getOrderNum($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $num = Db::name('contract_order')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->where('is_tmp','=','0')
            ->count();
        return $num;
    }

    //获取订单总本金
    public function getOrderCapital($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $num = Db::name('contract_order')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->where('is_tmp','=','0')
            ->sum('capital');
        return $num;
    }

    //获取订单总手续费
    public function getOrderServerFee($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $num = Db::name('contract_order')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->where('is_tmp','=','0')
            ->sum('service_total');
        return $num;
    }

    //获取订单总盈亏
    public function getOrderProfit($uid) {
        $start_time = strtotime('-2 day');
        $end_time = strtotime('-1 day');
        $num = Db::name('contract_order')->where('uid','=',$uid)
            ->where('create_time','>=',$start_time)
            ->where('create_time','<=',$end_time)
            ->where('is_tmp','=','0')
            ->sum('profit');
        return $num;
    }

    //获取统计时间的余额
    public function getBalance($uid) {
        return Db::name('user_money')->where('uid','=',$uid)->value('balance');
    }

    //保存统计数据
    public function add($data) {
        Db::name('stat_money')->insert($data);
    }




    /**
     * 单例入口
     * @return mixed
     */
    public static function getInstance() {
        $className =  get_called_class();
        if(!isset(self::$server[$className])){
            self::$server[$className] = new $className();
        }
        return self::$server[$className];
    }
}
