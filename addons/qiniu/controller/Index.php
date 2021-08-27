<?php

namespace addons\qiniu\controller;

use app\common\exception\UploadException;
use app\common\library\Upload;
use app\common\model\Attachment;
use Qiniu\Auth;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Storage\UploadManager;
use think\addons\Controller;
use think\Config;

/**
 * 七牛管理
 *
 */
class Index extends Controller
{

    public function _initialize()
    {
        //跨域检测
        check_cors_request();

        parent::_initialize();
        Config::set('default_return_type', 'json');
    }

    public function index()
    {
        Config::set('default_return_type', 'html');
        $this->error("当前插件暂无前台页面");
    }

    /**
     * 中转上传文件
     * 上传分片
     * 合并分片
     */
    public function upload()
    {
        Config::set('default_return_type', 'json');

        $this->check();
        $config = get_addon_config('qiniu');
        $config['savekey'] = str_replace(['{year}', '{mon}', '{day}', '{filemd5}', '{.suffix}'], ['$(year)', '$(mon)', '$(day)', '$(etag)', '$(ext)'], $config['savekey']);

        // 构建鉴权对象
        $auth = new Auth($config['accessKey'], $config['secretKey']);
        // 生成上传 Token
        $token = $auth->uploadToken($config['bucket'], null, 3600, ['saveKey' => ltrim($config['savekey'], '/')]);
        // 初始化 UploadManager 对象并进行文件的上传。
        $uploadMgr = new UploadManager();

        $chunkid = $this->request->post("chunkid");
        if ($chunkid) {
            $action = $this->request->post("action");
            $chunkindex = $this->request->post("chunkindex/d");
            $chunkcount = $this->request->post("chunkcount/d");
            $filesize = $this->request->post("filesize");
            $filename = $this->request->post("filename");
            if ($action == 'merge') {
                if ($config['uploadmode'] == 'server') {
                    $attachment = null;
                    //合并分片文件
                    try {
                        $upload = new Upload();
                        $attachment = $upload->merge($chunkid, $chunkcount, $filename);
                    } catch (UploadException $e) {
                        $this->error($e->getMessage());
                    }
                }

                $contexts = $this->request->post("contexts/a", []);
                $uploader = new ResumeUploader($token, null, null, $filesize);
                list($ret, $err) = $uploader->setContexts($contexts)->makeFile($filename);
                if ($err !== null) {
                    $this->error("上传失败");
                } else {
                    $this->success("上传成功", '', ['url' => '/' . $ret['key'], 'fullurl' => cdnurl('/' . $ret['key'], true), 'hash' => $ret['hash']]);
                }
            } else {
                //默认普通上传文件
                $file = $this->request->file('file');
                try {
                    $upload = new Upload($file);
                    $file = $upload->chunk($chunkid, $chunkindex, $chunkcount);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }

                //上传分片文件
                //$file = $this->request->file('file');
                $filesize = $file->getSize();
                //合并分片文件
                $uploader = new ResumeUploader($token, null, fopen($file->getRealPath(), 'rb'), $filesize);
                $ret = $uploader->uploadChunk($chunkindex, $file, $filesize);
                $this->success("上传成功", "", $ret);
            }
        } else {
            $attachment = null;
            //默认普通上传文件
            $file = $this->request->file('file');
            try {
                $upload = new Upload($file);

                $suffix = $upload->getSuffix();
                $md5 = md5_file($file->getRealPath());
                $search = ['$(year)', '$(mon)', '$(day)', '$(etag)', '$(ext)'];
                $replace = [date("Y"), date("m"), date("d"), $md5, '.' . $suffix];
                $savekey = ltrim(str_replace($search, $replace, $config['savekey']), '/');

                $attachment = $upload->upload($savekey);
            } catch (UploadException $e) {
                $this->error($e->getMessage());
            }

            //文件绝对路径
            $filePath = $upload->getFile()->getRealPath() ?: $upload->getFile()->getPathname();

            //上传到七牛后保存的文件名
            $saveKey = ltrim($attachment->url, '/');

            try {
                // 调用 UploadManager 的 putFile 方法进行文件的上传。
                list($ret, $err) = $uploadMgr->putFile($token, $saveKey, $filePath);

                if ($err !== null) {
                    throw new \Exception("上传失败");
                }
                //成功不做任何操作
            } catch (\Exception $e) {
                $attachment->delete();
                @unlink($filePath);
                $this->error("上传失败");
            }

            $this->success("上传成功", '', ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
        }
    }

    /**
     * 通知回调
     */
    public function notify()
    {
        Config::set('default_return_type', 'json');

        $this->check();

        $size = $this->request->post('size');
        $name = $this->request->post('name');
        $hash = $this->request->post('hash', '');
        $type = $this->request->post('type');
        $width = $this->request->post('width');
        $height = $this->request->post('height');
        $url = $this->request->post('url');
        $suffix = substr($name, stripos($name, '.') + 1);

        $attachment = Attachment::where('url', $url)->where('storage', 'qiniu')->find();
        if (!$attachment) {
            $params = array(
                'admin_id'    => (int)session('admin.id'),
                'user_id'     => (int)cookie('uid'),
                'filename'    => $name,
                'filesize'    => $size,
                'imagewidth'  => $width,
                'imageheight' => $height,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $type,
                'url'         => $url,
                'uploadtime'  => time(),
                'storage'     => 'qiniu',
                'sha1'        => $hash,
            );
            Attachment::create($params);
        }
        $this->success();
    }

    /**
     * 检查签名是否正确或过期
     */
    protected function check()
    {
        $qiniutoken = $this->request->post('qiniutoken', $this->request->server('AUTHORIZATION'), 'trim');
        if (!$qiniutoken) {
            $this->error("参数不正确");
        }
        $config = get_addon_config('qiniu');
        $auth = new Auth($config['accessKey'], $config['secretKey']);
        list($accessKey, $sign, $data) = explode(':', $qiniutoken);
        if (!$accessKey || !$sign || !$data) {
            $this->error("参数不正确");
        }
        if ($accessKey !== $config['accessKey']) {
            $this->error("参数不正确");
        }
        if ($accessKey . ':' . $sign !== $auth->sign($data)) {
            $this->error("签名不正确");
        }
        $json = json_decode(\Qiniu\base64_urlSafeDecode($data), true);
        if ($json['deadline'] < time()) {
            $this->error("请求已经超时");
        }
    }
}
