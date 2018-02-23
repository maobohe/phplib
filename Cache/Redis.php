<?php
namespace Lib\Cache;
/**
 * 修改 2017年7月18日
 * 对\Redis 进行封装修改
 * 调用使用方法
 *
 * 如调用redis::decr(key); 则使用 \Lib\Cache::redis()->decr(key)
 * 如调用redis::setbit(key); 则使用 \Lib\Cache::redis()->setbit(key)
 *
 * 使用分组 封装 也是直接使用redis對象
 * 其中包括 string() sets() hashs() lists() 类型操作
 * 对应关系 String（字符串）  Hash（哈希表）List（列表） Set（集合）
 * 使用方式
 * \Lib\Cache::redis()->string()->set(key,vale)
 * \Lib\Cache::redis()->hashs()->hget(key)
 * \Lib\Cache::redis()->lists()->llen(key)
 * \Lib\Cache::redis()->Set()->sadd(key)
 *
 * 需要重写 redis原生方法 需要在后面Rewrite 进行重写 注:重写会自动调用
 * 如类下面 setRewrite 对redis set进行重写
 * 类属性 cache 是redis对象 可以使用cache进行调用
 *
 * 使用  nocache
 * 需要在useNocache 方法中添加redis命令
 *  useNocacheArray 数组中直接添加 redis命令 参数加入nocache=1就不会进入redis命令
 *
 * redis cache class
 * @author ZhengJL
 * @since 2015年6月29日 下午2:52:37
 * @version 2.0
 * @copyright    (c) 2015 , DANG
 *
 */
class Redis extends \Lib\Cache
{

    /**
     * cache instance
     *
     * @var Redis
     */
    protected $cache;

    /**
     * redis connect
     * $conf array redis configuration
     *
     * @see Lib_Cache::connect()
     */
    protected function connect($conf)
    {
        $redis = new \Redis();
        $connect = $redis->connect($conf['host'], $conf['port'], 1);
        if(!$connect){
            //连接不存在的时候用来进行空数据的返回
            return new \Lib\StdClassNull();
        }
        return $redis;
    }


    /**
     * @return \Redis
     */
    public function string()
    {
        return new Redis\RString($this->cache, $this->adapterName);
    }

    /**
     * @return \Redis
     */
    public function sets()
    {
        return new Redis\Sets($this->cache, $this->adapterName);
    }

    /**
     * @return \Redis
     */
    public function hashs()
    {
        return new Redis\Hashs($this->cache, $this->adapterName);
    }

    /**
     * @return \Redis
     */
    public function lists()
    {
        return new Redis\Lists($this->cache, $this->adapterName);
    }

    /**
     * 带格式化set
     * @param string $key
     * @param mixed $value
     * @param number $ttl   缓存时间
     * @param string $toStrType 格式值类型
     * @return return_type
     */
    public function set($key, $value, $ttl = 300, $toStrType = 'serialize')
    {
        switch ($toStrType){
            case 'serialize':
                $value = serialize($value);
                break;
            case 'json':
                $value = json_encode($value);
                break;
            default:
                $value = serialize($value);
        }
        self::string()->setex($key, $ttl, $value);
    }

    /**
     * 带格式化get
     * @param string $key
     * @param string $toStrType
     * @return string 格式值类型
     * @since 2016年7月7日
     * @copyright
     * @return string
     */
    public function get($key, $toStrType = 'serialize')
    {
        $value = self::string()->get($key);
        switch ($toStrType){
            case 'serialize':
                $value = unserialize($value);
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
            default:
                $value = unserialize($value);
        }
        return $value;
    }



    /**
     * 使用  nocache
     * 需要在useNocache 方法中添加redis命令
     *  useNocacheArray 数组中直接添加 redis命令 参数加入nocache=1就不会进入redis命令
     * @param 使用的命令 $callName
     * @since 2017年7月17日
     * @copyright
     * @return return_type
     */
    public function useNocache( $redisCommand ){

        $useNocacheArray = [
            'get','mget',
            'lrange',
            'hget','hgetall',
            'hmget','smembers',
            'zrange','zrevrange','zrangebyscore','zrangebylex',
            'getbit',
        ];
        return isInternalHttpAccess() && \Lib\Cache::isNoCache() && in_array( strtolower($redisCommand), $useNocacheArray );
    }

    /**
     * 对redis方法进行封装的 走这里
     *  重新封装调用 Rewrite 后缀  如get 使用 getRewrite
     *  需要重写 redis原生方法 需要在后面Rewrite 进行重写 注:重写会自动调用
     * 如类下面 setRewrite 对redis set进行重写
     * 类属性 cache 是redis对象 可以使用cache进行调用
     * @param $redisCommand
     * @param $args
     * @return bool
     */
    protected function callRewrite( $redisCommand, $args, &$result ){
        $redisCommandRewrite = $redisCommand . 'Rewrite';
        if ( method_exists ( $this,  $redisCommandRewrite)){
            $result = call_user_func_array(array($this, $redisCommandRewrite), $args);
            return true;
        }
        return false;
    }


    /**
     * 自动调用redis
     * @param string $name  redis命令
     * @param array $args   参数
     * @since 2017年7月17日
     * @copyright
     * @return redis->name
     */
    public function __call( $redisCommand, $args ){
        self::_operationLog($this->adapterName, $redisCommand, $args);
        //nocache不进入cache操作
        if( $this->useNocache( $redisCommand ) ){
            return false;
        }
        //调用重写方法
        if( $this->callRewrite($redisCommand, $args, $result) ){
            return $result;
        }
        return call_user_func_array(array($this->cache, $redisCommand), $args);
    }

}
