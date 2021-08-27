//修改上传的接口调用
require(['upload'], function (Upload) {

    //初始化中完成判断
    Upload.events.onInit = function () {
        //如果上传接口不是七牛云，则不处理
        if (this.options.url !== Config.upload.uploadurl) {
            return;
        }
        var _success = this.options.success;

        $.extend(this.options, {
            chunkSuccess: function (chunk, file, response) {
                this.contexts = this.contexts ? this.contexts : [];
                this.contexts.push(typeof response.ctx !== 'undefined' ? response.ctx : response.data.ctx);
            },
            chunksUploaded: function (file, done) {
                var that = this;
                Fast.api.ajax({
                    url: "/addons/qiniu/index/upload",
                    data: {
                        action: 'merge',
                        filesize: file.size,
                        filename: file.name,
                        chunkid: file.upload.uuid,
                        chunkcount: file.upload.totalChunkCount,
                        width: file.width || 0,
                        height: file.height || 0,
                        type: file.type,
                        qiniutoken: Config.upload.multipart.qiniutoken,
                        contexts: this.contexts
                    },
                }, function (data, ret) {
                    done(JSON.stringify(ret));
                    return false;
                }, function (data, ret) {
                    file.accepted = false;
                    that._errorProcessing([file], ret.msg);
                    return false;
                });

            },
        });

        //先移除已有的事件
        this.off("success", _success).on("success", function (file, response) {
            var ret = {code: 0, msg: response};
            try {
                ret = typeof response === 'string' ? JSON.parse(response) : response;
                if (file.xhr.status === 200) {
                    if (typeof ret.key !== 'undefined') {
                        ret = {code: 1, msg: "", data: {url: '/' + ret.key, hash: ret.hash}};
                    }
                    Fast.api.ajax({
                        url: "/addons/qiniu/index/notify",
                        data: {name: file.name, url: ret.data.url, hash: ret.data.hash, size: file.size, width: file.width || 0, height: file.height || 0, type: file.type, qiniutoken: Config.upload.multipart.qiniutoken}
                    }, function () {
                        return false;
                    }, function () {
                        return false;
                    });
                }
            } catch (e) {
                console.error(e);
            }
            _success.call(this, file, ret);
        });

        //如果是直传模式
        if (Config.upload.uploadmode === 'client') {
            var _url = this.options.url;

            //分片上传时URL链接不同
            this.options.url = function (files) {
                this.options.headers = {"Authorization": "UpToken " + Config.upload.multipart.qiniutoken};
                if (files[0].upload.chunked) {
                    var chunk = null;
                    files[0].upload.chunks.forEach(function (item) {
                        if (item.status === 'uploading') {
                            chunk = item;
                        }
                    });
                    if (!chunk) {
                        return Config.upload.uploadurl + '/mkfile/' + files[0].size;
                    } else {
                        return Config.upload.uploadurl + '/mkblk/' + chunk.dataBlock.data.size;
                    }
                }
                return _url;
            };

            this.options.params = function (files, xhr, chunk) {
                var params = Config.upload.multipart;
                if (chunk) {
                    return $.extend({}, params, {
                        filesize: chunk.file.size,
                        filename: chunk.file.name,
                        chunkid: chunk.file.upload.uuid,
                        chunkindex: chunk.index,
                        chunkcount: chunk.file.upload.totalChunkCount,
                        chunkfilesize: chunk.dataBlock.data.size,
                        width: chunk.file.width || 0,
                        height: chunk.file.height || 0,
                        type: chunk.file.type,
                    });
                } else {
                    var retParams = $.extend({}, params);
                    //七牛云直传使用的是token参数
                    retParams.token = retParams.qiniutoken;
                    delete retParams.qiniutoken;
                    return retParams;
                }
            };

            //分片上传时需要变更提交的内容
            this.on("sending", function (file, xhr, formData) {
                if (file.upload.chunked) {
                    var _send = xhr.send;
                    xhr.send = function () {
                        var chunk = null;
                        file.upload.chunks.forEach(function (item) {
                            if (item.status == 'uploading') {
                                chunk = item;
                            }
                        });
                        if (chunk) {
                            _send.call(xhr, chunk.dataBlock.data);
                        }
                    };
                }
            });
        }
    };

});
