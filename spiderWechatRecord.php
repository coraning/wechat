<?php
/**
 * Created by PhpStorm.
 * User: think
 * Date: 2016/11/16
 * Time: 17:23
 */
require("HandleSpiderProcess.php");
require("ParseFile.php");
require("ContentService.php");
$t1 = microtime(true);
ini_set("memory_limit", "10G");
echo date("Y-m-d  h:i:sa", time())." begin"."\n";
Bd_Init::init();
Bd_Log::trace(sprintf("script start"));
$params = $argv;
$num   = isset($params[1])?(int)$params[1]:1;
$obj  = new StepController();
$obj->run($num);
echo date("Y-m-d  h:i:sa", time())." end\n";
Bd_Log::trace(sprintf("script end"));
$t2 = microtime(true);
echo '耗时'.round($t2-$t1,3).'秒', PHP_EOL;
exit(0);

class StepController {

    const STEP_SEND_NUMBER = '1';
    const STEP_SEND_LIST_PAGE = '2';
    const STEP_SEND_RECORD_LIST = '3';
    const STEP_FREQUENT_OPERATION = '9';

    private $step = '999';

    private $numberprefix = null;
    private $listprefix = null;
    private $recordprefix = null;

    public function __construct()
    {
        $obj  = new HandleSpiderProcess();
        $this->numberprefix = $obj->fetchParseNumberPath();
        $this->listprefix = $obj->fetchParseListPath();
        $this->recordprefix = $obj->fetchParseRecordPath();

        if (!empty($this->recordprefix)) {
            $this->step = $this->getStep();
        }
    }

    public  function run($num){
        echo "step : ".$this->step."\n";
        switch ($this->step) {
            case self::STEP_FREQUENT_OPERATION:
                echo "Frequent operation,Later try again!\n";
                break;
            case self::STEP_SEND_NUMBER:
                $this->sendParamForNumber($num);
                break;
            case self::STEP_SEND_LIST_PAGE:
                $this->sendParamForList();
                break;
            case self::STEP_SEND_RECORD_LIST:
                $this->sendParamForRecord();
                break;
            case '999':
                echo "da is running.\n";
                break;
            default:
                $this->sendParamForNumber($num);
                break;
        }
    }
    /**
     * sendParms
     */
    public  function sendParamForNumber($num) {
        echo "step1\n";
        $model = new ContentService();
        $recordlist = $model->getNeedParseRecordList();
        if (!empty($recordlist)) {
            self::parseHtmlForRecord();
        }
        $numbers = $model->getNumbers($num);
        Bd_Log::warning("params ".@json_encode($numbers));
        if (!empty($numbers)) {
            do {
                $obj  = new HandleSpiderProcess();
                $ret = $obj->sendParamsOfNumber($numbers);
            } while ($ret===false);
            return $this->setStep(self::STEP_SEND_LIST_PAGE);
        }
    }

    /**
     * @return array
     */
    private function parseHtmlForNumber() {
        $prefix = $this->numberprefix;
        if (!empty($prefix)) {
            $parser = new ParseFile($prefix);
            return $parser->parseWechatNumber();
        }
    }

    /**
     * 发送params 到da
     */
    public  function sendParamForList() {
        echo "step2\n";
        $urls = self::parseHtmlForNumber();
        Bd_Log::warning("params ".@json_encode($urls));
        if (!empty($urls)) {
            $obj  = new HandleSpiderProcess();
            $obj->sendParamsOfList($urls);
            return $this->setStep(self::STEP_SEND_RECORD_LIST);
        } else {
            return $this->setStep(self::STEP_SEND_NUMBER);
        }
    }
    /**
     * 解析文章列表页
     * @return array|bool
     */
    private function parseHtmlForList() {
        echo "parseHtmlForList\n";
        $prefix = $this->listprefix;
        if (!empty($prefix)) {
            $parser = new ParseFile($prefix);
            return $parser->parseRecordList();
        }
    }

    /**
     *  解析文章页
     */
    public  function sendParamForRecord() {
        echo "step3\n";
        $urls = self::parseHtmlForList();
        Bd_Log::warning("params ".@json_encode($urls));
        if (!empty($urls)) {
            $obj  = new HandleSpiderProcess();
            $obj->sendParamsOfRecord($urls);
        }
        return $this->setStep(self::STEP_SEND_NUMBER);
    }

    /**
     *解析文章页
     */
    private function parseHtmlForRecord() {
        $prefix = $this->recordprefix;
        if (!empty($prefix)) {
            $parser = new ParseFile($prefix);
            $parser->parseRecordDetail();
        }
    }

    /**
     * @param $str
     */
    private function setStep($str) {
        $file = fopen(dirname(__FILE__)."/stepFile.txt", "w") or die("Unable to setStep!");
        fwrite($file, $str);
        fclose($file);
    }

    /**
     * @return string
     */
    private function getStep() {
        $filename = dirname(__FILE__)."/stepFile.txt";
        if(file_exists($filename)) {
            $beforeTime = fileatime($filename);
            if (time()<$beforeTime+60) {
                return self::STEP_FREQUENT_OPERATION;
            }
            $file = fopen($filename, "r") or die("Unable to getStep!");
        } else {
            $file = fopen($filename, "a") or die("Unable to getStep!");
	}
        var_dump($filename);
        $str = fread($file,filesize($filename));
	fclose($file);
        return $str;
    }

}
