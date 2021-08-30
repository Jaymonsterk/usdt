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
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'uid', title: __('Uid')},
                        {field: 'parent_uid', title: __('Parent_uid')},
                        {field: 'parent_uids', title: __('Parent_uids'), operate: 'LIKE'},
                        {field: 'invite_uids', title: __('Invite_uids'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'group6_uid', title: __('Group6_uid')},
                        {field: 'group7_uid', title: __('Group7_uid')},
                        {field: 'group8_uid', title: __('Group8_uid')},
                        {field: 'platform_uid', title: __('Platform_uid')},
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