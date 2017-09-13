<?php
/**
 * 获取访问者IP地址
 * @param bool $ip2long 是否将结果转为长整数
 * @return int
 */
function getIp($ip2long = false)
{
    if (!empty($_SERVER['HTTP_DD_REAL_IP'])) {
        $ip = $_SERVER['HTTP_DD_REAL_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (strpos($ip, ',') > 0) {
        $ips = explode(',', $ip);
        $ip = $ips[0];
    }

    if (true == $ip2long) {
        $ip = ip2long($ip); //!!32bit 64bit calculated result not same... so suggest not use it. and use the MySQL function INET_ATON,INET_NTOA with UNSIGNED INT(11) field
    }
    return $ip;
}

/**
 * 写日志
 *
 * @param string $filename
 * @param string $text1
 * @param string $text2
 * @param string $text3
 * @param string $text4
 * @param string $text5 ……
 */
function writeLog($filename, $text1, $text2 = null, $text3 = null, $text4 = null, $text5 = null)
{
    $args = func_get_args();
    $log_filename = strtolower(LOG_PATH . DIRECTORY_SEPARATOR . $args[0] . '_' . date("Ymd") . '.log');
    $args[0] = date("Y-m-d H:i:s");
    error_log('[' . implode('][', $args) . "]\n", 3, $log_filename);
}

/**
 * 为输入参数添加slash
 * @param $var
 * @return array|string
 */
function deepAddSlashes($var)
{
    if (is_array($var)) {
        foreach ($var as $key => $val) {
            $var[$key] = deepAddSlashes($val);
        }
    } else {
        $var = addslashes($var);
    }
    return $var;
}

/**
 * 检测字符串当前编码
 *
 * @param string $string 需要检测的字符串
 * @access public
 * @return string 字符编码:UTF-8,...
 */
function detect_encode($string)
{
    $encodes = array('ASCII', 'UTF-8', 'GBK', 'GB2312', 'CP936');
    $now_encode = mb_detect_encoding($string, $encodes);
    if (false === $now_encode) { //user reco 为什么返回的东西就不能分析出编码呢...
        return 'CP936';
    }
    return $now_encode;
}


/**
 * 转换编码，m.dd通常是转换为utf-8
 *
 * @param string $string 要转换的字符串
 * @param string $toencode 要转换为的编码,默认为UTF-8
 * @return string
 */
function convert_encoding($string, $to_encode = 'UTF-8')
{
    //当前编码
    $now_encode = detect_encode($string);
    // 只有编码不同时才转换
    if (strtolower($to_encode) != strtolower($now_encode)) {
        $string = mb_convert_encoding($string, $to_encode, $now_encode);
    }
    return $string;
}

/**
 * 是否正常ip
 * @param $ip
 * @return bool
 */
function isNormalIp($ip)
{
    $result = true;

    $len = strlen($ip);
    //长度不正常
    if ($len < 7 || $len > 15) {
        $result = false;
    }

    if (!preg_match('/((?:(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d)))\.){3}(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d))))/', $ip)) {
        $result = false;
    }

    return $result;
}


/**
 * 测试服务器是否在线
 * @param $host
 * @param $port
 * @param string $timeout
 * @return array
 */
function checkServerStatus($host, $port, $timeout = '2')
{
    $socket = @stream_socket_client(
        $host . ':' . $port,
        $errorNumber,
        $errorDescription,
        $timeout ? $timeout : ini_get("default_socket_timeout")
    );

    $result = false;
    if ($socket) {
        $result = true;
        stream_socket_shutdown($socket, STREAM_SHUT_WR);
    }

    return array('alive' => $result, 'errorNumber' => $errorNumber, 'errorDescription' => $errorDescription);
}

/**
 * 根据二维数组中某个值进行排序
 * @param $array
 * @param string $keyName 二维数组中的键值
 * @param bool $descending 降序
 * @return mixed
 */
function sort2dArray($array, $keyName, $descending = true)
{
    uasort($array, function ($a, $b) use ($keyName, $descending) {
        if ($a[$keyName] == $b[$keyName]) {
            return 0;
        } elseif ($a[$keyName] > $b[$keyName]) {
            if ($descending)
                return -1;
            else
                return 1;
        } else {
            if ($descending)
                return 1;
            else
                return -1;
        }
    });
    return $array;
}

/**
 * 判断是否为内网访问
 * @return bool
 */
function isInternalHttpAccess()
{
    $ip = getIp();
    $ips = explode('.', $ip);
    if (count($ips) < 4) {
        return false;
    }
    $ipChunk = $ips[0] . '.' . $ips[1];
    //内网ip地址过滤
    $haystackIn = array(
        '192.168',
        '127.0'
    );
    //公司ip地址过滤
    $haystack = array(
        '111.207.228.104',
    );
    //在10.0.0.1 和 10.255.255.254 之间的
    $inRange = (ip2long($ip) <= ip2long("10.255.255.254")) && (ip2long($ip) >= ip2long("10.0.0.1"));

    return in_array($ip, $haystack) || in_array($ipChunk, $haystackIn) || $inRange;
}

/**
 * 是否开启debug模式
 * @return bool
 */
function isDebug()
{
    // console
    if (PHP_SAPI == 'cli') {
        global $argv;
        if (in_array('debug', $argv)) {
            return true;
        }
    } elseif (!empty($_REQUEST['debug'])) {
        return true;
    }

    return false;
}

/**
 * 输出调试信息
 * @param $varName
 * @param $varValue
 */
function debugMsg($varName, $varValue)
{
    //办公网络才可以调试
    if (isInternalHttpAccess() && isDebug()) {
        $_debug_string[0] = "";
        $_debug_string[1] = "<b>" . $varName . " :</b>";
        $_debug_string[2] = print_r($varValue, true);
        $_debug_string[3] = "";

        // console
        if (PHP_SAPI == 'cli' || \Lib\Request::getInstance()->isAjax()) {
            $_debug_string[1] = $varName . ":";
        } else {
            $_debug_string[0] = "<pre>";
            $_debug_string[3] = "</pre>";
        }
        echo("\n" . (implode("\n", $_debug_string)) . "\n");
    }
}

/**
 * 客户端版本对比
 * @param $version1
 * @param $version2
 * @return string
 */
function appVersionCompare($version1, $version2)
{
    $version1_arr = explode('.', $version1);
    $version2_arr = explode('.', $version2);

    $count = count($version1_arr) > count($version2_arr) ? count($version1_arr) : count($version2_arr);
    for ($i = 0; $i < $count; $i++) {
        if ($version1_arr[$i] > $version2_arr[$i]) {
            return ">";
        } else if ($version1_arr[$i] < $version2_arr[$i]) {
            return "<";
        }
    }
    return "=";
}

//运行环境
function runtimeInfo()
{
    debugMsg('errors', error_get_last());
    debugMsg('include files', get_included_files());
//    $xhprof_data = xhprof_disable();
//    include_once "xhprof_lib/utils/xhprof_lib.php";
//    include_once "xhprof_lib/utils/xhprof_runs.php";
//    $objXhprofRun = new XHProfRuns_Default();
//    $objXhprofRun->save_run($xhprof_data, "xhprof");
}