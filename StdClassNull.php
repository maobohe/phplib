<?php
namespace Lib;
/**
 * 空类 继承空对象
 * 处理需要返回空
 * Class StdClass
 * @package Lib
 */
class StdClassNull extends  \stdClass
{

    /**
     * 
     */
    public function __call($name, $args){
        return false;
    }

}