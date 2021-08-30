<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;

/**
 * 充U订单
 *
 * @icon fa fa-circle-o
 */
class OrderCashin extends Backend
{
    
    /**
     * OrderCashin模型对象
     * @var \app\admin\model\order\OrderCashin
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\OrderCashin;
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

                if ($row['status'] != 0) {
                    //报错
                    $this->error('status error!');
                }
                $data = [
                    'status' => 2,
                    //'note' => $params['note'],
                ];
                //状态改为3,关闭订单
                $row->save($data);

                $msg = 'close success!';

                $this->success($msg);
        }
        $this->error('Error');
    }
}
