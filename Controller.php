<?php
namespace Lib;

/**
 * Class Controller
 * @package Lib
 */
abstract class Controller
{

    /**
     * @var string
     */
    protected $controllerName;

    /**
     * @var string
     */
    protected $actionName;

    /**
     * 视图类
     * @var View
     */
    protected $view;

    /**
     * @var \Lib\Request
     */
    protected $request;

    public $__init = true;

    /**
     * @var \Lib\Session
     */
    public $session;

    public function __construct()
    {
        if (empty($this->request)) {
            $this->request = \Lib\Request::getInstance();
        }
        $this->actionName = Dispatch::$actionName;
        $this->controllerName = Dispatch::$controllerName;
        if (empty($this->view) && !empty(Dispatch::view())) {
            $this->view = Dispatch::view();
        }

        if (empty($this->session) && !empty(Dispatch::session())) {
            $this->session = Dispatch::session();
        }

        if ($this->__init)
            $this->__init();
    }

    /**
     * 控制类自动初始化函数
     * @return mixed
     */
    abstract function __init();

    /**
     * 跨域设置
     * @param $domain
     */
    public function allowCrossDomain($domain)
    {
        header('Access-Control-Allow-Origin: ' . $domain);
    }

    /**
     * 获取 _request 查询字符串 参数
     * @param unknown $name 查询可以
     * @param string $defaultValue 默认值
     * @return string
     * @since 2016年6月24日
     * @copyright
     * @return string
     */
    protected function _Q($name, $defaultValue = NULL)
    {

        return $this->request->_request($name, $defaultValue);
    }

    /**
     *
     * @param string $text1
     * @param string $text2
     * @param string $text3 $text4 $text5 $text6 .... $text999
     * @since 2015年7月7日 下午8:32:05
     * @access
     *
     */
    public function log($text1, $text2 = null, $text3 = null)
    {
        $args = func_get_args();
        array_unshift($args, 'action', $this->controllerName, self::$actionName);
        call_user_func_array('writeLog', $args);
    }

    public function __get($var)
    {
        return $this->$var;
    }
}