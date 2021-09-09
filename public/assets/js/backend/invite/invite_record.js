define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'invite/invite_record/index' + location.search,
                    add_url: 'invite/invite_record/add',
                    edit_url: 'invite/invite_record/edit',
                    del_url: 'invite/invite_record/del',
                    multi_url: 'invite/invite_record/multi',
                    import_url: 'invite/invite_record/import',
                    table: 'invite_record',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                //切换卡片视图和表格视图两种模式
                showToggle:false,
                //显示隐藏列可以快速切换字段列的显示和隐藏
                showColumns:true,
                //导出整个表的所有行导出整个表的所有行
                showExport:false,
                //搜索
                search: false,
                //搜索功能，
                commonSearch: true,
                //表格上方的搜索搜索指表格上方的搜索
                searchFormVisible: true,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'dates', title: __('Dates'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'reg_num', title: __('Reg_num')},
                        {field: 'recharge_usdt', title: __('Recharge_usdt'), operate:'BETWEEN'},
                        {field: 'withdraw_rmb', title: __('Withdraw_rmb'), operate:'BETWEEN'},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});