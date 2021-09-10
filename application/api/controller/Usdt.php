<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\Db;
use think\Hook;

/**
 * 数字货币买卖交易
 */
class Usdt extends Api
{
    protected $noNeedLogin = '';
    protected $noNeedRight = '*';

    /**
     * 充币(返回ERC20和TRC20地址）
     *
     * @ApiMethod (POST)
     */
    public function buy_info()
    {
	    $data = \app\api\logic\Usdt::getInstance()->getBuyInfo();

	    if ($data) {
            $this->success(__('获取充币地址成功'),$data);
        } else {
            $this->error(__('地址配置不正确'));
        }
    }

    /**
     * 提交充币凭证
     *
     * @ApiMethod (POST)
     * @param string $status 状态 1=交易中，2=已完成,3=已取消
     */
    public function buy()
    {
	    //币种 USDT
	    $user = $this->auth->getUserinfo();
	    $params = $this->request->param();

	    $result= $this->validate(request()->param(),[
		    'address'  => 'require|max:64',
		    'image'   => 'require',
		    'amount' => 'require|number|min:1',
	    ]);

	    if ($result !== true) {
		    $this->error(__($result));
	    }

	    //获取参数
	    $time = time();
	    $params['user_id'] = $user['id'];
	    $params['username'] = $user['username'];
	    $params['createtime'] = $time;
	    $params['updatetime'] = $time;
	    $data = \app\api\logic\Usdt::getInstance()->Buy($params);
	    $params['id'] = $data;

        if ($data) {
            $this->success(__('Success'),$params);
        } else {
            $this->error(__('Error'));
        }
    }

    /**
     * 充币订单列表
     *
     * @ApiMethod (POST)
     * @param string $status 状态 1=交易中，2=已完成,3=已取消
     * @param string $my 是否我的交易
     */
    public function buy_order_list()
    {
	    //币种 USDT
	    $user = $this->auth->getUserinfo();

	    //条件
	    $status = $this->request->post("status","");
	    $my = $this->request->post("my","");
        $page_size = $this->request->post("page_size","20");
        $where = [];
	    if($status){
		    $where['status'] = $status;
	    }
	    if($my){
		    $where['user_id'] = $user['id'];
	    }

	    //获取数据
	    $data = \app\api\logic\Usdt::getInstance()->getBuyOrderList($where,$page_size);

        if ($data) {
            $this->success(__('Success'),$data);
        } else {
            $this->error(__('Error'));
        }
    }

    /**
     * 卖币交易列表
     *
     * @ApiMethod (POST)
     * @param string $status 状态 1=交易中，2=已完成,3=已取消
     * @param string $my 是否我的交易
     */
    public function sell_order_list()
    {
	    //币种 USDT
	    $user = $this->auth->getUserinfo();

	    $status = $this->request->post("status","");
	    $my = $this->request->post("my","");
	    $page_size = $this->request->post("page_size","20");
	    $where = [];
	    if($status){
		    $where['status'] = $status;
	    }
	    if($my){
		    $where['user_id'] = $user['id'];
	    }
	    $data = \app\api\logic\Usdt::getInstance()->getSellOrderList($where,$page_size);

        if ($data) {
            $this->success(__('Success'),$data);
        } else {
            $this->error(__('Error'));
        }
    }

    /**
     * 卖币虚拟用户列表
     *
     * @ApiMethod (POST)
     * @param string $status 状态 1=交易中，2=已完成,3=已取消
     * @param string $my 是否我的交易
     */
    public function sell_user_list()
    {
        //币种 USDT
        $user = $this->auth->getUserinfo();

        $where = [];
        $where['status'] = 1;
        $data = db("user_virtual")->where($where)->select();

        if ($data) {
            $this->success(__('Success'),$data);
        } else {
            $this->error(__('Error'));
        }
    }

	/**
	 * 最新USDT汇率
	 */
    public function latest_price()
    {
	    //汇率
	    $ret = \app\api\logic\Usdt::getInstance()->getLatestPrice();

	    if ($ret) {
		    $this->success(__('Success'),$ret);
	    } else {
		    $this->error(__('Error'));
	    }
    }

	/**
	 * 订单历史信息
	 *
	 * @ApiMethod (POST)
	 * @param string $status 状态 1=交易中，2=已完成,3=已取消
	 */
	public function my_sell_order()
	{
		//币种 USDT
		$user = $this->auth->getUserinfo();

		$status = $this->request->post("status","");
		$where = [];
		$where = [
			"user_id"=>$user['id'],
		];

		if($status){
			$where['status'] = $status;
		}
		$data = \app\api\logic\Usdt::getInstance()->getSellOrderList($where);

		if ($data) {
			$this->success(__('Success'),$data);
		} else {
			$this->error(__('Error'));
		}
	}

	/**
	 * 获取卖币信息
	 */
	public function sell_info()
	{
		//币种 USDT
		$user = $this->auth->getUserinfo();
		$bank_list = Db::name('user_bank_card')->where('uid','=',$user['id'])->select();
		$usdt_rate = \app\api\logic\Usdt::getInstance()->getLatestPrice();
		$balance = \app\api\logic\Usdt::getInstance()->getBalance($user['id']);

		$data = [
			'bank_list'=>$bank_list,//银行卡信息
			'usdt_rate'=>$usdt_rate,//汇率
			'balance'=>$balance,//余额
		];

		if ($data) {
			$this->success(__('获取卖币信息成功'),$data);
		} else {
			$this->error(__('配置不正确'));
		}
	}

    /**
     * 发布广告卖币
     *
     * @ApiMethod (POST)
     * @param string $num Coin数量
     * @param string $bank_id 银行卡ID
     */
    public function sell()
    {
	    //币种 USDT
	    $user = $this->auth->getUserinfo();
	    $params = $this->request->param();

	    $result= $this->validate(request()->param(),[
		    'bank_id'   => 'require|number',
		    'num' => 'require|number|min:1',
	    ]);

	    if ($result !== true) {
		    $this->error(__($result));
	    }

        $uid = $user['id'];
        $amount = -1*$params['num'];

	    //余额判断
	    $balance = \app\api\logic\Usdt::getInstance()->getBalance($user['id']);

	    if($balance<$params['num']){
		    $this->error(__('余额不足'));
	    }

	    //银行卡判断
	    $json = Db::name('user_bank_card')->where('id','=',$params['bank_id'])->where('uid','=',$user['id'])->find();
	    if(!$json){
		    $this->error(__('银行卡不存在'));
	    }

	    $rate_arr = \app\api\logic\Usdt::getInstance()->getLatestPrice();

	    //获取参数
	    $time = time();
	    $params['user_id'] = $user['id'];
	    $params['username'] = $user['username'];
	    $params['rate'] = $rate_arr['local'];
	    $params['amount'] = $params['num']*$rate_arr['local'];
	    $params['json'] = json_encode($json);
	    $params['createtime'] = $time;
	    $params['updatetime'] = $time;
	    $data = \app\api\logic\Usdt::getInstance()->Sell($params);
	    $params['id'] = $data;

        if ($data) {
            //扣除U
            \app\admin\model\User::useaMoney($uid,$amount,3);

            $this->success(__('卖币成功'),$params);
        } else {
            $this->error(__('验证码不正确'));
        }
    }
}
