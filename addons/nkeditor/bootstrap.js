require.config({
    paths: {
        'nkeditor': '../addons/nkeditor/js/customplugin',
        'nkeditor-core': '../addons/nkeditor/nkeditor',
        'nkeditor-lang': '../addons/nkeditor/lang/zh-CN',
    },
    shim: {
        'nkeditor': {
            deps: [
                'nkeditor-core',
                'nkeditor-lang'
            ]
        },
        'nkeditor-core': {
            deps: [
                'css!../addons/nkeditor/themes/black/editor.min.css',
                'css!../addons/nkeditor/css/common.css'
            ],
            exports: 'window.KindEditor'
        },
        'nkeditor-lang': {
            deps: [
                'nkeditor-core'
            ]
        }
    }
});
require(['form'], function (Form) {
    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);
        if ($(".editor", form).size() > 0) {
            require(['nkeditor', 'upload'], function (Nkeditor, Upload) {
                var getImageFromClipboard, getImageFromDrop, getFileFromBase64;
                getImageFromClipboard = function (data) {
                    var i, item;
                    i = 0;
                    while (i < data.clipboardData.items.length) {
                        item = data.clipboardData.items[i];
                        if (item.type.indexOf("image") !== -1) {
                            return item.getAsFile() || false;
                        }
                        i++;
                    }
                    return false;
                };
                getImageFromDrop = function (data) {
                    var i, item, images;
                    i = 0;
                    images = [];
                    while (i < data.dataTransfer.files.length) {
                        item = data.dataTransfer.files[i];
                        if (item.type.indexOf("image") !== -1) {
                            images.push(item);
                        }
                        i++;
                    }
                    return images;
                };
                getFileFromBase64 = function (data, url) {
                    var arr = data.split(','), mime = arr[0].match(/:(.*?);/)[1],
                        bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
                    while (n--) {
                        u8arr[n] = bstr.charCodeAt(n);
                    }
                    var filename, suffix;
                    if (typeof url != 'undefined') {
                        var urlArr = url.split('.');
                        filename = url.substr(url.lastIndexOf('/') + 1);
                        suffix = urlArr.pop();
                    } else {
                        filename = Math.random().toString(36).substring(5, 15);
                    }
                    if (!suffix) {
                        suffix = data.substring("data:image/".length, data.indexOf(";base64"));
                    }

                    var exp = new RegExp("\\." + suffix + "$", "i");
                    filename = exp.test(filename) ? filename : filename + "." + suffix;
                    var file = new File([u8arr], filename, {type: mime});
                    return file;
                };

                var getImageFromUrl = function (url, callback) {
                    var req = new XMLHttpRequest();
                    req.open("GET", Fast.api.fixurl("/addons/nkeditor/index/download") + "?url=" + encodeURIComponent(url), true);
                    req.responseType = "blob";
                    req.onload = function (event) {
                        var file;
                        if (req.status >= 200 && req.status < 300 || req.status == 304) {
                            var blob = req.response;
                            var mimetype = blob.type || "image/png";
                            var mimetypeArr = mimetype.split("/");
                            if (mimetypeArr[0].toLowerCase() === 'image') {
                                var suffix = ['jpeg', 'jpg', 'bmp', 'gif', 'png', 'webp'].indexOf(mimetypeArr[1]) > -1 ? mimetypeArr[1] : 'png';
                                var filename = Math.random().toString(36).substring(5, 15) + "." + suffix;
                                file = new File([blob], filename, {type: mimetype});
                            }
                        }
                        callback.call(this, file);
                    };
                    req.send();
                    return;
                };
                //??????Word??????
                Nkeditor.uploadwordimage = function (index, image) {
                    var that = this;
                    (function () {
                        var file = getFileFromBase64(image);
                        var placeholder = new RegExp("##" + index + "##", "g");
                        Upload.api.send(file, function (data) {
                            that.html(that.html().replace(placeholder, Fast.api.cdnurl(data.url)));
                        }, function (data) {
                            that.html(that.html().replace(placeholder, ""));
                        });
                    }(index, image));
                };

                Nkeditor.lang({
                    remoteimage: '??????????????????'
                });
                //??????????????????
                Nkeditor.plugin('remoteimage', function (K) {
                    var editor = this, name = 'remoteimage';
                    editor.plugin.remoteimage = {
                        download: function (e) {
                            var that = this;
                            var html = that.html();
                            var staging = {}, orgined = {}, index = 0, images = 0, completed = 0, failured = 0;
                            var checkrestore = function () {
                                if (completed + failured >= images) {
                                    $.each(staging, function (i, j) {
                                        that.html(that.html().replace("<code>" + i + "</code>", j));
                                    });
                                    Toastr.info("?????????" + completed + " ?????????" + failured);
                                }
                            };
                            html.replace(/<code>([\s\S]*?)<\/code>/g, function (code) {
                                    staging[index] = code;
                                    return "<code>" + index + "</code>";
                                }
                            );
                            html = html.replace(/<img([\s\S]*?)\ssrc\s*=\s*('|")((http(s?):)([\s\S]*?))('|")([\s\S]*?)[\/]?>/g, function () {
                                images++;
                                var url = arguments[3];
                                var placeholder = '<img src="' + Fast.api.cdnurl("/assets/addons/nkeditor/img/downloading.png") + '" data-index="' + index + '" />';
                                //?????????????????????????????????????????????,?????????
                                if ((Config.upload.cdnurl && url.indexOf(Config.upload.cdnurl) > -1) || url.indexOf(location.origin) > -1) {
                                    completed++;
                                    return arguments[0];
                                } else {
                                    orgined[index] = arguments[0];
                                }
                                //??????????????????
                                (function (index, url, placeholder) {
                                    getImageFromUrl(url, function (file) {
                                        if (!file) {
                                            failured++;
                                            that.html(that.html().replace(placeholder, orgined[index]));
                                            checkrestore();
                                        } else {
                                            Upload.api.send(file, function (data) {
                                                completed++;
                                                that.html(that.html().replace(placeholder, '<img src="' + Fast.api.cdnurl(data.url, true) + '" />'));
                                                checkrestore();
                                            }, function (data) {
                                                failured++;
                                                that.html(that.html().replace(placeholder, orgined[index]));
                                                checkrestore();
                                            });
                                        }
                                    });
                                })(index, url, placeholder);
                                index++;
                                return placeholder;
                            });
                            if (index > 0) {
                                that.html(html);
                            } else {
                                Toastr.info("?????????????????????????????????");
                            }
                        }
                    };
                    // ?????????????????????
                    editor.clickToolbar(name, editor.plugin.remoteimage.download);
                });

                $(".editor", form).each(function () {
                    var that = this;
                    Nkeditor.create(that, {
                        width: '100%',
                        filterMode: false,
                        wellFormatMode: false,
                        allowMediaUpload: true, //????????????????????????
                        allowFileManager: true,
                        allowImageUpload: true,
                        fontSizeTable: ['9px', '10px', '12px', '14px', '16px', '18px', '21px', '24px', '32px'],
                        wordImageServer: typeof Config.nkeditor != 'undefined' && Config.nkeditor.wordimageserver ? "127.0.0.1:10101" : "", //word????????????????????????IP?????????
                        cssPath: Fast.api.cdnurl('/assets/addons/nkeditor/plugins/code/prism.css'),
                        cssData: "body {font-size: 13px}",
                        fillDescAfterUploadImage: false, //??????????????????????????????????????????
                        themeType: typeof Config.nkeditor != 'undefined' ? Config.nkeditor.theme : 'black', //???????????????,????????????????????????
                        fileManagerJson: Fast.api.fixurl("/addons/nkeditor/index/attachment/module/" + Config.modulename),
                        items: [
                            'source', 'undo', 'redo', 'preview', 'print', 'template', 'code', 'quote', 'cut', 'copy', 'paste',
                            'plainpaste', 'wordpaste', 'justifyleft', 'justifycenter', 'justifyright',
                            'justifyfull', 'insertorderedlist', 'insertunorderedlist', 'indent', 'outdent', 'subscript',
                            'superscript', 'clearhtml', 'quickformat', 'selectall',
                            'formatblock', 'fontname', 'fontsize', 'forecolor', 'hilitecolor', 'bold',
                            'italic', 'underline', 'strikethrough', 'lineheight', 'removeformat', 'image', 'multiimage', 'graft',
                            'flash', 'media', 'insertfile', 'table', 'hr', 'emoticons', 'baidumap', 'pagebreak',
                            'anchor', 'link', 'unlink', 'remoteimage', 'about', 'fullscreen'
                        ],
                        afterCreate: function () {
                            var self = this;
                            //Ctrl+????????????
                            Nkeditor.ctrl(document, 13, function () {
                                self.sync();
                                $(that).closest("form").submit();
                            });
                            Nkeditor.ctrl(self.edit.doc, 13, function () {
                                self.sync();
                                $(that).closest("form").submit();
                            });
                            //????????????
                            $("body", self.edit.doc).bind('paste', function (event) {
                                var image, pasteEvent;
                                pasteEvent = event.originalEvent;
                                if (pasteEvent.clipboardData && pasteEvent.clipboardData.items) {
                                    image = getImageFromClipboard(pasteEvent);
                                    if (image) {
                                        event.preventDefault();
                                        Upload.api.send(image, function (data) {
                                            self.exec("insertimage", Fast.api.cdnurl(data.url, true));
                                        });
                                    }
                                }
                            });
                            //????????????
                            $("body", self.edit.doc).bind('drop', function (event) {
                                var image, pasteEvent;
                                pasteEvent = event.originalEvent;
                                if (pasteEvent.dataTransfer && pasteEvent.dataTransfer.files) {
                                    images = getImageFromDrop(pasteEvent);
                                    if (images.length > 0) {
                                        event.preventDefault();
                                        $.each(images, function (i, image) {
                                            Upload.api.send(image, function (data) {
                                                self.exec("insertimage", Fast.api.cdnurl(data.url, true));
                                            });
                                        });
                                    }
                                }
                            });
                        },
                        afterChange: function () {
                            $(this.srcElement[0]).trigger("change");
                        },
                        //FastAdmin???????????????
                        beforeUpload: function (callback, file) {
                            var file = file ? file : $("input.ke-upload-file", this.form).prop('files')[0];
                            Upload.api.send(file, function (data) {
                                var data = {code: '000', data: {url: Fast.api.cdnurl(data.url, true)}, title: '', width: '', height: '', border: '', align: ''};
                                callback(data);
                            });

                        },
                        //???????????? handler
                        errorMsgHandler: function (message, type) {
                            try {
                                console.log(message, type);
                            } catch (Error) {
                                alert(message);
                            }
                        }
                    });
                });
            });
        }
    }
});
