<?php
namespace Lib;
class Dispatch
{
    /**
     * @var string
     */
    static $controllerName;

    /**
     * @var string
     */
    static $controllerClassName;

    /**
     * @var string
     */
    static $actionName;

    /**
     * @var string
     */
    static $actionMethodName;

    /**
     * @var View
     */
    private static $view;

    static function view()
    {
        return self::$view;
    }

    /**
     * @var \Lib\Session
     */
    private static $session;

    static function session()
    {
        return self::$session;
    }

    static function setSession($session)
    {
        self::$session = $session;
    }

    /**
     * 将URL中的Controller名称转换为Controller类名，不包含命名空间
     * @param string $controllerUrl
     * @return string
     */
    static function controllerName2Class($controllerUrl)
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $controllerUrl)));
    }

    /**
     * 将controller class name 转换为 url
     * @param string $controllerClassName
     * @return string
     */
    static function controllerName2Url($controllerClassName)
    {
        return ltrim(strtolower(preg_replace("/([A-Z])/", '-\1', $controllerClassName)), '-');
    }

    /**
     * 将URL中的Action名称转换为Action方法名称，不包含Action
     *
     * a=a_abc 会被转换为 A_Abc
     *
     * @param string $actionUrl
     * @return string
     */
    static function actionName2Method($actionUrl)
    {
        return lcfirst(str_replace('-', '', preg_replace_callback("/(_|-)([a-z])/", function ($matches) {
            return strtoupper($matches[0]);
        }, $actionUrl)));
    }

    /**
     * 将action method name 转换为 url
     * 大写字母会以 "-" 分隔，方法名称，以及类名包含 "_" 时，"_-" 会转换为 "_"
     *
     * @param string $actionMethodName
     * @return string
     */
    static function actionName2Url($actionMethodName)
    {
        return ltrim(strtolower(str_replace('_-', '_', preg_replace("/([A-Z])/", '-\1', $actionMethodName))), '-');
    }

    /**
     * 初始化controller action的 名称
     * Controller 和 Action 的类名在URL中大小写之间用 - 来分割
     */
    private static function initMVC()
    {
        /**
         * 脚本控制台
         * job console
         */
        if (self::isConsole()) {
            global $argv;
            $daemonName = trim($argv[2]);
            $scriptName = trim($argv[3]);
            if (!empty($daemonName) && !empty($scriptName)) {
                self::$controllerName = $daemonName;
                self::$controllerClassName = "\\Inc\\Daemon\\" . self::$controllerName;
                self::$actionName = $scriptName;
                self::$actionMethodName = self::$actionName . 'Script';
            } else {
                throw new Exception("argv error!please input -d [daemonName] [scriptName].");
            }
        } else {
            /**
             * TODO:以后若有静态化路径的获取方法
             * 获取参数中对应的Controller 和 对应页面处理的action
             */
            $c = 'main';
            if (isset($_REQUEST['c'])) {
                $c = $_REQUEST['c'];
            }
            self::$controllerName = self::controllerName2Class($c);
            self::$controllerClassName = "\\Inc\\Controller\\" . self::$controllerName;

            $a = 'index';
            if (isset($_REQUEST['a'])) {
                $a = $_REQUEST['a'];
            }
            self::$actionName = self::actionName2Method($a);
            self::$actionMethodName = self::$actionName . 'Action';
        }
    }

    static $httpHandler;

    static function setHttpHandler($httpHandler)
    {
        self::$httpHandler = $httpHandler;
    }

    private static $autoRendering = false;

    /**
     * @param boolean $autoRendering
     */
    static function setAutoRendering($autoRendering)
    {
        self::$autoRendering = $autoRendering;
    }

    static function setView($view, $tplPath, $viewConf)
    {
        self::$view = $view;
        self::$view->setTplPath($tplPath);
        self::$view->setViewConf($viewConf);
        self::$view->assign('controllerName', Dispatch::$controllerName);
        self::$view->assign('actionName', Dispatch::$actionName);
    }

    private static function httpHandler()
    {
        if (!empty(self::$httpHandler)) {
            $httpHandler = self::$httpHandler;
            $httpHandler();
        } else {
            if (!class_exists(self::$controllerClassName)) {
                throw new \Lib\Exception("Controller '" . self::$controllerClassName . "' not exist!");
            }

            if (!method_exists(self::$controllerClassName, self::$actionMethodName)) {
                throw new \Lib\Exception("Controller Action '" . self::$controllerClassName . "' '" . self::$actionMethodName . "' not exist!");
            }

            $controller = new self::$controllerClassName();
            $controller->{self::$actionMethodName}();

            /**
             * 自动显示模板文件
             */
            if (self::$autoRendering) {
                $controller->view->display(self::$controllerName . DIRECTORY_SEPARATOR . self::$actionName);
            }
        }
    }

    private static function consoleHandler()
    {
        $daemon = new self::$controllerClassName();
        $daemon->{self::$actionMethodName}();
    }

    public static function isConsole()
    {
        global $argv;
        if ($argv[1] == '-d' && PHP_SAPI == 'cli') {
            return true;
        }
        return false;
    }

    public static function run()
    {
        self::initMVC();

        if (self::isConsole()) {
            Dispatch::consoleHandler();
        } else {
            Dispatch::httpHandler();
        }
    }
}