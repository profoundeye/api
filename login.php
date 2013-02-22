<?php
/////////////////////////////////////////////////////////////////
//云边开源轻博, Copyright (C)   2010 - 2011  qing.thinksaas.cn 
//EMAIL:nxfte@qq.com QQ:234027573                              
//$Id: login.php 1188 2012-07-04 13:04:17Z anythink $ 

//登录注册邀请注册API
class login extends top
{ 

	function __construct(){  
         parent::__construct(); 
    }

	/*用户登录*/
	function vary(){
		if($this->spArgs('email') == '' || $this->spArgs('password') == '')  return $this->api_error('用户名密码不能为空');
		if($this->yb['loginCodeSwitch'] == 1){
			if(!spClass('spVerifyCode')->verify( $this->spArgs('verifycode'))){
				$this->api_error('验证码不正确');
			}
		}
		
		$user = spClass('db_member');
		$rs = $user->findBy('email',$this->spArgs('email'));
		if(!is_array($rs))		$this->api_error('用户名不存在');
		if($rs['open'] == 0) 	$this->api_error('该帐号被限制登录');
		
		$password = password_encode($this->spArgs('password') ,$rs['salt']);
		if($rs['password'] == $password)
		{
			$user->userLogin($rs,$this->spArgs('savename'),$this->spArgs('savename'),$this->spArgs('autologin'));
			$this->api_success(true);
		}else{
			$this->api_error('用户名密码错误');
		}
	}
	
	
	function reg(){
		if($this->spArgs('email') == '' || $this->spArgs('password') == ''|| $this->spArgs('username') == '')  return $this->api_error('用户名、密码、昵称不能为空'); 
		if(strlen($this->spArgs('email')) < 5 || strlen($this->spArgs('email')) > 30) return $this->api_error('邮箱必须大于5小于30个字符'); 
		if(!validateEmail($this->spArgs('email'))) return $this->api_error('邮箱格式不符合规范'); 
		if(strlen($this->spArgs('username')) < 2 || strlen($this->spArgs('username')) > 15) return $this->api_error('昵称最短为2个字符最长为15个字符');
		if(strlen($this->spArgs('password')) < 6) return $this->api_error('密码最少6位');
		$keep =  $this->yb['keep_email'];
		if($keep != ''){
			$keeparray = explode(',',$keep);
			$emails = explode('@',$all['email']);
			if(in_array($emails[0],$keeparray))
			{
				$this->api_error('该邮箱帐号前缀被限制注册');
			}
		}

		if($this->yb['invite_switch'] == 1 || $this->spArgs('invitemode') == 1){
		
			if(!$invite = spClass('db_invite')->chkInviteCode($this->spArgs('invitecode'))){
				$this->api_error('邀请码不存在或者已过期');
			}
		}
		
		$result = spClass('db_member')->findBy('email',$this->spArgs('email'));
		if(is_array($result)){return $this->api_error('注册邮箱已经存在,请更换邮箱');}
		$result = spClass('db_member')->findBy('username',$this->spArgs('username'));
		if(is_array($result)){return $this->api_error('昵称已经存在,请更换昵称');}
		
		if($this->yb['loginCodeSwitch'] == 1){
			if(!spClass('spVerifyCode')->verify( $this->spArgs('verifycode'))){
				$this->api_error('验证码不正确');
			}
		}
		//写入注册信息
		$uid = spClass('db_member')->userReg($this->spArgs());
		//写入邀请信息&互相关注
		if($this->yb['invite_switch'] == 1 || $this->spArgs('invitemode') == 1){
			spClass('db_invite')->useInviteCode($invite['inviteCode'],$invite['uid'],$uid);
			spClass('db_follow')->createTwoFollow($invite['uid'],$uid);
		}
		$this->api_success(true);
	}
	
	
	//找回密码的前置实现
	function findpwd(){
		if($this->spArgs('token') != ''){
			$rs = spClass('db_findpwd')->varyToken($this->spArgs('token'));
			if(is_array($rs)){
				if($this->spArgs('password') == '' || strlen($this->spArgs('password')) < 6)  $this->api_error('密码最小6位');
				if($this->spArgs('password') != $this->spArgs('password2'))  $this->api_error('两次密码不一样请检查');	
				if(spClass('db_member')->changePwd($rs['uid'],$this->spArgs('password'))){
					spClass('db_findpwd')->clearToken($rs['token']);
					$this->api_success('密码修改成功,请使用新密码登录');
				}else{
					$this->api_success('密码修改失败,请稍候再试');
				}
			}else{
				$this->api_error('验证失败,请重新发起请求');
			}
		}
		
		if($this->spArgs('email') == '' || strlen($this->spArgs('email')) < 5)  $this->api_error('请输入正确的邮箱');
		if(!spClass('spVerifyCode')->verify( $this->spArgs('verifycode')))		$this->api_error('验证码不正确');
		$result = spClass('db_member')->findBy('email',$this->spArgs('email'));
		$url = 'http://'.$_SERVER['SERVER_NAME'];
		if(is_array($result)){
		
			if($row = spClass('db_findpwd')->checkToken($result['uid'])){  //如果已经有记录且不过期
				if($row['mailsend'] > time())  $this->api_error('您已经提交过请求，下次请求请在'.date('Y-m-d H:i:s',$row['mailsend']).'后执行'); 
				spClass('db_findpwd')->updateMailsendTime($result['uid']);
				spClass('db_notice')->sendFindPwd($row['uid'],$url.spURl('main','resetpwd',array('token'=>$row['token'])));
			}else{
				$row = spClass('db_findpwd')->createToken($result['uid']);
				spClass('db_findpwd')->updateMailsendTime($result['uid']); //更新邮件发送时间
				spClass('db_notice')->sendFindPwd($row,$url.spURl('main','resetpwd',array('token'=>$row['token'],'do'=>'submit')));
			}
			if($this->yb['mail_open'] == 0){
			 $this->api_success('系统没有开启发信功能,请联系系统管理员'); 
			}
			 $this->api_success('密码找回请求已经发到您的邮箱'.$result['email'].',请查收邮件完成后续操作'); 
		}else{
			$this->api_error('没有找到该邮箱,请检查'); 
		}
	}
	
	function logout(){
		spClass('ybCookie')->set_cookie('sid','delete',1);
		spClass('ybCookie')->set_cookie('auth','delete',1);
		$_SESSION = null;
		session_destroy();
        @session_unset();
        $_COOKIE = NULL;
		$this->api_success(true);
	}
	
	
}
