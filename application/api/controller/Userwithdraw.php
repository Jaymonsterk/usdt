<?php

namespace app\api\controller;

use app\api\logic\BaseConfig;
use app\api\logic\InviteRecord;
use app\common\controller\Api;
use app\common\library\Utils;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;
use think\Url;

/**
 * 用户提现管理
 * @ApiInternal
 */
class Userwithdraw extends Api
{
	protected $noNeedLogin = ['index'];
	protected $noNeedRight = ['*'];

	//默认
	public function index() {
		$data = [
			'User Bank List'=>"OK",
		];
		$this->success(__('OK'),$data);
	}

	//列表
	public function list() {
		$result= $this->validate(request()->param(),[
			'bank_name' => 'chsAlphaNum',
			'card' => 'number',
		]);

		if ($result !== true) {
			$this->error(__($result));
		}
		$user = $this->auth->getUser()->toArray();
		$uid = $user['id'];

		$where['uid'] = ['=',$uid];
		$bank_name = request()->param('bank_name');
		if($bank_name){
			$where['bank_name'] = ['like',$bank_name."%"];
		}
		$card = request()->param('card');
		if($card){
			$where['card'] = ['like',$card."%"];
		}

		$page_rows = 30;

		$data = Db::name('user_withdraw')->field('*')->where($where)->paginate($page_rows);

		$data = $data->toArray();
		foreach ($data['data'] as $k => &$v) {

		}

		$this->success(__('成功'),$data);
	}

	//保存
	public function save()
	{
		$result= $this->validate(request()->param(),[
			'bank_id' => 'require|number',
			'money' => 'require|number',
		]);

		if ($result !== true) {
			$this->error(__($result));
		}
		$user = $this->auth->getUser()->toArray();
		$uid = $user['id'];
		$uname = $user['username'];

		$bank_id = input("bank_id");
		//查找提现银行
		$bank_info = Db::name('user_bank_card')->where('id','=',$bank_id)->where('uid','=',$uid)->find();
		if(!$bank_info){
			$this->error(__('提现银行信息不存在'));
		}else{
			$bank_json = json_encode($bank_info);
		}

		$money = input("money");
		$time = time();

		$token_id = 1;
		//最低配置
		$min_limit = BaseConfig::getInstance()->getBaseConfig('min_outtoken_limit');
		if ($money < $min_limit) {
			$this->error(__('提币数量低于最低限额'));
		}

		$check = \app\chainex\logic\Usermoney::getInstance()->checkOuttokenLimit($uid);
		if (is_string($check)) {
			$this->error($check);
		}
		if ($check !== true) {
			if ($check < $money) {
				$this->error(__('可提取余额不足'));
			}
		}
		//手续费
		$total_fee = BaseConfig::getInstance()->getBaseConfig('outtoken_fee');
		//实际到账
		$real_total = $money - $total_fee;
		//判断余额
		$usermoney = Db::name('user_money')->where(['uid' => $uid,'token_id' => 1])->value('balance');
		if ($usermoney < $money) {
			$this->error(__('余额不足'));
		}

		Db::startTrans();

		try {

			$key = \fast\Random::alnum(12);

			//扣除余额
			Db::name('user_money')->where(['uid' => $uid,'token_id' => $token_id])->setInc('balance',-$money);
			//增加冻结
			Db::name('user_money')->where(['uid' => $uid,'token_id' => $token_id])->setInc('freeze',$money);
			$admin_id = InviteRecord::getInstance()->getUserAdminId($uid);
			//增加提币订单
			Db::name('user_withdraw')->insert([
				'uid' => $uid,
				'uname' => $uname,
				'money' => $money,
				'bank_id' => $bank_id,
				'json' => $bank_json,
				'createtime' => $time,
				'updatetime' => $time,
				'total_fee' => $total_fee,
				'real_money' => $real_total,
			]);
			//增加流水
			\app\chainex\library\Common::write_flow(
				$uid,
				1,
				Config::get('account_flow_type.balance'),
				Config::get('account_flow_sub_type.outtoken'),
				Config::get('opt_type.dute'),
				$money,
				'提现',
				$key
			);
			Db::commit();
		} catch (Exception $e) {
			Db::rollback();
			Log::error($e->getMessage());
			$this->error(__($e->getMessage()));
		}
		$this->success(__('提现成功'));

	}

	//获取单条记录
	public function info()
	{
		$id = input("id/d");
		if(!$id){
			$this->error(__('ID为空'));
		}
		$data = [];
		$user = $this->auth->getUser();
		$uid = $user['id'];
		$uname = $user['username'];

		$data = Db::name('user_withdraw')->where('id','=',$id)->where('uid','=',$uid)->find();
		if (!$data) {
			$this->error(__('收款信息为空'));
		}

		$this->success(__('成功'),$data);
	}

}

