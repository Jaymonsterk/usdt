<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Sms as Smslib;
use app\common\model\User;
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
	    //币种 USDT
	    $tips = "
	    温馨提示<br>
• 最小充值金额：1 USDT ，小于最小金额的充值将不会上账且无法退回。<br>
• 请勿向上述地址充值任何非USDT资产，否则资产将不可找回。<br>
• 您充值至上述地址后，需要整个网络节点的确认，1次网络确认后到账，3 次网络确认后可提币。<br>
• 您的充值地址不会经常改变，可以重复充值；如有更改，我们会尽量通过网站公告或邮件通知您。<br>
• 请务必确认电脑及浏览器安全，防止信息被篡改或泄露。<br>
";
	    $address = [
    		['network'=>'ERC20','address'=>'0x5202eab6062ed76c6f3dac789c7340ad55de4a08','tips'=>$tips],
    		['network'=>'TRC20','address'=>'0x5202eab6062ed76c6f3dac789c7340ad55de4a08','tips'=>$tips],
	    ];
	    $data = [
	    	'coin_type'=>'USDT',
		    'from_address'=>'0x5202eab6062ed76c6f3dac789c7340ad55de4a08',//唯一
		    'to_address'=>$address,
	    ];

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
     * @param string $my 是否我的交易
     */
    public function buy()
    {
	    //币种 USDT
	    $data = [
    		['id'=>1,'username'=>'user01','status'=>1,'num'=>'1000','rate'=>'6.5','total'=>6500,'type'=>'银行卡'],
    		['id'=>2,'username'=>'user02','status'=>2,'num'=>'2000','rate'=>'6.5','total'=>13000,'type'=>'银行卡'],
	    ];

        if ($data) {
            $this->success(__('Success'),$data);
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
	    $data = [
    		['id'=>1,'username'=>'user01','status'=>1,'num'=>'1000','rate'=>'6.5','total'=>6500,'type'=>'银行卡'],
    		['id'=>2,'username'=>'user02','status'=>2,'num'=>'2000','rate'=>'6.5','total'=>13000,'type'=>'银行卡'],
	    ];

        if ($data) {
            $this->success(__('Success'),$data);
        } else {
            $this->error(__('Error'));
        }
    }

    /**
     * 交易列表
     *
     * @ApiMethod (POST)
     * @param string $status 状态 1=交易中，2=已完成,3=已取消
     * @param string $my 是否我的交易
     */
    public function list()
    {
	    //币种 USDT
	    $data = [
    		['id'=>1,'username'=>'user01','status'=>1,'num'=>'1000','rate'=>'6.5','total'=>6500,'type'=>'银行卡'],
    		['id'=>2,'username'=>'user02','status'=>2,'num'=>'2000','rate'=>'6.5','total'=>13000,'type'=>'银行卡'],
	    ];

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
	    $usdt_rate = [
		    ['platform'=>"火币网",'usdt_rate'=>"6.5"],
		    ['platform'=>"本站",'usdt_rate'=>"6.55"],
	    ];

	    if ($usdt_rate) {
		    $this->success(__('Success'),$usdt_rate);
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
	public function order()
	{
		//币种 USDT
		$data = [
			['id'=>1,'username'=>'user01','status'=>1,'num'=>'1000','rate'=>'6.5','total'=>6500,'type'=>'银行卡','createtime'=>time(),'paytime'=>time()],
		];

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
		$bank_list = [
			['id'=>'1','bank_name'=>'中国银行','default'=>1,'bank_branch'=>'北京支行','card'=>'622612349876'],
			['id'=>'2','bank_name'=>'招商银行','default'=>0,'bank_branch'=>'天津支行','card'=>'622600009876'],
		];

		$usdt_rate = [
			['platform'=>"火币网",'usdt_rate'=>"6.5"],
			['platform'=>"本站",'usdt_rate'=>"6.55"],
		];

		$data = [
			'bank_list'=>$bank_list,//银行卡信息
			'usdt_rate'=>$usdt_rate,//汇率
			'balance'=>'1000.00',//余额
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
        $num = $this->request->post("num");
        $bank_id = $this->request->post("bank_id");
        //校验余额

		$ret = 1;
        if ($ret) {
            $this->success(__('成功'));
        } else {
            $this->error(__('验证码不正确'));
        }
    }
}
