<?php

namespace app\api\logic;

use fast\Random;
use think\Config;
use think\Db;

/**
 * Token接口
 */
class Zuobi
{

    private static $server = array();


    //作弊
    public function check($symbol,$fd_price,$data) {

        //查询最新一条数据
        $one = Db::name('token_market')->where('symbol','=',$symbol)
            ->order('create_time desc')
            ->limit(0,1)
            ->find();
//        var_dump('作弊',$one);


        //查询是否作弊时间
//        $check = Db::name('token_no_market_time')->where('symbol','=',$symbol)
//            ->find();
//        if (!$check) {
//            $data['open'] = $data['open'] + $fd_price;
//            $data['close'] = $data['close'] + $fd_price;
//            $data['high'] = $data['high'] + $fd_price;
//            $data['low'] = $data['low'] - $fd_price;
//            return $data;
//        }

        $time = time();

//        if ($time < $check['start_time'] || $time > $check['end_time']) {
//            $data['open'] = $data['open'] + $fd_price;
//            $data['close'] = $data['close'] + $fd_price;
//            $data['high'] = $data['high'] + $fd_price;
//            $data['low'] = $data['low'] - $fd_price;
//            return $data;
//        }

        //查询作弊详情
        $detail = Db::name('token_no_market_time_detail')->where('start_time','<=',$time)
            ->where('end_time','>',$time)->find();
        if (!$detail) {
            $data['open'] = $data['open'] + $fd_price;
            $data['close'] = $data['close'] + $fd_price;
            $data['high'] = $data['high'] + $fd_price;
            $data['low'] = $data['low'] - $fd_price;
            return $data;
        }

        $num = $detail['num'];
        if ($num > 0) {
//            $data['open'] = $one['open'] + $num;
            $data['close'] = $one['close'] + $num;
            $data['high'] = $one['high'] + $num;
//            $data['low'] = $one['low'] + $num;
        } else {
            var_dump('====================作弊=================',$num);
//            $data['open'] = $one['open'] + $num;
            $data['close'] = $one['close'] + $num;
//            $data['high'] = $one['high'] + $num;
            $data['low'] = $one['low'] + $num;
        }


        return $data;
    }

    //对k线进行作弊
    public function checkKline($symbol,$data) {
        $check = Db::name('token_no_market_time')
            ->where('start_time','<=',$data['id'])
            ->where('end_time','>=',$data['id'])
            ->where('symbol','=',$symbol)
            ->find();
        if (!$check) {
            $check = Db::name('token_no_market_time')
                ->where('start_time','<=',$data['id'] - 60)
                ->where('end_time','>=',$data['id'])
                ->where('symbol','=',$symbol)
                ->find();
            if (!$check) {
                return ['is' => 0,'data' => $data];
            }
        }

        $num = $check['num'];
        if ($num > 0) {
//            $data['open'] = $data['open'] + $num;
            $data['close'] = $data['close'] + $num;
            $data['high'] = $data['high'] + $num;
//            $data['low'] = $data['low'] + $num;
        } else {
//            $data['open'] = $data['open'] + $num;
            $data['close'] = $data['close'] + $num;
//            $data['high'] = $data['high'] + $num;
            $data['low'] = $data['low'] + $num;
        }

//        Db::name('token_no_market_time')->where('id','=',$check['id'])->update(['kline_status' => 1]);

        return ['is' => 1,'data' => $data];
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
