<?php


/**
 * configuration management
 * 配置管理
 */

namespace Lib;

class Conf
{
    private static $conf;

    /**
     * @var \Lib\Db\Command
     */
    private static $db;

    /**
     * @var \Lib\Redis
     */
    private static $redis;

    /**
     * 保存配置的缓存过期时间
     */
    private static $cacheExpiration = 300;

    /**
     * @var array
     */
    private static $configurations;

    /**
     * 根据name获得配置
     * @param $name
     * @return null
     * @throws \Exception
     */
    public static function get($name)
    {
        if (empty(self::$conf[$name])) {
            throw new \Exception('Configuration name not exist!');
        }

        if (self::$conf[$name]['type'] == 'file') {
            if (!self::$conf[$name]['value'] instanceof \Lib\Conf\File) {
                self::$conf[$name]['value'] = new \Lib\Conf\File(self::$conf[$name]['file']);
            }
            return call_user_func_array(array(self::$conf[$name]['value'], 'get'), func_get_args());
        }

        /*
        if (empty($name)) {
            throw new \Exception('Configure name error');
        }

        if (!(self::$redis instanceof \Lib\Redis\String)) {
            throw new \Exception('Configure redis driver error');
        }

        if (!(self::$db instanceof \Lib\Db\Command)) {
            throw new \Exception('Configure db driver error');
        }

        if (!empty(self::$configurations[$name])) {
            return self::$configurations[$name]['value'];
        }

        $confInfoOfCache = self::$redis->string()->get($name);
        if (!empty($confInfoOfCache)) {
            self::$configurations[$name] = json_decode($confInfoOfCache, true);
            return self::$configurations[$name]['value'];
        }

        $confInfoOfDb = self::$db->queryBuilder()->select('var_value')->where("var_name='{$name}'")->queryRow();
        if ($confInfoOfDb !== false) {
            //写入类变量
            self::$configurations[$name] = array('name' => $name, 'value' => $confInfoOfDb);

            //写入缓存
            $confInfoOfCache = json_encode(self::$configurations[$name]);
            self::$redis->string()->set($name, $confInfoOfCache, self::$cacheExpiration);
            return self::$configurations[$name]['value'];
        }

        return null;
        */
    }


    /**
     * 设置配置文件
     * @param $key
     * @param $file
     * @throws \Exception
     */
    public static function setConfFile($key, $file)
    {
        if (!empty(self::$conf[$key])) {
            throw new \Exception('Configuration name exist!');
        }
        self::$conf[$key]['name'] = $key;
        self::$conf[$key]['file'] = $file;
        self::$conf[$key]['type'] = 'file';
        self::$conf[$key]['value'] = null;
    }

    /**
     * 设置配置中心
     * TODO::
     * @param $key
     * @param $confApi
     */
    public static function setConfApi($key, $confApi)
    {
        $apiClient = new \Lib\ApiClient();
        $apiClient->get($confApi,null,100);

    }

    /**
     * TODO::
     * 设置配置管理中的 redis 和 db
     * @param Db $confDb
     * @param \Lib\Redis $confRedis
     */
    public static function setDriver(\Lib\Db $confDb, \Lib\Redis $confRedis)
    {
        $confDb->tableName = 'config';
        $confDb->tablePrimary = 'var_name';
        self::$db = $confDb;
        self::$redis = $confRedis;
    }
}