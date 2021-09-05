<?php

namespace app\admin\model;

use app\admin\model\user\UserMoneyLog;
use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use think\Cache;
use think\Db;
use think\Model;

class User extends Model
{

    // 表名
    protected $name = 'user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'prevtime_text',
        'logintime_text',
        'jointime_text'
    ];

    public function getOriginData()
    {
        return $this->origin;
    }

    protected static function init()
    {
        self::beforeUpdate(function ($row) {
            $changed = $row->getChangedData();
            //如果有修改密码
            if (isset($changed['password'])) {
                if ($changed['password']) {
                    $salt = \fast\Random::alnum();
                    $row->password = \app\common\library\Auth::instance()->getEncryptPassword($changed['password'], $salt);
                    $row->salt = $salt;
                } else {
                    unset($row->password);
                }
            }
        });


        self::beforeUpdate(function ($row) {
            $changedata = $row->getChangedData();
            $origin = $row->getOriginData();
            if (isset($changedata['money']) && (function_exists('bccomp') ? bccomp($changedata['money'], $origin['money'], 2) !== 0 : (double) $changedata['money'] !== (double) $origin['money'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money'] - $origin['money'], 'before' => $origin['money'], 'after' => $changedata['money'], 'memo' => '管理员变更金额']);
            }
            if (isset($changedata['score']) && (int) $changedata['score'] !== (int) $origin['score']) {
                ScoreLog::create(['user_id' => $row['id'], 'score' => $changedata['score'] - $origin['score'], 'before' => $origin['score'], 'after' => $changedata['score'], 'memo' => '管理员变更积分']);
            }
        });
    }

    public function getGenderList()
    {
        return ['1' => __('Male'), '0' => __('Female')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function getPrevtimeTextAttr($value, $data)
    {
        $value = $value ? $value : $data['prevtime'];
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getLogintimeTextAttr($value, $data)
    {
        $value = $value ? $value : $data['logintime'];
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getJointimeTextAttr($value, $data)
    {
        $value = $value ? $value : $data['jointime'];
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPrevtimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setLogintimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setJointimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setBirthdayAttr($value)
    {
        return $value ? $value : null;
    }

    public function group()
    {
        return $this->belongsTo('UserGroup', 'group_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    //用户资金操作
    //$type为 extra/money.php内user_money_log 对应的数据
    //$moneytype = money 用户金额  $moneytype = mortgage 用户押金
    public static function useaMoney($uid, $money,$type,$orderid='',$moneytype='money',$data=[])
    {
        if(!in_array($moneytype,['money','agent'])){
            return false;
        }

        if(empty($data)){
            $data = self::where("id",$uid)->find();
        }
        if($money > 0){
            self::where('id',$uid)->setInc($moneytype,$money);
        }else{
            self::where('id',$uid)->setDec($moneytype,abs($money));
        }
        $user_money_log = config('site.user_money_log');
        if(!isset($user_money_log[$type])){
            echo "用户资金日志 site.user_money_log 的 type 错误";exit;
        }
        $type_arr = isset($user_money_log[$type])?$user_money_log[$type]:$user_money_log[0];

        $arr = [];
        $arr['user_id']=$data['id'];
        $arr['username']=$data['username'];
        $arr['money']=$money;
        $arr['before']=$data['money'];
        $arr['after']=$data['money'] + $money;
        $arr['type']=$type;
        $arr['typename']=$user_money_log[$type];
        $arr['memo']=$data['note']??"";
        $arr['createtime']=time();
        self::inUserMoneyLog($arr);
        return true;
    }
    //插入日志
    public static function inUserMoneyLog($arr)
    {
        $UserMoneyLog = new MoneyLog($arr);
        $UserMoneyLog->save();
    }


}
