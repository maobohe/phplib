<?php
namespace Lib;

class Request
{

    private $_header = array();

    private $_method = '';

    private $_isAjax = false;

    private $_body = '';

    private $_params = array();

    private $_protocol = '';

    /**
     * 单例存储
     * @var null
     */
    protected static $_me = null;

    //禁止克隆
    protected function __clone()
    {
    }

    /**
     * 协议
     * @return string
     */
    public function protocol()
    {

        if ($this->_protocol === '') {
            $this->_protocol = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        }

        return $this->_protocol;
    }

    /**
     * 单例实例化入口
     * @return null|static
     */
    public static function getInstance()
    {

        if (self::$_me === null) {
            self::$_me = new static();
        }
        return self::$_me;
    }

    protected function __construct()
    {
        $this->_getHeader();
        $this->_getMethod();
        $this->_isAjax();
        $this->_getBody();
        $this->initParams();
    }

    /**
     * 获得header内容
     * @param null $key
     * @return array
     */
    public function header($key = null)
    {
        return $key === null ? $this->_header : $this->_header[$key];
    }

    /**
     * 获取远 _SERVER下参数
     * @param null $key
     * @return mixed
     */
    public function server($key = null)
    {
        return $key === null ? $this->_params['server'] : $this->_params['server'][$key];
    }

    /**
     * 访问方式post get
     * @return string
     */
    public function method()
    {
        return $this->_method;
    }

    /**
     * 是否ajax访问
     * @return bool
     */
    public function isAjax()
    {
        return $this->_isAjax;
    }

    /**
     * 获取实体
     * @return string
     */
    public function body()
    {
        return $this->_body;
    }

    public function _get($name, $defaultValue = null)
    {
        if (isset($this->_params['get'][$name])) {
            return $this->_params['get'][$name];
        }
        return $defaultValue;
    }

    public function _post($name, $defaultValue = null)
    {
        if (isset($this->_params['post'][$name])) {
            return $this->_params['post'][$name];
        }
        return $defaultValue;
    }

    public function _request($name, $defaultValue = null)
    {
        if (isset($this->_params['request'][$name])) {
            return $this->_params['request'][$name];
        }
        return $defaultValue;
    }

    public function _cookie($name, $defaultValue = null)
    {
        if (isset($this->_params['cookie'][$name])) {
            return $this->_params['cookie'][$name];
        }
        return $defaultValue;
    }

    /**
     * 获得参数
     */
    private function initParams()
    {
        if ($this->_params === array()) {
            $this->_params['get'] = $_GET;
            $this->_params['post'] = $_POST;
            $this->_params['request'] = $_REQUEST;
            $this->_params['cookie'] = $_COOKIE;
            $this->_params['server'] = $_SERVER;
        }
    }

    /**
     * 获得body
     */
    private function _getBody()
    {
        if ($this->_body === '') {
            $this->_body = file_get_contents('php://input');
        }
    }

    /**
     * 是否ajax访问
     * @return bool
     */
    private function _isAjax()
    {
        if ($this->_isAjax === false) {
            $this->_isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
        }
    }

    /**
     * 获得方法
     * @return string
     */
    private function _getMethod()
    {
        if ($this->_method === '') {
            if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->_method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            } else {
                $this->_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
            }
        }
    }

    /**
     * 获得头信息
     * @return mixed
     */
    private function _getHeader()
    {
        if ($this->_header === array()) {
            foreach ($_SERVER as $name => $value) {
                if (strncmp($name, 'HTTP_', 5) === 0) {
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $this->_header[$name] = $value;
                }
            }
        }
    }

    /**
     * 获取多个参数
     * @param array $keys
     * @return array
     */
    public function getParams($keys = array())
    {
        $params = array();
        if (!empty($keys)) {
            foreach ($keys as $key) {
                $params[$key] = $this->_request($key);
            }
        } else {
            $params = $this->_params;
        }
        return $params;
    }

    /**
     * 获取当前的URL地址
     * @param bool|true $absolute 是否取全地址
     * @return string
     */
    public function getRequestUri($absolute = true)
    {
        $host = $this->server('HTTP_HOST');
        $host = str_replace(':8000', '', $host);
        $serverPort = $this->server('SERVER_PORT');
        $port = ($serverPort == 8000 || $serverPort == 80 || $serverPort == 443
            || $serverPort == 888) ? '' : ":{$serverPort}";
        $uri = $this->server('REQUEST_URI');
        $protocal = $serverPort == 443 ? 'https://' : 'http://';
        return $absolute ? $protocal . $host . $port . $uri : $uri;
    }

    /**
     * 获取上一次浏览uri
     */
    public function getReferUri()
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    }
}
