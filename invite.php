<?php
/////////////////////////////////////////////////////////////////
//云边开源轻博, Copyright (C)   2010 - 2011  qing.thinksaas.cn 
//EMAIL:zhangshaomin_1990@126.com QQ:1470506882
//$Id: invite.php 962 2012-06-22 07:30:13Z anythink $                            


class invite extends top
{

    function __construct()
    {
        parent::__construct();
        $this->needLogin();
    }

    public function getInviteList()
    {
        $db_invite = spClass('db_invite');
        $this->api_success($db_invite->initInvite($this->uid));
    }

    public function addToFull()
    {
        if(spClass('db_invite')->addToFull($this->uid) ){
			 $this->api_success(true);
		}else{
			$this->api_error('请稍候再试');
		}
    }

    public function getInvitedFriendList()
    {
        $row = array('uid' => $this->uid);
        $friendBaseInfo = spClass('db_invite_friend')->spLinker()->spPager($this->spArgs('page', 1), 30)->findAll($row); //邀请的好友 编号
		foreach($friendBaseInfo as &$d){
			$d += $d['user'];
			$d['h_url'] = goUserHome(array('uid'=>$d['touid'], 'domain'=>$d['domain']));
			$d['h_img'] = avatar(array('uid'=>$d['touid'],'size'=>'middle'));
			unset($d['user']);
		}
        $this->api_success($friendBaseInfo);
    }

}