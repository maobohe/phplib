<?php
/**
 * 熔断策略类
 */

namespace Lib\Fusing;

use Lib\LocalMacAddressUtil;


class FusingStrategy {

    /**
     * @var int 最大熔断时间
     */
    private static $maxTime = 3600;

    /**
     * 每次请求前都进行熔断是否开始检查（只有需要熔断的接口才会进这里）
     * @param $requestUrl 熔断接口
     * @param $time 熔断阈值
     * @param $fusingAlertInfo 熔断报警信息
     * @return array
     */
    public static function checkFusingBeforeRequest($requestUrl, $time, $fusingAlertInfo) {
        $fusingRes = array("is_fusing" => 0);//默认认为没有发生熔断
        $cacheKey = self::getCacheKey($requestUrl);
        $fusingInfo = \Lib\Cache::redis()->get($cacheKey);
        if($fusingInfo) {
            $fusingInfo = json_decode($fusingInfo, true);
            if($fusingInfo['max'] != 1) {
                $fusingInfo["times"]++;
                $fusingInfo['last_req_time'] = time();
                $expireTime = (pow(2, ($fusingInfo["times"] - 1)) * $time );
                $finalExpireTime = ($expireTime > self::$maxTime ? self::$maxTime : $expireTime);
                if($finalExpireTime == self::$maxTime) {
                    $fusingInfo['max']++;
                }
                //当第二次到达max时，不会进行更新redis时间
                \Lib\Cache::redis()->set($cacheKey, json_encode($fusingInfo), $finalExpireTime);
            }
            self::triggerRadarAlert($fusingAlertInfo, $cacheKey, $finalExpireTime);
            $fusingRes["is_fusing"] = 1; //缓存存在，返回开启熔断状态
            $fusingRes["fusing_result"] = false; //缓存存在，返回空串给上层调用
        }
        return $fusingRes;
    }

    /**
     * 第一次触发熔断
     * @param $requestUrl 熔断接口
     * @param $time 熔断阈值
     * @param $fusingAlertInfo 熔断报警信息
     * @return mixed
     */
    public static function startUpFusing($requestUrl, $time, $fusingAlertInfo) {
        $cacheKey = self::getCacheKey($requestUrl);
        $fusingInfo["times"] = 1;
        $fusingInfo['last_req_time'] = time();
        $fusingInfo['start_up_time'] = time();
        $expireTime = ($time > self::$maxTime ? self::$maxTime : $time);
        if($expireTime == self::$maxTime) {
            $fusingInfo['max']++;
        }
        //当第二次到达max时，不会进行更新redis时间
        if($fusingInfo['max'] == 1 || $expireTime < self::$maxTime) {
            \Lib\Cache::redis()->set($cacheKey, json_encode($fusingInfo), $expireTime);
        }
        $fusingRes["is_fusing"] = 1; //缓存存在，返回开启熔断状态
        $fusingRes["fusing_result"] = false; //缓存存在，返回空串给上层调用
        self::triggerRadarAlert($fusingAlertInfo, $cacheKey, $expireTime);
        return $fusingRes;
    }

    /**
     * @param $fusingAlertInfo 报警信息
     * @param $cacheKey 熔断缓存key
     * @param $expireTime 熔断过期时间
     */
    public static function triggerRadarAlert($fusingAlertInfo, $cacheKey, $expireTime) {
        writelog("fusing_alert", date('Y-m-d H:i:s', time()) . "_fusing_alert_cachekey=" . $cacheKey
            . ", expire at" . $expireTime . "s, alertInfo:" . $fusingAlertInfo);
    }

    /**
     * @param $requestUrl 通过请求的url 及本机的MAC地址来缓存key
     * @return string 缓存key
     */
    private static function getCacheKey($requestUrl) {
        $localMacAddress = (new LocalMacAddressUtil())->getMacAddr(PHP_OS);
        return ("mapi7_fusing_" . $localMacAddress . md5($requestUrl));
    }

}