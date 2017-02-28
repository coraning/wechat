<?php

/**
 * Created by PhpStorm.
 * User: think
 * Date: 2016/12/14
 * Time: 11:24
 */
class ContentService
{
    private $objDsWebchatRecord = null;
    private $objDsWebchatNumber = null;
    private $objPsContent = null;
    private $objDaoSource = null;
    private $objDsCoop = null;
    private $objDsContent = null;

    const NO_THEME_ID = 0;
    const NO_THEME_NAME = '无栏目';
    const MORE_THEME_ID = 1;
    const MORE_THEME_NAME = '多栏目';
    const RECORD_SUCCESS = 3;
    const RECORD_FAIL = 2;
    const DA_STAUTS_SUCCESS = '2';
    const TRY_TIMES = 3;

    public function __construct() {
        $this->objDsWebchatRecord = new Service_Data_WebchatRecord();
        $this->objDsWebchatNumber = new Service_Data_WebchatNumber();
        $this->objPsContent = new Service_Page_Audit_AddContent();
        $this->objDaoSource  = new Dao_Db_Source();
        $this->objDsCoop  = new Service_Data_Cooperation();
        $this->objDsContent  = new Service_Data_Content();
    }

    public function getNeedParseRecordList() {
        return $this->objDsWebchatRecord->getNeedParseRecordList();
    }
    public function getSouceRecord() {
        return $this->objDsWebchatRecord->getSouceRecord();
    }
    public function updateVideoRecord() {
        return $this->objDsWebchatRecord->updateVideoRecord();
    }
    public function getNeedSpiderRecordList() {
        return $this->objDsWebchatRecord->getNeedSpiderRecordList();
    }
    public function updateStatusFailSendUrl() {
        return $this->objDsWebchatRecord->updateStatusFailSendUrl();
    }
    public function updateStatusFinishSendUrl() {
        return $this->objDsWebchatRecord->updateStatusFinishSendUrl();
    }
    public function deleteFailRecord() {
        $ret = $this->objDsWebchatRecord->deleteFailRecord();
        if (false === $ret) {
            Bd_Log::warning(
                sprintf(
                    "CLASS[%s] FUNCTION[%s] del webchat record fail",
                    __CLASS__,
                    __FUNCTION__
                )
            );
        }
    }
    /**
     * 获取需要爬取文章的公众号
     * @param $num
     * @return array
     */
    public function getNumbers($num) {
        $ret = $this->objDsWebchatNumber->getNeedSpiderNumber($num);
        if (!empty($ret)) {
            $numberArr = array();
            foreach ($ret as $item) {
                $numberArr[] = $item['webchat_num'];
            }
            return $numberArr;
        }
    }

    /**
     * 更新公众号的完成时间
     */
    public function  updateFinishNumber() {
        $number_all = $this->objDsWebchatNumber->getNumberFinishAll();
        if (!empty($number_all)) {
            foreach ($number_all as $item) {
                $this->objDsWebchatNumber->updateFinishTimeById($item['id'],strtotime(date("Ymd",time()-86400)));
            }
        }
        $number_list = $this->objDsWebchatNumber->getNumberNeedFinish();
        if (!empty($number_list)) {
            foreach ($number_list as $item) {
                $this->objDsWebchatNumber->updateFinishTimeById($item['id'],$item['finish_time']);
            }
        }
    }

    private function getCoopInfo($coopId){
        $coop = $this->objDsCoop->getCoopById($coopId);
        if(empty($coop)){
            Bd_Log::warning(sprintf('class[%s] function[%s] getCoopById empty $coopId[%s]', __CLASS__, __FUNCTION__, $coopId));
            return array(
                'coop_id' => 0,
                'coop_name' => '',
            );
        }
        return $coop[0];
    }

    private function getThemeByCS($coopId) {
        $themeRes = array(
            'theme' => self::NO_THEME_NAME,
            'theme_id' => self::NO_THEME_ID
        );
        $arrFields = array('theme_id', 'theme');
        $arrConds  = array(
            'coop_id='      => $coopId,
        );
        $theme = $this->objDaoSource->select($arrFields, $arrConds);
        if (empty($theme) || false == $theme) {
            return $themeRes;
        }else if(count($theme) > self::MORE_THEME_ID){
            $themeRes['theme'] = self::MORE_THEME_NAME;
            $themeRes['theme_id'] = self::MORE_THEME_ID;
            return $themeRes;
        }
        return $theme[0];
    }

    /**
     * 更新number表finish_time
     * @param $number
     * @param $finish_time
     */
    private function updateParseListSuccess($number, $finish_time) {
        $sum = $this->objDsWebchatRecord->getRecordSumOnce($number);
        $num = 0;
        while ($num === 0 and $finish_time < strtotime(date("Ymd",time()))) {
            $num = $this->objDsWebchatRecord->getRecordNumByDate($num, $finish_time);
            if ($num === false) {
                Bd_Log::warning("getRecordNumByDate fail" );
                break;
            }
            $finish_time += 86400;
        }
        $this->objDsWebchatNumber->updateFinishTime($number , $num, $finish_time, $sum);
    }

    private function updateParseListFail($number, $finish_time) {
        $this->objDsWebchatNumber->updateFinishTime($number , -1, $finish_time, -1);
    }

    /**
     * 新增record
     * @param $content_url
     * @param $post_time
     * @param $author
     * @param $title
     * @param $image
     * @param $abstract
     * @param $webchat_name
     * @param $webchat_num
     */
    private function handleRecord($content_url, $post_time, $author, $title, $image, $abstract, $webchat_name, $webchat_num) {
        $timetamp = strpos($content_url, "timestamp=");
        if ($timetamp === false) {
            $url = $content_url;
        } else {
            $url = substr($content_url, $timetamp + strlen("timestamp="));
        }
        $source_url = str_replace("amp;","",$url);
        $conditions = array(
            'post_time'      => $post_time,
            'author'         => $author,
            'title'          => str_replace('&nbsp;', '', $title),
            'webchat_name'  => $webchat_name,
            'webchat_num'   => $webchat_num
        );
        $ret = $this->objDsWebchatRecord->searchWebchatRecord($conditions);
        if (empty($ret)) {
            $data['source_url'] = $source_url;
            $data['post_time'] = $post_time;
            $data['author'] = $author;
            $data['image'] = $image;
            $data['abstract'] = $abstract;
            $data['title'] = str_replace('&nbsp;', '', $title);
            $data['webchat_name'] = $webchat_name;
            $data['webchat_num'] = $webchat_num;
            $data['create_time'] = time();
            $data['update_time'] = time();
            $this->objDsWebchatRecord->addRecord($data);
        } elseif ($ret[0]['record_status'] == self::RECORD_FAIL) {
            $data['source_url'] = $source_url;
            $data['update_time'] = time();
            $data['record_status'] = 0;
            $this->objDsWebchatRecord->updateWebchatRecord($data, $ret[0]['id']);
        }
    }

    /**
     * @param $id
     * @return bool
     */
    private function updateWebchatRecordSuccess($id){
        $resRecordUpdata = $this->objDsWebchatRecord->updateRecordStatusByID(self::RECORD_SUCCESS, $id);
        if(empty($resRecordUpdata)){
            Bd_Log::warning(sprintf('class[%s] function[%s] update rocord is failed record_id[%s]', __CLASS__, __FUNCTION__, $id));
            return false;
        }
        return true;
    }
    /**
     * @brief 保存数据到content,更新record表状态
     * @param $detail
     */
    public function addContent($param){

        $detail = json_decode($param, true);
        if(is_null($detail)){
            Bd_Log::warning(sprintf(
                'class[%s] function[%s] detail is empty--param is[%s]',
                __CLASS__,
                __FUNCTION__,
                $param
            ));
            return ;
        }
        $title = $detail['title'];
        $webchatName = $detail['webchat_name'];
        $detailInfo = $detail['detail'];

        $number = $this->objDsWebchatNumber->getWebChatByName($webchatName);

        if (empty($number)) {
            Bd_Log::warning(sprintf(
                'class[%s] function[%s] getnumberBy wechatName Fail name[%s]',
                __CLASS__, __FUNCTION__,
                $webchatName
            ));
            return ;
        }
        //判断文章是否已经添加
        $contentList = $this->objDsContent->getContentByTitleAndCoop($title, $number[0]['coop_id']);

        if(false ===  $contentList){
            Bd_Log::warning(sprintf(
                'class[%s] function[%s] get content list is failed title[%s] coopName[%s]',
                __CLASS__,
                __FUNCTION__,
                $title,
                $number[0]['coop_id']
            ));
            return ;
        }
        //todo 关联表获取文章其它信息
        $res = $this->objDsWebchatRecord->getRecodeByTitleAndChatname($webchatName, str_replace(' ', '', $title));

        if(false === $res || empty($res)){
            Bd_Log::warning(sprintf(
                'class[%s] function[%s] getRecodeByTitleAndChatname is failed Name[%s] Title[%s]',
                __CLASS__,
                __FUNCTION__,
                $webchatName,
                $title
            ));
            return ;
        }

        if(!empty($contentList)){
            //文章已经存在
            Bd_Log::warning(sprintf(
                'class[%s] function[%s] content is exists title[%s] coopName[%s]',
                __CLASS__,
                __FUNCTION__,
                $title,
                $webchatName
            ));
            //跟新record状态
            $this->updateWebchatRecordSuccess($res[0]['id']);
            return;
        }
        //首图转存
        $heardImage = $res[0]['image'];
        if(!empty($heardImage)){
            $heardImage = str_replace('\\', '', $heardImage);
            if(preg_match('/.*video\?.*/', $heardImage)){
                $heardImage = null;
            }else{
                $url = $this->uploadBos($heardImage);
                $heardImage = $url;
            }
        }

        if(!empty($detailInfo)){
            //todo 详情页图片转存
            foreach ($detailInfo as &$item) {
                if('image' == $item['detail_type']) {
                    if(preg_match('/.*\.gif/', $item['value'])){
                        $item['detail_type'] = 'ext';
                    }
                    $url = $this->uploadBos($item['value']);
                    $item['value'] = $url;
                }
            }
        }else{
            $detailInfo = array();
        }
        $detailInfo = json_encode($detailInfo);

        //获取合作方信息
        $coopInfo = $this->getCoopInfo($number[0]['coop_id']);
        //获取栏目信息
        $themInfo = $this->getThemeByCS($coopInfo['coop_id']);
        //添加content表中
        $arrInput = array(
            'content_id'  	   => Core_Util::genUUID(Core_Util::CHISHA_UUID),
            'title'            => $title,
            'sub_title'        => $res[0]['sub_title'],
            'image'            => $heardImage,
            'coop_id'          => $coopInfo['coop_id'],
            'coop_name'        => $coopInfo['coop_name'],
            'source'           => $coopInfo['coop_name'],
            'source_time'      => time(),
            'abstract'         => $res[0]['abstract'],
            'detail'           => $detailInfo,
            'audit_status'     => 3,
            'theme'            => $themInfo['theme'],
            'theme_id'         => $themInfo['theme_id'],
            'release_time'     => 0
        );
        //添加到表中
        $resContent = $this->objPsContent->execute($arrInput);
        if(empty($resContent)){
            Bd_Log::warning(sprintf('class[%s] function[%s] add content is failed arrayInput[%s]', __CLASS__, __FUNCTION__, @json_encode($arrInput)));
            return;
        }

        //更新chisha_webchat_record 状态
        $this->updateWebchatRecordSuccess($res[0]['id']);
    }

    /**
     * @param $url
     * @return string
     */
    private function uploadBos($url) {
        if (empty($url)) {
            return '';
        }
        $picName       = array_pop(explode('/', $url));
        $tmp_save_path = '/tmp/chisha-' . $picName;
        $time = 0;
        do {
            $ret = Wm_Lib_ProxyCurl::curl($url,null,Strategyui_Define_Strategy::$proxyConfig);
            file_put_contents($tmp_save_path, $ret);
            $bosInfo = Wm_Lib_BosOperation::uploadPic($tmp_save_path, 'c');
            $time++;
        }while($time < self::TRY_TIMES and $bosInfo === false);
        if (false === $bosInfo) {
            Bd_Log::warning(sprintf('class[%s] function[%s] 图片转存失败 image upload failed $url[%s]', __CLASS__, __FUNCTION__, $url));
            return '';
        }
        return $bosInfo['url'];
    }

    /**
     * 解析列表
     * @param $recordList
     * @return bool
     */
    public function handleSpiderRecordList($params) {
        $number = $params['webchat_number'];
        $name = $params['webchat_name'];
        $recordList = $params['record_list'];

        $detail = json_decode($recordList);
        if (empty($number) || empty($name) || is_null($detail)) {
            Bd_Log::warning(sprintf('class[%s] function[%s] param is illegal[%s]', __CLASS__, __FUNCTION__, @json_encode($params)));
            return false;
        }
        $numberDetail = $this->objDsWebchatNumber->findByNumber($number);
        if (empty($numberDetail)) {
            Bd_Log::warning(sprintf('class[%s] function[%s] not found number [%s]', __CLASS__, __FUNCTION__, @json_encode($number)));
            return false;
        }
        $finish_time = $numberDetail[0]['finish_time'];

        Bd_Log::trace("nuber is:".$number);
        Bd_Log::trace("finish_time is:".$finish_time);

        if (count($detail)) {
            foreach ($detail->list as $key=>$item) {
                if ($item->comm_msg_info->datetime < strtotime(date("Ymd",$finish_time-86400))) {
                    continue;  //已完成日期则不考虑,未爬取的会先进行插入
                }
                $this->handleRecord($item->app_msg_ext_info->content_url, $item->comm_msg_info->datetime, $item->app_msg_ext_info->author, $item->app_msg_ext_info->title, $item->app_msg_ext_info->cover, $item->app_msg_ext_info->digest, $name, $number);
                if (isset($item->app_msg_ext_info->multi_app_msg_item_list) && !empty($item->app_msg_ext_info->multi_app_msg_item_list)) {
                    foreach ($item->app_msg_ext_info->multi_app_msg_item_list as $item_item) {
                        $this->handleRecord($item_item->content_url, $item->comm_msg_info->datetime, $item_item->author, $item_item->title, $item_item->cover, $item_item->digest, $name, $number);
                    }
                }
            }
            $this->updateParseListSuccess($number, $finish_time);
            return true;
        } else {
            $this->updateParseListFail($number, $finish_time);
            return false;
        }
    }

}