<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

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


    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $status = $params['status']??0;
                    $ret = $this->handle($status,$row);
                    $result = false;
                    if($ret['continue']) {
                        $result = $row->allowField(true)->save($params);
                    }
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

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
            \app\admin\model\User::useaMoney($row['user_id'], $row['amount'],3);

            $msg = '审核失败退回!';

            $this->success($msg);
        }
        $this->error('Error');
    }

    //处理充值
    protected function handle($status,$row){
        $old_status = $row['status'];
        $status = (int)$status;
        $ret['continue'] = false;
        switch ($status){
            case 1:
                if($old_status == 2){
                    $ret['code'] = 109;
                    $ret['msg'] = "禁止修改到此状态1-1";
                }else{
                    $ret['code'] = 109;
                    $ret['msg'] = "不允许修改";
                }
                break;
            case 2:
                if($old_status >=2){
                    $ret['code'] = 109;
                    $ret['msg'] = "禁止修改到此状态23-2";
                }else{
                    //充值成功 修改状态 充值用户余额
                    $ret['continue'] = true;
                }
                break;
            case 3:
                if($old_status >=2){
                    $ret['code'] = 109;
                    $ret['msg'] = "禁止修改到此状态23-3";
                }else{
                    \app\admin\model\User::useaMoney($row['user_id'], $row['amount'],3);
                    $ret['continue'] = true;
                }
                break;
            default:
                $ret['code'] = 109;
                $ret['msg'] = "充值失败";
                break;
        }
        return $ret;
    }
}
