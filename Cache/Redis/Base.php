<?php
namespace Lib\Cache\Redis;
/**
 * 使用参见 \Lib\Cache\Redis
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
 */
abstract class Base
{
    /**
     * @var \redis
     */
    protected $redis;

    /**
     * Lib_Cache $adapterName
     * @var string
     */
    protected $_cacheAdapterName;

    public function __construct($redis, $adapterName)
    {
        $this->redis = $redis;
        $this->_cacheAdapterName = $adapterName;
    }

    /**
     * 使用  nocache
     * 需要在useNocache 方法中添加redis命令
     *  useNocacheArray 数组中直接添加 redis命令 参数加入nocache=1就不会进入redis命令
     * @param 使用的命令 $redisCommand
     * @since 2017年7月17日
     * @copyright
     * @return return_type
     */
    abstract public function useNocache( $redisCommand );

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
        
        \Lib\Cache::_operationLog($this->_cacheAdapterName, $redisCommand, $args);
        //nocache不进入cache操作
        if( $this->useNocache( $redisCommand ) ){
            return false;
        }
        
        //调用重写方法
        if( $this->callRewrite($redisCommand, $args, $result) ){
            return $result;
        }
        return call_user_func_array(array($this->redis, $redisCommand), $args);
    }
}