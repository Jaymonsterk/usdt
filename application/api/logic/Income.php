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
class Income
{

    private static $server = array();

    //添加到分成队列
    public static function pushTranFeeShareQueue() {
        Queue::pushTranFeeShareQueue(['id' => 1]);
    }

    //手续费分享
    public function execTranFeeShare() {

        //更新做单的状态
        Db::name('contract_order')
            ->where('status','=','1')
            ->where('is_tmp','=',1)
            ->update(['share_status' => 2]);

        //查询数量
        $count = Db::name('contract_order')->where('status','=','1')->where('share_status','=','0')->where('is_tmp','=',0)->count();
        if ($count == 0) {
            return '暂未发现可分配的手续费';
        }

        //查询已平仓且未分享的合约单
        $data = Db::name('contract_order')->where('status','=','1')->where('share_status','=','0')->where('is_tmp','=',0)->limit(0,1)->select();

        $result = [];

        foreach ($data as $k => $v) {
            $result[] = $this->execShare($v);
        }
        return $result;
    }

    //对手续费按照比例进行分配
    public function execShare($data) {
        $uid = $data['uid'];
        $invite_record = InviteRecord::getInstance()->getInviteRecordByUid($uid);

        //比例配置
        $config_rate = $this->getShareConfigRate($invite_record);
        if (!$config_rate) {
            return '比例未配置';
        }
        dump($config_rate);

        //获取要分配的用户uid
        $uids = $this->getShareUids($invite_record);
        dump($uid);
        dump($invite_record);
        dump($uids);

        $share_result = $this->getShareResult($data['service_total'],$config_rate,$uids);
        dump($share_result);

//        dump($config_rate);
//        dump($uids);
//        dump($share_result);
//        dump($data['service_total']);
//        dump($data['id']);
//        exit;

        //获取客损分配数量
        $lose_data = $this->getUserWinShareNum(
            $data['profit'],
            BaseConfig::getInstance()->getBaseConfig('platform_uid'),
            $uids['group6']
        );
        if ($lose_data === false) {
            return '客损比例未配置';
        }
        dump($lose_data);

        //开始分配
        Db::startTrans();
        try {

            $ress = Db::name('contract_order')->where('status','=','1')->where('id','=',$data['id'])->where('share_status','=','0')->lock(true)->find();
            if (!$ress) {
                Db::rollback();
                return '暂未发现可分配的手续费';
            }

            $key = Random::alnum();
            //分配手续费
            foreach ($share_result as $k => $v) {
                if ($v <= 0) {
                    continue;
                }
                //增加用户余额
                Db::name('user_money')->where('token_id','=',1)->where('uid','=',$k)->setInc('balance',$v);
                //增加流水
                Common::write_flow(
                    $k,
                    1,
                    Config::get('account_flow_type.balance'),
                    Config::get('account_flow_sub_type.share_service_fee'),
                    Config::get('opt_type.add'),
                    $v,
                    '手续费分成',
                    $key
                );
            }
            //承担亏损
            foreach ($lose_data as $k => $v) {
                if ($v != 0) {
                    //增加用户余额
                    Db::name('user_money')->where('token_id','=',1)->where('uid','=',$k)->setInc('balance',$v);
                    //增加流水
                    Common::write_flow(
                        $k,
                        1,
                        Config::get('account_flow_type.balance'),
                        Config::get('account_flow_sub_type.share_tran_lose'),
                        Config::get('opt_type.dute'),
                        $v,
                        '客损费用',
                        $key
                    );
                }
            }
            //修改合约单状态
            Db::name('contract_order')->where('id','=',$data['id'])->update(['share_status' => '1']);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            return $e->getMessage();
        }
        return [
            'config_rate' => $config_rate,
            'uids' => $uids,
            'share_result' => $share_result,
            'service_total' => $data['service_total'],
            'order_id' => $data['id']
        ];
    }

    //进行比例分配
    public function getShareResult($fee,$config_fate,$uids) {
        $data = [];
        foreach ($uids as $k => $v) {
            if ($v > 0) {
                if (isset($data[$v])) {
                    $data[$v] += bcmul($config_fate[$k],$fee,8);
                } else {
                    $data[$v] = bcmul($config_fate[$k],$fee,8);
                }
            }
        }

        $remain_rate = $this->getRemainRate($config_fate,$uids);
        if ($remain_rate > 0) {
            if (isset($data[$uids['platform']])) {
                $data[$uids['platform']] += bcmul($remain_rate,$fee,8);
            } else {
                $data[$uids['platform']] = bcmul($remain_rate,$fee,8);
            }
        }

        return $data;
    }

    //计算剩余概率
    public function getRemainRate($config_fate,$uids) {
        $remain_rate = 0;
        foreach ($uids as $k => $v) {
            if ($v == 0) {
                $remain_rate += $config_fate[$k];
            }
        }
        return $remain_rate;
    }


    //获取进行分配的uids
    public function getShareUids($invite_record) {
        $parent_uids = explode(',',trim($invite_record['parent_uids'],','));

        $parent_uids = array_reverse($parent_uids);
        if (count($parent_uids) > 6) {
            $parent_uids = array_slice($parent_uids,0,6);
        }


        $uids = [
            'platform' => $invite_record['platform_uid'],
            'group6' => $invite_record['group6_uid'],
            'group7' => $invite_record['group7_uid'],
            'group8' => $invite_record['group8_uid'],
            'user1' => isset($parent_uids[0]) ? $parent_uids[0] : 0,
            'user2' => isset($parent_uids[1]) ? $parent_uids[1] : 0,
            'user3' => isset($parent_uids[2]) ? $parent_uids[2] : 0,
            'user4' => isset($parent_uids[3]) ? $parent_uids[3] : 0,
            'user5' => isset($parent_uids[4]) ? $parent_uids[4] : 0,
        ];
        return $uids;
    }


    //获取分配比例配置
    public function getShareConfigRate($invite_record) {
        //获取推广员配置
        if ($invite_record['group8_uid'] > 0) {
            $rate = $this->getConfig($invite_record['group8_uid']);
            if ($rate) {
                return $rate;
            }
        }
        if ($invite_record['group7_uid'] > 0) {
            $rate = $this->getConfig($invite_record['group7_uid']);
            if ($rate) {
                return $rate;
            }
        }
        if ($invite_record['group6_uid'] > 0) {
            $rate = $this->getConfig($invite_record['group6_uid']);
            if ($rate) {
                return $rate;
            }
        }
        if ($invite_record['group8_uid'] > 0) {
            $rate = $this->getConfig($invite_record['group8_uid']);
            if ($rate) {
                return $rate;
            }
        }
        if ($invite_record['platform_uid'] > 0) {
            $rate = $this->getConfig($invite_record['platform_uid']);
            if ($rate) {
                return $rate;
            }
        }

        $rate = $this->getConfig(BaseConfig::getInstance()->getBaseConfig('platform_uid'));
        if ($rate) {
            return $rate;
        }

        return false;
    }

    //查询比例
    public function getConfig($uid) {
        return Db::name('fenxiao_rate')->where('uid','=',$uid)->find();
    }

    //获取客损承担数量
    public function getUserWinShareNum($profit,$platform_uid,$group6_uid) {
        //如果用户没有在运营商下面，则由平台承担亏损
        if ($group6_uid == 0) {
            return [$platform_uid => -$profit];
        }

        $admin_id = Db::name('user')->where('id','=',$group6_uid)->value('admin_id');

        //查询平台赔付比例

        $rate = Db::name('fenxiao_custom_lose_rate')->where('group6_id','=',$admin_id)->limit(0,1)->find();
        if (!$rate) {
            $rate = Db::name('fenxiao_custom_lose_rate')->where('group6_id','=',BaseConfig::getInstance()->getBaseConfig('platform_uid'))->find();
        }
        if (!$rate) {
            return false;
        }
        $platform = bcmul($rate['platform_rate'],$profit,8);
        $group6 = bcmul($rate['group6_rate'],$profit,8);
        return [
            $platform_uid => -$platform,
            $group6_uid => -$group6
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
