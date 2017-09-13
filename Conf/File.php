<?php
namespace Lib\Conf;


class File
{

    /**
     * 缓存
     * @var array|mixed
     */
    protected $_cache = array();

    /**
     * 配置文件
     * @var null
     */
    public $file = null;

    /**
     * File constructor.
     * @param $file
     * @throws \Exception
     */
    public function __construct($file)
    {

        if (!file_exists($file)) {
            throw new \Exception("文件{$file}不存在!");
        }

        $this->file = $file;

        //ini
        if (substr($this->file, -3) == 'ini') {
            $this->_cache = parse_ini_file($this->file, true);
        } else {
            throw new \Exception("文件类型不支持:{$this->file}");
        }

        //TODO:: xml 等其他

    }

    /**
     * 根据key获取
     * @param $key
     * @return null
     */
    public function get($key)
    {
        $args = func_get_args();
        unset($args[0]);
        $result = $this->_cache;
        foreach ($args as $key) {
            if (isset($result[$key])) {
                $result = $result[$key];
            } else {
                return null;
            }
        }
        return $result;
    }
}