<?php

namespace app\admin\model\order;

use think\Cache;
use think\Model;


class OrderCashin extends Model
{

    

    

    // 表名
    protected $name = 'order_cashin';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_sound_text',
        'status_text',
        'opertime_text'
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
        });

        self::beforeUpdate(function ($row) {
            //操作者
            $row['aid'] = session('admin.id');
            $row['aname'] = session('admin.username');
            if($row['status']=="1" || $row['status']=="2"){
                $row['opertime'] = time();
            }
        });
    }

    public function getIsSoundList()
    {
        return ['1' => __('Is_sound 1'), '0' => __('Is_sound 0')];
    }
    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getIsSoundTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_sound']) ? $data['is_sound'] : '');
        $list = $this->getIsSoundList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getOpertimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['opertime']) ? $data['opertime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setOpertimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
