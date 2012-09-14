<?php

/////////////////////////////////////////////////////////////////
//云边开源轻博, Copyright (C)   2010 - 2011  qing.thinksaas.cn 
//EMAIL:zhangshaomin_1990@126.com QQ:1470506882     
//$id$      

class getad extends top
{

    function __construct(){
        parent::__construct();
    }

    //最终显示广告
    public function getAdUnit(){
        $ads = $this->getUnit2Ad();
        $enable_ad = $this->getEnable2Ad($ads);
        if(!$enable_ad){
            $this->api_error('no ad(s) passed limit');
        }
        $ad = $this->getWeight2Ad($enable_ad);
        if($ad){
            $this->api_success($ad);
        }else{
			$this->api_error("return try"); //无值
        }
    }

    //根据广告位返回广告列表
    private function getUnit2Ad(){
        $unit = intval($this->spArgs('unit'));
        if(!$unit){
            $this->api_error('param error');
        }
           return spClass('db_ad_list')->getAccessAds($unit);
    }

    //根据广告位列表判断可用广告数量
    private function getEnable2Ad($adInfo){
        $ret = array();

        foreach($adInfo as $key => $val){
		

			if($val['time_date_limit'] != ''){
				$arr_date = explode('|', $val['time_date_limit']); //日期
				if(strtotime($arr_date[0]) > time()  || strtotime($arr_date[1]. '23:59:59') < time()){
					continue;
				}
			}
			

			//判断时段是否越界
			if($val['time_area_limit'] != ''){
				$arr_time = json_decode($val['time_area_limit'], true); //时间
				$passed =0 ;
				foreach($arr_time as $d){
					$hm = explode('-', $d);
					if(strtotime($hm[0]) < time() && strtotime($hm[1]) < time()){
					}else{
						$passed = 1;
					}
				}
				if(!$passed){
					continue;
				}
			}
	
             $ret[$val['adid']] = $val;
        }
        return $ret;
    }

    //根据广告权重返回一条广告
    private function getWeight2Ad($data){
        $_tmp = array();
		$weight = 0;

        foreach ($data as $k) {
            $_tmp[$k['adid']] = array($weight + 1, $weight + $k['weight']);
            $weight += $k['weight'];
        }
        $r = rand(1, $weight);
        $adid = 0;
        foreach ($_tmp as $k => $v){
            if ($r >= $v['0'] && $r <= $v['1']) {
                $adid = $k;
                break;
            }
        }
	
        if($adid){
            return array(
                'adid'  => $data[$adid]['adid'],
                'title' => $data[$adid]['title'],
                'type'  => $data[$adid]['type'],
                'body'  => $data[$adid]['body'],
				'url'   => $data[$adid]['url'],
                'ga'    => $data[$adid]['ga']
            );
        }
        return false;
    }
}

?>
