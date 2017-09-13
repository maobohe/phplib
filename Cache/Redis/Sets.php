<?php
namespace Lib\Cache\Redis;
/**
 * 使用参见 \Lib\Cache\Redis
 * Lib_Cache_Redis_Sets
 * 集合
 *
 */
class Sets extends Base
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
            'smembers','sinter','sismember','sunion','sunionstore'
        ];
        return isInternalHttpAccess() && \Lib\Cache::isNoCache() && in_array( strtolower($callName), $useNocacheArray );
    }

}