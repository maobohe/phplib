<?php
namespace Lib;

/**
 * 会话管理类
 * session
 */
abstract class Session
{
    /**
     * @var string
     */
    protected $sid;

    /**
     * @var \Lib\Session\Storage
     */
    protected $storage;

    /**
     * 单例
     * @var null
     */
    private static $instance = null;

    /**
     * @return Session
     */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            $class = get_called_class();
            self::$instance = new $class;
        }
        return self::$instance;
    }

    protected function __construct()
    {
        $this->sid = $this->getSid();

        $this->__init();
    }

    abstract public function __init();

    /**
     * @return \Lib\Session\Storage
     */
    public function storage()
    {
        return $this->storage;
    }

    abstract public function getSid();

    public function update()
    {

    }
}