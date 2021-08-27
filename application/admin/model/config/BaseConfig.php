<?php

namespace app\admin\model\config;

use think\Model;


class BaseConfig extends Model
{

    

    

    // 表名
    protected $name = 'base_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'group_text'
    ];
    

    
    public function getGroupList()
    {
        return ['1' => __('Group 1'), '2' => __('Group 2'), '3' => __('Group 3'), '4' => __('Group 4'), '5' => __('Group 5'), '6' => __('Group 6'), '7' => __('Group 7'), '9' => __('Group 9')];
    }


    public function getGroupTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['group']) ? $data['group'] : '');
        $list = $this->getGroupList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
