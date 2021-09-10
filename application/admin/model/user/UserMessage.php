<?php

namespace app\admin\model\user;

use think\Model;


class UserMessage extends Model
{

    // 表名
    protected $name = 'user_message';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'ctime_text',
        'utime_text'
    ];

    protected static function init()
    {
        self::beforeInsert(function ($row) {
            //操作者
            if (!isset($row['aid']) || !$row['aid']) {
                $row['aid'] = session('admin.id');
            }
            if (!isset($row['aname']) || !$row['aname']) {
                $row['aname'] = session('admin.username');
            }
            $row['ctime'] = time();
            $row['cdate'] = date("Y-m-d H:i:s");
            if (!isset($row['utime']) || !$row['utime']) {
                $row['utime'] = time();
            }
        });

        self::beforeUpdate(function ($row) {
            //操作者
            $row['aid'] = session('admin.id');
            $row['aname'] = session('admin.username');
            $row['utime'] = time();
        });
    }

    public function getCtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ctime']) ? $data['ctime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getUtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['utime']) ? $data['utime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
