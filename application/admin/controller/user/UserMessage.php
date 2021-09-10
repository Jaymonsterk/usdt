<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 用户消息管理
 *
 * @icon fa fa-circle-o
 */
class UserMessage extends Backend
{
    
    /**
     * UserMessage模型对象
     * @var \app\admin\model\user\UserMessage
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\UserMessage;

    }


}
