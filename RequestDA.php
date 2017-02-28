<?php

/**
 * Created by PhpStorm.
 * User: think
 * Date: 2016/11/16
 * Time: 17:15
 */
class RequestDA
{
    private  $recordList =  array(
        'jobId' => 146,
        'tt_jobid' => 3187,
        'conf' => array(
            "conf_type" => "commonorder",
            "id" 		=> "22",
            "name"		=> "微信文章列表抓取",
            "source"	=> "other",
            "comp_id"	=> "40",
            "comp_name"	=> "微信公众号文章列表爬取",
            "param_data_path" => "",
            "param_data"	=> ''
        ),
    );
    private  $wechatNumber =  array(
                'jobId' => 165,
                'tt_jobid' => 4693,
                'conf' => array(
                   "conf_type" => "commonorder",
                   "id" 		=> "21",
                   "name"		=> "搜狗公众号爬取",
                   "source"	=> "other",
                   "comp_id"	=> "39",
                   "comp_name"	=> "搜狗微信公众号抓取",
                   "param_data_path" => "",
                    "param_data"	=> ''
                    ),
                );
    private $recordDetail = array(
        'jobId' => 140,
        'tt_jobid' => 1119,
        'conf' => array(
            "conf_type" => "commonorder",
            "id" 		=> "19",
            "name"		=> "微信公众号抓取3",
            "source"	=> "other",
            "comp_id"	=> "32",
            "comp_name"	=> "微信公众号",
            "param_data_path" => "",
            "param_data"	=> ''
        ),
    );

    private $recordDetailTest = array(
        'jobId' => 144,
        'tt_jobid' => 3178,
        'conf' => array(
            "conf_type" => "commonorder",
            "id" 		=> "20",
            "name"		=> "微信公众号抓取-test",
            "source"	=> "other",
            "comp_id"	=> "32",
            "comp_name"	=> "微信公众号",
            "param_data_path" => "",
            "param_data"	=> ''
        ),
    );

    private  $jobId = 0;
    private  $tt_jobid = 0;
    private  $conf = array();
    private  $username = null;

    public function __construct($param)
    {
        $this->getUserName();
        $this->chooseConf($param);
    }
    private function getUserName() {
        $this->username = Bd_Conf::getConf('wechat/username');
    }
    /**
     * @param $param
     */
    private function chooseConf($param) {
        switch ($param) {
            case 'WechatNumber':
                $this->__setSpiderParams($this->wechatNumber);
                break;
            case 'RecordList':
                $this->__setSpiderParams($this->recordList);
                break;
            case 'RecordDetail':
                $this->__setSpiderParams($this->recordDetailTest);  //TODO先用测试环境，上线要改回
                break;
        }
    }

    /**
     * @param $ret
     */
    protected  function __setSpiderParams($ret) {
        $this->jobId = $ret['jobId'];
        $this->tt_jobid = $ret['tt_jobid'];
        $this->conf = $ret['conf'];
    }
     /**
     * 发送url，并开启trigger
     * @param $param
     * @return bool
     */
    public function requestSpider($param) {
        if($this->requestSendUrl($param)) {
            if($this->triggerSpider()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 返回为ture则可以执行爬取，返回false退出流程
     */
    public function checkStatus() {
        $url = "http://compete.do.baidu.com/compete/api/getjobstatus?username=".$this->username;
        $post_data = array(
            "jobId" 	=> $this->jobId,
            "date"      => date("Ymd", time())
        );
        $ret = $this->curlPost($url, $post_data);
        if($ret == false){
            exit(9);
        }
        return $ret;
    }

    /**
     * 发送url
     * @param $param 一维数组
     * @return mixed
     */
    private function requestSendUrl($param) {
        if (empty($param) || !is_array($param)) {
            return false;
        }
        $url ='http://compete.do.baidu.com/compete/api/updateconf?username='.$this->username;
        $param_data = "param";
        foreach ($param as $item) {
            if (empty($item)) {
                continue;
            }
            $param_data .="\n".$item;
        }
        $this->conf['param_data'] = $param_data;
        Bd_Log::trace("param_data is:".@json_encode($this->conf));
        $ret = $this->curlPost($url, $this->conf);
        Bd_Log::trace("ret is:".@json_encode($ret));
        if($ret['error'] !== 0){
            Bd_Log::warning("send url to da fail！ " );
            return false;
        }
        return true;
    }

    /**
     * 触发爬取
     * @return mixed
     */
    private function triggerSpider() {
        $url = 'http://compete.do.baidu.com/compete/api/runjob?username='.$this->username;
        $post_data = array(
            "tt_jobid" 	=> $this->tt_jobid,
        );
        Bd_Log::trace("param_data is:".@json_encode($post_data));
        $ret = $this->curlPost($url, $post_data);

        Bd_Log::trace("ret is:".@json_encode($ret));
        if(is_null($ret)||$ret['error'] !== 0){
            Bd_Log::warning("trigger da spider fail！ " );
            return false;
        }
        return true;
    }
    /**
     * @param $url
     * @param $post_data
     * @return mixed
     */
    private function curlPost($url,$post_data) {
        $data = Wm_Lib_ProxyCurl::curl($url,$post_data,Strategyui_Define_Strategy::$proxyConfig);
        Bd_Log::warning("url: ".@json_encode($url) );
        Bd_Log::warning("data: ".@json_encode($post_data) );
        $temp = explode("\n", $data);
	$result = json_decode($temp[count($temp)-1], true);
	Bd_Log::warning("result: ".@json_encode($data) );
        if(is_null($result) || $result == false){
            echo "RequestDA fail.\n";
            return false;
        }
        return $result;
    }
}
