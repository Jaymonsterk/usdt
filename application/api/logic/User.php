<?php

namespace app\api\logic;

use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;
use think\Url;

/**
 * Token接口
 */
class User
{

    private static $server = array();


    //获取邀请总人数
    public function getInviteNum($uid) {
        return Db::name('invite_record')->where('parent_uid','=',$uid)->count();
    }

    //获取邀请列表
    public function getInviteList($uid,$page_rows) {
        $data = Db::name('invite_record')
            ->alias('ir')
            ->join('user u','ir.uid = u.id','LEFT')
            ->field('ir.uid,u.nickname,u.logintime create_time')
            ->where('ir.parent_uid','=',$uid)
            ->order('ir.id desc')
            ->paginate($page_rows,true,[
                'query' => []
            ]);
        return $data;
    }

    //查询管理员关联的uid
    public function getUidByAdminId($admin_id) {
        return Db::name('user')->where('admin_id','=',$admin_id)->value('id');
    }

    //创建谷歌验证
    public function createGoogleCheck($uid) {
        $data = Google::getInstance()->createQrcode();
        Db::name('user_google_check')->insert([
            'uid' => $uid,
            'secret' => $data['secret'],
            'qrcode' => $data['qrcode_url'],
            'status' => '0',
            'create_time' => time()
        ]);
        return $data;
    }

    //绑定谷歌验证
    public function bindGoogleCheck($uid,$code) {
        $data = Db::name('user_google_check')
            ->where('uid','=',$uid)
            ->where('status','=','0')
            ->find();
        if (!$data) {
            return '数据不存在';
        }
        $secret = $data['secret'];
        $check = Google::getInstance()->checkCode($secret,$code);
        if ($check) {
            Db::name('user_google_check')
                ->where('uid','=',$uid)
                ->where('id','=',$data['id'])
                ->update(['status' => '1','update_time' => time()]);
            Db::name('user_google_check')
                ->where('uid','=',$uid)
                ->where('id','<>',$data['id'])
                ->update(['status' => '2','update_time' => time()]);
            return true;
        }
        return false;
    }

    //验证谷歌验证
    public function checkGoogleCheck($uid,$code) {
        $data = Db::name('user_google_check')
            ->where('uid','=',$uid)
            ->where('status','=','1')
            ->find();
        if (!$data) {
            return '数据不存在';
        }
        $secret = $data['secret'];
        $check = Google::getInstance()->checkCode($secret,$code);
        if ($check) {
            return true;
        }
        return false;
    }

    //检查是否绑定谷歌验证
    public function isBindGoogleCheck($uid) {
        return Db::name('user_google_check')
            ->where('uid','=',$uid)
            ->where('status','=','1')
            ->count();
    }

    //获取邀请信息
    public function getInviteInfo($uid) {
        //查询用户邀请码
        $userinfo = Db::name('user')->where('id','=',$uid)->field('id,invite_code')->find();
        if (!$userinfo) {
            $this->error(__('用户未发现'));
        }

        //查询以太坊地址
        $usermoney = Db::name('user_money')->where('uid','=',$uid)->field('address')->find();
        if (!$usermoney) {
            $this->error(__('地址为空'));
        }


        //邀请注册连接
        $content = Url::build('chainex/web/register','invite_code='.$userinfo['invite_code'],false,true);
        $invite_url = 'http://www.baidu.com';

        //创建二维码
        $path = 'uploads/chainex/invite/'.$uid.'.png';
        Qrcode::create_qrcode($content,$path);

        $data = [
            'invite_qrcode' => Url::build("/uploads/chainex/invite/".$uid.".png",'',false,true),
            'invite_url' => $content
        ];
        return $data;
    }

    //获取海报
    public function getHaiBao($uid) {
        $userinfo = Db::name('user')->where('id','=',$uid)->field('invite_code')->find();


        $content = Url::build('chainex/web/register','invite_code='.$userinfo['invite_code'],false,true);

        //判断是否存在
//        if (file_exists("uploads/chainex/invite/haibao_invite_".$uid.".png")) {
//            $url = Url::build("/uploads/chainex/invite/haibao_invite_".$uid.".png",'',false,true);
//            $this->success(__('OK'),['img' => $url]);
//        }


        //创建二维码
        Qrcode::create_invite_qrcode($content,'uploads/chainex/invite/'.$uid.'.png');

        //合成图片

        $bigImgPath = Db::name('ads')->where('type','=','2')->value('image');
//            'uploads/chainex/invite/invite_bg.png';
        $bigImgPath = ltrim($bigImgPath,'/');
        $img = imagecreatefromstring(file_get_contents($bigImgPath));

        $qCodePath = 'uploads/chainex/invite/'.$uid.'.png';

        $qCodeImg = imagecreatefromstring(file_get_contents($qCodePath));
        imagesavealpha($img,true);//假如是透明PNG图片，这里很重要 意思是不要丢了图像的透明<code class="php spaces"></code>
        list($qCodeWidth, $qCodeHight, $qCodeType) = getimagesize($qCodePath);
// imagecopymerge使用注解
        imagecopymerge($img, $qCodeImg, 266, 1000, 0, 0, $qCodeWidth, $qCodeHight, 100);
        list($bigWidth, $bigHight, $bigType) = getimagesize($bigImgPath);

        header('Content-Type:image/png');
        imagepng($img,"uploads/chainex/invite/haibao_invite_".$uid.".png");

        $url = Url::build("/uploads/chainex/invite/haibao_invite_".$uid.".png",'',false,true);

        $share_info = Db::name('ads')->where('type','=','2')->order('sort desc')->find();
        $share_info['haibao_url'] = $url;
        return $share_info;
    }



    /**
     * 单例入口
     * @return mixed
     */
    public static function getInstance() {
        $className =  get_called_class();
        if(!isset(self::$server[$className])){
            self::$server[$className] = new $className();
        }
        return self::$server[$className];
    }
}
