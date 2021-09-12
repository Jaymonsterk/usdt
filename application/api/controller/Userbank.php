<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Utils;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;
use think\Url;

/**
 * 用户提现银行卡管理
 */
class Userbank extends Api
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

		$data = Db::name('user_bank_card')->field('*')->where($where)->paginate($page_rows);

		$data = $data->toArray();
		foreach ($data['data'] as $k => &$v) {

		}

		$this->success(__('成功'),$data);
	}

	//保存
	public function save()
	{
		$result= $this->validate(request()->param(),[
			'id' => 'number',
			'name' => 'require|chsAlphaNum',
			'bank_name' => 'require|chsAlphaNum',
			'card' => 'require|number',
			//'status' => 'number',
			'bank_branch' => 'chsAlphaNum',
			'note' => 'chsAlphaNum',
		]);

		if ($result !== true) {
			$this->error(__($result));
		}
		$user = $this->auth->getUser()->toArray();
		$uid = $user['id'];
		$uname = $user['username'];

		$id = input("id","");
		$name = input("name");
		$bank_name = input("bank_name");
		$card = input("card");
		$status = input("status",0);
		$bank_branch = input("bank_branch");
		$note = input("note");
		$time = time();

		//数据
		$data = [
			'uid' => $uid,
			'uname' => $uname,
			'name' => $name,
			'bank_name' => $bank_name,
			'card' => $card,
			'bank_branch' => $bank_branch,
			'note' => $note,
			'status' => $status,
			'createtime' => $time,
			'updatetime' => $time,
		];
		if($id){
			//更新
			$ret = Db::name('user_bank_card')->where("id",$id)->update($data);
			if ($ret) {
				$this->success(__('OK'),$data);
			}else{
				$this->error(__('失败'),$data);
			}
		}else{
			//插入
			$ret = Db::name('user_bank_card')->insert($data);
			if ($ret) {
				$this->success(__('OK'),$data);
			}else{
				$this->error(__('失败'),$data);
			}
		}
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

		$data = Db::name('user_bank_card')->where('id','=',$id)->where('uid','=',$uid)->find();
		if (!$data) {
			$this->error(__('收款信息为空'));
		}

		$this->success(__('成功'),$data);
	}

	//删除单条记录
	public function delete()
	{
		$id = input("id/d");
		if(!$id){
			$this->error(__('ID为空'));
		}
		$data = [];
		$user = $this->auth->getUser();
		$uid = $user['id'];
		$uname = $user['username'];

		$data = Db::name('user_bank_card')->where('id','=',$id)->where('uid','=',$uid)->find();
		if (!$data) {
			$this->error(__('银行卡不存在'));
		}else{
            Db::name('user_bank_card')->where('id','=',$id)->where('uid','=',$uid)->delete();
        }

		$this->success(__('删除成功'),$id);
	}

}

