<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

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

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model

                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
            }
            $rows = $list->items();

            $cashin = Db::name('order_cashin')->field('id,username,is_read')
                ->where('status',0)
                ->count("id");
            $cashout = Db::name('order_cashout')->field('id,username,is_read')
                ->where('status',1)
                ->count("id");

            $result = array("total" => $list->total(), "rows" => $rows,"cashin"=>$cashin,"cashout"=>$cashout);

            return json($result);
        }
        return $this->view->fetch();
    }

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

    //处理充值
    protected function handle($status,$row){
        $old_status = $row['status'];
        $status = (int)$status;
        $ret['continue'] = false;
        switch ($status){
            case 0:
                if($old_status == 1){
                    $ret['code'] = 109;
                    $ret['msg'] = "禁止修改到此状态1-1";
                }else{
                    $ret['code'] = 109;
                    $ret['msg'] = "不允许修改";
                }
                break;
            case 1:
                if($old_status >=1){
                    $ret['code'] = 109;
                    $ret['msg'] = "禁止修改到此状态23-2";
                }else{
                    //充值成功 修改状态 充值用户余额
                    \app\admin\model\User::useaMoney($row['user_id'], $row['amount'],2,"");
                    $ret['continue'] = true;
                }
                break;
            case 2:
                if($old_status >=1){
                    $ret['code'] = 109;
                    $ret['msg'] = "禁止修改到此状态23-3";
                }else{
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
