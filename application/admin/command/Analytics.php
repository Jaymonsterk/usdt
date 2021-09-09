<?php

namespace app\admin\command;

use fast\Arr;
use fast\Date;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;

class Analytics extends Command
{
    protected function configure()
    {
        $site = Config::get('site');
        $this
            ->setName('tongji')
            ->setDescription('统计邀请数据')
            ->addOption('type', 't', Option::VALUE_OPTIONAL, '统计天数1=10分钟，2=按天统计最近3天', '1');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = $input->getOption('type');

        if($type == "2"){
            //TODO
            $this->days();
            $output->info("day Success!");
        }else{
            $this->mins();
            $output->info("min Success!");
        }

    }

    //按照5分钟统计
    protected function mins()
    {
        $user_list = db("user")->where("group_id",2)->select();
        foreach ($user_list as $u){
           $userinfo =  $this->count($u['id']);
            $date = date("Y-m-d");
            $userinfo =  $this->count($u['id']);
            if(!$userinfo){
                continue;
            }
            $userinfo['create_time'] = strtotime($date);
            $userinfo['dates'] = $date;
            $userinfo['user_id'] = $u['id'];
            $userinfo['username'] = $u['username'];
            $this->insertReportData($userinfo);
        }
    }

    //按照天统计
    protected function days()
    {
        $n = -6;
        $user_list = db("user")->where("group_id",2)->select();
        foreach ($user_list as $u){
            for ($start=$n; $start++; $start < 0) {
                $end = $start;
                $starttime = Date::unixtime('day', $start);
                $date = date("Y-m-d",$starttime);
                $userinfo =  $this->count($u['id'],$start,$end);
                if(!$userinfo){
                    continue;
                }
                $userinfo['create_time'] = $starttime;
                $userinfo['dates'] = $date;
                $userinfo['user_id'] = $u['id'];
                $userinfo['username'] = $u['username'];
                $this->insertReportData($userinfo);
            }
        }
    }

    //统计用户的邀请人数，充U数量，卖U金额
    protected function count($uid = 0,$start=0,$end=0){
        if(!$uid){return false;}
        $starttime = Date::unixtime('day', $start);
        $endtime = Date::unixtime('day', $end, 'end');

        $data = [
            'reg_num'=>0,
            'recharge_usdt'=>0,
            'withdraw_rmb'=>0,
        ];
        //获取下线3级的所有用户ID，按照注册日期获取
        $down_user_reg_list = $this->getDownUserID($uid,$start,$end);
        $data['reg_num'] = count($down_user_reg_list);

        //统计充U成功数量 ，统计卖U金额 ,获取今天下线所有用户
        $down_user_list = $this->getDownUserAllID($uid);
        $ids = array_column($down_user_list,"id");
        if($ids){
            //充U
            $recharge_usdt = db("order_cashin")->where("status",1)
                ->where("user_id","in",$ids)
                ->where('opertime', 'between time', [$starttime, $endtime])->sum("amount");
            //卖U
            $withdraw_rmb = db("order_cashout")->where("status",2)
                ->where('opertime', 'between time', [$starttime, $endtime])->sum("amount");
            $data['recharge_usdt'] = $recharge_usdt;
            $data['withdraw_rmb'] = $withdraw_rmb;
        }else{
            return false;
        }

        return $data;
    }

    //获取下线用户ID
    protected function getDownUserID($uid = 0,$start=0,$end=0)
    {
        $starttime = Date::unixtime('day', $start);
        $endtime = Date::unixtime('day', $end, 'end');
        $sql = "
        SELECT
	T2.id,T2.group_id,T2.username,T2.jointime,
	T1.clevel 
FROM
	(
	SELECT
		@r AS _pid,
		(
		SELECT
			@r := group_concat( id ) 
		FROM
			`u_user` 
		WHERE
		FIND_IN_SET( parent_id, _pid )) AS cid,
		@l := @l + 1 AS clevel 
	FROM
		( SELECT @r := $uid ) vars,-- 查询id为4的所有子节点
		( SELECT @l := 0 ) clevel,
		u_user h 
	WHERE
		@r IS NOT NULL 
	) T1
	INNER JOIN u_user T2 ON FIND_IN_SET( T2.parent_id, T1._pid ) 
WHERE
	T1.clevel <= 3 and jointime>=$starttime and jointime<=$endtime
ORDER BY
	id ASC;
";

        $user = db()->query($sql);
//        echo db()->getLastSql();
        return $user;
    }

    //获取下线用户ID
    protected function getDownUserAllID($uid = 0)
    {
        $sql = "
        SELECT
	T2.id,T2.group_id,T2.username,T2.jointime,
	T1.clevel 
FROM
	(
	SELECT
		@r AS _pid,
		(
		SELECT
			@r := group_concat( id ) 
		FROM
			`u_user` 
		WHERE
		FIND_IN_SET( parent_id, _pid )) AS cid,
		@l := @l + 1 AS clevel 
	FROM
		( SELECT @r := $uid ) vars,-- 查询id为4的所有子节点
		( SELECT @l := 0 ) clevel,
		u_user h 
	WHERE
		@r IS NOT NULL 
	) T1
	INNER JOIN u_user T2 ON FIND_IN_SET( T2.parent_id, T1._pid ) 
WHERE
	T1.clevel <= 3 
ORDER BY
	id ASC;
";

        $user = db()->query($sql);
        return $user;
    }

    protected function insertReportData($data)
    {
        $table = "u_invite_record";
        $set = array();
        foreach($data as $k=>$v) $set[] = "{$k}='{$v}'";

        $sql = "INSERT INTO {$table}(".implode(',', array_keys($data))
            .") VALUES ('".implode("','", array_values($data))."') ON DUPLICATE KEY UPDATE "
            .implode(',',$set);
        db("invite_record")->query($sql);
    }

}
