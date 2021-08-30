<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;

/**
 * 卖U订单
 *
 * @icon fa fa-circle-o
 */
class OrderCashout extends Backend
{
    
    /**
     * OrderCashout模型对象
     * @var \app\admin\model\order\OrderCashout
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\OrderCashout;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    public function import()
    {
        parent::import();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /*
      * 关闭订单
      */
    public function refund($ids)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isAjax()) {

            if ($row['status'] != 1) {
                //报错
                $this->error('不是待审核订单，无法关闭');
            }
            $data = [
                'status' => 3,
                //'note' => $params['note'],
            ];
            //状态改为3,关闭订单
            $row->save($data);

            //TODO 处理余额退回

            $msg = '审核失败退回!';

            $this->success($msg);
        }
        $this->error('Error');
    }
}
