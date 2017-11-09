<?php

namespace Lib;

/**
 * Databases operation class
 *
 * @author Richard Zheng <tenleaves@gmail.com>
 *
 */
class Db
{

    /**
     * Database link collection
     *
     * @var array
     */
    private static $adapters;

    /**
     * Database link name
     *
     * @var string
     */
    protected $adapterName = 'default';

    /**
     * 数据库链接
     * @var Db\Connection
     */
    protected $db;

    /**
     * 数据库从库链接
     * @var Db\Connection
     */
    protected $dbSlave;

    /**
     * 表名，不带前缀
     * @var string
     */
    public $tableName;

    /**
     * 表主键
     * @var string
     */
    public $tablePrimary;

    /**
     * 当前句柄资源
     */
    public static $_Handle = [];

    /**
     * 获取当前类句柄
     * @param $adapterName
     * @param null $tableName
     * @param null $tablePrimary
     * @return \Lib\Db
     */
    public static function getInstance($adapterName, $tableName = NULL, $tablePrimary = NULL)
    {

        if (self::$_Handle[$adapterName] === NULL) {
            self::$_Handle[$adapterName] = new self($tableName, $tablePrimary, $adapterName);
        }
        return self::$_Handle[$adapterName];
    }


    /**
     * @param string $tableName
     * @param string $tablePrimary
     * @param string $adapterName
     */
    public function __construct($tableName = NULL, $tablePrimary = NULL, $adapterName = NULL)
    {
        if (!empty($adapterName)) {
            $this->adapterName = $adapterName;
        }

        if (!empty($tableName) && empty($this->tableName)) {
            $this->tableName = $tableName;
        }
        if (!empty($tablePrimary) && empty($this->tablePrimary)) {
            $this->tablePrimary = $tablePrimary;
        }

        $this->connect();

        $this->__init();
    }

    /**
     * behalf __construct
     */
    public function __init()
    {
    }

    /**
     * connect database host
     * @param bool $slave 是否链接从库
     */
    private function connect($slave = false)
    {
        if ($slave) {
            if (!empty(self::$adapters[$this->adapterName]['slave']['connId'])) {
                $this->dbSlave = self::$adapters[$this->adapterName]['slave']['connId'];
            } else {
                if (!empty(self::$adapters[$this->adapterName]['conf']['slave'])) {
                    $conf = self::$adapters[$this->adapterName]['conf']['slave'];
                    $this->dbSlave = new \Lib\Db\Connection(
                        sprintf('mysql:host=%s;port=%s;dbname=%s;', $conf['host'], $conf['port'], $conf['dbname']),
                        $conf['username'],
                        $conf['password']
                    );
                    if (!empty($conf['charset'])) {
                        $this->dbSlave->charset = $conf['charset'];
                    }
                } else {
                    throw new \PDOException("Adapter {$this->adapterName} slave configuration error!");
                }
            }
        } else {
            if (!empty(self::$adapters[$this->adapterName]['connId'])) {
                $this->db = self::$adapters[$this->adapterName]['connId'];
            } else {
                if (!empty(self::$adapters[$this->adapterName]['conf'])) {
                    $conf = self::$adapters[$this->adapterName]['conf'];
                    $this->db = new \Lib\Db\Connection(
                        sprintf('mysql:host=%s;port=%s;dbname=%s;', $conf['host'], $conf['port'], $conf['dbname']),
                        $conf['username'],
                        $conf['password']
                    );

                    if (isset($conf['prefix'])) {
                        $this->db->tablePrefix = $conf['prefix'];
                    }

                    if (!empty($conf['charset']) && $conf['charset']) {
                        $this->db->charset = $conf['charset'];
                    }

                    self::$adapters[$this->adapterName]['connId'] = $this->db;
                } else {
                    throw new \PDOException("Adapter {$this->adapterName} configuration error!");
                }
            }
        }
    }

    /**
     * set adapters
     *
     * @param string $adapterName
     * @param string $adapter
     * @param string $host
     * @param int $port
     * @param string $dbname
     * @param string $username
     * @param string $password
     * @param string $charset
     * @param string $prefix
     * @access
     *
     */
    static function factory($adapterName, $adapter, $host, $port, $dbname, $username, $password, $charset, $prefix = null)
    {
        self::$adapters[$adapterName]['conf']['host'] = $host;
        self::$adapters[$adapterName]['conf']['port'] = $port;
        self::$adapters[$adapterName]['conf']['adapter'] = $adapter;
        self::$adapters[$adapterName]['conf']['dbname'] = $dbname;
        self::$adapters[$adapterName]['conf']['username'] = $username;
        self::$adapters[$adapterName]['conf']['password'] = $password;
        self::$adapters[$adapterName]['conf']['charset'] = $charset;
        self::$adapters[$adapterName]['conf']['prefix'] = $prefix;
    }

    static function factorySlave($adapterName, $host, $port, $dbname, $username, $password, $charset)
    {
        if (empty(self::$adapters[$adapterName]['conf'])) {
            throw new \Exception("There isn't master $adapterName configuration...");
        }
        self::$adapters[$adapterName]['conf']['slave']['host'] = $host;
        self::$adapters[$adapterName]['conf']['slave']['port'] = $port;
        self::$adapters[$adapterName]['conf']['slave']['dbname'] = $dbname;
        self::$adapters[$adapterName]['conf']['slave']['username'] = $username;
        self::$adapters[$adapterName]['conf']['slave']['password'] = $password;
        self::$adapters[$adapterName]['conf']['slave']['charset'] = $charset;
    }

    /**
     * 添删改查用 query builder
     * @return Db\Command
     * @throws \Exception
     */
    public function queryBuilder()
    {
        $command = $this->db->createCommand();
        $command->setTableInfo($this->tableName, $this->tablePrimary);
        return $command;
    }

    /**
     * 从库 添删改查用 query builder
     * @return Db\Command
     * @throws \Exception
     */
    public function queryBuilderOfSlave()
    {
        if (empty(self::$adapters[$this->adapterName]['conf']['slave'])) {
            throw new \Exception("There isn't slave configuration...");
        }
        $this->connect(true);
        $command = $this->dbSlave->createCommand();
        $command->setTableInfo($this->tableName, $this->tablePrimary);
        return $command;
    }

    /**
     * 运行sql语句
     * @param $sql
     * @param bool|true $autoTablePrefix 是否自动添加表前缀
     * @return Db\Command
     */
    public function query($sql, $autoTablePrefix = true)
    {
        if ($autoTablePrefix === true) {
            $sql = preg_replace('/(from)(\s+)(`|)?([a-zA-Z_]+)(`|)(\s+|)/i', '\1\2\3' . $this->db->tablePrefix . '\4\5\6', $sql);
            $sql = preg_replace('/(join)(\s+)(`|)?([a-zA-Z_]+)(`|)(\s+|)/i', '\1\2\3' . $this->db->tablePrefix . '\4\5\6', $sql);
        }

        self::_operationLog($this->adapterName, $sql);
        return $this->db->createCommand($sql);
    }

    /**
     * 表结构操作相关的 query builder
     * @return \Lib\Db\Schema\CommandBuilder
     */
    public function criteriaBuilder()
    {
        return $this->db->getCommandBuilder();
    }

    public function db()
    {
        return $this->db;
    }

    public static function _operationLog($adapterName, $sql)
    {
        self::$adapters[$adapterName]['log'][] = $sql;
    }

    public function _getOperationLog()
    {
        return self::$adapters[$this->adapterName]['log'];
    }

    public function __destruct()
    {
        unset($this->pdo);
        foreach (self::$adapters as $k => $v) {
            if (!empty($v['connId'])) {
                unset(self::$adapters[$k]['connId']);
            }
        }
    }
}

?>