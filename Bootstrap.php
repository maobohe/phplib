<?php
namespace Lib;

/**
 * 这是一个引导类
 * Class Lib_Bootstrap
 */
abstract class Bootstrap
{
    /**
     * 子类必须继承此方法，用于初始化
     * @return mixed
     */
    static function init()
    {
    }

    /**
     * 设置时区
     * @param string $timezone
     */
    static function setDefaultTimeZone($timezone)
    {
        date_default_timezone_set($timezone);
    }

    /**
     * @param $environment
     * @param $_confFilePath
     */
    static function setConfFileByEnv($environment, $_confFilePath)
    {
        $confFilePath = $_confFilePath . '/' . $environment;
        $files = scandir($confFilePath);
        foreach ($files as $file) {
            if (preg_match("/(\w+)\.(\w+)/i", $file, $match)) {
                \Lib\Conf::setConfFile($match[1], $confFilePath . '/' . $file);
            }
        }
    }

    /**
     * 设置模板类
     *
     * 必须要在initMVC后面执行
     *
     * @param string $tplPath
     * @param array $viewConf 模板类的视图配置变量
     */
    static function setView($view, $tplPath, $viewConf)
    {
        \Lib\Dispatch::setView($view, $tplPath, $viewConf);
    }

    /**
     * 初始化 session 配置
     */
    static function setSession($session)
    {
        \Lib\Dispatch::setSession($session);
    }

    /**
     * 设置异常处理类
     * @param boolean $display_errors
     * @param int $e_levels
     */
    public static function setException($display_errors, $e_levels = null)
    {
        if ($e_levels === null) {
            $e_levels = E_ALL ^ E_NOTICE;
        }

        error_reporting($e_levels);

        if ($display_errors) {
            ini_set("display_startup_errors", 1);
            ini_set("display_errors", 1);
        } else {
            ini_set("display_errors", 0);
            ini_set("display_startup_errors", 0);
        }
        /**
         * HHVM 3.1.2 error handler bug fix
         * PHP7 don't need this
         */
        set_error_handler("\Lib\Exception::errorHandler");

        set_exception_handler("\Lib\Exception::exceptionHandler");

        //debug
        register_shutdown_function("runtimeInfo");
    }

    /**
     * 配置数据库
     * @param string $adapterName
     * @param array $config
     * @param array $configOfSlave 从库
     */
    public static function setDatabase($adapterName, $config, $configOfSlave = array())
    {
        if ($config['adapter'] == 'mysql') {
            \Lib\Db::factory($adapterName, $config['adapter'], $config['host'], $config['port'], $config['database'], $config['username'], $config['password'], $config['charset'], $config['prefix']);
            if (!empty($configOfSlave)) {
                \Lib\Db::factorySlave($adapterName, $configOfSlave['host'], $configOfSlave['port'], $configOfSlave['database'], $configOfSlave['username'], $configOfSlave['password'], $config['charset']);
            }
        } else if ($config['adapter'] == 'mongo') {
            \Lib\Mongo::factory($adapterName, $config);
        }
        unset($config);
    }


    /**
     * 配置缓存
     * @param string $adapterType
     * @param string $adapterName
     * @param array $config
     */
    public static function setCache($adapterType, $adapterName, $config)
    {
        \Lib\Cache::factory($adapterType, $adapterName, $config);
    }

}