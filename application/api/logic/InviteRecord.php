<?php
/**
 * Created by PhpStorm.
 * User: start
 * Date: 2019/3/24
 * Time: 8:33 PM
 */

namespace app\api\logic;

use app\api\library\Common;
use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;

class InviteRecord {
    // 表名
    protected $name = 'invite_record';

    //记录邀请信息
    public function record($uid,$parent_uid,$is_invite_award = true) {
        //根据parent_uid查询上级uids
        $parent_uids = Db::name('invite_record')->where(['uid' => $parent_uid])->find();

        //记录邀请人所有下级
        if (!$parent_uids) {
            $platform_uid = BaseConfig::getInstance()->getBaseConfig('platform_uid');
            //新增
            Db::name('invite_record')->insert([
                'uid' => $parent_uid,
                'create_time' => time(),
                'invite_uids' => "$uid,",
                'platform_uid' => $platform_uid,
                'group6_uid' => 0,
                'group7_uid' => 0,
                'group8_uid' => 0,
                'parent_uid' => 0,
            ]);
        } else {
            Db::name('invite_record')->where(['uid' => $parent_uid])->update([
                'invite_uids' => $parent_uids['invite_uids'] ? "$parent_uids[invite_uids]$uid," : "$uid,"
            ]);
        }


        //组uids
        $group_uids = $this->getGroupUids($parent_uid);

        Db::name('invite_record')->insert([
            'uid' => $uid,
            'parent_uid' => $parent_uid,
            'parent_uids' => $parent_uids ? "$parent_uids[parent_uids]$parent_uid," : "$parent_uid,",
            'create_time' => time(),
            'platform_uid' => $group_uids[0],
            'group6_uid' => $group_uids[1],
            'group7_uid' => $group_uids[2],
            'group8_uid' => $group_uids[3],
        ]);

        //检查是否升级
        $this->checkUpLeval($parent_uid);

        if ($is_invite_award && BaseConfig::getInstance()->getBaseConfig('switch_invite_award') == 1) {
            //邀请奖励
            $this->inviteAward($parent_uid);
        }

        //修改用户表的直接上级
        $parent_admin_id = 0;
        if ($group_uids[3] > 0) {
            $parent_admin_id = $group_uids[3];
        } else if ($group_uids[2] > 0) {
            $parent_admin_id = $group_uids[2];
        } else if ($group_uids[1] > 0) {
            $parent_admin_id = $group_uids[1];
        } else {
            $parent_admin_id = $group_uids[0];
        }
        $user = Db::name('user')->where('id','=',$parent_admin_id)->find();
        if ($user['admin_id'] > 0) {
            $parent_admin_id = $user['admin_id'];
        } else {
            $parent_admin_id = $user['parent_admin_id'];
        }

        Db::name('user')->where('id','=',$uid)->update(['parent_admin_id' => $parent_admin_id]);
    }

    //没有邀请码的，算平台邀请的
    public function recordNoInviteCode($uid) {
        $platform_uid = BaseConfig::getInstance()->getBaseConfig('platform_uid');
        Db::name('invite_record')->insert([
            'uid' => $uid,
            'parent_uid' => $platform_uid,
            'parent_uids' => "$platform_uid,",
            'create_time' => time(),
            'platform_uid' => $platform_uid,
            'group6_uid' => 0,
            'group7_uid' => 0,
            'group8_uid' => 0,
        ]);
        Db::name('user')->where('id','=',$uid)->update(['parent_admin_id' => $platform_uid]);
    }

    //检查是否升级
    public function checkUpLeval($uid) {
        //查询配置
        $config = Db::name('fenxiao_config')->order('level asc')->select();
        //查询用户邀请人数
        $invite_num = Db::name('invite_record')->where('parent_uid','=',$uid)->count();
        //查询用户等级
        $userinfo = Db::name('user')->where('id','=',$uid)->field('level')->find();

        $level = 1;
        foreach ($config as $k => $v) {
            if ($invite_num >= $v['invite_num']) {
                $level = $v['level'];
            }
        }
        if ($level < $userinfo['level']) {
            return false;
        }

        //升级
        Db::name('user')->where('id','=',$uid)->update(['level' => $level]);
    }

    //邀请奖励
    public function inviteAward($uid) {
        //查询用户等级
        $userinfo = Db::name('user')->where('id','=',$uid)->field('level')->find();
        //查询配置
        $config = Db::name('fenxiao_config')->where('level','=',$userinfo['level'])->find();

        if ($config['invite_award_usdt'] <= 0) {
            return false;
        }

        Db::startTrans();
        try {
            //奖励usdt
            Db::name('user_money')->where('uid','=',$uid)->where('token_id','=',1)->setInc('balance',$config['invite_award_usdt']);
            //增加流水
            Common::write_flow(
                $uid,
                1,
                Config::get('account_flow_type.balance'),
                Config::get('account_flow_sub_type.invite_award'),
                Config::get('opt_type.add'),
                $config['invite_award_usdt'],
                '邀请奖励',
                Random::alnum()
            );
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
        }
    }

    //查询运营商、业务员、小组长uid
    public function getGroupUids($uid) {
        $platform_uid = BaseConfig::getInstance()->getBaseConfig('platform_uid'); //平台uid
        $group6_uid = 0; //运营商
        $group7_uid = 0; //小组长
        $group8_uid = 0; //业务员
        $invite_data = Db::name('invite_record')->where(['uid' => $uid])->find();
        if (!$invite_data) {
            return [$platform_uid,$group6_uid,$group7_uid,$group8_uid];
        }
        //查询用户组别
        $group_id = Db::name('user')->where('id','=',$uid)->field('group_id')->value('group_id');
//        if (!$group_id) {
//            return [$platform_uid,$group6_uid,$group7_uid,$group8_uid];
//        }

        if ($group_id == 0) {
            //一般用户，说明是一般用户邀请
            $group6_uid = $invite_data['group6_uid'];
            $group7_uid = $invite_data['group7_uid'];
            $group8_uid = $invite_data['group8_uid'];
        } else if ($group_id == 6) {
            //说明是运营商邀请
            $group6_uid = $uid;
            $group7_uid = 0;
            $group8_uid = 0;
        } else if ($group_id == 7) {
            //说明是小组长邀请
            $group6_uid = $invite_data['group6_uid'];
            $group7_uid = $uid;
            $group8_uid = 0;
        } else if ($group_id == 8) {
            //说明是业务员邀请
            $group6_uid = $invite_data['group6_uid'];
            $group7_uid = $invite_data['group7_uid'];
            $group8_uid = $uid;
        } else if ($group_id == 1) {

        }

        return [$platform_uid,$group6_uid,$group7_uid,$group8_uid];
    }

    //查询用户邀请记录
    public function getInviteRecordByUid($uid) {
        return Db::name('invite_record')->where('uid','=',$uid)->find();
    }

    //根据字段值，查询所有uid
    public function getUidByField($field,$value) {
        $data = Db::name('invite_record')->where($field,'=',$value)->select();
        $uids = [];
        if (!$data) {
            return $uids;
        }
        foreach ($data as $k => $v) {
            $uids[] = $v['uid'];
        }
        return $uids;
    }

    //获取用户所属管理员id
    public function getUserAdminId($uid) {
        $admin_id = BaseConfig::getInstance()->getBaseConfig('platform_uid');
        $invite = Db::name('invite_record')->where('uid','=',$uid)->find();
        if (!$invite) {
            //返回超级管理员id
            return $admin_id;
        }
        $group8_uid = $invite['group8_uid'];
        $group7_uid = $invite['group7_uid'];
        $group6_uid = $invite['group6_uid'];

        $uid = 0;

        if ($group8_uid > 0) {
            $uid = $group8_uid;
        } else if ($group7_uid > 0) {
            $uid = $group7_uid;
        } else if ($group6_uid > 0) {
            $uid = $group6_uid;
        }
        if ($uid > 0) {
            $invit_admin_id = Db::name('user')->where('id','=',$uid)->value('admin_id');
            if ($invit_admin_id > 0) {
                return $invit_admin_id;
            }
        }
        return $admin_id;
    }




    public static function getInstance() {
        return new self();
    }

}