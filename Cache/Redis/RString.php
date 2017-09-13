<?php
namespace Lib\Cache\Redis;
/**
 * 使用参见 \Lib\Cache\Redis
 * redis string
 * Redis 字符串操作类
 *
 */
class RString extends Base
{

    /**
     * set value to redis
     *
     * @param string $key
     * @param string $value
     * @param int $expiration
     * @return boolean
     */
    function setRewrite($key, $value, $expiration = null)
    {
        \Lib\Cache::_operationLog($this->_cacheAdapterName, 'set', func_get_args());
        if (!empty($expiration) && ctype_digit($expiration)) {
            $return = $this->redis->setex($key, $expiration, $value);
        } else {
            $return = $this->redis->set($key, $value);
        }
        return $return;
    }



    function setnxRewrite($key, $value, $expiration = null)
    {
        $setnx = $this->redis->setnx($key, $value);
        if (!empty($expiration) && ctype_digit($expiration)) {
            $this->redis->expire($key, $expiration);
        }
        return $setnx;
    }


    /**
     * 使用nocache
     * @param unknown $callName
     * @since 2017年7月17日
     * @copyright
     * @return return_type
     */
    public function useNocache( $callName ){

        $useNocacheArray = [
            'get','getbit','getrange','getset','mget'
        ];
        return isInternalHttpAccess() && \Lib\Cache::isNoCache() && in_array( strtolower($callName), $useNocacheArray );
    }


}
