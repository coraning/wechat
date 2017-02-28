<?php

/**
 * Created by PhpStorm.
 * User: think
 * Date: 2016/11/17
 * Time: 11:14
 */
require("RequestDA.php");
class HandleSpiderProcess
{
    const wechatNumber = "WechatNumber";
    const wechatList    = "RecordList";
    const wechatRecrod  = "RecordDetail";
    const SUCCESS = '2';

    private $numberPath = null;
    private $listPath = null;
    private $recordPath = null;

    public function __construct()
    {
        $this->checkStatus();
    }

    /**
     * @param $param_array
     */
    public function sendParamsOfNumber($param_array){
        return $this->sendParams(self::wechatNumber, $param_array);
    }

    /**
     * @param $param_array
     */
    public function sendParamsOfList($param_array){
        return $this->sendParams(self::wechatList, $param_array);
    }

    /**
     * @param $param_array
     */
    public function sendParamsOfRecord($param_array){
        $ret = $this->sendParams(self::wechatRecrod, $param_array);
        if ($ret) {
            $model = new ContentService();
            $model->updateStatusFinishSendUrl();
        }
    }
    /**
     * @return null
     */
    public function fetchParseNumberPath(){
        return $this->numberPath;
    }

    /**
     * 可以执行解析列表
     * 1：列表爬取的状态是完成
     * 2：文章爬取状态是完成
     * 3：文章中不存在待解析文章
     * 执行后执行文章爬取
     */
    public function fetchParseListPath() {
        return $this->listPath;
    }

    /**
     * 可以执行解析文章
     * 1：文章爬取的状态是完成
     * 2：文章存在待解析文章
     * 解析后执行列表爬取
     */
    public function fetchParseRecordPath() {
        return $this->recordPath;
    }

    private function checkStatus() {
        //查询状态
        $requestList = new RequestDA(self::wechatList);
        $retList = $requestList->checkStatus();
        $statusList = ($retList['status'] === self::SUCCESS || $retList['status'] == NULL);
        $requestRecord = new RequestDA(self::wechatRecrod);
        $retRecord = $requestRecord->checkStatus();
        $statusRecord = ($retRecord['status'] === self::SUCCESS || $retRecord['status'] == NULL);
        $requestNumber = new RequestDA(self::wechatNumber);
        $retNumber = $requestNumber->checkStatus();
        $statusNumber = ($retNumber['status'] === self::SUCCESS || $retNumber['status'] == NULL);

        if ($statusList && $statusRecord && $statusNumber) {
                $this->numberPath = $retNumber['prefix'];
                $this->listPath = $retList['prefix'];
                $this->recordPath = $retRecord['prefix'];
        }
    }

    /**
     * @param $req
     * @param $param_array
     * @return bool
     */
    private function  sendParams($req, $param_array){
        $request = new RequestDA($req);
        return $request->requestSpider($param_array);
    }
}