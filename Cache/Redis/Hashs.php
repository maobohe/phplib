<?php
namespace Lib\Cache\Redis;
/**
 * 使用参见 \Lib\Cache\redis
 * Redis Hashs
 * 哈希
 */
class Hashs extends Base
{

    /**
     * 使用nocache
     * @param unknown $callName
     * @since 2017年7月17日
     * @copyright
     * @return return_type
     */
    public function useNocache( $callName ){

        $useNocacheArray = [
            'hget','hgetall', 'hmget'
        ];
        return isInternalHttpAccess() && \Lib\Cache::isNoCache() && in_array( strtolower($callName), $useNocacheArray );
    }
}