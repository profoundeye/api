<?php
/////////////////////////////////////////////////////////////////
//云边开源轻博, Copyright (C)   2010 - 2011  qing.thinksaas.cn 
//EMAIL:nxfte@qq.com QQ:234027573                              
//$Id: blog.php 1334 2012-08-06 14:25:25Z anythink $ 

class blog extends top
{ 

	function __construct(){  
        parent::__construct(); 
    }

	
	//获取feeds
	function feeds(){
		if($this->spArgs('uid')){
			$uid = (int) $this->spArgs('uid');
			$cond = "and b.uid = '$uid'";
		}
		if($this->spArgs('pagelimit')){
			$pageLimit = ($this->spArgs('pagelimit') < 30) ? $this->spArgs('pagelimit') : 30 ;  //自定义分页
		}else{
			$pageLimit = $this->yb['show_page_num'];
		}


		//LEFT JOIN `".DBPRE."follow` AS f ON ( b.uid = f.touid and f.uid = '$uid' )
		$sql = "SELECT b. * , k.id AS likeid  ,m.username,m.domain
				FROM `".DBPRE."blog` AS b LEFT JOIN `".DBPRE."likes` AS k ON ( b.bid = k.bid AND k.uid ='$this->uid' )
				LEFT JOIN `".DBPRE."member`  as m on b.uid = m.uid where b.open = 1 $cond ORDER BY b.time desc";
			
		$data['blog'] = spClass('db_blog')->spPager($this->spArgs('page',1),$pageLimit)->findSql($sql);
		$data['page'] = spClass('db_blog')->spPager()->getPager();
		unset($data['page']['all_pages']);
		if(!empty($data['blog'])){
			foreach($data['blog'] as &$d){
				$this->foramt_feeds($d);
			}
			$this->api_success($data);
		}else{
			$this->api_success("");
		}
	}
	
	
	//获取单个博客
	function getOneBlog(){
		$bid = (int) $this->spArgs('bid');
		$sql = "SELECT b. * , k.id AS likeid  ,m.username,m.domain
				FROM `".DBPRE."blog` AS b LEFT JOIN `".DBPRE."likes` AS k ON ( b.bid = k.bid AND k.uid ='$this->uid' )
				LEFT JOIN `".DBPRE."member`  as m on b.uid = m.uid where b.open in (1,-2) and b.bid = '$bid'";
		$data['blog'] = spClass('db_blog')->findSql($sql);
		foreach($data['blog'] as &$d){
			$this->foramt_feeds($d,0);
		}
		$this->api_success($data);
	}
	
	
	
	//获取我关注的用户feeds
	function followfeeds(){
		$followuid =  spClass('db_follow')->getFollowUid($this->uid);
		if($followuid){
			$sql = "SELECT b. * , k.id AS likeid  ,m.username,m.domain
					FROM `".DBPRE."blog` AS b LEFT JOIN `".DBPRE."likes` AS k ON ( b.bid = k.bid AND k.uid ='$this->uid' )
					LEFT JOIN `".DBPRE."member`  as m on b.uid = m.uid where b.open = 1";
			$sql .= " and b.uid in ($followuid) and b.open=1 ORDER BY b.time desc";
			$data['blog'] = spClass('db_blog')->spPager($this->spArgs('page',1),10)->findSql($sql);
			$data['page'] = spClass('db_blog')->spPager()->getPager();
			
			foreach($data['blog'] as &$d){
				$this->foramt_feeds($d);
			}
		}
		$this->api_success($data);
	}
	
	
	/*获取随机推荐图片列表，首页用的*/
	function recommendImg(){
		$type = 3; //获取图像
		$cachename = 'recommend_shuffle_'.$type;
        if(!spAccess('r',$cachename)){
            $recommend = spClass('db_blog')->recommend_shuffle($type);
			foreach($recommend as $d){
				$body  = split_attribute($d['body']);
				if(is_array($body['attr']['img'])){
					foreach($body['attr']['img'] as $img){
						$imgs[] = array('bid'=>$d['bid'],
										'uid'=>$d['uid'],
										'img'=>$img['url'],
										'username'=>$d['user']['username'],
										'h_url'=>goUserHome(array('uid'=>$d['user']['uid'])),
										'h_img'=>avatar(array('uid'=>$d['user']['uid'],'size'=>'small')),
										'b_url'=>goUserBlog(array('bid'=>$d['bid'],'domain'=>$d['user']['domain'],'uid'=>$d['user']['uid']))
						);
					}
				}
			}
            spAccess('w',$cachename,$imgs,86400);
        }else{
            $imgs =  spAccess('r',$cachename);
        }
        $count = count($imgs); 
        $numbers = range (0,$count-1);
        shuffle($numbers);
        $queue = array_slice($numbers,0,1); 
        
        $result = array();
        foreach($queue as $d){
            $result = $imgs[$d];
        }
		$this->api_success($result);
	}
	
	
	//发现频道 随机发现最新的100个博客内容，取前15个
	function discoverBlog(){
		$num = ($this->spArgs('num')) ? $this->spArgs('num') : 15;
		$page = ($this->spArgs('page',0)) ? $num * ($this->spArgs('page')-1) : 0;
		$isshuffle = ($this->spArgs('isshuffle',0)) ? 1 : 0;
		$type = 'all';
		$cachename = 'recommend_shuffle_'.$type;
		if(!spAccess('r',$cachename)){
			$recommend = spClass('db_blog')->recommend_shuffle($type,150);//条数 15*10
			

			$data = array();
			foreach($recommend as $d){
				$body  = split_attribute($d['body']);
				if($d['type'] == 1){
					$d['attr'] = ' ';
				}

				if($d['type'] == 3){
					$d['attr'] = $body['attr']['img'][0]['url'];
				}
				if($d['type'] == 2 || $d['type'] == 4){
				
				
					if(!empty($body['attr'])){
						if(is_array($body['attr'])){
						
							if( count($body['attr']) <= 1){
								if($body['attr']['type'] == 'yinyuetai'){ //对音乐台特殊处理
									$d['attr'] = 'index.php?c=blog&a=getyytimg&src=' . $body['attr']['img'];
								}else{
									$d['attr'] = $body['attr']['img'];  //将图片返回给前台
								}
		
							}else{
								
								if($body['attr'][0]['type'] == 'yinyuetai'){ //对音乐台特殊处理
									$d['attr'] = 'index.php?c=blog&a=getyytimg&src=' . $body['attr'][0]['img'];
								}else{
									$d['attr'] = $body['attr'][0]['img'];  //将图片返回给前台
								}
								
							}
						}
					}else{
						$d['attr'] =$body['attr'];
					}
				}
	
				if($d['attr']){
					$data[] = array('bid'=>$d['bid'],
									'title'=>$d['title'],
									'body'=>utf8_substr(strip_tags($body['body']),0,120),
									'type'=>$d['type'],
									'uid'=>$d['uid'],
									'username'=>$d['user']['username'],
									'b_url'=>goUserBlog(array('bid'=>$d['bid'],'domain'=>$d['user']['domain'],'uid'=>$d['user']['uid'])),
									'tag'=>($d['tag'] != '') ? array_shift((explode(',',$d['tag']))) : '',
									'img'=>$d['attr']
					);
				}
			}
			unset($recommend);

			spAccess('w',$cachename,$data,86400);
		}else{
			 $data =  spAccess('r',$cachename);
		}
		

		$count = count($data); 
		
        $numbers = range (0,$count-1);
		if($isshuffle){
			shuffle($numbers);
		}

        $queue = array_slice($numbers,$page,$num); 
        
        $result = array();
        foreach($queue as $d){
            $result[] = $data[$d];
        }
		unset($data);
		$this->api_success($result);
	}
	
	/*发现频道 随机发现20个tag model 已缓存*/
	function discovertag(){
		$tags = spClass('db_tags')->discoverTag();
		$maxhit = 0;
		foreach($tags as &$d){
			if($d['hit'] > $maxhit){
				$maxhit = $d['hit'];
			}
			if(is_array($d['ulist'])){
				foreach($d['ulist'] as &$list){
					$list['h_url'] = goUserHome(array('uid'=>$list['uid'], 'domain'=>$list['domain']));
					$list['h_img'] = avatar(array('uid'=>$list['uid'],'size'=>'small'));
				}
			}
		}
		$data = array();
		$data['maxhit'] = $maxhit;
		$data['data'] = $tags;
		$this->api_success($data);
	}
	
	/*推荐频道 推荐用户*/
	function recommendUser(){
		
		//如果是自动推荐模式
		if($this->yb['recomm_switch'] != 1){
		
			$data = spClass('db_tags_blog')->findUserBytid($this->spArgs(),$this->uid);
			foreach($data['data'] as & $d){
				$d['h_url'] = goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['domain']));
				$d['h_img'] = avatar(array('uid'=>$d['uid'],'size'=>'big'));
				$d['logtime'] = ybtime(array('time'=>$d['logtime']));
				$d['sign']  = strip_tags($d['sign']);
				$d['isfollow'] = ($d['isfollow'] == $this->uid) ? true: false;
				$d['blogtag'] = ($d['blogtag'] != '' ) ? explode(',',$d['blogtag']) : '';
			}
			
		}else{
		
		}
		$this->api_success($data);
	}
	
	/*首页获取评论*/
	function reply(){
		$bid = $this->spArgs('bid');
		$result = spClass('db_replay')->spLinker()->spPager($this->spArgs('page',1),$this->spArgs('limit',20))->findAll(array('bid'=>$bid),'time desc','');
		$pager = '';
		$data  = array();
		$data['page'] = spClass('db_replay')->spPager()->getPager();
		
		foreach($result as &$d){
			$d['msg'] =  strip_tags(strreplaces($this->parse_uid($d['msg'])));
			$d['h_url']    = goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['domain']));
			$d['h_img']    = avatar(array('uid'=>$d['uid'],'size'=>'small'));
			$d['time']     = ybtime(array('time'=>$d['time']));
			$d['del_flag'] = islogin() ? 1:0;
			$d['rep_flag'] = ( $this->uid != $d['uid'] && $this->uid != '') ? 1:0;
		}
		$data['body'] = $result;
		$this->api_success($data);
	}
	
	//后端改完了，就差添加收藏和取消收藏所记录的tid了，tid采用system tag 表的id来统一
	function tag(){
		$data = spClass('db_tag_system')->findTagByAttr($this->spArgs(),$this->uid);
	
		if(is_array($data['blog'])){
			foreach($data['blog'] as &$d){
				$d['h_url'] =  goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['domain']));
				$d['h_img'] = avatar(array('uid'=>$d['uid'],'size'=>'middle'));
				$d['b_url'] = goUserBlog(array('bid'=>$d['bid'],'domain'=>$d['domain'],'uid'=>$d['uid']));
				$d['tag'] =  ($d['tag'] != '') ? explode(',',$d['tag']) : '';
				
				$d['time']  = ybtime(array('time'=>$d['time']));
				$rs         = split_attribute(converPic($d['body'])); 
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
			$this->api_success($data);
		}else{
			$this->api_success(false);
		}
		
	}
	
	/*获取tag页用来显示右侧 该标签的活跃用户*/
	function tagHotuser(){
		$data = spClass('db_tags')->findTagHotUser($this->spArgs('tag'));
		foreach($data as &$d){
			$d['h_url'] = goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['domain']));
			$d['h_img'] = avatar(array('uid'=>$d['uid'],'size'=>'small'));
		}
		$this->api_success($data);
	}
	
	/*进行回复评论*/
	function setReply()
	{
		if($this->uid ==0){
			$this->api_error('需要登陆才能继续操作');
		}	
		$err = spClass('db_replay')->createReplay($this->spArgs());
		if($err['err'] == ''){
			$this->api_success(true);
		}else{
			$this->api_error($err['err']);
		}
	}
	
	/*删除回复*/
	function delReply(){
		if($this->uid ==0){
			$this->api_error('需要登陆才能继续操作');
		}
		$id = $this->spArgs('id');
		spClass('db_replay')->delReplay($this->spArgs(),$this->uid);
		$this->api_success(true);
	}
	
	
	/*设置喜欢与取消喜欢*/
	function setLike()
	{
		if($this->uid ==0){
			$this->api_error('需要登陆才能继续操作');
		}	
		$rs = spClass('db_blog')->find(array('bid'=>$this->spArgs('bid')));
		if(!is_array($rs)){
			$this->api_error('内容不存在');
		}
		$result = spClass('db_likes')->changeLikes($this->spArgs(),$this->uid);
		if($result != 'remove' && $result != 'add'){
			$this->api_error($result);
		}
		$this->api_success($result);
	}
	
	/*转载
     * params $id
     */
	public function repblog(){
		if($this->uid ==0){
			$this->api_error('需要登陆才能继续操作');
		}
		$rs = spClass('db_blog')->blogrep($this->spArgs('bid'));
		if($rs == 1)
		{
			$this->api_success('内容已经成功转载',goUserHome(array('uid'=>$this->uid)));
		}elseif($rs == -2){
			$this->api_error('不能转载自己的内容');
		}else{
			$this->api_error('转载的内容不存在');
		}
	}
	
    /**
     * 投票
     * @param $bid
     * @param 选择项
     */
    public function vote(){
        if($this->uid == 0){
            $this->api_error('需要登陆才能继续操作');
        }
        $db_voted = spClass('db_model_voted');
        $db_blog = spClass('db_blog');
		
		if($db_voted->find(array('uid' => $this->uid , 'bid' => $this->spArgs('bid') ))){
			$this->api_error('您已经投过票了');
			return;
		}
		
        $row = array(
          'uid' => $this->uid,
          'bid' => $this->spArgs('bid'),
          'selected' => $this->spArgs('inputs'),
        );
        $blog = spClass('db_blog')->find(array('bid' => $this->spArgs('bid')));
        $selected = explode(',',$this->spArgs('inputs'));
        $body = split_attribute($blog['body']);
        foreach($selected as $k => $v){
            $body['attr']['v_options'][$v]['v_count'] += 1;
        }
        $body['attr']['v_total_count'] += count($selected);
        $blog['body'] = '[attribute]'.serialize($body['attr']).'[/attribute]';
        if(true == $db_voted->create($row) && true==$db_blog->update(array('bid'=>$this->spArgs('bid')),$blog)){
            $this->api_success('投票成功');
        }else{
            $this->api_error('投票失败');
        }
    }
    
    
	/*首页获取博文动态的列表feed*/
	function getHit()
	{
		$bid = $this->spArgs('bid');
		$result = spClass('db_feeds')->spLinker()->spPager($this->spArgs('page',1),$this->spArgs('limit',10))->findAll(array('bid'=>$this->spArgs('bid')),'id desc');
		
		$pager = '';
		$data  = array();
		$data['page'] = spClass('db_feeds')->spPager()->getPager();
		
		foreach($result as &$d){
			
			$d['info']     = $this->parse_uid($d['info']);
			$d['h_url']    = goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['domain']));
			$d['h_img']    = avatar(array('uid'=>$d['uid'],'size'=>'small'));
			$d['time']     = ybtime(array('time'=>$d['time']));
			$d['del_flag'] = islogin() ? 1:0;
			$d['rep_flag'] = ( $this->uid != $d['uid'] && $this->uid != '') ? 1:0;
		}
		$data['body'] = $result;
		$this->api_success($data);
	}
	
	/*登录时候显示用户的状态*/
	function loginUserHot(){
		if(!spAccess('r','loginUserHot')){  //读取缓存
			$data = spClass('db_replay')->loginUserHot(12);
			foreach($data as &$d){
				if(is_array($d['user'])){
					$d['username'] = $d['user']['username'];
					$d['blogtag']  = ($d['user']['blogtag'] != '' ) ? explode(',',$d['user']['blogtag']) : '';
					$d['u_url']    = goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['user']['domain']));
					$d['u_img']    = avatar(array('uid'=>$d['uid'],'size'=>'middle'));
					$d['msg']      = strip_tags($this->parse_uid($d['msg']));
					unset($d['user']);
				}
				$d['b_url']   = goUserBlog(array('bid'=>$d['blog']['bid'],'domain'=>$d['user']['domain'],'uid'=>$d['uid']));
				$d['b_title'] = $d['blog']['title'];
				unset($d['blog']);
			}
			spAccess('w','loginUserHot',$data,3600);
		}else{
			$data = spAccess('r','loginUserHot');
		}
		$this->api_success($data);
	} 
	
	
	//处理feeds给前端显示
	//$split 是否截断内容
	private function foramt_feeds(& $d,$split=1){
		$d['more'] = 0;
		$d['h_url'] =  goUserHome(array('uid'=>$d['uid'], 'domain'=>$d['domain']));
		$d['h_img'] = avatar(array('uid'=>$d['uid'],'size'=>'middle'));
		$d['b_url'] = goUserBlog(array('bid'=>$d['bid'],'domain'=>$d['domain'],'uid'=>$d['uid']));
		$d['tag'] =  ($d['tag'] != '') ? explode(',',$d['tag']) : '';
		
		
		$d['time_y']  = date('Y.m',$d['time']);
		$d['time_d']  = date('d',$d['time']);
		$d['time']  = ybtime(array('time'=>$d['time']));
		$rs         = split_attribute(converPic($d['body'])); 
		$d['attr']  = $rs['attr'];
		$d['repto'] = $rs['repto'];
		if(!empty($d['repto'])){
			$d['repto']['h_url'] = goUserHome(array('uid'=>$d['repto']['uid'], 'domain'=>$d['repto']['domain']));
			$d['repto']['h_img'] = avatar(array('uid'=>$d['repto']['uid'],'size'=>'small'));
		}else{
			$d['repto'] = null;
		}
		if($split == 1){
			$d['body'] = utf8_substr(strip_tags($rs['body'],'<br><p><embed>'),0,500);
		}else{
			$d['body'] = strip_tags($rs['body'],'<br><p><embed>');
		}
		if($d['body'] == false){
			$d['body'] = '';
		}
		$d['more'] = (utf8_strlen($rs['body']) > 500) ? 1: 0;
		//处理音乐和视频
		if($d['type'] == 2 || $d['type'] == 4){
			if(count($d['attr']) > 4){
				$d['mode'] = 1;
				if($split == 1){
					$d['attr'] = array_slice($d['attr'],0,4);
				}
			}
		}
		//处理图片,超过10个就任务more
		if($d['type'] == 3){
			if($split == 1){
				if($d['attr']['count'] > 10){
					$d['attr']['img'] = array_slice($d['attr']['img'],0,10);
					$d['mode'] = 1;
				}
			}
		}
		//如果显示全部则把more改成0
		if($split != 1){
			$d['show_reply'] = 1; //展开评论
		}
	}
	
	
}
