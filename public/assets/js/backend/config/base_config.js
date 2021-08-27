define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'config/base_config/index' + location.search,
                    add_url: 'config/base_config/add',
                    edit_url: 'config/base_config/edit',
                    del_url: 'config/base_config/del',
                    multi_url: 'config/base_config/multi',
                    import_url: 'config/base_config/import',
                    table: 'base_config',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'value', title: __('Value'), operate: 'LIKE'},
                        {field: 'msg', title: __('Msg'), operate: 'LIKE'},
                        {field: 'group', title: __('Group'), searchList: {"1":__('Group 1'),"2":__('Group 2'),"3":__('Group 3'),"4":__('Group 4'),"5":__('Group 5'),"6":__('Group 6'),"7":__('Group 7'),"9":__('Group 9')}, formatter: Table.api.formatter.normal},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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