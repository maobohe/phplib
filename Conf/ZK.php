<?php


/**
 * configuration management
 * 配置管理
 */

namespace Lib\Conf;

class ZK
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

    public function getConf(){
        //parse_ini_file
    }

    /**
     * 根据name获得配置
     * @param $name
     * @return null
     * @throws \Exception
     */
    public function get($name)
    {
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
    }
}