<?php
namespace Lib;

use \Curl\Curl;
use \Curl\MultiCurl;
use \Lib\Fusing\FusingStrategy;

class ApiClient
{
    /**
     * 连接超时时间
     * @var Int
     */
    private $_connectTimeout;

    /**
     * 链接重试次数
     * @var int
     */
    private $_connectRetryTimes;

    /**
     * 单个curl 的结果
     * @var string
     */
    private $_result;

    /**
     * 并发curl的结果集
     * @var array
     */
    private $_results = [];

    /**
     * 开始计算时间
     * @var int
     */
    private $_curlStartTime;

    /**
     * url 基本参数
     * @var string
     */
    private $_url;

    /**
     * 请求方法
     * @var string get|post
     */
    private $_method;

    /**
     * 参数
     * @var array
     */
    private $_arguments = array();

    /**
     * 封装 curl 对象 实例一次
     * @var \Curl\Curl
     */
    private $_curl;

    /**
     * 错误信息
     * @var
     */
    private $errorMessage;

    /**
     * 响应结果
     * @var
     */
    private $rawResponse;


    /**
     * 记录api响应数据
     * @var bool
     */
    private $recordApiResponse = false;

    /**
     * 是否熔断0：不熔断，1开启熔断
     * @var int 默认不熔断
     */
    private $fusing = 0;

    /**
     * @var string 发送熔断时的输出日志信息
     */
    private $fusingAlertInfo = "";

    /**
     * 构造函数
     */
    public function __construct()
    {
    }

    /**
     * 设置URL
     * @param string $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->_url = $url;
        return $this;
    }

    /**
     * 设置 是否记录api响应数据
     * @param boolean $recordApiResponse
     * @return $this
     */
    public function setRecordApiResponse($recordApiResponse)
    {
        $this->recordApiResponse = $recordApiResponse;
        return $this;
    }


    /**
     * 获取 curl对象
     * @return \Curl\Curl
     */
    public function getCurl()
    {
        if (!($this->_curl instanceof Curl)) {
            $this->_curl = new Curl();
        }
        return $this->_curl;
    }

    /**
     * POST 方式请求 API
     * @param string $requestUrl
     * @param array $postArguments
     * @param int $timeOutMS
     * @return array
     */
    public function post($requestUrl, $postArguments, $timeOutMS)
    {
        $this->_method = 'post';
        return $this->send($requestUrl, $postArguments, $timeOutMS);
    }

    /**
     * PUT 方式请求 API
     * @param string $requestUrl
     * @param array $putArguments
     * @param int $timeOutMS
     * @return array
     */
    public function put($requestUrl, $putArguments, $timeOutMS)
    {
        $this->_method = 'put';
        return $this->send($requestUrl, $putArguments, $timeOutMS);
    }

    /**
     * GET 方式请求 API
     * @param string $requestUrl
     * @param array $getArguments
     * @param int $timeOutMS
     * @param int $fusing
     * @param string $fusingAlertInfo
     * @return array
     */
    public function get($requestUrl, $getArguments, $timeOutMS, $fusing = 0, $fusingAlertInfo = "")
    {
        $this->_method = 'get';
        return $this->send($requestUrl, $getArguments, $timeOutMS, $fusing, $fusingAlertInfo);
    }

    /**
     * 设置属性 并发送
     * @param string $requestUrl
     * @param array $arguments
     * @param int $timeOutMS
     * @param int $fusing
     * @param string $fusingAlertInfo
     * @return array
     */
    protected function send($requestUrl, $arguments, $timeOutMS, $fusing = 0, $fusingAlertInfo = "")
    {
        $this->_url = $requestUrl;
        $this->_arguments = $arguments;
        $this->_connectTimeout = $timeOutMS;
        $this->fusing = $fusing;
        $this->fusingAlertInfo = $fusingAlertInfo;
        return $this->httpCall();
    }

    /**
     * 多进程 POST 方式请求 API
     * @param string $requestUrl
     * @param string $postArguments
     * @param string $timeOutMS
     * @return array
     */
    public function postMulti($requestUrl, $postArguments, $timeOutMS)
    {

        $this->_method = 'post';
        return $this->sendMulti($requestUrl, $postArguments, $timeOutMS);
    }

    /**
     * 多进程 GET 方式请求 API
     * @param $requestUrl
     * @param $getArguments
     * @param $timeOutMS
     * @return array
     */
    public function getMulti($requestUrl, $getArguments, $timeOutMS)
    {
        $this->_method = 'get';
        return $this->sendMulti($requestUrl, $getArguments, $timeOutMS);
    }

    /**
     * 设置属性 并发送多进程
     * @param string $requestUrl
     * @param array $arguments
     * @param int $timeOutMS
     * @return array
     * @throws \ErrorException
     */
    protected function sendMulti($requestUrl, $arguments, $timeOutMS)
    {
        $this->_url = $requestUrl;
        $this->_arguments = $arguments;
        $this->_connectTimeout = $timeOutMS;
        $this->httpMultiCall();
        return $this->_results;
    }

    private function _curlCall()
    {
        if (empty($this->_url)) {
            throw new \ErrorException('url is must');
        }

        /**
         * 熔断方案：阈值基数60秒
         */
        $baseTimeStep = 60;
        //熔断方案
        if($this->fusing) {//判断是否已经开启熔断，如果开启则直接返回缓存信息
            $fusingRes = FusingStrategy::checkFusingBeforeRequest($this->_url, $baseTimeStep, $this->fusingAlertInfo);
            if($fusingRes["is_fusing"]) {
                //处理返回结果
                $this->getCurl()->rawResponse = false;
                return $fusingRes["fusing_result"];
            }
        }

        $tryTimes = 0;
        do {
            if ($this->_method == 'post') {
                $result = $this->getCurl()->post($this->_url, $this->_arguments);
            }elseif ($this->_method == 'put') {
                $result = $this->getCurl()->put($this->_url, $this->_arguments);
            } else {
                $result = $this->getCurl()->get($this->_url, $this->_arguments);
            }
            ++$tryTimes;
            $strResult = '';
            if( is_string( $result ) ){
                $strResult = $result;
            }
            if( is_object( $result ) ){
                $strResult = json_encode($result);
            }
        } while (strlen($strResult) == 0 && $tryTimes < $this->_connectRetryTimes);

        if($this->fusing && (strlen($strResult)==0 || $this->getCurl()->httpStatusCode != 200)){
            //开启熔断，并处理返回结果
            $this->getCurl()->rawResponse = false;
            return FusingStrategy::startUpFusing($this->_url, $baseTimeStep, $this->fusingAlertInfo);
        }
    }

    /**
     * http call api
     */
    protected function httpCall()
    {
        //设置请求开始时间
        $this->_curlStartTime = microtime(true);

        /**
         * CURLOPT_NOSIGNAL 是为了结局 1秒以下问题bug
         * 但是有一个隐患, 那就是DNS解析将不受超时限制了
         * 但是一般不会出现问题
         */
        $this->getCurl()->setOpt(CURLOPT_NOSIGNAL, 1);
        $this->getCurl()->setOpt(CURLOPT_CONNECTTIMEOUT_MS, $this->_connectTimeout);
        $this->getCurl()->setOpt(CURLOPT_TIMEOUT_MS, $this->_connectTimeout);
        //执行curl
        $this->_curlCall();

        //相应值转换变量
        $this->_result = $this->getCurl()->rawResponse;

        //debug
        $this->_debug();
        //日志
        $this->_log();


        return $this->_result;
    }


    /**
     *  multi 的封装 多进程处理
     * @throws \ErrorException
     */
    protected function httpMultiCall()
    {
        if (empty($this->_url)) {
            throw new \ErrorException('url is must');
        }
        if (count($this->_arguments) < 2) {
            throw new \ErrorException('no need use multi');
        }
        $this->_curlStartTime = microtime(true);
        $curl = new MultiCurl();

        /*
         * 结束回调操作
         */
        $callable = [$this, 'multiCallback'];
        $curl->success($callable);
        $curl->error($callable);
        $curl->complete($callable);

        foreach ($this->_arguments as $arg) {

            if ($this->_method == 'get') {
                $curl->addGet($this->_url, $arg);
            } else {
                $curl->addPost($this->_url, $arg);
            }
        }
        $curl->start();
    }

    /**
     * 多进程curl调用 回调函数
     *
     * @param \Curl\Curl $curl \Curl\Curl
     * @since 2017年8月4日
     * @copyright
     * @return return_type
     */
    public function multiCallback($curl)
    {

        $this->_url = $curl;
        //debug
        $this->_debug();
        //日志
        $this->_log();

        $this->_results[] = $curl->rawResponse;
    }

    /**
     * 处理 soap 请求
     * @param $requestUrl
     * @param $func
     * @param $param
     * @return array
     */
    public function soapCall($requestUrl, $func, $param)
    {
        $this->_url = $requestUrl;
        //设置请求开始时间
        $this->_curlStartTime = microtime(true);

        $this->_arguments = $param;

        //执行Soap
        try {
            $soapObject = new \SoapClient($this->_url,
                array(
                    'connection_timeout' => floor($this->_connectTimeout / 1000)) //毫秒超时时间
            );
            $this->_result = $soapObject->__soapCall($func, array($param));
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }

        //debug
        $this->_debug('soap');
        //日志
        $this->_log();

        return $this->_result;
    }

    /**
     * debug的请求类型 curl,soap
     * @param string $sendType
     * @since 2016年12月28日
     * @copyright
     * @return void
     */
    private function _debug($sendType = 'curl')
    {
        if (!(isInternalHttpAccess() && isDebug())) {
            return;
        }

        $curlStartTime = $this->_curlStartTime;
        $curlEndTime = microtime(true);
        $microTime = round($curlEndTime - $curlStartTime, 5);
        $host = parse_url($this->_url)['host'];
        debugMsg('API url', $this->_url);
        debugMsg('spent seconds', $microTime);
        debugMsg('host', $host);
        $hostIp = gethostbyname($host);
        debugMsg('host ip', $hostIp);

        if ($sendType == 'curl') {
            debugMsg('parameter_query', http_build_query($this->_arguments));
        }
        debugMsg('parameter', $this->_arguments);

        if ($sendType == 'curl') {
            debugMsg('http status code', $this->getCurl()->httpStatusCode);
            debugMsg("error({$this->getCurl()->curlErrorCode})", $this->getCurl()->curlErrorMessage);
            debugMsg('result', $this->getCurl()->rawResponse);
        }
        if ($sendType == 'soap') {
            debugMsg("error:", $this->errorMessage);
            debugMsg('result', $this->_result);
        }
    }

    /**
     * 记录日志
     */
    private function _log()
    {
        //判断是否是curl
        $totalTime = 0;
        $httpCode = 0;
        if ($this->getCurl()) {
            $totalTime = $this->getCurl()->getInfo(CURLINFO_TOTAL_TIME);
            $httpCode = $this->getCurl()->getInfo(CURLINFO_HTTP_CODE);
        }
        $curlStartTime = $this->_curlStartTime;
        $curlEndTime = microtime(true);
        $microTime = round($curlEndTime - $curlStartTime, 5);
        $host = parse_url($this->_url)['host'];
        $hostIp = gethostbyname($host);
        $curlErrorCode = $this->getCurl()->curlErrorCode;
        $curlErrorMessage = $this->getCurl()->errorMessage;
        $soapErrorMessage = $this->errorMessage;;
        //获取当前请求的 uri
        $uri = 'http://' . $_SERVER['SERVER_ADDR'];
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri .= $_SERVER['REQUEST_URI'];
        } else {
            if (isset($_SERVER['argv'])) {
                $uri .= $_SERVER['PHP_SELF'] . '?' . $_SERVER['argv'][0];
            } else {
                $uri .= $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
            }
        }

        $args = [
            'apitime',
            $this->_url,
            implode(',', $this->_arguments),
            $host,
            $hostIp,
            $httpCode,
            $curlErrorCode,
            $curlErrorMessage,
            $soapErrorMessage,
            $totalTime,
            $microTime,
            $uri, ////请保留此字段为最后一位
        ];

        /**
         * 记录错误日志
         */
        if (!empty($this->getCurl()->curlErrorCode) || !empty($this->errorMessage)) {
            $apiError = $args;
            $apiError[0] = 'api_error';
            if (is_string($this->_result)) {
                $apiError[] = $this->_result;
            } else {
                $apiError[] = var_export($this->_result, TRUE);
            }
            call_user_func_array('writeLog', $apiError);
        }

        /*
         * 记录响应内容信息日志
         */
        if ($this->recordApiResponse) {
            $argsResponse = $args;
            $argsResponse[0] = 'apitime_for_response';
            if (is_string($this->_result)) {
                $argsResponse[] = $this->_result;
            } else {
                $argsResponse[] = var_export($this->_result, TRUE);
            }
            call_user_func_array('writeLog', $argsResponse);
        }

        call_user_func_array('writeLog', $args);
    }

    /**
     * 对于私有参数的操作
     * @param $key
     * @param $val
     */
    public function __set($key, $val)
    {
        $this->$key = $val;
    }

    public function __get($key)
    {
        return $this->$key;
    }
}