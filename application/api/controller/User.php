<?php

namespace app\api\controller;

use app\api\logic\BaseConfig;
use app\api\logic\InviteRecord;
use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use fast\Random;
use think\Config;
use think\Db;
use think\Validate;

/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

    }

    /**
     * 会员中心
     */
    public function index()
    {
        $data =[];
        $user=$this->auth->getUser();
        $user = (new \app\common\model\User())->find($user->id);
        $data['user_info'] = $user;
        $this->success('',$data);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @param string $account  账号
     * @param string $password 密码
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     *
     * @ApiInternal
     * @ApiMethod (POST)
     * @param string $mobile  手机号
     * @param string $captcha 验证码
     */
    public function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email    邮箱
     * @param string $mobile   手机号
     * @param string $invite_code   邀请码
     * @param string $code     验证码
     */
    public function register()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $email = $this->request->post('email');
        $mobile = $this->request->post('mobile');
        $code = $this->request->post('code');
        $invite_code = $this->request->post('invite_code');
        if ($invite_code) {
            //判断邀请码是否存在
            if (Db::name('user')->where(['invite_code' => $invite_code])->count() == 0) {
                $this->error('邀请码不存在');
            }
        }

        //判断是否强制邀请注册
        $must_invite_code = BaseConfig::getInstance()->getBaseConfig('must_invite_code');
        if ($must_invite_code == 1 && !$invite_code) {
            $this->error(__('请输入邀请码'));
        }

        if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        $ret = Sms::check($mobile, $code, 'register');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        $ret = $this->auth->register($username, $password, $email, $mobile, ['invite_code'=>$invite_code]);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            //邀请信息
            if ($data && $invite_code != '') {
                //查询邀请人信息
//                $invite_userinfo = \app\common\model\User::get(['invite_code' => $invite_code]);
//                if ($invite_userinfo) {
//                    InviteRecord::getInstance()->record($data['userinfo']['id'],$invite_userinfo->id);
//                }
            }
            if ($invite_code == '') {
//                InviteRecord::getInstance()->recordNoInviteCode($data['userinfo']['id']);
            }
            //end 邀请信息
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @param string $avatar   头像地址
     * @param string $username 用户名
     * @param string $nickname 昵称
     * @param string $bio      个人简介
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio');
        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Username already exists'));
            }
            $user->username = $username;
        }
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success();
    }

    /**
     * 修改邮箱
     *
     * @ApiMethod (POST)
     * @param string $email   邮箱
     * @param string $captcha 验证码
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @param string $mobile  手机号
     * @param string $captcha 验证码
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     *
     * @ApiInternal
     * @ApiMethod (POST)
     * @param string $platform 平台名称
     * @param string $code     Code码
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @param string $mobile      手机号
     * @param string $newpassword 新密码
     * @param string $captcha     验证码
     */
    public function resetpwd()
    {
        $type = $this->request->post("type");
        $mobile = $this->request->post("mobile");
        $email = $this->request->post("email");
        $newpassword = $this->request->post("newpassword");
        $captcha = $this->request->post("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if ($type == 'mobile') {
            if (!Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            $user = \app\common\model\User::getByMobile($mobile);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Sms::check($mobile, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpwd');
        } else {
            if (!Validate::is($email, "email")) {
                $this->error(__('Email is incorrect'));
            }
            $user = \app\common\model\User::getByEmail($email);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Ems::check($email, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Ems::flush($email, 'resetpwd');
        }
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

	/**
	 * 获取邀请信息
	 */
	public function get_invite_info() {

		$user = $this->auth->getUser();
		$uid = 0;
		if ($user) {
			$uid = $user->toArray()['id'];
		} else {
			$this->error(__('未登录'));
		}

//		$share_info = \app\api\logic\User::getInstance()->getHaiBao($uid);
		$data = \app\api\logic\User::getInstance()->getInviteInfo($uid);
		$data['invite_award_msg'] = BaseConfig::getInstance()->getBaseConfig('invite_award_msg');
//		$bg = Db::name('ads')->where('type','=','3')->value('image');
//		$bg = Url::build($bg,'',false,true);

		$this->success(__('OK'),[
//			'img' => $share_info['haibao_url'],
//			'link' => $share_info['link'],
			'username' => $user['username'],
			'invite_code' => $user['invite_code'],
			'invite_url' => $data['invite_url'],
			'invite_award_msg' => $data['invite_award_msg'],
//			'bg' => $bg
		]);
	}

	/*
	 * 获取邀请列表
	 */
	public function get_invite_list() {
		$user = $this->auth->getUser();
		$uid = 0;
		if ($user) {
			$uid = $user->toArray()['id'];
		}

		$page_rows = 10;

		$data = \app\api\logic\User::getInstance()->getInviteList($uid,$page_rows);

		$data = $data->toArray();

		//获取邀请总数
		$invite_num = \app\api\logic\User::getInstance()->getInviteNum($uid);

		//查询邀请总奖励
		//$invite_award = \app\api\logic\Usermoney::getInstance()->getInviteAwardTotal($uid);

		$data['invite_num'] = $invite_num;
		$data['invite_award'] = 0;

		//格式化数据
		foreach ($data['data'] as $k => &$v) {
			$v['tran_num'] = \app\api\logic\Tran::getInstance()->getUserCompleteTranNum($v['uid']);
			$v['expensess'] = 1;
		}

		$this->success(__('Success'),$data);

	}

    public function team(){
        $user =$this->auth->getUser();
        $uid=$user->id;
        $userModel =new \app\admin\model\User();
        $user =$userModel->find($uid);
        $res =$this->getTree($uid,0);
        $price =$user->money;
        $arr1[0]=[];
        $arr1[1]=[];
        $p1count =0;
        $p2count =0;
        foreach ($res as $key =>$v){
            $v['mobile']=hidtel($v['mobile']);
            if($v['lv']==0){
                array_push($arr1[0],$v);
                $p1count++;
            }
            if($v['lv']==1){
                array_push($arr1[1],$v);
                $p2count++;
            }
            $price+=$v['money'];
        }
        $data=['arr'=>$arr1,'price'=>$price,'count'=>count($res),'p1count'=>$p1count,'p2count'=>$p2count];
        $this->success('获取成功',$data);

    }
    public function getTree($pid,$lv){
        static $arr=array();
        $data=Db::table('fa_user')->where('p1',$pid)->field('username,mobile,money,score,p1,id,avatar')->select();
        foreach ($data as $key => $value) {
            if($value['p1'] == $pid){
                $value['lv']=$lv;
                array_push($arr,$value);
                $this->getTree($value['id'],$lv+1);
            }
        }
        return $arr;
    }
}
