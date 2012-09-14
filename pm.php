<?php
/////////////////////////////////////////////////////////////////
//云边开源轻博, Copyright (C)   2010 - 2011  qing.thinksaas.cn 
//EMAIL:nxfte@qq.com QQ:234027573                              
//$Id: pm.php 1017 2012-06-24 16:11:30Z anythink $ 

class pm extends top
{ 

	function __construct(){  
        parent::__construct(); 
		$this->needLogin();
    }

	/*获取pm列表*/
	function pmlist(){
		$rs = spClass('db_pm')->pmlist($this->uid,$this->spArgs('page',1));
		if($rs){
			foreach($rs['data'] as &$d){
				$d['h_url'] = goUserHome(array('uid'=>$d['touid'], 'domain'=>$d['todoman']));
				$d['h_img'] = avatar(array('uid'=>$d['touid'],'size'=>'small'));
				$d['time'] = ybtime(array('time'=>$d['time']));
			}
			$this->api_success($rs);
		}
		$this->api_success('');
	}
	
	/*发送pm to title body*/
	function sendpm(){
		if($this->spArgs('username') == '' || $this->spArgs('body') == ''){
			$this->api_error('收信人或内容不能为空');
		}
		$user = spClass('db_member')->find(array('username'=>strreplaces($this->spArgs('username'))));
		if(!is_array($user)){
			$this->api_error('收信人不存在');
		}
		if($user['uid'] == $_SESSION['uid']){
			$this->api_error('不能给自己发信');
		}
		if($_SESSION['pm_ready'.$user['uid']] > time()){
			$this->api_error('每次每人发信需要间隔1分钟');
		}
		
		spClass('db_pm')->sendpm($_SESSION['uid'], $user['uid'], strreplaces($this->spArgs('body')));
		$_SESSION['pm_ready'.$user['uid']]  = time()+60;
		$this->api_success(true);
	}
	
	//获取我和某人的对话
	function pminfo(){
		$touid = intval($this->spArgs('uid'));
		if($touid == ''){
			$this->api_error('对话参数有误');
		}
		if($touid == $this->uid){
			//$this->api_error('无法与自己对话');
		}
		$data = spclass('db_pm')->pminfo($this->uid,$touid,$this->spArgs());
		$toname = spclass('db_member')->find(array('uid'=>$touid),'','username');
		$num = '';
		
		foreach($data['data'] as &$d){
			$d['h_url'] = goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['todoman']));
			$d['h_img'] = avatar(array('uid'=>$d['uid'],'size'=>'middle'));
			$d['time'] = ybtime(array('time'=>$d['time']));
			$num = $d['pmnum'];
		}
		$me = array(
			'm_img'    => avatar(array('uid'=>$_SESSION['uid'],'size'=>'middle')),
			'm_url'    => goUserHome(array('uid'=>$_SESSION['uid'], 'domain'=>$_SESSION['domain'])),
			'm_name'   => $_SESSION['username'], 
			't_name'   => $toname['username'],
			'num'   => $num
		);
		$data['args'] = $me;
		spclass('db_pm')->update(array('touid'=>$this->uid,'uid'=>$touid),array('isnew'=>0));
		$this->api_success($data);
	}
	
	//讲我跟某人的对话设为已读
	function toread(){
	
	
	
	}
	
	function delpm(){
		if($this->spArgs('id')){
			$id = intval($this->spArgs('id'));
		}else{
			$this->api_error('删除的记录不存在');
		}
		if(spClass('db_pm')->delpm($this->uid,$id)){
			$this->api_error('没有记录需要删除');
		}else{
			$this->api_success(true);
		}
	}
	
	
	
	/*我的通知*/
	public function mynotice(){
		
		$this->memberinfo();
		$this->display('user_mynotice.html');	

	}
	
		
	
}