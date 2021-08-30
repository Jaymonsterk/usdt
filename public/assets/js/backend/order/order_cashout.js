define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/order_cashout/index' + location.search,
                    add_url: 'order/order_cashout/add',
                    edit_url: 'order/order_cashout/edit',
                    del_url: 'order/order_cashout/del',
                    multi_url: 'order/order_cashout/multi',
                    import_url: 'order/order_cashout/import',
                    table: 'order_cashout',
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
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'num', title: __('Num'), operate:'BETWEEN'},
                        {field: 'rate', title: __('Rate'), operate:'BETWEEN'},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.status},
                        {field: 'note', title: __('Note'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'opertime', title: __('Opertime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'aid', title: __('Aid'), visible:false, operate: false},
                        {field: 'aname', title: __('Aname'), visible:false, operate: false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter:function(value,row,index) {
                                var that = $.extend({}, this);
                                var table = $(this.table).clone(true);

                                if(row.status>1){
                                    $(table).data("operate-del", null);
                                }
                                if(row.status>2){
                                    $(table).data("operate-edit", null);
                                }

                                that.table = table;
                                return Table.api.formatter.operate.call(that, value, row, index);
                            },
                        buttons:[
                            {
                                name: 'process',
                                title: __('审核未通过退回'),
                                text: __('审核未通过退回'),
                                classname: 'btn btn-xs btn-info btn-ajax',
                                icon: 'fa fa-flash',
                                url: function (row, j) {
                                    return 'order/order_cashout/refund/ids/'+row.id;
                                },
                                refresh:true,
                                visible:function (row, j) {
                                    return row.status == 1;
                                },
                                hidden:function (row, j) {
                                    return row.status > 1;
                                }
                            },
                        ]
                        }
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