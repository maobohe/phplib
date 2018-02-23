<?php
namespace Lib\Session;


interface Storage
{
    public function __construct();

    public function setVal($key, $val);

    public function getVal($key);

    /**
     * @param $array
     */
    public function setInfo($array);

    /**
     * @return array
     */
    public function getInfo();

    /**
     * 更新缓存信息
     * @return mixed
     */
    public function update();
}