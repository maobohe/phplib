<?php
namespace Lib\Cache\Redis;
/**
 * Redis List
 * 使用参见 \Lib\Cache\Redis
 * 
 * 列表
 */
class Lists extends Base
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
            'lrange','lindex'
        ];
        return isInternalHttpAccess() && \Lib\Cache::isNoCache() && in_array( strtolower($callName), $useNocacheArray );
    }
    
}