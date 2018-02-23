<?php
namespace Lib\Cache\Redis;
/**
 * 使用参见 \Lib\Cache\Redis
 * Lib_Cache_Redis_SortedSets
 * 有序集合
 */
class SortedSets extends Base
{


    /**
     * Creates an intersection of sorted sets given in second argument.
     * The result of the union will be stored in the sorted set defined by the first argument.
     * The third optional argument defines weights to apply to the sorted sets in input.
     * In this case, the weights will be multiplied by the score of each element in the sorted set
     * before applying the aggregation. The forth argument defines the AGGREGATE option which
     * specify how the results of the union are aggregated.
     *
     * @param   string $Output
     * @param   array $ZSetKeys
     * @param   array $Weights
     * @param   string $aggregateFunction Either "SUM", "MIN", or "MAX":
     * defines the behaviour to use on duplicate entries during the zInter.
     * @return  int     The number of values in the new sorted set.
     * @link    http://redis.io/commands/zinterstore
     * @example
     * <pre>
     * $redis->delete('k1');
     * $redis->delete('k2');
     * $redis->delete('k3');
     *
     * $redis->delete('ko1');
     * $redis->delete('ko2');
     * $redis->delete('ko3');
     * $redis->delete('ko4');
     *
     * $redis->zAdd('k1', 0, 'val0');
     * $redis->zAdd('k1', 1, 'val1');
     * $redis->zAdd('k1', 3, 'val3');
     *
     * $redis->zAdd('k2', 2, 'val1');
     * $redis->zAdd('k2', 3, 'val3');
     *
     * $redis->zInter('ko1', array('k1', 'k2'));               // 2, 'ko1' => array('val1', 'val3')
     * $redis->zInter('ko2', array('k1', 'k2'), array(1, 1));  // 2, 'ko2' => array('val1', 'val3')
     *
     * // Weighted zInter
     * $redis->zInter('ko3', array('k1', 'k2'), array(1, 5), 'min'); // 2, 'ko3' => array('val1', 'val3')
     * $redis->zInter('ko4', array('k1', 'k2'), array(1, 5), 'max'); // 2, 'ko4' => array('val3', 'val1')
     * </pre>
     */
    public function zInterStoreRewrite($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
        return $this->redis->zInter($Output, $ZSetKeys, $Weights, $aggregateFunction);
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
            'zrange','zrangebyscore','zrank','zrevrange','zrevrangebyscore','zrevrank','zscore'
        ];
        return isInternalHttpAccess() && \Lib\Cache::isNoCache() && in_array( strtolower($callName), $useNocacheArray );
    }


}