<?php

namespace app\api\logic;

use fast\Random;
use think\Config;
use think\Db;

/**
 * Token接口
 */
class BaseConfig
{

    private static $server = array();

    //获取基础配置
    public function getBaseConfig($name) {
        return Db::name('base_config')->where('name','=',$name)->value('value');
    }

    //获取本金
    public function getCapital() {
        $data = Db::name('base_config')->where('group','=',7)->select();
        return array_column($data,'value');
    }

    //获取杠杆
    public function getHeaver() {
        $data = Db::name('base_config')->where('group','=',9)->select();
        return array_column($data,'value');
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
