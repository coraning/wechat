<?php

/**
 * Created by PhpStorm.
 * User: think
 * Date: 2016/11/17
 * Time: 14:12
 */

require("ExePython.php");
class ParseFile extends ExePython
{
    private $contentService = null;
    private $exePython = null;
    private $objects = null;

    public function __construct($prefix) {
        $this->contentService = new ContentService();
        $this->bosClient = new Wm_Lib_BosClient('.configbprivatedagz');
        $this->getObjects($prefix);
    }

    /**
     * http://mp.weixin.qq.com/profile?src=3&timestamp=1479716741&ver=1&signature=*WP1sc7H0LExEAq5GYgtMhjgz6gEYhybg-Gmc07bYkNTDAvcDEVzmBIJRlRKCzDQ1XBkCgeYeSy1fy1UvLHGkQ==
     * 解析公众号搜狗，获取公众号地址
     * @return array
     */
    public function parseWechatNumber() {
        $source_urls = array();
        foreach ($this->objects as $item) {
            $url = $this->generateUrl($item->key);
            Bd_Log::warning("url ".$url);
            $number_url = $this->getWebchaNumberUrl($url);
            if (!empty($number_url)) {
                $source_urls[] = substr($number_url[0],strpos($number_url[0],'timestamp=')+strlen('timestamp='));
            }
            usleep(100);
        }
        return $source_urls;
    }

    /**
     * 解析列表，获取文章的地址，写入wechat_record
     */
    public function parseRecordList() {
        foreach ($this->objects as $item) {
            $url = $this->generateUrl($item->key);
            Bd_Log::warning("url ".$url);
            $recordList = $this->getRecordListUrl($url);
            $ret = $this->contentService->handleSpiderRecordList($recordList);
            if ($ret === false) {
                Bd_Log::warning(sprintf('class[%s] function[%s] handleSpiderRecordList fail! data[%s]', __CLASS__, __FUNCTION__, @json_encode($recordList)));
            }
            usleep(100);
        }
        $this->handleSpecialRecord();
        $lists = $this->contentService->getNeedSpiderRecordList();
        if (empty($lists)) {
            Bd_Log::warning(sprintf('class[%s] function[%s] getNeedSpiderRecordList empty!', __CLASS__, __FUNCTION__));
            return false;
        }
        $urls = array();
        foreach ($lists as $record) {
            $urls[] = $record['source_url'];
        }
        return $urls;
    }

    /**
     * 解析文章，写入content
     */
    public function parseRecordDetail() {
        foreach ($this->objects as $item) {
            $url = $this->generateUrl($item->key);
            Bd_Log::warning("url ".$url);
            $recordDetail = $this->getRecordDetail($url);
            if (!empty($recordDetail)) {
                $this->contentService->addContent($recordDetail);
            }
            usleep(100);
        }
        $this->contentService->updateFinishNumber();
        $this->contentService->updateStatusFailSendUrl();
    }

    /**
     * 处理特殊情况
     */
    private function handleSpecialRecord() {
        // 永久链接可以自己去爬取，目前da 不支持
        $source_url = $this->contentService->getSouceRecord();
        if (!empty($source_url)) {
            foreach ($source_url as $item) {
                $record_detail = $this->getRecordDetail($item['source_url']);
                if (!empty($recordDetail)) {
                    $this->contentService->addContent($record_detail);
                }
                usleep(100);
            }
        }
        //视频模板类型文章过滤
        $this->contentService->updateVideoRecord();

        /*
        $video_url = $this->objDsWebchatRecord->getVideoRecord();
        if (!empty($video_url)) {
            foreach ($video_url as $item) {
                $id = explode(' ', microtime());
                $a_id = $id[1]*1000+$id[0]*1000;
                $detail = '[{"a_id":"'.(int)$a_id.'","detail_type":"video","value":"'.$item['source_url'].'","title":"视频"}]';
                $record_detail = array(
                    "title" => $item['title'],
                    "webchat_name" => $item['webchat_name'],
                    "detail" => json_decode($detail)
                );
                $this->addContent(json_encode($record_detail));
            }
        }
        */
    }

    private function getObjects($prefix) {
        $options = array(
            "prefix"=>$prefix,
        );
        $file = $this->bosClient->listObjects($options);
        if (empty($file)) {
            Bd_Log::warning("da-bos empty ".$prefix);
        }
        $this->objects = $file->contents;
    }
    private function generateUrl($key) {
        return $this->bosClient->generateUrl($key);
    }
}