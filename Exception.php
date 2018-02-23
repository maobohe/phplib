<?php
namespace Lib;

class Exception extends \Exception
{

    static $error_html_charset = 'utf-8';

    static $display_errors = false;

    /**
     *
     * @param $isShow
     */
    public static function setDisplayErrors($isShow)
    {
        self::$display_errors = $isShow;
    }

    public static function exceptionHandler($e)
    {
        if (defined('HPHP_VERSION')) {

        } else {
            error_log($e->__toString());
        }

//        if (ini_get('display_errors')) {
        if (self::$display_errors) {
            // console
            if (PHP_SAPI == 'cli') {
                print_r($e->getMessage() . "\n");
                print_r($e->getTraceAsString());
            } else {
                print_r('<pre>');
                $debugBackTrace = debug_backtrace();
                print_r($debugBackTrace[0]['args'][0]->__toString());
            }
        }
    }

    /**
     * HHVM 3.1.2 error handler bug fix
     * PHP7 don't need this
     * @param $errorNumber
     * @param $message
     * @param $errfile
     * @param $errline
     */
    public static function errorHandler($errorNumber, $message, $errfile, $errline)
    {
//        $display_errors = ini_get("display_errors");
        $e_levels = ini_get("error_reporting");

        if (self::$display_errors) {
            if ($errorNumber >= $e_levels) {
                switch ($errorNumber) {
                    case E_PARSE:
                    case E_COMPILE_ERROR:
                    case E_CORE_ERROR:
                    case E_ERROR:
                    case 16777217:
                        $errorLevel = 'Fatal error';
                        break;
                    case E_WARNING: // 2 //
                        $errorLevel = 'E_WARNING';
                        break;
                    case E_NOTICE: // 8 //
                        $errorLevel = 'E_NOTICE';
                        break;
                    case E_CORE_WARNING: // 32 //
                        $errorLevel = 'E_CORE_WARNING';
                        break;
                    case E_COMPILE_WARNING: // 128 //
                        $errorLevel = 'E_COMPILE_WARNING';
                        break;
                    case E_USER_ERROR: // 256 //
                        $errorLevel = 'E_USER_ERROR';
                        break;
                    case E_USER_WARNING: // 512 //
                        $errorLevel = 'E_USER_WARNING';
                        break;
                    case E_USER_NOTICE: // 1024 //
                        $errorLevel = 'E_USER_NOTICE';
                        break;
                    case E_STRICT: // 2048 //
                        $errorLevel = 'E_STRICT';
                        break;
                    case E_RECOVERABLE_ERROR: // 4096 //
                        $errorLevel = 'E_RECOVERABLE_ERROR';
                        break;
                    case E_DEPRECATED: // 8192 //
                        $errorLevel = 'E_DEPRECATED';
                        break;
                    case E_USER_DEPRECATED: // 16384 //
                        $errorLevel = 'E_USER_DEPRECATED';
                        break;
                    default :
                        $errorLevel = 'Undefined error(' . $errorNumber . ')';
                }

                if (PHP_SAPI == 'cli') {
                    echo "\n" . $errorLevel . ': ' . $message . ' in ' . $errfile . ' on line ' . $errline . "\n";
                } else {
                    echo "\n<b>" . $errorLevel . '</b>: ' . $message . ' in <b>' . $errfile . '</b> on line <b>' . $errline . "</b>\n";
                }
            }
        }
    }

}