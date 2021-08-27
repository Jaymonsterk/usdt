<?php

return array(

    array(
        'name'    => 'accessKey',
        'title'   => 'accessKey',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => '',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '请在个人中心 > 密钥管理中获取 > AK',
        'ok'      => '',
        'extend'  => '',
    ),

    array(
        'name'    => 'secretKey',
        'title'   => 'secretKey',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => '',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '请在个人中心 > 密钥管理中获取 > SK',
        'ok'      => '',
        'extend'  => '',
    ),

    array(
        'name'    => 'bucket',
        'title'   => 'bucket',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => 'yourbucket',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '存储空间名称',
        'ok'      => '',
        'extend'  => '',
    ),

    array(
        'name'    => 'uploadurl',
        'title'   => '上传接口地址',
        'type'    => 'select',
        'content' =>
            array(
                'https://upload-z0.qiniup.com'  => '华东 https://upload-z0.qiniup.com',
                'https://upload-z1.qiniup.com'  => '华北 https://upload-z1.qiniup.com',
                'https://upload-z2.qiniup.com'  => '华南 https://upload-z2.qiniup.com',
                'https://upload-na0.qiniup.com' => '北美 https://upload-na0.qiniup.com',
                'https://upload-as0.qiniup.com' => '东南亚 https://upload-as0.qiniup.com',
            ),
        'value'   => 'https://upload-z2.qiniup.com',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '推荐选择最近的地址',
        'ok'      => '',
        'extend'  => '',
    ),

    array(
        'name'    => 'cdnurl',
        'title'   => 'CDN地址',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => 'http://yourbucket.yoursite.com',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '未绑定CDN的话可使用七牛分配的测试域名',
        'ok'      => '',
        'extend'  => '',
    ),

    array(
        'name'    => 'uploadmode',
        'title'   => '上传模式',
        'type'    => 'select',
        'content' =>
            array(
                'client' => '客户端直传(速度快,无备份)',
                'server' => '服务器中转(占用服务器带宽,可备份)',
            ),
        'value'   => 'client',
        'rule'    => '',
        'msg'     => '',
        'tip'     => '启用服务器中转时务必配置操作员和密码',
        'ok'      => '',
        'extend'  => '',
    ),

    array(
        'name'    => 'savekey',
        'title'   => '保存文件名',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => '/uploads/$(year)$(mon)$(day)/$(etag)$(ext)',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),

    array(
        'name'    => 'expire',
        'title'   => '上传有效时长',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => '600',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => 'maxsize',
        'title'   => '最大可上传',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => '10M',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => 'mimetype',
        'title'   => '可上传后缀格式',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => 'jpg,png,bmp,jpeg,gif,zip,rar,xls,xlsx',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => 'multiple',
        'title'   => '多文件上传',
        'type'    => 'radio',
        'content' =>
            array(
                '1' => '开启',
                '0' => '关闭',
            ),
        'value'   => '0',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => 'thumbstyle',
        'title'   => '缩略图样式',
        'type'    => 'string',
        'content' =>
            array(),
        'value'   => '',
        'rule'    => '',
        'msg'     => '',
        'tip'     => '用于附件管理缩略图样式，可使用：?imageView2/2/w/120/h/90/q/80',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => 'chunking',
        'title'   => '分片上传',
        'type'    => 'radio',
        'content' =>
            array(
                '1' => '开启',
                '0' => '关闭',
            ),
        'value'   => '1',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => 'chunksize',
        'title'   => '分片大小',
        'type'    => 'number',
        'content' =>
            array(),
        'value'   => '4194304',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '固定大小,不能修改',
        'ok'      => '',
        'extend'  => 'readonly',
    ),
    array(
        'name'    => 'syncdelete',
        'title'   => '附件删除时是否同步删除文件',
        'type'    => 'bool',
        'content' =>
            array(),
        'value'   => '0',
        'rule'    => 'required',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),
    array(
        'name'    => '__tips__',
        'title'   => '温馨提示',
        'type'    => '',
        'content' =>
            array(),
        'value'   => '在使用之前请注册七牛账号并进行认证，注册链接:<a href="https://portal.qiniu.com/signup?code=3l79xtos9w9qq" target="_blank">https://portal.qiniu.com/signup?code=3l79xtos9w9qq</a>',
        'rule'    => '',
        'msg'     => '',
        'tip'     => '',
        'ok'      => '',
        'extend'  => '',
    ),
);
