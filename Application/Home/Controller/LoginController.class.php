<?php
namespace Home\Controller;
use Think\Controller;

class LoginController extends BaseController {

    /**
     * 处理登陆页
     */
    public function login(){
        $password = md5(I('password',''));
        $username = I('username','');
        if(!$password){
            $this->resultMsg('error','密码不能为空');
        }
        if(!$username){
            $this->resultMsg('error','用户名不能为空');
        }

        $where = array('username' =>$username);
        $table = M('user');
        $user = $table->where($where)->find();
        if($user['password']!=$password){
            $this->resultMsg('error','账号或密码错误');
        }
        if ($user['lock']) {
            $this->resultMsg('error','用户被锁定！');
        }
        session('uid', $user['id']);
        $this->resultMsg('success','登录成功！');
    }

    /**
     * [forget 手机忘记密码]
     */
    public function mobileForget(){
        $mobile = I('username','');
        $password = md5(I('password',''));
        $code = I('code','');
        $table = M('mobile_verify_record');
        $where = array('mobile' =>$mobile,'is_user'=>0);
        $result = $table->where($where)->order('created desc')->find();
        if($result['code']!=$code){
            $this->resultMsg('error','验证码不正确');
        }
        $nowTime = date('Y-m-d H:i:s',time());
        $timeOut = date('Y-m-d H:i:s',strtotime($result['created']."+30 minute"));
        //判断验证码是否超时
        if(strtotime($timeOut)<strtotime($nowTime)){
            $this->resultMsg('error','验证码超时，请重新发送！');
        }
        $user = D("user"); // 实例化用户
        $condition = array('username'=>$mobile);
        $user->where($condition)->data(array('password'=>$password))->save();
        $this->resultMsg('success','密码重置成功！');
    }

    /**
     * 邮箱忘记密码
     */

    public function email_forget(){
        
    }

    /**
     * [reset 重置密码]
     */
    public function reset(){
        $arr = I();
        $oldPassword = md5(I('oldPassword',''));
        $password = md5(I('password',''));
        if($status = is_login()){
            $where = array('id' =>$status);
            $user = M('user');
            $result = $user->where($where)->find();
            if($result['password']!=$oldPassword){
                $this->resultMsg('error','旧密码不正确');
            }
            $user->where(array('id'=>$status))->data(array('password'=>$password))->save();
            $this->resultMsg('success','密码修改成功');
        }else{
            $this->resultMsg('error','尚未登录');
        }
    }


    /**
     * [mobile_register 手机用户注册]
     */
    public function mobileRegister() {
        $mobile = I('username','');
        $code = I('code','');
        $password = md5(I('password',''));
        $nickname = I('nickname','');
        $user = M("user");
        if(!preg_match("/1[3458]{1}\d{9}$/",$mobile)){
            $this->resultMsg('error','请输入正确的手机号码！');
        }
        if(!preg_match('/^[_a-zA-Z0-9\x{4e00}-\x{9fa5}]{2,18}$/u',$nickname)){
            $this->resultMsg('error','请输入2-18中文，数字，下划线');
        }

        $condition['nickname'] = $nickname;
        $nickData = $user->where($condition)->find();
        if($nickData){
            $this->resultMsg('error','昵称已经存在');
        }

        $mobileWhere = array('username'=>$mobile);
        $re = $user->where($mobileWhere)->find();
        if($re){
            $this->resultMsg('error','该手机号已经被注册');
        }
        if(empty($password)){
            $this->resultMsg('error','密码不能为空');
        }
        $table = M('mobile_verify_record');
        $where = array('mobile' =>$mobile,'is_user'=>0);
        $result = $table->where($where)->order('created desc')->find();
        if($result['code']!=$code){
            $this->resultMsg('error','验证码不正确');
        }
        $nowTime = date('Y-m-d H:i:s',time());
        $timeOut = date('Y-m-d H:i:s',strtotime($result['created']."+30 minute"));
        //判断验证码是否超时
        if(strtotime($timeOut)<strtotime($nowTime)){
            $this->resultMsg('error','验证码超时，请重新发送！');
        }
        $data = array(
            'username' => $mobile,
            'password' => $password,
            'mobile' => $mobile,
            'nickname' =>$nickname,
            'registime' => date('Y-m-d H:i:s',time()),
            'valid_mobile' => 1
        );

        $uid = $user->data($data)->add();
        if($uid){
            session('uid', $uid);
            $table->where($where)->data(array('is_user'=>1))->save();
            $this->resultMsg('success','注册成功！');
        }else{
            $this->resultMsg('error','注册失败！');
        }
    }


    /**
     * [email_register 手机用户注册]
     */
    public function emailRegister() {
        $email = I('username','');
        $password = md5(I('password',''));
        $nickname = I('nickname','');
        $user = M("user");
        if(!preg_match('/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i',$email)){
            $this->resultMsg('error','请输入正确邮箱账户！');
        }
        if(!preg_match('/^[_a-zA-Z0-9\x{4e00}-\x{9fa5}]{2,18}$/u',$nickname)){
            $this->resultMsg('error','请输入2-18中文，数字，下划线');
        }
        
        $condition['nickname'] = $nickname;
        $nickData = $user->where($condition)->find();
        if($nickData){
            $this->resultMsg('error','昵称已经存在');
        }

        $emailWhere = array('username'=>$email);
        $re = $user->where($emailWhere)->find();
        if($re){
            $this->resultMsg('error','该邮箱已经被注册');
        }
        if(empty($password)){
            $this->resultMsg('error','密码不能为空');
        }
        $uData = array(
            'username' => $email,
            'password' => $password,
            'email' => $email,
            'nickname' =>$nickname,
            'registime' => date('Y-m-d H:i:s',time()),
        );
        $uid = $user->data($uData)->add();
        if($uid){
            session('uid', $uid);
            $table = M('email_verify_record');
            $ip = get_client_ip();
            $createTime = date('Y-m-d H:i:s',time());
            //token生成
            $code = sha1($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].time().rand());
            $data = array('code'=>$code,'email'=>$email,'ip'=>$ip,'created'=>$createTime);
            $status = $table->add($data);
            sendMail('1509699669@qq.com', '用户激活邮件','点击或复制该地址：http://www.withcode.com/index.php/Login/emailActivate/token/'.$code.'/email/'.$email);
            $this->resultMsg('success','注册成功！，稍后您将会收到一条激活邮件。');
        }else{
            $this->resultMsg('error','注册失败！');
        }
    }

    //邮件激活
    public function emailActivate(){
        $token = I('token');
        $email = I('email');
        $table = M('email_verify_record');
        $user = M('user');
        $re = $user->where(array('email'=>$email,'valid_email'=>1))->find();
        if($re){
            $this->resultMsg('error','用户已经被激活，不需要再次激活');
        }
        $where = array('code' =>$token,'email'=>$email,'is_user'=>0);
        $result = $table->where($where)->order('created desc')->find();
        if($result['code']!=$token){
            $this->resultMsg('error','token失效，请重新发送邮件');
        }
        $nowTime = date('Y-m-d H:i:s',time());
        $timeOut = date('Y-m-d H:i:s',strtotime($result['created']."+30 minute"));
        //判断验证码是否超时
        if(strtotime($timeOut)<strtotime($nowTime)){
            $this->resultMsg('error','激活链接超时，请重新发送邮件！');
        }
        $user->where(array('email'=>$email))->data(array('valid_email'=>1))->save();
        $table->where($where)->data(array('is_user'=>1))->save();
        $this->resultMsg('success','用户激活成功');
        // echo $token;


    }

    /**
     * [sendCode 发送验证码]
     * @param  [type]  $mobile [description]
     * @param  integer $type   [0为注册，1为找回密码]
     */
    public function sendCode(){
        $mobile = I('mobile','');
        if(!preg_match("/1[3458]{1}\d{9}$/",$mobile)){
            $this->resultMsg('error','请输入正确的手机号码！');
        }
        $table = M('mobile_verify_record');
        $code=sprintf("%06d",rand(1,999999));
        $ip = get_client_ip();
        $createTime = date('Y-m-d H:i:s',time());
        $endTime = date('Y-m-d',time()).' 23:59:59';
        $data = array('code'=>$code,'mobile'=>$mobile,'ip'=>$ip,'created'=>$createTime);
        $where = array('ip'=>$ip,'created'=>array('lt',$endTime));
        $count = $table->where($where)->count();
        if($count >= 25){
            $this->resultMsg('error','请求次数过多！');
        }
        $status = $table->add($data);
        //此处添加短信发送接口
        if($status){
            // $sms = new ChuanglanSmsApi();
            // $result = $sms->sendSMS($mobile,'短信测试');
            $this->resultMsg('success','发送成功，请注意查收！');
            // if($result['status'] == 'success'){
            //     $this->resultMsg('success','发送成功，请注意查收！');
            // }else{
            //     $this->resultMsg('error','短信出现未知错误');
            // }
        }else{
            $this->resultMsg('error','发送失败，请重试！');
        }
    }



    /**
     * 判断用户是否存在
     */
    public function checkAccount(){
        $username = I('username','');
        $user = M("user");
        $where = array('username'=>$username);
        $result = $user->where($where)->find();
        if($result){
            echo 'false';
        }else{
            echo 'true';
        }
    }


    /**
     * 判断昵称是否存在
     */
    public function checkNickname(){
        $nickname = I('nickname','');
        $user = M("user");
        $where = array('nickname'=>$nickname);
        $result = $user->where($where)->find();
        if($result){
            echo 'false';
        }else{
            echo 'true';
        }
    }


    /**
     * [logout 退出登录]
     */
    public function logout() {
        //卸载SESSION
        session_unset();
        session_destroy();
        $this->resultMsg('success','退出登录成功！');
    }

}
