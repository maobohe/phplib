<?php
namespace Lib;
/**
 * 拦截器类
 * 
 * @code 
 * //调用方法
 * /*表示 在self::$expireSecond 时间内
 *  * key是 uid 调用 15次返回true  
 *  * key是 cid 调用 16次返回true  
 *  *
 *  * 并且会根据频段 进入黑名单
 * $Param = array(
 *          'uid'  =>  15,//次数
 *          'cid'  =>  16 
 * );
 * $ntercept = new Lib_Lntercept($Param);
 * $return = $ntercept->run();
 * //不使用频段进入黑名单
 * $return = $ntercept->notUsedFrequency()->run();
 * @endcode
 * 
 * @author songzhian<songzhian@dangdang.com>
 * @since 2015年11月16日 下午3:26:49
 * @version 0.1
 * @copyright    
 * @file  file_name
 */
class Intercept
{
    
    /*
     * 初始属性内容  array( key值 =>  key值对应的次数 ); 
     * 根据默认时间过期
     */
    protected $_paramKeys = array(); 
    protected $_IP = 0;
    
    /*
     * 是否使用频段限制控制
     */
    private $useFrequency = true;
    
    protected static $_instance;
    public static function getInstance($configParam)
    {
        if (self::$_instance == null) {
            self::$_instance = new self($configParam);
        }
        return self::$_instance;
    }
    
    public  function __construct($configKeys){
        $this->initParamKeys($configKeys);
    }
    
    public  function initParamKeys($configKeys){
        $this->_paramKeys = $configKeys;
    }
    
    /**
     * 频段 秒多少次
     * @var unknown
     */
    private static $frequencylimits = array(
            60      => 25,
            300     => 50,
            3600    => 120,
            7200    => 150,
            18000   => 290,
    );
    private  static  $expireSecond =  1800; //默认过期时间
    public   static  $limitExpire = 86400;
    
    /**
     * 可设置时间
     * @param unknown $frequencylimits
     * @since 2015年11月16日  下午4:29:39
     * @access
     * @return return_type
     */
    public function setFrequencylimits($frequencylimits){
        self::$frequencylimits = $frequencylimits;
    }
    public function setExpireSecond($expireSecond){
        self::$expireSecond = $expireSecond;
    }
    
    
    /**
     * 运行
     * @return boolean
     * @since 2015年11月16日  下午4:30:01
     * @access
     * @return boolean
     */
    public  function run(){
    
        //分解处理
        try {
            $this->runSplit();
        } catch (Exception $e) {
            return true;
        }
    
        return false;
    }
    
    /**
     * 分割key处理
     * 
     * @since 2015年11月16日  下午3:51:59
     * @access
     * @return return_type
     */
    public  function runSplit(){
    
        foreach($this->_paramKeys as $key=>$limit){
            $this->startCompr($key, $limit);
        }
    }
    
    
    /**
     * 总格计算
     * 
     * @param unknown $key
     * @param unknown $limit
     * @throws Exception
     * @since 2015年11月16日  下午3:51:53
     * @access
     * @return return_type
     */
    private  function startCompr($key, $limit){
    
    
        $keyIp = $key . $this->_getIp();
        //在频段内部进入名单 在名单内抛出
        $this->isIllegal($keyIp);
        //频率控制 
        $this->frequencyCtrl($keyIp);
        
        //普通名单阀值
        if($this->keyInCache($key, self::$expireSecond) >= $limit) {
            throw new Exception('is expire');
        }
    }
    
    /**
     * 频率控制 
     * 
     * @since 2015年11月17日  上午10:49:05
     * @access
     * @return return_type
     */
    private function frequencyCtrl($keyIp){
        
        //是否使用频段过滤
        if(!$this->useFrequency) return;
        //配置频段
        foreach(self::$frequencylimits as $time => $count){
            $this->counTexpire($keyIp, $time, $count);
        }
        
    }
    
    
    /**
     * 频道计算
     * 
     * @param unknown $key
     * @param unknown $time
     * @param unknown $count
     * @since 2015年11月16日  下午3:52:09
     * @access
     * @return return_type
     */
    private function counTexpire($key, $time, $count){
    
        //超过频道的加入非法cache
        if($count >= $this->keyInCache($key . $time, $time)){
            $this->keyInCache($this->getIpKey(), self::limitExpire);
        }
    }
    
    /**
     * 判断是否非法
     * 
     * @param unknown $keyIp
     * @throws Exception
     * @since 2015年11月16日  下午3:52:18
     * @access
     * @return return_type
     */
    public function isIllegal($keyIp){
    
        $val = $this->getKeyIncache($this->getIpKey());
        if($val > 0){
            throw new Exception('is Illegal');
        }
    }
    
    /**
     * 
     * 
     * @since 2015年11月17日  上午10:54:48
     * @access
     * @return return_type
     */
    public function notUsedFrequency(){
        $this->useFrequency = false;
        return $this;
    }
    
    /**
     * 重置
     *
     * @since 2015年11月16日  下午3:52:28
     * @access
     * @return return_type
     */
    public  function resetSplit(){
    
        foreach($this->_paramKeys as $key=>$expire){
            $this->reset($key);
        }
    }
    
    /**
     * 重置时间
     */
    private function reset($key){
        //设置缓存并获取当前次数
        $keyTimes = $this->keyInCache($key, 0, true);
    }
    
    
    /**
     * 添加 cache
     * @param unknown $keystring
     * @param unknown $expireSecond
     * @param string $isResetExpire
     * @return unknown
     * @since 2015年7月28日  上午9:36:45
     * @access
     * @return unknown
     */
    private function keyInCache($keystring, $expireSecond, $isResetExpire = false){

        $key = $this->getKey($keystring);
         
        $keyTimes = Lib_Cache::redis()->string()->incr($key);
    
        if($keyTimes == 1 || $isResetExpire){
            $keyTimes = Lib_Cache::redis()->string()->expire($key, $expireSecond);
        }
         
        return $keyTimes;
    }
    
    /**
     * 从cache中获取获取key 值
     * @param unknown $action
     * @param unknown $keystring
     * @throws Exception
     * @return boolean
     */
    private function getKeyIncache($keystring){
    
        return Lib_Cache::redis()->string()->get($this->getKey($keystring));
    }
    
    /**
     * 获取生成的key
     * 
     * @param unknown $keystring
     * @return string
     * @since 2015年11月16日  下午3:55:43
     * @access
     * @return string
     */
    private function getKey($keystring){
        
        $keystring = $this->timeAlgorithm() . $keystring;
        $key = md5($keystring);
        
        return $key;
    }
    
    
    private function getIpKey(){
    
        $keystring = $this->timeAlgorithm() . $this->_getIp();
        $key = md5($keystring);
    
        return $key;
    }
    
    
    
    /**
     * 时间算法
     * 最多一天过期
     */
    private function timeAlgorithm(){
    
        return date("Ymd");
    }
    
    /**
     * 获取ip
     * @return number
     * @since 2015年7月28日  上午10:23:34
     * @access
     * @return number
     */
    protected function _getIp(){
    
        if(empty($this->_IP)){
            $this->_IP = getIp();
        }
        return  $this->_IP;
    }
    
}

?>