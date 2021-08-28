<?php

namespace app\api\logic;

use app\api\library\Common;
use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;

/**
 * Token接口
 */
class Tran
{

    private static $server = array();


    //查询最近成交
    public function getFinishTran($symbol,$page_rows) {
        $data = Db::name('contract_order')->field('finish_time,opt,finish_price')->where(['symbol' => $symbol,'status' => '1'])
            ->order('create_time desc')
            ->paginate($page_rows,true,[
                'query' => []
            ]);
        return $data;
    }

    //添加合约交易
    public function addTran($uid,$token_id,$symbol,$capital,$heaver,$stop_profit_rate,$stop_lose_rate,$opt) {
        $service_rate = $this->getTranFeeRate($uid);
        $heaver_total = $heaver * $capital;
        $service_total = $capital * $service_rate;
        $order_total = $capital + $service_total;

        //判断余额是否足够
        if (Db::name('user_money')->where(['uid' => $uid,'token_id' => $token_id])->value('balance') < $order_total) {
            return '余额不足';
        }

        $key = 'contract_tran_' . Random::alnum();

        //获取币对价格
        $create_price = $this->getPriceBySymbol($symbol);

        //作弊
        $price_dute = $this->getZBPrice($uid,$symbol);
        if ($opt == 1) {
            $create_price = $create_price + $price_dute;
        } else {
            $create_price = $create_price - $price_dute;
        }


        //获取止盈止损价格
        $stop_profit_price = $this->getStopWinPrice($capital,$heaver,$create_price,$stop_profit_rate,$opt);
        $stop_lose_price = $this->getStopLosePrice($capital,$heaver,$create_price,$stop_lose_rate,$opt);


        //查询是否做单组
        $group_id = Db::name('user')->where('id','=',$uid)->value('group_id');
        $is_tmp = 0;
        if ($group_id == 9) {
            $is_tmp = 1;
        }

        Db::startTrans();
        try {
            //扣除余额
            Db::name('user_money')->where(['uid' => $uid,'token_id' => $token_id])->setInc('balance',-$order_total);
            Db::name('user_money')->where(['uid' => $uid,'token_id' => $token_id])->setInc('freeze',$order_total);
            //添加交易单
            Db::name('contract_order')->insert([
                'uid' => $uid,
                'capital' => $capital,
                'heaver' => $heaver,
                'service_rate' => $service_rate,
                'heaver_total' => $heaver_total,
                'order_total' => $order_total,
                'create_price' => $create_price,
                'finish_price' => 0,
                'profit' => 0,
                'create_time' => time(),
                'stop_profit_rate' => $stop_profit_rate,
                'stop_lose_rate' => $stop_lose_rate,
                'symbol' => $symbol,
                'token_id' => $token_id,
                'status' => 0,
                'opt' => $opt,
                'key' => $key,
                'stop_profit_price' => $stop_profit_price,
                'stop_lose_price' => $stop_lose_price,
                'service_total' => $service_total,
                'is_tmp' => $is_tmp
            ]);
            //增加流水
            Common::write_flow(
                $uid,
                1,
                Config::get('account_flow_type.balance'),
                Config::get('account_flow_sub_type.contract_tran'),
                Config::get('opt_type.dute'),
                $order_total,
                '开仓',
                $key
            );
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            return $e->getMessage();
        }
        return true;
    }

    public function getProfit($capital,$heaver,$price,$create_price,$opt) {
        //(行情价格-建仓价格) * 0.05( 50倍杠杆) *本金
        $pp = ($price - $create_price) * ($heaver / 1000) * $capital;
        $pp = sprintf("%.2f",$pp);
        if ($opt == 1) {
            //买入
            if ($pp > 0) {
                //盈利
                return $pp;
            } else {
                //亏损
                return $pp;
            }
        }
        if ($opt == 2) {
            //卖出
            if ($pp > 0) {
                //亏损
                return -$pp;
            } else {
                //盈利
                return -$pp;
            }
        }
        return $pp;
    }

    //
    public function getStopWinPrice($capital,$heaver,$create_price,$rate,$opt) {
        //(行情价格-建仓价格) * 0.05( 50倍杠杆) * 本金 * 比率
        $pp = $rate / ($heaver / 1000);
        if ($opt == 1) {
            return $create_price + abs($pp);
        }
        return $create_price - abs($pp);
    }

    public function getStopLosePrice($capital,$heaver,$create_price,$rate,$opt) {
        //(行情价格-建仓价格) * 0.05( 50倍杠杆) * 本金 * 比率
        $pp = $rate / ($heaver / 1000);
        if ($opt == 1) {
            return $create_price - abs($pp);
        }
        return $create_price + abs($pp);
    }

    public function getZBPrice($uid,$symbol) {
        $data = Db::name('user_tran_price_control')
            ->where('uid','=',$uid)
            ->where('status','=','1')
            ->where('symbol','=',$symbol)
            ->find();
        if (!$data) {
            return BaseConfig::getInstance()->getBaseConfig('tran_price_dute');
        }
        return $data['num'];
    }

    public function getOrder($uid,$type,$page_rows) {
        $order = 'create_time desc';
        if ($type == 1) {
            $order = 'finish_time desc';
        }
        $data = Db::name('contract_order')
            ->where('uid','=',$uid)
            ->where('status','=',strval($type))
            ->order($order)
            ->paginate($page_rows,true,[
                'query' => []
            ]);
        if (count($data) == 0) {
            return $data;
        }
        if ($type == 0) {
            $data = $data->toArray();
            foreach ($data['data'] as $k => &$v) {
                $price = $this->getPriceBySymbol($v['symbol']);
                $v['profit'] = $this->getProfit($v['capital'],$v['heaver'],
                    $price,$v['create_price'],$v['opt']);
            }
        }
        return $data;
    }

    //获取用户交易成功单数
    public function getUserCompleteTranNum($uid) {
        return Db::name('contract_order')->where('uid','=',$uid)
            ->where('status','=','1')
            ->count();
    }

    //修改止盈止损配置
    public function updateSet($uid,$id,$stop_profit_rate,$stop_lose_rate) {
        Db::startTrans();
        try {
            //获取数据
            $data = Db::name('contract_order')->lock(true)->where('uid','=',$uid)->where('id','=',$id)->find();
            if ($data['status'] != '0') {
                Db::rollback();
                return '订单已平仓';
            }

            //获取止盈止损价格
            $stop_profit_price = $this->getWinLostPrice($data['capital'],$data['heaver'],$data['create_price'],$stop_profit_rate,$data['opt'],1);
            $stop_lose_price = $this->getWinLostPrice($data['capital'],$data['heaver'],$data['create_price'],$stop_lose_rate,$data['opt'],2);

            Db::name('contract_order')->where('uid','=',$uid)
                ->where('id','=',$id)
                ->update([
                    'stop_profit_rate' => $stop_profit_rate,
                    'stop_lose_rate' => $stop_lose_rate,
                    'stop_profit_price' => $stop_profit_price,
                    'stop_lose_price' => $stop_lose_price
                ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            return $e->getMessage();
        }
        return true;
    }

    //计算止盈止损价格
    public function getWinLostPrice($capital,$heaver,$price,$rate,$opt,$type) {
        $n = $capital / $heaver;
        $add_price = $n * $rate;

        if ($opt == 1) {
            //买入
            if ($type == 1) {
                //获取止盈
                return $price + $add_price;
            } else {
                return $price - $add_price;
            }
        } else {
            //卖出
            if ($type == 1) {
                //获取止盈
                return $price - $add_price;
            } else {
                return $price + $add_price;
            }
        }
    }

    //获取币对价格
    public function getPriceBySymbol($symbol) {
        return Db::name('token_market')->where('symbol','=',$symbol)
            ->order('create_time desc')
            ->limit(0,1)
            ->value('close');
    }

    //检查满足平仓的交易单
    public function checkSureFinish() {
        $price = $this->getPriceBySymbol('BTC/USDT');
        dump($price);
        $data = Db::name('contract_order')
            ->where('status','=','0')
            ->where(function ($query) use ($price) {
                $query->where('stop_profit_price','<=',$price)
                    ->whereOr('stop_lose_price','>=',$price);
            })
            ->select();
        dump($data);
        if (!$data) {
            return false;
        }
        foreach ($data as $k => $v) {
            $this->sysFinish($v,$price,1);
        }
    }

    //平仓【自动平仓】
    public function sysFinish($data,$price,$finish_type = 1) {

        //盈利金额
        $win_lose_total = 0;
        //是否平仓
        $is_finish = false;
        //平仓价格
        $finish_price = 0;

        if ($data['opt'] == 1) {
            //买入
            if ($price >= $data['stop_profit_price']) {
                //平仓，盈利
                $is_finish = true;
                $finish_price = $data['stop_profit_price'];
                $win_lose_total = $this->getProfit($data['capital'],$data['heaver'],
                    $finish_price,$data['create_price'],$data['opt']);
            }
            if ($price <= $data['stop_lose_price']) {
                //平仓，亏损
                $is_finish = true;
                $finish_price = $data['stop_lose_price'];
                $win_lose_total = $this->getProfit($data['capital'],$data['heaver'],
                    $finish_price,$data['create_price'],$data['opt']);
            }
        }

        if ($data['opt'] == 2) {
            //卖出
            if ($price >= $data['stop_lose_price']) {
                //亏损
                $is_finish = true;
                $finish_price = $data['stop_lose_price'];
                $win_lose_total = $this->getProfit($data['capital'],$data['heaver'],
                    $finish_price,$data['create_price'],$data['opt']);
            }
            if ($price <= $data['stop_profit_price']) {
                //盈利
                $is_finish = true;
                $finish_price = $data['stop_profit_price'];
                $win_lose_total = $this->getProfit($data['capital'],$data['heaver'],
                    $finish_price,$data['create_price'],$data['opt']);
            }
        }

        var_dump($price,$data['stop_lose_price'],$data['stop_profit_price'],$data['opt']);

        var_dump('是否：',$is_finish);

        if (!$is_finish) {
            return false;
        }

        //更新订单状态
        Db::startTrans();
        try {

            $status = Db::name('contract_order')->lock(true)->where('id','=',$data['id'])->value('status');
            if ($status == '1') {
                Db::rollback();
                return false;
            }

            Db::name('contract_order')->lock(true)->where('id','=',$data['id'])->find();

            //修改订单状态
            Db::name('contract_order')->where('id','=',$data['id'])->update([
                'finish_price' => $finish_price,
                'profit' => $win_lose_total,
                'finish_type' => $finish_type,
                'status' => 1,
                'finish_time' => time()
            ]);

            $opt = Config::get('opt_type.dute');
            if ($win_lose_total > 0) {
                $opt = Config::get('opt_type.add');
            }

            $result_total = $data['capital'] + $win_lose_total;

            $del_freeze = $data['capital'] + $data['service_total'];

            Db::name('user_money')->where('uid','=',$data['uid'])->where('token_id','=',1)->setInc('balance',$result_total);

            Db::name('user_money')->where('uid','=',$data['uid'])->where('token_id','=',1)->setInc('freeze',-$del_freeze);

            //增加流水
            Common::write_flow(
                $data['uid'],
                1,
                Config::get('account_flow_type.balance'),
                Config::get('account_flow_sub_type.finish_contract_tran'),
                $opt,
                abs($result_total),
                '系统平仓',
                $data['key']
            );
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            return $e->getMessage();
        }
        return true;
    }

    //用户平仓
    public function userFinish($uid,$id,$finish_type = 2) {
        $data = Db::name('contract_order')->where('id','=',$id)->where('uid','=',$uid)->find();

        if ($data['status'] != '0') {
            return '订单已平仓';
        }

        $price = $this->getPriceBySymbol($data['symbol']);

        $win_lose_total = $this->getProfit($data['capital'],$data['heaver'],
            $price,$data['create_price'],$data['opt']);

        //更新订单状态
        Db::startTrans();
        try {

            $status = Db::name('contract_order')->lock(true)->where('id','=',$id)->value('status');
            if ($status == '1') {
                Db::rollback();
                return false;
            }


            Db::name('contract_order')->lock(true)->where('id','=',$id)->where('uid','=',$uid)->find();

            //修改订单状态
            Db::name('contract_order')->where('id','=',$id)->update([
                'finish_price' => $price,
                'profit' => $win_lose_total,
                'finish_type' => $finish_type,
                'status' => 1,
                'finish_time' => time()
            ]);

//            $opt = Config::get('opt_type.dute');
//            if ($win_lose_total > 0) {
//                $opt = Config::get('opt_type.add');
//            }

            $result_total = $data['capital'] + $win_lose_total;

            $del_freeze = $data['capital'] + $data['service_total'];

            Db::name('user_money')->where('uid','=',$data['uid'])->where('token_id','=',1)->setInc('balance',$result_total);

            Db::name('user_money')->where('uid','=',$data['uid'])->where('token_id','=',1)->setInc('freeze',-$del_freeze);

            //增加流水
            Common::write_flow(
                $data['uid'],
                1,
                Config::get('account_flow_type.balance'),
                Config::get('account_flow_sub_type.finish_contract_tran'),
                Config::get('op_type.add'),
                abs($win_lose_total),
                '用户平仓',
                $data['key']
            );
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            return $e->getMessage();
        }
        return true;
    }


    //获取平仓盈利数量
    public function getFinishWinLostTotal($heaver,$price,$now_price,$opt) {

        $win = bcmul(($now_price - $price),$heaver,2);
        $zf = $now_price - $price;
        if ($opt == 1) {
            //买入
            if ($zf > 0) {
                return $win;
            } else {
                return $win;
            }
        } else if ($opt == 2) {
            //卖出
            if ($zf > 0) {
                return -$win;
            } else {
                return -$win;
            }
        }

//        $n = $capital / $heaver;
//        $price100 = $n * $rate;
//
//        $now_price = $now_price - $price;
//
//        //实际盈利数量
//        $pp = ($now_price / $price100) * $capital;
//
//        if ($pp > 0) {
//            if ($opt == 1) {
//                //买入
//                //盈利了
//                return $pp;
//            } else {
//                //损失了
//                return -$pp;
//            }
//        } else {
//            if ($opt == 1) {
//                //买入
//                //损失了
//                return $opt;
//            } else {
//                return -$opt;
//            }
//        }
    }

    //获取交易手续费
    public function getTranFeeRate($uid) {
        $level = Db::name('user')->where('id','=',$uid)->value('level');
        $default_rate = Config::get('service_rate');
        $tran_fee_rate = Db::name('fenxiao_config')->where('level','=',$level)->value('tran_fee_rate');
        if (!$tran_fee_rate) {
            return $default_rate;
        }
        return $tran_fee_rate;
    }

    //获取订单盈亏
    public function getOrderProfit($ids) {
        $data = Db::name('contract_order')->where('id','in',$ids)->select();
        if (!$data) {
            return $data;
        }

        $price = $this->getPriceBySymbol('BTC/USDT');

        foreach ($data as $k => &$v) {


            if ($v['opt'] == 1) {
                //买入
                if ($price >= $v['stop_profit_price']) {
                    $price = $v['stop_profit_price'];
                }
                if ($price <= $v['stop_lose_price']) {
                    $price = $v['stop_lose_price'];
                }
            }

            if ($v['opt'] == 2) {
                //卖出
                if ($price >= $v['stop_lose_price']) {
                    $price = $v['stop_lose_price'];
                }
                if ($price <= $v['stop_profit_price']) {
                    $price = $v['stop_profit_price'];
                }
            }




            $win_lose_total = $this->getProfit($v['capital'],
                $v['heaver'],$price,$v['create_price'],$v['opt']);
            $v['profit'] = $win_lose_total;
//            $v['price'] = $price;
            unset($v['symbol']);
            unset($v['heaver']);
//            unset($v['create_price']);
//            unset($v['opt']);
            $v['price'] = $price;
        }
        return $data;
    }

    //获取所有持仓订单
    public function getCCOrderIds($uid) {
        $data = Db::name('contract_order')->where('uid','=',$uid)->where('status','=','0')->field('id')->select();
        return array_column($data,'id');
    }

    //获取权益
    public function getQuanyi($uid) {
        $balance = Db::name('user_money')->where('uid','=',$uid)->value('balance');
        $order_ids = $this->getCCOrderIds($uid);
        if (!$order_ids) {
            return [
                'profit' => [
                    'trends_balance' => '0',
                    'no_complete_balance' => '0',
                    'risk' => '0'
                ],
                'order_profits' => []
            ];
        }
        $profits = $this->getOrderProfit($order_ids);
        $profit = 0;
        $order_profit = [];
        foreach ($profits as $k => $v) {
            $profit += $v['profit'];
            $order_profit[] = [
                'id' => $v['id'],
                'profit' => (string)$v['profit']
            ];
        }
        return [
            'profit' => [
                'trends_balance' => (string)($balance + $profit),
                'no_complete_balance' => (string)$profit,
                'risk' => '0' //$profit > 0 ? 0 : 1
            ],
            'order_profits' => $order_profit
        ];
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
