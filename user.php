<?php
/////////////////////////////////////////////////////////////////
//云边开源轻博, Copyright (C)   2010 - 2011  qing.thinksaas.cn 
//EMAIL:nxfte@qq.com QQ:234027573                              
//$Id: user.php 1304 2012-07-19 13:18:47Z anythink $ 

class user extends top
{ 

	function __construct(){  
        parent::__construct(); 
		$this->needLogin();
    }

	/*获取随机推荐用户列表*/
	function recommend()
	{
        if(!spAccess('r','ybrecommenduser')){
            $recommend = spClass('db_member')->recommend();
            spAccess('w','ybrecommenduser',$recommend,86400); //一天
        }else{
            $recommend =  spAccess('r','ybrecommenduser');
        }
        
        $count = count($recommend); 
        $numbers = range (0,$count-1);
        shuffle($numbers);
        if($count < $this->spArgs('num',12)){
            $num = $count;
        }else{
            $num = $this->spArgs('num',12);
        }
        $queue = array_slice($numbers,0,$num-1); 
        
        $result = array();
        foreach($queue as $d){
            $result[] = $recommend[$d];
        }
		$this->api_success($result);
	}
	
	function save_baseinfo(){
		if($this->yb['keep_niname'] != ''){
			$arr = explode(',',$this->yb['keep_niname']);
			if(in_array($this->spArgs('niname'),$arr)) {$this->api_error('该昵称被保留或限制');}
		}
		
		if($this->yb['keep_domain'] != ''){
			$arr = explode(',',$this->yb['keep_domain']);
			if(in_array($this->spArgs('domain'),$arr))  {$this->api_error('该个性域名被保留或限制');} 
		}

		if(utf8_strlen($this->spArgs('niname')) < 2 || utf8_strlen($this->spArgs('niname')) > 15){$this->api_error('昵称最短2位最长15位'); }
		$niname = spClass('db_member')->find(array('username'=>$this->spArgs('niname')),'','uid,username');
		if(is_array($niname) && $niname['uid'] != $this->uid){$this->api_error('该昵称已被使用'); } //判断昵称是否被使用
		if(utf8_strlen($this->spArgs('domain')) < 4 || utf8_strlen($this->spArgs('domain')) > 15){ $this->api_error('个性域名最短4位最长15位'); }
		if(!preg_match('/^[a-zA-Z]{1}([a-zA-Z0-9]|[._]){1,15}$/',$this->spArgs('domain'))) {$this->api_error('个性域名不符合要求');}
		$domain = spClass('db_member')->find(array('domain'=>$this->spArgs('domain')),'','uid,domain');
		if(is_array($domain) && $domain['uid'] != $this->uid){ $this->api_error('个性域名已被使用');} //判断个性域名是否被使用
		if($this->spArgs('tag') != ''){

			$tagstr = substr($this->spArgs('tag'),0,-1);
			$tag = explode('|',$tagstr);
			if(count($tag) > 8){ $this->api_error('最多关注8个分类'); }
			$tagname_str = ''; //默认名称数据
			$tag_num = array();//默认tagid数据
			foreach($tag as $d){
				$t = explode(',',$d);
				$tagname_str .= $t[0].',';
				$tag_num[] = $t[1];
			}
			$tagstr = substr($tagname_str,0,-1);
			spClass('db_tags_blog')->createTags($this->uid,$tag_num);
		}
		
		/*处理通知*/
		if($this->yb['mail_open'] == 1){
			if($this->spArgs('m_rep') == 1){$_mrep = 1;}else{$_mrep = 0;}
			if($this->spArgs('m_fows') == 1){$_mfow = 1;}else{$_mfow = 0;}
			if($this->spArgs('m_pms') == 1){$_mpm = 1;}else{$_mpm = 0;}
		}else{
			$_mrep = $_mfow = $_mpm = 1;
		}
		$row = array(
			'username'=>htmlspecialchars($this->spArgs('niname')),
			'domain'=>$this->spArgs('domain'),
			'blogtag'=>$tagstr,
			'sign'=>strreplaces($this->spArgs('sign')),
			'm_rep'=>$_mrep,
			'm_fow'=>$_mfow,
			'm_pm'=>$_mpm
		);
	
		if(spClass('db_member')->update(array('uid'=>$this->uid),$row)){
			$_SESSION['username'] = $row['username'];
			$_SESSION['domain'] = $row['domain'];
			$_SESSION['user']['sign'] = $row['sign'];
			$this->api_success(true);				
		}else{
		    $this->api_error('系统繁忙'); 
		}
	
	}
	
	function save_password(){
		if($this->spArgs('pwd') == '' || $this->spArgs('pwd1') == '' || $this->spArgs('pwd2') == ''){
			$this->api_error('所有字段不能为空');	
		}
		if($this->spArgs('pwd1') != $this->spArgs('pwd2')){
			$this->api_error('两次密码不一致');
		}
		if(strlen($this->spArgs('pwd1')) <5) {
			$this->api_error('新密码不能小于6位');
		}
		
		$user = spClass('db_member')->findBy('uid',$this->uid);
		$localpwd = password_encode($this->spArgs('pwd'),$user['salt']);
		if($user['password'] != $localpwd){
			$this->api_error('原始密码错误');	
		}else{
			$salt = randstr();
			$password = password_encode($this->spArgs('pwd1'),$salt);
			$row = array('password' => $password, 'salt' =>$salt );	
			spClass('db_member')->update(array('uid'=>$this->uid),$row);
			if(1 >= spClass('db_member')->affectedRows() ){
				spClass('ybCookie')->set_cookie('sid','delete',1);
				spClass('ybCookie')->set_cookie('auth','delete',1);
				$_SESSION = null;
				@session_unset();
				@session_destroy();
				$_COOKIE = NULL; //删除登陆状态
				$this->api_success(true);	
			}else{
				$this->api_error('密码没有修改成功,可能没有改变');
			}
		}
	}
	
	
	function mylikes(){
		$sql = "SELECT k.id, k.uid AS likeid,k.time as ktime, b . * , m.username, m.domain
				FROM `".DBPRE."likes` AS k
				LEFT JOIN `".DBPRE."blog` AS b ON k.bid = b.bid
				LEFT JOIN `".DBPRE."member` AS m ON b.uid = m.uid WHERE k.uid = '{$this->uid}'";
				
		$data['blog'] = spClass('db_likes')->spPager($this->spArgs('page',1),10)->findSql($sql);
		foreach($data['blog'] as &$d){
			$d['h_url'] =  goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['domain']));
			$d['h_img'] = avatar(array('uid'=>$d['uid'],'size'=>'middle'));
			$d['b_url'] = goUserBlog(array('bid'=>$d['bid'],'domain'=>$d['domain'],'uid'=>$d['uid']));
			$d['tag'] =  ($d['tag'] != '')? explode(',',$d['tag']) : '';
			$d['time']  = ybtime(array('time'=>$d['ktime'])); //切换成我喜欢的时间
			$rs         = split_attribute($d['body']); 
			$d['attr']  = $rs['attr'];
			$d['repto'] = $rs['repto'];
			if(!empty($d['repto'])){
				$d['repto']['h_url'] = goUserHome(array('uid'=>$d['repto']['uid'], 'domain'=>$d['repto']['domain']));
				$d['repto']['h_img'] = avatar(array('uid'=>$d['repto']['uid'],'size'=>'small'));
			}else{
				$d['repto'] = null;
			}
			$d['body'] = strip_tags($rs['body']);
		}
		$data['page'] = spClass('db_likes')->spPager()->getPager();
		$this->api_success($data);
	}
	
	function mypost(){
		if($this->spArgs('type') == 'draft'){
			$res = spClass('db_blog')->spLinker()->spPager($this->spArgs('page',1),10)->findAll("`uid` = {$this->uid} and `open` = 0 ",'bid desc');
		}else{
			$res = spClass('db_blog')->spLinker()->spPager($this->spArgs('page',1),10)->findAll("`uid` = {$this->uid} and `open` != 0 ",'bid desc');
		}
		
		foreach($res as &$d){
			$d['h_url'] =  goUserHome(array('uid'=>$d['user']['uid'], 'domain'=>$d['user']['domain']));
			$d['h_img'] = avatar(array('uid'=>$d['user']['uid'],'size'=>'middle'));
			$d['b_url'] = goUserBlog(array('bid'=>$d['bid']));
			$d['tag'] =  ($d['tag'] != '')? explode(',',$d['tag']) : '';
			$d['time']  = ybtime(array('time'=>$d['time']));
			$rs         = split_attribute($d['body']); 
			$d['attr']  = $rs['attr'];
			$d['repto'] = $rs['repto'];
			$d['body'] = strip_tags($rs['body']);
			if(!empty($d['repto'])){
				$d['repto']['h_url'] = goUserHome(array('uid'=>$d['repto']['uid'], 'domain'=>$d['repto']['domain']));
				$d['repto']['h_img'] = avatar(array('uid'=>$d['repto']['uid'],'size'=>'small'));
			}else{
				$d['repto'] = null;
			}
			$d['username'] = $d['user']['username'];
		}
		$data['blog'] = $res;
		$data['page'] = spClass('db_blog')->spPager()->getPager();	

		$this->api_success($data);
	}
	
	
	public function myfollow(){
		$data = array();
		if($this->spArgs('type') == 'follow'){
			$obj = spClass('db_follow');
			$obj->linker['0']['enabled'] = false;
			$data['data'] = $obj->spLinker()->spPager($this->spArgs('page',1),10)->findAll("`touid` = {$this->uid}  ",'time desc');	
		}else{
			$obj = spClass('db_follow');
			$obj->linker['1']['enabled'] = false;
			$data['data'] = $obj->spLinker()->spPager($this->spArgs('page',1),10)->findAll("`uid` = {$this->uid}  ",'time desc');
		}
		
		
		if($this->spArgs('type') == 'search'){
			$obj = spClass('db_follow');
			$obj->linker['0']['enabled'] = false;
			$data['data'] = $obj->spLinker()->spPager($this->spArgs('page',1),10)->findAll("`touid` = {$this->uid}  ",'time desc');	
		}
		
		$data['page'] = $obj->spPager()->getPager();
		
		foreach($data['data'] as &$d){
			if($this->spArgs('type') != 'follow'){
				$tudo = $d['tome'];
			}else{
				$tudo = $d['meto'];
			}
			unset($d['meto'],$d['tome']);
			$tudo['h_url'] = goUserHome(array('uid'=>$tudo['uid'], 'domain'=>$tudo['domain']));
			$tudo['h_img'] = avatar(array('uid'=>$tudo['uid'],'size'=>'middle'));
			$tudo['sign'] = strip_tags($tudo['sign']);
				$tudo['blogtag'] = ($tudo['blogtag'] != '') ?  explode(',',$tudo['blogtag']) : '';
				$d['touid'] =  $tudo;
				unset($tudo,$d['touid']['domain']);
			$d['time'] = ybtime(array('time'=>$d['time']));
			if($d['linker'] == 1){
				$d['linker'] = true;
			}else{
				$d['linker'] = false;
			}
		}
		$this->api_success($data);
	}

	function myreply(){
		$obj = spClass('db_replay');
		if($this->spArgs('type') == 1){ //我收到的
			$data['data'] = $obj->spLinker()->spPager($this->spArgs('page',1),10)->findAll("`repuid` = {$this->uid}");
			$data['page'] = $obj->spPager()->getPager();
		}else{	//我发出的 回复
			$data['data'] = $obj->spLinker()->spPager($this->spArgs('page',1),10)->findAll("`uid` = {$this->uid}",'id desc');
			$data['page'] = $obj->spPager()->getPager();
		}
	
		foreach($data['data'] as &$d){
			if($this->spArgs('type') == 1){ //我收到的
				$d['me'] = $d['user'];
				$d['to'] = $d['touid'];
				
			}else{
				$d['me'] = $d['user'];
				$d['to'] = $d['touid'];
			}
			unset($d['user'],$d['touid']);
			$d['me']['h_url'] = goUserHome(array('uid'=>$d['me']['uid'], 'domain'=>$d['me']['domain'])); 
			$d['me']['h_img'] = avatar(array('uid'=>$d['me']['uid'],'size'=>'middle'));
			$d['to']['h_url'] = goUserHome(array('uid'=>$d['to']['uid'], 'domain'=>$d['to']['domain'])); 
			$d['to']['h_img'] = avatar(array('uid'=>$d['to']['uid'],'size'=>'middle'));
			$d['blog']['b_url'] = goUserBlog(array('bid'=>$d['blog']['bid']));
			$d['time'] = ybtime(array('time'=>$d['time']));
			$d['msg']  = $this->parse_uid($d['msg']);
		}
		$this->api_success($data);
	}
	
	//我的通知0 用户私信（已经移到pm表了）  1 评论通知  2 系统通知 3关注通知
	public function mynotice(){
		$data = array();
		$rs = spClass('db_notice')->findByall($this->uid);
		if(!empty($rs)){
			foreach($rs as $d){
				$d['user']['h_url'] = goUserHome(array('uid'=>$d['user']['uid'], 'domain'=>$d['user']['domain'])); 
				$d['user']['h_img'] = avatar(array('uid'=>$d['user']['uid'],'size'=>'small'));
				$d['time'] = ybtime(array('time'=>$d['time']));
				$d['info'] = $this->parse_uid($d['info']);
				$href = explode('|',$d['location']);
				if($href[0] == 'blog'){
					$d['location'] = goUserBlog(array('bid'=>$href[1]));
				}
				if($href[0] == 'user'){
					$d['location'] = goUserHome(array('uid'=>$href[1]));
				}
				
				if($d['sys'] == 1){
					$data['reply_count']++;
					$data['reply'][] =  $d;
				}elseif($d['sys'] == 2){
					$data['sys_count']++;
					$data['sys'][] =  $d;
				}elseif($d['sys'] == 3){
					$data['follow_count']++;
					$data['follow'][] =  $d;
				}	
				$data['all_count'] ++;
			}
		}
		$this->api_success($data);
	}
	
	/*动态推送机制，检查通知和短信是否有新的*/
	public function chkNoticPm(){
		$data = array();
		if(islogin()){
			$data['notic'] = spClass('db_notice')->findCount(array('foruid'=>$this->uid,'isread'=>0));
			$data['pm']    = spCLass('db_pm')->findCount(array('touid'=>$this->uid,'isnew'=>1));
		}else{
			$data['notic'] = $data['pm'] = 0;
		}
		$this->api_success($data);
	}
	

	
	/*清除我看过的通知*/
	public function clearnotice(){
		if($this->spArgs('id') != 0){
			$row = array('foruid'=>$this->uid, 'id'=>$this->spArgs('id') );
		}
		if($this->spArgs('type') == 'reply'){
			$row = array('foruid'=>$this->uid, 'sys'=>1);
		}
		if($this->spArgs('type') == 'follow'){
			$row = array('foruid'=>$this->uid, 'sys'=>3);
		}
		if($this->spArgs('type') == 'sys'){
			$row = array('foruid'=>$this->uid, 'sys'=>2);
		}
		if($this->spArgs('type') == 'all'){
			$row = array('foruid'=>$this->uid);
		}
		$this->api_success( spClass('db_notice')->delete($row));
	}
    
    
    /*
     * 用户删除文章
     * params $id 
     */
    public function delblog(){
        $blog = spClass('db_blog')->findBy('bid',$this->spArgs('id'));
        if($blog['uid'] == $this->uid || $_SESSION['admin'] == 1)
        {
            spClass('db_blog')->delblog($blog['bid'],$this->uid);
            $this->api_success(true);
        }else{
            $this->api_error('删除失败,无权限或不存在该档案');
        }
    }
    
    /*del user attach*/
    public function delattach(){
    	$uid = $this->uid;
    	if($_SESSION['admin']==1){
    		//如果是管理员，变更附件uid
    		$uid = spClass('db_attach')->find(array('id'=>$this->spArgs('id')));
    		$uid=$uid['uid'];
    	}
		
        if(spClass('db_attach')->delBy($this->spArgs('id'),$uid)){
            $this->api_success(true);
        }else{
            $this->api_error('删除失败,无权限或不存在该档案');
        }
    }
	
	public function myFavaTag(){
		$data = spClass('db_mytag')->myFavaTag($this->spArgs(),$this->uid);
		$this->api_success($data);
	}
    
   
	
	/*关注用户*/
	function fllow(){
		if($this->spArgs('uid',0) == 0){
			$this->api_error('关注者为空');
		}
		if(!spClass('db_member')->find(array('uid'=>$this->spArgs('uid')))){
			$this->api_error('关注者不存在');
		}
		if($this->spArgs('type') == 'link'){
			$res = spClass('db_follow')->changeFollow('link',$this->spArgs('uid'),$this->uid);
		}else{
			$res = spClass('db_follow')->changeFollow('unlink',$this->spArgs('uid'),$this->uid);
		}
		if($res){
			$this->api_success(true);
		}else{
			$this->api_error('关注失败');
		}
	}
	
	/*添加标签收藏*/
	function addMytag(){

		if($this->spArgs('tid') == ''){
			$this->api_error('缺少参数');
		}

		if(spClass('db_mytag')->addTag($this->spArgs('tid'),$this->uid)){
			$this->api_success(true);
		}else{
			$this->api_error('无法添加或相关标签无效');
		}
	}
	
	/*删除标签收藏*/
	function delTag(){
		if(spClass('db_mytag')->delTag($this->spArgs('tid') ,$this->uid)){
			$this->api_success(true);
		}else{
			$this->api_error('无法添加');
		}
	}
	//删除绑定
	public function CancelConnect(){
		if($this->spArgs('type'))
		{
			$type = $this->spArgs('type');
			spClass('db_memberex')->CancelBind($type ,$_SESSION['uid']);
			unset($_SESSION['openconnect'][$type]);
			$this->api_success(true);
		}
	}
	
		/*单独保存域名,wizard 使用*/
	function wizard_save_domain(){
		if($this->yb['keep_domain'] != ''){
			$arr = explode(',',$this->yb['keep_domain']);
			if(in_array($this->spArgs('domain'),$arr)) $this->api_error('该个性域名被保留或限制');
		}
		if(utf8_strlen($this->spArgs('domain')) < 4 || utf8_strlen($this->spArgs('domain')) > 15)  $this->api_error('个性域名最短4位最长15位');
		if(!preg_match('/^[a-zA-Z]{1}([a-zA-Z0-9]|[._]){1,15}$/',$this->spArgs('domain'))) $this->api_error('个性域名不符合要求'); 
		$domain = spClass('db_member')->find(array('domain'=>$this->spArgs('domain')),'','uid,domain');
		if(is_array($domain) && $domain['uid'] != $this->uid) $this->api_error('个性域名已被使用');  
		$row['domain'] = $this->spArgs('domain');
		if(spClass('db_member')->update(array('uid'=>$this->uid),$row)){
			$_SESSION['domain'] = $this->spArgs('domain');
			$this->api_success(true);			
		}else{
		    $this->api_error('系统繁忙');  
		}
	}
	
	function wizard_save_tag(){
		if($this->spArgs('tag') != ''){
			$tagstr = substr($this->spArgs('tag'),0,-1);
			$tag = explode('|',$tagstr);
			if(count($tag) > 8){ $this->api_error('最多关注8个分类'); }
			$tagname_str = ''; //默认名称数据
			$tag_num = array();//默认tagid数据
			foreach($tag as $d){
				$t = explode(',',$d);
				$tagname_str .= $t[0].',';
				$tag_num[] = $t[1];
			}
			$tagstr = substr($tagname_str,0,-1);
			spClass('db_tags_blog')->createTags($this->uid,$tag_num);
			$row = array('blogtag'=>$tagstr);
			spClass('db_member')->update(array('uid'=>$this->uid),$row);
			$this->api_success(true);
		}
		$this->api_error('系统繁忙'); 
	}
	
	function wizard_save_follow(){
		if($this->spArgs('uid') != ''){
			$uid = explode('|',$this->spArgs('uid'));
			foreach($uid as $d){
				$uids = intval($d);
				if($uids != '') spClass('db_follow')->changeFollow('link',$uids,$this->uid);
			}
			$this->api_success(true);
		}
		$this->api_error('系统繁忙'); 
	}
	
	
	
	

    
	
	
	
}
