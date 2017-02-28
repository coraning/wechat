<?php

/**
 * Created by PhpStorm.
 * User: think
 * Date: 2016/11/17
 * Time: 20:22
 */
class ExePython
{
    const STEP_NUMBER = '1';
    const STEP_LIST = '2';
    const STEP_RECORD = '3';
    /**
     * 通过url,解析公众号页
     * @param $param
     */
    public function getWebchaNumberUrl($url) {
        return $this->exeShell(self::STEP_NUMBER, $url);
    }
    /**
     * 通过url,解析文章页
     */
    public function getRecordListUrl($url) {
        $result = $this->exeShell(self::STEP_LIST, $url);
        if ($result === false) {
            Bd_Log::trace(sprintf('class[%s] function[%s] data[%s]', __CLASS__, __FUNCTION__, $url));
            return false;
        }
        $name = $result[0];
        $number = $result[1];
        $list = $result[2];

        $data = array(
            "webchat_number" => $number,
            "webchat_name" => $name,
            "record_list" => $list
        );

        return $data;
    }
    /**
     * 获取文章详情
     * @param $url
     * @return string
     */
    public function getRecordDetail($url) {
        $result = $this->exeShell(self::STEP_RECORD, $url);

        if ($result === false) {
            Bd_Log::trace(sprintf('class[%s] function[%s] data[%s]', __CLASS__, __FUNCTION__, $url));
            return false;
        }
        $title = array_shift($result);
        $name = array_shift($result);
        $info = '';
        foreach($result as $item){
            $info .= $item;
        }
        $detail = json_decode($info, JSON_UNESCAPED_UNICODE);
        $data = array(
            "title" => $title,
            "webchat_name" => $name,
            "detail" => $detail
        );
        Bd_Log::trace(sprintf('class[%s] function[%s] data[%s]', __CLASS__, __FUNCTION__, @json_encode($data)));
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    /**
     * 执行shell命令
     * @param $file
     * @param $url
     */
    private function exeShell($type, $argv) {
        if (empty($argv) || empty($type)) {
            return;
        }
        $file = $this->getHtmlFromUrl($argv);
        $sh = "python ".dirname(__FILE__)."/parseHtml.py '".$type."' '".$file."'";
        //$sh = "python ".dirname(__FILE__)."/parseHtml.py '".$type."' ".dirname(__FILE__)."'/html/".$type.".html'";
        exec($sh, $output, $result);

        if ($result !== 0)
        {
            Bd_Log::trace(sprintf('class[%s] function[%s] data[%s]', __CLASS__, __FUNCTION__, @json_encode($output)));
            return false;
        }
        unlink($file);
        return $output;
    }

    /**
     * @param $url
     * @return string
     */
    private function getHtmlFromUrl($url){
        $temp_url = explode('?', $url);
        $temp_prefix = explode('/', $temp_url[0]);
        $picName = array_pop($temp_prefix).'.html';
        $tmp_save_path = dirname(__FILE__).'/html/' . $picName;
        $time = 0 ;
        do {
            $file = Wm_Lib_ProxyCurl::curl($url,null,Strategyui_Define_Strategy::$proxyConfig);
            $ret = file_put_contents($tmp_save_path, $file);
            $time++;
        } while ($ret === false and $time < 3);
        return $tmp_save_path;
    }
}