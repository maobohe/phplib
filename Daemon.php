<?php
namespace Lib;

abstract class Daemon
{



    static $daemonName;

    static $scriptName;

    public function __construct()
    {
        // 判断是否在脚本方式运行
        if (PHP_SAPI != 'cli') {
            throw new \Exception('daemon script must run it in a console');
        }
    }

    abstract public function __init();

    /**
     * 获取daemon script 的当前执行中的进程数
     *
     * @since 2015年6月30日 下午3:11:44
     * @access
     *
     */
    public function getProcessCount()
    {
        $jobCommandString = "'" . $_SERVER['SCRIPT_NAME'] . " " . self::$daemonName . ' ' . self::$scriptName . "'";
        exec("ps aux | grep $jobCommandString", $log_exec_rs);
        $scriptExecCount = 0;
        foreach ($log_exec_rs as $v) {
            if (strpos($v, $_SERVER['SCRIPT_NAME']) > 0 && !strpos($v, 'grep') && !strpos($v, '.sh') && !strpos($v, '-c')) {
                $scriptExecCount++;
            }
        }
        return $scriptExecCount;
    }

    public function shEcho($var)
    {
        $args = func_get_args();
        echo(date("Y-m-d H:i:s") . "\t");
        echo(implode("\t",$args));
        echo("\n");
    }

    /**
     *
     * @param string $text1
     * @param string $text2
     * @param string $text3
     * @param string $text4 $text5 $text6 .... $text999
     * @since 2015年7月7日 下午8:32:05
     * @access
     *
     */
    public function log($text1, $text2 = null, $text3 = null)
    {
        $args = func_get_args();
        array_unshift($args, 'job_' . self::$daemonName . '_' . self::$scriptName);
        call_user_func_array('writeLog', $args);
    }
}
