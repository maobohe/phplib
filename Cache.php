<?php
namespace Lib;

/**
 * cache
 *
 * @author Richard Zheng <tenleaves@gmail.com>
 *
 */
abstract class Cache
{

    /**
     * cache instance
     *
     * @var object
     */
    protected $cache;

    /**
     * cache link name
     *
     * @var string
     */
    protected $adapterName = 'default';

    /**
     * cache conf collection
     *
     * @var array
     */
    private static $adapters = array();

    public function __construct($_adapterName = null)
    {
        if (!empty($_adapterName)) {
            $this->adapterName = $_adapterName;
        }

        if (empty(self::$adapters[$this->adapterName])) {
            throw new \Exception('This adapter conf not exist.');
        }

        if (!empty(self::$adapters[$this->adapterName]['connId'])) {
            $this->cache = self::$adapters[$this->adapterName]['connId']->cache;
        } else {
            if (empty(self::$adapters[$this->adapterName]['conf'])) {
                throw new \Exception('Cache driver "' . $this->adapterName . '" do not exist...');
            }
            $this->cache = $this->connect(self::$adapters[$this->adapterName]['conf']);
            self::$adapters[$this->adapterName]['connId'] = $this;
        }
    }

    /**
     * TODO:: 目前只支持Redis
     */
    public static function Close()
    {
        foreach (self::$adapters as $adapterName => $adapter) {
            if ($adapter['adapter'] === 'redis' && is_object($adapter['connId']->cache)) {
                $adapter['connId']->cache->close();
            }
        }
    }

    /**
     * @param string $adapterType
     * @param string $adapterName
     * @param array $adapterConf
     */
    public static function factory($adapterType, $adapterName, $adapterConf)
    {
        if (empty(self::$adapters[$adapterName])) {
            self::$adapters[$adapterName]['adapter'] = $adapterType;
            self::$adapters[$adapterName]['conf'] = $adapterConf;
        }
    }

    abstract protected function connect($conf);

    /**
     * redis 操作日志
     * @param $adapterName
     * @param $methodName
     * @param array $arguments
     */
    public static function _operationLog($adapterName, $methodName, array $arguments)
    {
        debugMsg('redis - ' . $adapterName . ' - ' . $methodName, print_r($arguments, true));
    }

    /**
     * 获取 redis 对象
     * @param string $adapterName 链接名称，多个redis服务时需要传递链接名称
     * @return \Lib\Cache\Redis
     * @throws \Exception
     */
    static public function redis($adapterName = 'default')
    {
        if (self::$adapters[$adapterName]['adapter'] != 'redis') {
            throw new \Exception("Cache '$adapterName' is not redis.");
        }

        if (!empty(self::$adapters[$adapterName]['connId'])) {
            return self::$adapters[$adapterName]['connId'];
        } else {
            return new \Lib\Cache\Redis($adapterName);
        }
    }

    /**
     * 是否开启无缓存模式
     * @return bool
     */
    static public function isNoCache()
    {
        // console
        if (PHP_SAPI == 'cli') {
            global $argv;
            if (in_array('nocache', $argv)) {
                return true;
            }
        } elseif (!empty($_REQUEST['nocache'])) {
            return true;
        }

        return false;
    }
}