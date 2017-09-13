<?php
namespace Lib\Db;

class Connection
{

    /**
     *
     * @var string The Data Source Name, or DSN, contains the information required to connect to the database.
     * @see http://wwwroot.php.net/manual/en/function.PDO-construct.php Note that if you're using GBK or BIG5 then it's highly recommended to
     *      update to PHP 5.3.6+ and to specify charset via DSN like
     *      'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
     */
    public $connectionString;

    /**
     *
     * @var string the username for establishing DB connection. Defaults to empty string.
     */
    public $username = '';

    /**
     *
     * @var string the password for establishing DB connection. Defaults to empty string.
     */
    public $password = '';

    /**
     *
     * @var boolean whether the database connection should be automatically established
     *      the component is being initialized. Defaults to true. Note, this property is only
     *      effective when the Lib_Db_Connection object is used as an application component.
     */
    public $autoConnect = true;

    /**
     *
     * @var string the charset used for database connection. The property is only used
     *      for MySQL and PostgreSQL databases. Defaults to null, meaning using default charset
     *      as specified by the database.
     *
     *      Note that if you're using GBK or BIG5 then it's highly recommended to
     *      update to PHP 5.3.6+ and to specify charset via DSN like
     *      'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
     */
    public $charset;

    /**
     *
     * @var boolean whether to turn on prepare emulation. Defaults to false, meaning PDO
     *      will use the native prepare support if available. For some databases (such as MySQL),
     *      this may need to be set true so that PDO can emulate the prepare support to bypass
     *      the buggy native prepare support. Note, this property is only effective for PHP 5.1.3 or above.
     *      The default value is null, which will not change the ATTR_EMULATE_PREPARES value of PDO.
     */
    public $emulatePrepare;

    /**
     *
     * @var boolean whether to log the values that are bound to a prepare SQL statement.
     *      Defaults to false. During development, you may consider setting this property to true
     *      so that parameter values bound to SQL statements are logged for debugging purpose.
     *      You should be aware that logging parameter values could be expensive and have significant
     *      impact on the performance of your application.
     */
    public $enableParamLogging = false;

    /**
     *
     * @var boolean whether to enable profiling the SQL statements being executed.
     *      Defaults to false. This should be mainly enabled and used during development
     *      to find out the bottleneck of SQL executions.
     */
    public $enableProfiling = false;

    /**
     *
     * @var string the default prefix for table names. Defaults to null, meaning no table prefix.
     *      By setting this property, any token like '{{tableName}}' in {@link Lib_Db_Command::text} will
     *      be replaced by 'prefixTableName', where 'prefix' refers to this property value.
     * @since 1.1.0
     */
    public $tablePrefix;

    /**
     *
     * @var array list of SQL statements that should be executed right after the DB connection is established.
     * @since 1.1.1
     */
    public $initSQLs;

    /**
     *
     * @var array mapping between PDO driver and schema class name.
     *      A schema class can be specified using path alias.
     */
    public $driverMap = array(
        'pgsql' => '\Lib\Db\Schema\Pgsql\Schema', // PostgreSQL
        'mysql' => '\Lib\Db\Schema\Mysql\Schema', // MySQL
        'oci' => '\Lib\Db\Schema\Oci\Schema', // Oracle driver
        'mssql'=>'\Lib\Db\Schema\Mssql\CMssqlSchema',    // Mssql driver on windows hosts
    );

    /**
     *
     * @var string Custom PDO wrapper class.
     * @since 1.1.8
     */
    public $pdoClass = 'PDO';

    private $_attributes = array();

    private $_active = false;

    /**
     * @var \PDO
     */
    private $_pdo;

    /**
     * @var Transaction
     */
    private $_transaction;

    private $_schema;

    /**
     * Constructor.
     * Note, the DB connection is not established when this connection
     * instance is created. Set {@link setActive active} property to true
     * to establish the connection.
     *
     * @param string $dsn
     *            The Data Source Name, or DSN, contains the information required to connect to the database.
     * @param string $username
     *            The user name for the DSN string.
     * @param string $password
     *            The password for the DSN string.
     * @see http://wwwroot.php.net/manual/en/function.PDO-construct.php
     */
    public function __construct($dsn = '', $username = '', $password = '')
    {
        $this->connectionString = $dsn;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Close the connection when serializing.
     *
     * @return array
     */
    public function __sleep()
    {
        $this->close();
        return array_keys(get_object_vars($this));
    }

    /**
     * Returns a list of available PDO drivers.
     *
     * @return array list of available PDO drivers
     * @see http://wwwroot.php.net/manual/en/function.PDO-getAvailableDrivers.php
     */
    public static function getAvailableDrivers()
    {
        return \PDO::getAvailableDrivers();
    }

    /**
     * Initializes the component.
     * This method is required by {@link IApplicationComponent} and is invoked by application
     * when the CDbConnection is used as an application component.
     * If you override this method, make sure to call the parent implementation
     * so that the component can be marked as initialized.
     */
    public function init()
    {
        if ($this->autoConnect)
            $this->setActive(true);
    }

    /**
     * Returns whether the DB connection is established.
     * @return boolean whether the DB connection is established
     */
    public function getActive()
    {
        return $this->_active;
    }

    /**
     * Open or close the DB connection.
     *
     * @param boolean $value whether to open or close DB connection
     */
    public function setActive($value)
    {
        if ($value != $this->_active) {
            if ($value)
                $this->open();
            else
                $this->close();
        }
    }

    /**
     * Opens DB connection if it is currently not
     */
    protected function open()
    {
        if ($this->_pdo === null) {
            if (empty($this->connectionString))
                throw new \PDOException('参数connectionString不能为空!');
            try {
//                writelog('application', 'Opening DB connection', 'system.db.Lib_Db_Connection');
                $this->_pdo = $this->createPdoInstance();
                $this->initConnection($this->_pdo);
                $this->_active = true;
            } catch (\PDOException $e) {
                throw new \PDOException('database connect error!');
            }
        }
    }

    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    protected function close()
    {
//        writelog('application', 'Closing DB connection', 'system.db.Lib_Db_Connection');
        $this->_pdo = null;
        $this->_active = false;
        $this->_schema = null;
    }

    /**
     * Creates the PDO instance.
     * When some functionalities are missing in the pdo driver, we may use
     * an adapter class to provides them.
     *
     * @return \PDO the pdo instance
     */
    protected function createPdoInstance()
    {
        $pdoClass = $this->pdoClass;
        if (($pos = strpos($this->connectionString, ':')) !== false) {
            $driver = strtolower(substr($this->connectionString, 0, $pos));
            if ($driver === 'mssql' || $driver === 'dblib' || $driver === 'sqlsrv')
                $pdoClass = '\Lib\Db\Schema\Mssql\PdoAdapter';
        }
        return new $pdoClass($this->connectionString, $this->username, $this->password, $this->_attributes);
    }

    /**
     * Initializes the open db connection.
     * This method is invoked right after the db connection is established.
     * The default implementation is to set the charset for MySQL and PostgreSQL database connections.
     *
     * @param \PDO $pdo
     *            the PDO instance
     */
    protected function initConnection($pdo)
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if ($this->emulatePrepare !== null && constant('PDO::ATTR_EMULATE_PREPARES'))
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $this->emulatePrepare);
        if ($this->charset !== null) {
            $driver = strtolower($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            if (in_array($driver, array(
                'pgsql',
                'mysql',
                'mysqli'
            )))
                $pdo->exec('SET NAMES ' . $pdo->quote($this->charset));
        }
        if ($this->initSQLs !== null) {
            foreach ($this->initSQLs as $sql)
                $pdo->exec($sql);
        }
    }

    /**
     * Returns the PDO instance.
     *
     * @return \PDO the PDO instance, null if the connection is not established yet
     */
    public function getPdoInstance()
    {
        return $this->_pdo;
    }

    /**
     * Creates a command for execution.
     *
     * @param mixed $query
     *            the DB query to be executed. This can be either a string representing a SQL statement,
     *            or an array representing different fragments of a SQL statement. Please refer to {@link Lib_Db_Command::__construct}
     *            for more details about how to pass an array as the query. If this parameter is not given,
     *            you will have to call query builder methods of {@link Lib_Db_Command} to build the DB query.
     * @return Command the DB command
     */
    public function createCommand($query = null)
    {
        $this->setActive(true);
        return new Command($this, $query);
    }

    /**
     * Returns the currently active transaction.
     *
     * @return Transaction the currently active transaction. Null if no active transaction.
     */
    public function getCurrentTransaction()
    {
        if ($this->_transaction !== null) {
            if ($this->_transaction->getActive())
                return $this->_transaction;
        }
        return null;
    }

    /**
     * Starts a transaction.
     *
     * @return Transaction the transaction initiated
     */
    public function beginTransaction()
    {
//        writelog('application', 'Starting transaction', 'system.db.Lib_Db_Connection');
        $this->setActive(true);
        $this->_pdo->beginTransaction();
        return $this->_transaction = new Transaction($this);
    }

    /**
     * @return Schema\Schema the database schema for the current connection
     */
    public function getSchema()
    {
        if ($this->_schema !== null)
            return $this->_schema;
        else {
            $driver = $this->getDriverName();
            if (isset($this->driverMap [$driver]))
                return $this->_schema = new $this->driverMap[$driver]($this);
            else
                throw new \PDOException(sprintf('数据库连接不支持读模式对%s类型数据库.', $driver));
        }
    }

    /**
     * Returns the SQL command builder for the current DB connection.
     *
     * @return Schema\CommandBuilder the command builder
     */
    public function getCommandBuilder()
    {
        return $this->getSchema()->getCommandBuilder();
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string $sequenceName
     *            name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @see http://wwwroot.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID($sequenceName = '')
    {
        $this->setActive(true);
        return $this->_pdo->lastInsertId($sequenceName);
    }

    /**
     * Quotes a string value for use in a query.
     *
     * @param string $str
     *            string to be quoted
     * @return string the properly quoted string
     * @see http://wwwroot.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue($str)
    {
        if (is_int($str) || is_float($str))
            return $str;

        $this->setActive(true);
        if (($value = $this->_pdo->quote($str)) !== false)
            return $value;
        else // the driver doesn't support quote (e.g. oci)
            return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     *
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteTableName($name)
    {
        return $this->getSchema()->quoteTableName($this->tablePrefix.$name);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     *
     * @param string $name
     *            column name
     * @return string the properly quoted column name
     */
    public function quoteColumnName($name)
    {
        return $this->getSchema()->quoteColumnName($name);
    }

    /**
     * Determines the PDO type for the specified PHP type.
     *
     * @param string $type
     *            The PHP type (obtained by gettype() call).
     * @return integer the corresponding PDO type
     */
    public function getPdoType($type)
    {
        static $map = array(
            'boolean' => \PDO::PARAM_BOOL,
            'integer' => \PDO::PARAM_INT,
            'string' => \PDO::PARAM_STR,
            'NULL' => \PDO::PARAM_NULL
        );
        return isset($map [$type]) ? $map [$type] : \PDO::PARAM_STR;
    }

    /**
     * Returns the case of the column names
     *
     * @return mixed the case of the column names
     * @see http://wwwroot.php.net/manual/en/pdo.setattribute.php
     */
    public function getColumnCase()
    {
        return $this->getAttribute(\PDO::ATTR_CASE);
    }

    /**
     * Sets the case of the column names.
     *
     * @param mixed $value the case of the column names
     * @see http://wwwroot.php.net/manual/en/pdo.setattribute.php
     */
    public function setColumnCase($value)
    {
        $this->setAttribute(\PDO::ATTR_CASE, $value);
    }

    /**
     * Returns how the null and empty strings are converted.
     *
     * @return mixed how the null and empty strings are converted
     * @see http://wwwroot.php.net/manual/en/pdo.setattribute.php
     */
    public function getNullConversion()
    {
        return $this->getAttribute(\PDO::ATTR_ORACLE_NULLS);
    }

    /**
     * Sets how the null and empty strings are converted.
     *
     * @param mixed $value
     *            how the null and empty strings are converted
     * @see http://wwwroot.php.net/manual/en/pdo.setattribute.php
     */
    public function setNullConversion($value)
    {
        $this->setAttribute(\PDO::ATTR_ORACLE_NULLS, $value);
    }

    /**
     * Returns whether creating or updating a DB record will be automatically committed.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @return boolean whether creating or updating a DB record will be automatically committed.
     */
    public function getAutoCommit()
    {
        return $this->getAttribute(\PDO::ATTR_AUTOCOMMIT);
    }

    /**
     * Sets whether creating or updating a DB record will be automatically committed.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @param boolean $value
     *            whether creating or updating a DB record will be automatically committed.
     */
    public function setAutoCommit($value)
    {
        $this->setAttribute(\PDO::ATTR_AUTOCOMMIT, $value);
    }

    /**
     * Returns whether the connection is persistent or not.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @return boolean whether the connection is persistent or not
     */
    public function getPersistent()
    {
        return $this->getAttribute(\PDO::ATTR_PERSISTENT);
    }

    /**
     * Sets whether the connection is persistent or not.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @param boolean $value whether the connection is persistent or not
     */


    /**
     * @param $value
     * @return array
     */
    public function setPersistent($value)
    {
        return $this->setAttribute(\PDO::ATTR_PERSISTENT, $value);
    }

    /**
     * Returns the name of the DB driver
     * @return string
     */
    public function getDriverName()
    {
        if (($pos = strpos($this->connectionString, ':')) !== false) {
            return strtolower(substr($this->connectionString, 0, $pos));
//        } else if ($this->_pdo instanceof PDO) {
//            return $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        }
        return null;
    }

    /**
     * Returns the version information of the DB driver.
     *
     * @return string the version information of the DB driver
     */
    public function getClientVersion()
    {
        return $this->getAttribute(\PDO::ATTR_CLIENT_VERSION);
    }

    /**
     * Returns the status of the connection.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @return string the status of the connection
     */
    public function getConnectionStatus()
    {
        return $this->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    }

    /**
     * Returns whether the connection performs data prefetching.
     *
     * @return boolean whether the connection performs data prefetching
     */
    public function getPrefetch()
    {
        return $this->getAttribute(\PDO::ATTR_PREFETCH);
    }

    /**
     * Returns the information of DBMS server.
     *
     * @return string the information of DBMS server
     */
    public function getServerInfo()
    {
        return $this->getAttribute(\PDO::ATTR_SERVER_INFO);
    }

    /**
     * Returns the version information of DBMS server.
     *
     * @return string the version information of DBMS server
     */
    public function getServerVersion()
    {
        return $this->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Returns the timeout settings for the connection.
     *
     * @return integer timeout settings for the connection
     */
    public function getTimeout()
    {
        return $this->getAttribute(\PDO::ATTR_TIMEOUT);
    }

    /**
     * Obtains a specific DB connection attribute information.
     *
     * @param integer $name
     *            the attribute to be queried
     * @return mixed the corresponding attribute information
     * @see http://wwwroot.php.net/manual/en/function.PDO-getAttribute.php
     */
    public function getAttribute($name)
    {
        $this->setActive(true);
        return $this->_pdo->getAttribute($name);
    }

    /**
     * Sets an attribute on the database connection.
     * @param integer $name the attribute to be set
     * @param mixed $value the attribute value
     * @see http://wwwroot.php.net/manual/en/function.PDO-setAttribute.php
     * @return array|bool
     */
    public function setAttribute($name, $value)
    {
        if ($this->_pdo instanceof \PDO)
            $this->_pdo->setAttribute($name, $value);
        else
            $this->_attributes [$name] = $value;
    }

    /**
     * Returns the attributes that are previously explicitly set for the DB connection.
     *
     * @return array attributes (name=>value) that are previously explicitly set for the DB connection.
     * @see setAttributes
     */
    public function getAttributes()
    {
        return $this->_attributes;
    }

    /**
     * Sets a set of attributes on the database connection.
     *
     * @param array $values
     *            attributes (name=>value) to be set.
     * @see setAttribute
     */
    public function setAttributes($values)
    {
        foreach ($values as $name => $value)
            $this->_attributes [$name] = $value;
    }
}
