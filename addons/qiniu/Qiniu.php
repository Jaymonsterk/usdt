<?php

namespace addons\qiniu;

use fast\Http;
use Qiniu\Auth;
use think\Addons;
use think\Loader;

/**
 * 七牛云储存插件
 */
class Qiniu extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 上传初始化时
     */
    public function uploadConfigInit(&$upload)
    {
        $config = $this->getConfig();

        $policy = array(
            'saveKey' => ltrim($config['savekey'], '/'),
        );

        $config['savekey'] = str_replace(['$(year)', '$(mon)', '$(day)', '$(etag)', '$(ext)'], ['{year}', '{mon}', '{day}', '{filemd5}', '{.suffix}'], $config['savekey']);
        $auth = new Auth($config['accessKey'], $config['secretKey']);
        $multipart['qiniutoken'] = $auth->uploadToken($config['bucket'], null, $config['expire'], $policy);
        $upload = array_merge($upload, [
            'cdnurl'     => $config['cdnurl'],
            'uploadurl'  => $config['uploadmode'] == 'client' ? $config['uploadurl'] : addon_url('qiniu/index/upload', [], false, true),
            'uploadmode' => $config['uploadmode'],
            'bucket'     => $config['bucket'],
            'maxsize'    => $config['maxsize'],
            'mimetype'   => $config['mimetype'],
            'savekey'    => $config['savekey'],
            'chunking'   => (bool)($config['chunking'] ?? $upload['chunking']),
            'chunksize'  => (int)($config['chunksize'] ?? $upload['chunksize']),
            'multipart'  => $multipart,
            'storage'    => $this->getName(),
            'multiple'   => $config['multiple'] ? true : false,
        ]);
    }

    /**
     * 附件删除后
     */
    public function uploadDelete($attachment)
    {
        $config = $this->getConfig();
        if ($attachment['storage'] == 'qiniu' && isset($config['syncdelete']) && $config['syncdelete']) {
            $auth = new Auth($config['accessKey'], $config['secretKey']);
            $entry = $config['bucket'] . ':' . ltrim($attachment->url, '/');
            $encodedEntryURI = \Qiniu\base64_urlSafeEncode($entry);
            $url = 'http://rs.qiniu.com/delete/' . $encodedEntryURI;
            $headers = $auth->authorization($url);
            //删除云储存文件
            $ret = Http::sendRequest($url, [], 'POST', [CURLOPT_HTTPHEADER => ['Authorization: ' . $headers['Authorization']]]);
            //如果是服务端中转，还需要删除本地文件
            //if ($config['uploadmode'] == 'server') {
            //    $filePath = ROOT_PATH . 'public' . str_replace('/', DS, $attachment->url);
            //    if ($filePath) {
            //        @unlink($filePath);
            //    }
            //}
        }
        return true;
    }

    public function appInit()
    {
        if (!class_exists('\Qiniu\Config')) {
            Loader::addNamespace('Qiniu', ADDON_PATH . 'qiniu' . DS . 'library' . DS . 'Qiniu' . DS);
            require_once ADDON_PATH . 'qiniu' . DS . 'library' . DS . 'Qiniu' . DS . 'functions.php';
        }
    }

}
