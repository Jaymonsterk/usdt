<?php

namespace app\api\logic;

use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;

/**
 * Token接口
 */
class Usermoney
{

    private static $server = array();

    //查询用户余额
    public function getUserMoney($uid,$token_id = 0) {
        $query = Db::name('user_money')
            ->alias('um')
            ->join('tokens t','um.token_id = t.token_id','LEFT')
            ->where('um.uid','=',$uid);
        if ($token_id > 0) {
            $query->where('um.token_id','=',$token_id);
        }
        $data = $query->field('um.token_id,um.balance,um.freeze,t.name')->select();

        $cny_price = BaseConfig::getInstance()->getBaseConfig('cn_price');
        foreach ($data as $k => &$v) {
            $v['cny_price'] = bcmul($v['balance'],$cny_price,8);
//                $v['balance'] * $cny_price;
        }

        return $data;
    }

    //获取邀请奖励
    public function getInviteAwardTotal($uid) {
        return Db::name('money_history')->where('uid','=',$uid)
            ->where('type','=',Config::get('account_flow_type.balance'))
            ->where('sub_type','=',Config::get('account_flow_sub_type.invite_award'))
            ->where('opt','=',Config::get('opt_type.add'))
            ->sum('num');
    }

    //添加到保证金队列
//    public static function pushCheckBailQueue() {
//
//    }

    //执行保证金队列
    public static function execCheckBailQueue() {
        //查询所有等待计算保证金的用户

        $interval = BaseConfig::getInstance()->getBaseConfig('min_bail_balance_interval');

        $time = time() - $interval;

        $data = Db::name('user_money')
            ->alias('um')
            ->join('user u','um.uid = u.id','INNER')
            ->where('u.group_id','in',[6,7,8])
            ->where('um.min_bail_balance_last_time','<=',$time)
            ->field('u.group_id,um.uid,um.min_bail_balance')
            ->select();
//        dump($data);
        if (!$data) {
            return false;
        }
        foreach ($data as $k => &$v) {
            if (!in_array($v['group_id'],[6,7,8])) {
                unset($data[$k]);
            }
        }
        if (count($data) <= 0) {
            return false;
        }

        $rate = BaseConfig::getInstance()->getBaseConfig('min_bail_rate');

        //开始计算保证金
        foreach ($data as $k => $v) {
            //查询邀请者如今金额
            $uid = $v['uid'];
            $amount = Db::name('invite_record')
                ->alias('ir')
                ->join('intoken i','ir.uid = i.uid','LEFT')
                ->where('ir.parent_uids','LIKE','%'.$uid.'%')
                ->sum('amount');
            $amount = bcmul($amount,$rate,8);
            //更新保证金
            Db::name('user_money')->where('uid','=',$uid)->update([
                'min_bail_balance' => $amount,
                'min_bail_balance_last_time' => time()
            ]);
        }

    }

    //查询提币限制
    public function checkOuttokenLimit($uid) {
        $group_id = Db::name('user')->where('id','=',$uid)->value('group_id');
        if ($group_id != 6) {
            return true;
        }
        //查询用户最低保证金
        $usermoney = Db::name('user_money')->where('uid','=',$uid)->find();
        if ($usermoney['balance'] > $usermoney['min_bail_balance']) {
            return $usermoney['balance'] - $usermoney['min_bail_balance'];
        }
        return '余额低于最低保证金，无法提币';
    }




    //放入发送交易命令
    public static function pushSendTranQueue() {
        Queue::pushCheckOuttokenQueue(['id' => 1]);
    }

    //执行发送交易队列
    public function execSendTreaQueue() {
        //查询是否有还在打包中的提币数据
        $check = Db::table('outtoken')->where('status','=','1')->count();
        if ($check > 0) {
            dump('存在，不发送');
            return false;
        }
        //查询已审核的提币数据
        $data = Db::name('outtoken')
            ->where('status','=',2)
            ->where('hash','=','')
            ->order('create_time asc')->find();
        if (!$data) {
            dump('没有正在审核中的，不发送');
            return false;
        }
        $url = sprintf('%s/eth/sendtx',Config::get('token_host'));
        $key = Config::get('token_key');
        $res = Utils::httpPost($url,[
            'key' => $key,
            'uid' => $data['uid'],
            'to_address' => $data['to_address'],
            'amount' => $data['real_num']
        ]);
        var_dump('提币发送结果：',$res);
        if ($res) {
            //修改提币单
            $res = json_decode($res,true);
            if ($res['code'] == 1) {
                Db::name('outtoken')->where('id','=',$data['id'])->update([
                    'hash' => $res['hash']
                ]);
            }
        }
    }

    //放入检查提币状态
    public static function pushCheckOuttokenQueue() {
        Queue::pushCheckOuttokenQueue(['id' => 1]);
    }

    //执行检查提币状态
    public function execCheckOuttokenQueue() {
        //查询已审核且已发送交易的
        $data = Db::name('outtoken')
            ->where('status','=','2')
            ->where('hash','<>','')
            ->order('update_time asc')
            ->limit(0,1)
            ->find();
        if (!$data) {
            dump('未找到');
            return false;
        }
        //检查是否完成
        $check = Db::table('outtoken')->where('txhash','=',$data['hash'])->value('status');
        if ($check == '1') {
            //未完成
            dump('未完成');
            return false;
        }

        Db::startTrans();
        try {
            if ($check == '2') {
                //修改状态
                Db::name('outtoken')->where('id','=',$data['id'])->update(['status' => 3,'update_time' => time(),'complete_time' => time()]);
                //删除冻结
                Db::name('user_money')->where('uid','=',$data['uid'])->setInc('freeze',-$data['num']);
            } else if ($check == '3') {
                //提币失败
                //修改状态
                Db::name('outtoken')->where('id','=',$data['id'])->update(['status' => 4,'update_time' => time(),'complete_time' => time()]);
                //删除冻结
                Db::name('user_money')->where('uid','=',$data['uid'])->setInc('freeze',-$data['num']);
                //增加流水
                Common::write_flow(
                    $data['uid'],
                    1,
                    Config::get('account_flow_type.balance'),
                    Config::get('account_flow_sub_type.outtoken'),
                    Config::get('opt_type.add'),
                    $data['num'],
                    '提币失败',
                    Random::alnum()
                );
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            dump($e->getMessage());
        }
        dump('检查完成');
        return true;
    }

    //放入检查充币命令
    public static function pushCheckIntokenQueue() {
        Queue::pushCheckIntokenQueue(['id' => 1]);
    }

    //执行检查充币命令
    public function execCheckIntokenQueue() {
        $data = Db::table('intoken')->where('status','=',0)->select();
        if (!$data) {
            return false;
        }

        $key = Random::alnum();

        Db::startTrans();
        try {
            foreach ($data as $k => $v) {
                $uid = Db::name('user_money')->where('address','=',$v['to'])->value('uid');
                if (!$uid) {
                    continue;
                }
                //修改状态
                Db::table('intoken')->where('id','=',$v['id'])->update(['status' => 1]);
                //增加余额
                Db::name('user_money')->where('uid','=',$uid)->where('token_id','=',1)->setInc('balance',$v['value']);
                //增加流水
                Common::write_flow(
                    $uid,
                    1,
                    Config::get('account_flow_type.balance'),
                    Config::get('account_flow_sub_type.intoken'),
                    Config::get('opt_type.add'),
                    $v['value'],
                    '充币',
                    $key
                );
                $admin_id = InviteRecord::getInstance()->getUserAdminId($uid);
                //添加记录
                if (Db::name('intoken')->where('hash','=',$v['txhash'])->count() == 0) {
                    Db::name('intoken')->insert([
                        'uid' => $uid,
                        'hash' => $v['txhash'],
                        'form_address' => $v['from'],
                        'to_address' => $v['to'],
                        'create_time' => strtotime($v['created_time']),
                        'amount' => $v['value'],
                        'token_id' => 1,
                        'admin_id' => $admin_id
                    ]);
                }
                Db::commit();
            }
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            dump($e->getMessage());
            return false;
        }
        dump('检查充币成功');
        return true;
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
