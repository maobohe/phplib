<?php
namespace Lib\Db;

class Command
{

    /**
     *
     * @var array the parameters (name=>value) to be bound to the current query.
     */
    public $params = array();

    private $_connection;

    private $_text;

    /**
     *
     * @var \PDOStatement
     */
    private $_statement;

    private $_paramLog = array();

    private $_query;

    private $_fetchMode = array(
        \PDO::FETCH_ASSOC
    );

    /**
     * Constructor.
     *
     * @param Connection $connection the database connection
     * @param mixed $query the DB query to be executed. This can be either
     *        a string representing a SQL statement, or an array whose name-value pairs
     *        will be used to set the corresponding properties of the created command object.
     *
     *        For example, you can pass in either <code>'SELECT * FROM tbl_user'</code>
     *        or <code>array('select'=>'*', 'from'=>'tbl_user')</code>. They are equivalent
     *        in terms of the final query result.
     *
     *        When passing the query as an array, the following properties are commonly set:
     *        {@link select}, {@link distinct}, {@link from}, {@link where}, {@link join},
     *        {@link group}, {@link having}, {@link order}, {@link limit}, {@link offset} and
     *        {@link union}. Please refer to the setter of each of these properties for details
     *        about valid property values. This feature has been available since version 1.1.6.
     *
     *        Since 1.1.7 it is possible to use a specific mode of data fetching by setting
     *        {@link setFetchMode FetchMode}. See {@link http://wwwroot.php.net/manual/en/function.PDOStatement-setFetchMode.php}
     *        for more details.
     */
    public function __construct(Connection $connection, $query = null)
    {
        $this->_connection = $connection;
        if (is_array($query)) {
            foreach ($query as $name => $value)
                $this->$name = $value;
        } else
            $this->setText($query);
    }

    /**
     * Set the statement to null when serializing.
     *
     * @return array
     */
    public function __sleep()
    {
        $this->_statement = null;
        return array_keys(get_object_vars($this));
    }

    /**
     * Set the default fetch mode for this statement
     *
     * @param mixed $mode fetch mode
     * @return Command
     * @see http://wwwroot.php.net/manual/en/function.PDOStatement-setFetchMode.php
     */
    public function setFetchMode($mode)
    {
        $params = func_get_args();
        $this->_fetchMode = $params;
        return $this;
    }

    /**
     * Cleans up the command and prepares for building a new query.
     * This method is mainly used when a command object is being reused
     * multiple times for building different queries.
     * Calling this method will clean up all internal states of the command object.
     *
     * @return Command this command instance
     */
    public function reset()
    {
        $this->_text = null;
        $this->_query = null;
        $this->_statement = null;
        $this->_paramLog = array();
        $this->params = array();
        return $this;
    }

    /**
     *
     * @return string the SQL statement to be executed
     */
    public function getText()
    {
        if ($this->_text == '' && !empty($this->_query))
            $this->setText($this->buildQuery($this->_query));
        return $this->_text;
    }

    /**
     * Specifies the SQL statement to be executed.
     * Any previous execution will be terminated or cancel.
     *
     * @param string $value the SQL statement to be executed
     * @return Command this command instance
     */
    public function setText($value)
    {
//        if($this->_connection->tablePrefix!==null && $value!=''){
//            $this->_text=preg_replace('/{{(.*?)}}/',$this->_connection->tablePrefix.'\1',$value);
//        }
//        else{
        $this->_text = $value;
//        }
        $this->cancel();
        return $this;
    }

    /**
     *
     * @return Connection the connection associated with this command
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     *
     * @return \PDOStatement the underlying PDOStatement for this command
     *         It could be null if the statement is not prepared yet.
     */
    public function getPdoStatement()
    {
        return $this->_statement;
    }

    /**
     * Prepares the SQL statement to be executed.
     * For complex SQL statement that is to be executed multiple times,
     * this may improve performance.
     * For SQL statement with binding parameters, this method is invoked
     * automatically.
     */
    public function prepare()
    {
        if ($this->_statement == null) {
            // try {
            $this->_statement = $this->getConnection()->getPdoInstance()->prepare($this->getText());
            $this->_paramLog = array();
            // } catch (Exception $e) {
            // error_log($e->getTraceAsString());
            // writelog('error', 'Error in preparing SQL: ' . $this->getText(), CLogger::LEVEL_ERROR, 'system.db.Lib_Db_Command');
            // $errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
            // throw new PDOException(sprintf('预处理sql语句失败: %s', $e->getMessage()), (int)$e->getCode(), $errorInfo);
            // }
        }
    }

    /**
     * Cancels the execution of the SQL statement.
     */
    public function cancel()
    {
        $this->_statement = null;
    }

    /**
     * Binds a parameter to the SQL statement to be executed.
     *
     * @param mixed $name Parameter identifier. For a prepared statement
     *        using named placeholders, this will be a parameter name of
     *        the form :name. For a prepared statement using question mark
     *        placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value Name of the PHP variable to bind to the SQL statement parameter
     * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @param integer $length length of the data type
     * @param mixed $driverOptions the driver-specific options (this is available since version 1.1.6)
     * @return Command the current command being executed
     * @see http://wwwroot.php.net/manual/en/function.PDOStatement-bindParam.php
     */
    public function bindParam($name, &$value, $dataType = null, $length = null, $driverOptions = null)
    {
        $this->prepare();
        if ($dataType === null)
            $this->_statement->bindParam($name, $value, $this->_connection->getPdoType(gettype($value)));
        else if ($length === null)
            $this->_statement->bindParam($name, $value, $dataType);
        else if ($driverOptions === null)
            $this->_statement->bindParam($name, $value, $dataType, $length);
        else
            $this->_statement->bindParam($name, $value, $dataType, $length, $driverOptions);
        $this->_paramLog[$name] = &$value;
        return $this;
    }

    /**
     * Binds a value to a parameter.
     *
     * @param mixed $name Parameter identifier. For a prepared statement
     *        using named placeholders, this will be a parameter name of
     *        the form :name. For a prepared statement using question mark
     *        placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value The value to bind to the parameter
     * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @return Command the current command being executed
     * @see http://wwwroot.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($name, $value, $dataType = null)
    {
        $this->prepare();
        if ($dataType === null)
            $this->_statement->bindValue($name, $value, $this->_connection->getPdoType(gettype($value)));
        else
            $this->_statement->bindValue($name, $value, $dataType);
        $this->_paramLog[$name] = $value;
        return $this;
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to {@link bindValue} except that it binds multiple values.
     * Note that the SQL data type of each value is determined by its PHP type.
     *
     * @param array $values the values to be bound. This must be given in terms of an associative
     *        array with array keys being the parameter names, and array values the corresponding parameter values.
     *        For example, <code>array(':name'=>'John', ':age'=>25)</code>.
     * @return Command the current command being executed
     */
    public function bindValues($values)
    {
        $this->prepare();
        foreach ($values as $name => $value) {
            $this->_statement->bindValue($name, $value, $this->_connection->getPdoType(gettype($value)));
            $this->_paramLog[$name] = $value;
        }
        return $this;
    }

    /**
     * Executes the SQL statement.
     * This method is meant only for executing non-query SQL statement.
     * No result set will be returned.
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *        to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *        them in this way can improve the performance. Note that if you pass parameters in this way,
     *        you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *        binding methods and the input parameters this way can improve the performance.
     * @return integer number of rows affected by the execution.
     * @throws \PDOException execution failed
     */
    public function execute($params = array())
    {
        if ($this->_connection->enableParamLogging && ($pars = array_merge($this->_paramLog, $params)) !== array()) {
            $p = array();
            foreach ($pars as $name => $value)
                $p[$name] = $name . '=' . var_export($value, true);
            $par = '. Bound with ' . implode(', ', $p);
        } else
            $par = '';

        $this->prepare();
        if ($params === array())
            $this->_statement->execute();
        else
            $this->_statement->execute($params);
        $n = $this->_statement->rowCount();
        return $n;
    }

    /**
     * Executes the SQL statement and returns query result.
     * This method is for executing an SQL query that returns result set.
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *        to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *        them in this way can improve the performance. Note that if you pass parameters in this way,
     *        you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *        binding methods and the input parameters this way can improve the performance.
     * @return DataReader the reader object for fetching the query result
     */
    public function query($params = array())
    {
        return $this->queryInternal('', 0, $params);
    }

    /**
     * Executes the SQL statement and returns all rows.
     *
     * @param boolean $fetchAssociative whether each row should be returned as an associated array with
     *        column names as the keys or the array keys are column indexes (0-based).
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *        to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *        them in this way can improve the performance. Note that if you pass parameters in this way,
     *        you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *        binding methods and the input parameters this way can improve the performance.
     * @return array all rows of the query result. Each array element is an array representing a row.
     *         An empty array is returned if the query results in nothing.
     */
    public function queryAll($fetchAssociative = true, $params = array())
    {
        return $this->queryInternal('fetchAll', $fetchAssociative ? $this->_fetchMode : \PDO::FETCH_NUM, $params);
    }

    /**
     * Executes the SQL statement and returns the first row of the result.
     * This is a convenient method of {@link query} when only the first row of data is needed.
     *
     * @param boolean $fetchAssociative whether the row should be returned as an associated array with
     *        column names as the keys or the array keys are column indexes (0-based).
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *        to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *        them in this way can improve the performance. Note that if you pass parameters in this way,
     *        you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *        binding methods and the input parameters this way can improve the performance.
     * @return mixed the first row (in terms of an array) of the query result, false if no result.
     */
    public function queryRow($fetchAssociative = true, $params = array())
    {
        return $this->queryInternal('fetch', $fetchAssociative ? $this->_fetchMode : \PDO::FETCH_NUM, $params);
    }

    /**
     * Executes the SQL statement and returns the value of the first column in the first row of data.
     * This is a convenient method of {@link query} when only a single scalar
     * value is needed (e.g. obtaining the count of the records).
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *        to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *        them in this way can improve the performance. Note that if you pass parameters in this way,
     *        you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *        binding methods and the input parameters this way can improve the performance.
     * @return mixed the value of the first column in the first row of the query result. False is returned if there is no value.
     */
    public function queryScalar($params = array())
    {
        $result = $this->queryInternal('fetchColumn', 0, $params);
        if (is_resource($result) && get_resource_type($result) === 'stream')
            return stream_get_contents($result);
        else
            return $result;
    }

    /**
     * Executes the SQL statement and returns the first column of the result.
     * This is a convenient method of {@link query} when only the first column of data is needed.
     * Note, the column returned will contain the first element in each row of result.
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *        to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *        them in this way can improve the performance. Note that if you pass parameters in this way,
     *        you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *        binding methods and the input parameters this way can improve the performance.
     * @return array the first column of the query result. Empty array if no result.
     */
    public function queryColumn($params = array())
    {
        return $this->queryInternal('fetchAll', \PDO::FETCH_COLUMN, $params);
    }

    /**
     *
     * @param string $method method of PDOStatement to be called
     * @param mixed $mode parameters to be passed to the method
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *        to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *        them in this way can improve the performance. Note that you pass parameters in this way,
     *        you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *        binding methods and the input parameters this way can improve the performance.
     * @return mixed the method execution result
     */
    private function queryInternal($method, $mode, $params = array())
    {
        $params = array_merge($this->params, $params);

        if ($this->_connection->enableParamLogging && ($pars = array_merge($this->_paramLog, $params)) !== array()) {
            $p = array();
            foreach ($pars as $name => $value)
                $p[$name] = $name . '=' . var_export($value, true);
            $par = '. Bound with ' . implode(', ', $p);
        } else
            $par = '';

        $this->prepare();
        if ($params === array())
            $this->_statement->execute();
        else
            $this->_statement->execute($params);

        if ($method === '')
            $result = new DataReader($this);
        else {
            $mode = ( array )$mode;
            $result = call_user_func_array(array(
                $this->_statement,
                $method
            ), $mode);
            $this->_statement->closeCursor();
        }
        return $result;
    }

    /**
     * Builds a SQL SELECT statement from the given queryScalar specification.
     *
     * @param array $query the query specification in name-value pairs. The following
     *        query options are supported: {@link select}, {@link distinct}, {@link from},
     *        {@link where}, {@link join}, {@link group}, {@link having}, {@link order},
     *        {@link limit}, {@link offset} and {@link union}.
     * @return string the SQL statement
     */
    public function buildQuery($query)
    {
        $sql = isset($query['distinct']) && $query['distinct'] ? 'SELECT DISTINCT' : 'SELECT';
        $sql .= ' ' . (isset($query['select']) ? $query['select'] : '*');

        if (isset($query['from']))
            $sql .= "\nFROM " . $query['from'];
        else
            throw new \PDOException('这个查询必须包含from');

        if (isset($query['join']))
            $sql .= "\n" . (is_array($query['join']) ? implode("\n", $query['join']) : $query['join']);

        if (isset($query['where']))
            $sql .= "\nWHERE " . $query['where'];

        if (isset($query['group']))
            $sql .= "\nGROUP BY " . $query['group'];

        if (isset($query['having']))
            $sql .= "\nHAVING " . $query['having'];

        if (isset($query['order']))
            $sql .= "\nORDER BY " . $query['order'];

        $limit = isset($query['limit']) ? ( int )$query['limit'] : -1;
        $offset = isset($query['offset']) ? ( int )$query['offset'] : -1;
        if ($limit >= 0 || $offset > 0)
            $sql = $this->_connection->getCommandBuilder()->applyLimit($sql, $limit, $offset);

        if (isset($query['union']))
            $sql .= "\nUNION (\n" . (is_array($query['union']) ? implode("\n) UNION (\n", $query['union']) : $query['union']) . ')';

        return $sql;
    }

    /**
     * Sets the SELECT part of the query.
     *
     * @param mixed $columns the columns to be selected. Defaults to '*', meaning all columns.
     *        Columns can be specified in either a string (e.g. "id, name") or an array (e.g. array('id', 'name')).
     *        Columns can contain table prefixes (e.g. "tbl_user.id") and/or column aliases (e.g. "tbl_user.id AS user_id").
     *        The method will automatically quote the column names unless a column contains some parenthesis
     *        (which means the column contains a DB expression).
     * @param string $option additional option that should be appended to the 'SELECT' keyword. For example,
     *        in MySQL, the option 'SQL_CALC_FOUND_ROWS' can be used. This parameter is supported since version 1.1.8.
     * @return Command the command object itself
     */
    public function select($columns = '*', $option = '')
    {
        if (is_string($columns) && strpos($columns, '(') !== false)
            $this->_query['select'] = $columns;
        else {
            if (!is_array($columns))
                $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($columns as $i => $column) {
                if (is_object($column))
                    $columns[$i] = ( string )$column;
                else if (strpos($column, '(') === false) {
                    if (preg_match('/^(.*?)(?i:\s+as\s+|\s+)(.*)$/', $column, $matches))
                        $columns[$i] = $this->_connection->quoteColumnName($matches[1]) . ' AS ' . $this->_connection->quoteColumnName($matches[2]);
                    else
                        $columns[$i] = $this->_connection->quoteColumnName($column);
                }
            }
            $this->_query['select'] = implode(', ', $columns);
        }
        if ($option != '')
            $this->_query['select'] = $option . ' ' . $this->_query['select'];
        return $this;
    }

    /**
     * @param string $columns
     * @return Command
     */
    public function selectCount($columns = null)
    {
        if (empty($columns)) {
            $columns = '*';
        }
        return $this->select('count(' . $columns . ')');
    }

    /**
     * Returns the SELECT part in the query.
     *
     * @return string the SELECT part (without 'SELECT') in the query.
     */
    public function getSelect()
    {
        return isset($this->_query['select']) ? $this->_query['select'] : '';
    }

    /**
     * Sets the SELECT part in the query.
     *
     * @param mixed $value the data to be selected. Please refer to {@link select()} for details
     *        on how to specify this parameter.
     */
    public function setSelect($value)
    {
        $this->select($value);
    }

    /**
     * Sets the SELECT part of the query with the DISTINCT flag turned on.
     * This is the same as {@link select} except that the DISTINCT flag is turned on.
     *
     * @param mixed $columns the columns to be selected. See {@link select} for more details.
     * @return Command the command object itself
     */
    public function selectDistinct($columns = '*')
    {
        $this->_query['distinct'] = true;
        return $this->select($columns);
    }

    /**
     * Returns a value indicating whether SELECT DISTINCT should be used.
     *
     * @return boolean a value indicating whether SELECT DISTINCT should be used.
     */
    public function getDistinct()
    {
        return isset($this->_query['distinct']) ? $this->_query['distinct'] : false;
    }

    /**
     * Sets a value indicating whether SELECT DISTINCT should be used.
     *
     * @param boolean $value a value indicating whether SELECT DISTINCT should be used.
     */
    public function setDistinct($value)
    {
        $this->_query['distinct'] = $value;
    }

    /**
     * Sets the FROM part of the query.
     *
     * @param mixed $tables the table(s) to be selected from. This can be either a string (e.g. 'tbl_user')
     *        or an array (e.g. array('tbl_user', 'tbl_profile')) specifying one or several table names.
     *        Table names can contain schema prefixes (e.g. 'public.tbl_user') and/or table aliases (e.g. 'tbl_user u').
     *        The method will automatically quote the table names unless it contains some parenthesis
     *        (which means the table is given as a sub-query or DB expression).
     * @return Command the command object itself
     */
    public function from($tables)
    {
        if (is_string($tables) && strpos($tables, '(') !== false)
            $this->_query['from'] = $tables;
        else {
            if (!is_array($tables))
                $tables = preg_split('/\s*,\s*/', trim($tables), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($tables as $i => $table) {
                if (strpos($table, '(') === false) {
                    if (preg_match('/^(.*?)(?i:\s+as\s+|\s+)(.*)$/', $table, $matches)) // with alias
                        $tables[$i] = $this->_connection->quoteTableName($matches[1]) . ' ' . $this->_connection->quoteTableName($matches[2]);
                    else
                        $tables[$i] = $this->_connection->quoteTableName($table);
                }
            }
            $this->_query['from'] = implode(', ', $tables);
        }
        return $this;
    }

    /**
     * Returns the FROM part in the query.
     *
     * @return string the FROM part (without 'FROM' ) in the query.
     */
    public function getFrom()
    {
        return isset($this->_query['from']) ? $this->_query['from'] : '';
    }

    /**
     * Sets the FROM part in the query.
     *
     * @param mixed $value the tables to be selected from. Please refer to {@link from()} for details
     *        on how to specify this parameter.
     */
    public function setFrom($value)
    {
        $this->from($value);
    }

    /**
     * Sets the WHERE part of the query.
     *
     * The method requires a $conditions parameter, and optionally a $params parameter
     * specifying the values to be bound to the query.
     *
     * The $conditions parameter should be either a string (e.g. 'id=1') or an array.
     * If the latter, it must be of the format <code>array(operator, operand1, operand2, ...)</code>,
     * where the operator can be one of the followings, and the possible operands depend on the corresponding
     * operator:
     * <ul>
     * <li><code>and</code>: the operands should be concatenated together using AND. For example,
     * array('and', 'id=1', 'id=2') will generate 'id=1 AND id=2'. If an operand is an array,
     * it will be converted into a string using the same rules described here. For example,
     * array('and', 'type=1', array('or', 'id=1', 'id=2')) will generate 'type=1 AND (id=1 OR id=2)'.
     * The method will NOT do any quoting or escaping.</li>
     * <li><code>or</code>: similar as the <code>and</code> operator except that the operands are concatenated using OR.</li>
     * <li><code>in</code>: operand 1 should be a column or DB expression, and operand 2 be an array representing
     * the range of the values that the column or DB expression should be in. For example,
     * array('in', 'id', array(1,2,3)) will generate 'id IN (1,2,3)'.
     * The method will properly quote the column name and escape values in the range.</li>
     * <li><code>not in</code>: similar as the <code>in</code> operator except that IN is replaced with NOT IN in the generated condition.</li>
     * <li><code>like</code>: operand 1 should be a column or DB expression, and operand 2 be a string or an array representing
     * the values that the column or DB expression should be like.
     * For example, array('like', 'name', '%tester%') will generate "name LIKE '%tester%'".
     * When the value range is given as an array, multiple LIKE predicates will be generated and concatenated using AND.
     * For example, array('like', 'name', array('%test%', '%sample%')) will generate
     * "name LIKE '%test%' AND name LIKE '%sample%'".
     * The method will properly quote the column name and escape values in the range.</li>
     * <li><code>not like</code>: similar as the <code>like</code> operator except that LIKE is replaced with NOT LIKE in the generated condition.</li>
     * <li><code>or like</code>: similar as the <code>like</code> operator except that OR is used to concatenated the LIKE predicates.</li>
     * <li><code>or not like</code>: similar as the <code>not like</code> operator except that OR is used to concatenated the NOT LIKE predicates.</li>
     * </ul>
     *
     * @param mixed $conditions the conditions that should be put in the WHERE part.
     * @param array $params the parameters (name=>value) to be bound to the query
     * @return Command the command object itself
     */
    public function where($conditions, $params = array())
    {
        if (!empty($conditions)) {
            $this->_query['where'] = $this->processConditions($conditions);
            foreach ($params as $name => $value)
                $this->params[$name] = $value;
        }
        return $this;
    }

    /**
     * Returns the WHERE part in the query.
     *
     * @return string the WHERE part (without 'WHERE' ) in the query.
     */
    public function getWhere()
    {
        return isset($this->_query['where']) ? $this->_query['where'] : '';
    }

    /**
     * Sets the WHERE part in the query.
     *
     * @param mixed $value the where part. Please refer to {@link where()} for details
     *        on how to specify this parameter.
     */
    public function setWhere($value)
    {
        $this->where($value);
    }

    /**
     * Appends an INNER JOIN part to the query.
     *
     * @param string $table the table to be joined.
     *        Table name can contain schema prefix (e.g. 'public.tbl_user') and/or table alias (e.g. 'tbl_user u').
     *        The method will automatically quote the table name unless it contains some parenthesis
     *        (which means the table is given as a sub-query or DB expression).
     * @param mixed $conditions the join condition that should appear in the ON part.
     *        Please refer to {@link where} on how to specify conditions.
     * @param array $params the parameters (name=>value) to be bound to the query
     * @return Command the command object itself
     */
    public function join($table, $conditions, $params = array())
    {
        return $this->joinInternal('join', $table, $conditions, $params);
    }

    /**
     * Returns the join part in the query.
     *
     * @return mixed the join part in the query. This can be an array representing
     *         multiple join fragments, or a string representing a single jojin fragment.
     *         Each join fragment will contain the proper join operator (e.g. LEFT JOIN).
     */
    public function getJoin()
    {
        return isset($this->_query['join']) ? $this->_query['join'] : '';
    }

    /**
     * Sets the join part in the query.
     *
     * @param mixed $value the join part in the query. This can be either a string or
     *        an array representing multiple join parts in the query. Each part must contain
     *        the proper join operator (e.g. 'LEFT JOIN tbl_profile ON tbl_user.id=tbl_profile.id')
     */
    public function setJoin($value)
    {
        $this->_query['join'] = $value;
    }

    /**
     * Appends a LEFT OUTER JOIN part to the query.
     *
     * @param string $table the table to be joined.
     *        Table name can contain schema prefix (e.g. 'public.tbl_user') and/or table alias (e.g. 'tbl_user u').
     *        The method will automatically quote the table name unless it contains some parenthesis
     *        (which means the table is given as a sub-query or DB expression).
     * @param mixed $conditions the join condition that should appear in the ON part.
     *        Please refer to {@link where} on how to specify conditions.
     * @param array $params the parameters (name=>value) to be bound to the query
     * @return Command the command object itself
     */
    public function leftJoin($table, $conditions, $params = array())
    {
        return $this->joinInternal('left join', $table, $conditions, $params);
    }

    /**
     * Appends a RIGHT OUTER JOIN part to the query.
     *
     * @param string $table the table to be joined.
     *        Table name can contain schema prefix (e.g. 'public.tbl_user') and/or table alias (e.g. 'tbl_user u').
     *        The method will automatically quote the table name unless it contains some parenthesis
     *        (which means the table is given as a sub-query or DB expression).
     * @param mixed $conditions the join condition that should appear in the ON part.
     *        Please refer to {@link where} on how to specify conditions.
     * @param array $params the parameters (name=>value) to be bound to the query
     * @return Command the command object itself
     */
    public function rightJoin($table, $conditions, $params = array())
    {
        return $this->joinInternal('right join', $table, $conditions, $params);
    }

    /**
     * Appends a CROSS JOIN part to the query.
     * Note that not all DBMS support CROSS JOIN.
     *
     * @param string $table the table to be joined.
     *        Table name can contain schema prefix (e.g. 'public.tbl_user') and/or table alias (e.g. 'tbl_user u').
     *        The method will automatically quote the table name unless it contains some parenthesis
     *        (which means the table is given as a sub-query or DB expression).
     * @return Command the command object itself
     */
    public function crossJoin($table)
    {
        return $this->joinInternal('cross join', $table);
    }

    /**
     * Appends a NATURAL JOIN part to the query.
     * Note that not all DBMS support NATURAL JOIN.
     *
     * @param string $table the table to be joined.
     *        Table name can contain schema prefix (e.g. 'public.tbl_user') and/or table alias (e.g. 'tbl_user u').
     *        The method will automatically quote the table name unless it contains some parenthesis
     *        (which means the table is given as a sub-query or DB expression).
     * @return Command the command object itself
     */
    public function naturalJoin($table)
    {
        return $this->joinInternal('natural join', $table);
    }

    /**
     * Sets the GROUP BY part of the query.
     *
     * @param mixed $columns the columns to be grouped by.
     *        Columns can be specified in either a string (e.g. "id, name") or an array (e.g. array('id', 'name')).
     *        The method will automatically quote the column names unless a column contains some parenthesis
     *        (which means the column contains a DB expression).
     * @return Command the command object itself
     */
    public function group($columns)
    {
        if (!empty($columns)) {
            if (is_string($columns) && strpos($columns, '(') !== false)
                $this->_query['group'] = $columns;
            else {
                if (!is_array($columns))
                    $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($columns as $i => $column) {
                    if (is_object($column))
                        $columns[$i] = ( string )$column;
                    else if (strpos($column, '(') === false)
                        $columns[$i] = $this->_connection->quoteColumnName($column);
                }
                $this->_query['group'] = implode(', ', $columns);
            }
        }
        return $this;
    }

    /**
     * Returns the GROUP BY part in the query.
     *
     * @return string the GROUP BY part (without 'GROUP BY' ) in the query.
     */
    public function getGroup()
    {
        return isset($this->_query['group']) ? $this->_query['group'] : '';
    }

    /**
     * Sets the GROUP BY part in the query.
     *
     * @param mixed $value the GROUP BY part. Please refer to {@link group()} for details
     *        on how to specify this parameter.
     */
    public function setGroup($value)
    {
        $this->group($value);
    }

    /**
     * Sets the HAVING part of the query.
     *
     * @param mixed $conditions the conditions to be put after HAVING.
     *        Please refer to {@link where} on how to specify conditions.
     * @param array $params the parameters (name=>value) to be bound to the query
     * @return Command the command object itself
     */
    public function having($conditions, $params = array())
    {
        $this->_query['having'] = $this->processConditions($conditions);
        foreach ($params as $name => $value)
            $this->params[$name] = $value;
        return $this;
    }

    /**
     * Returns the HAVING part in the query.
     *
     * @return string the HAVING part (without 'HAVING' ) in the query.
     */
    public function getHaving()
    {
        return isset($this->_query['having']) ? $this->_query['having'] : '';
    }

    /**
     * Sets the HAVING part in the query.
     *
     * @param mixed $value the HAVING part. Please refer to {@link having()} for details
     *        on how to specify this parameter.
     */
    public function setHaving($value)
    {
        $this->having($value);
    }

    /**
     * Sets the ORDER BY part of the query.
     *
     * @param mixed $columns the columns (and the directions) to be ordered by.
     *        Columns can be specified in either a string (e.g. "id ASC, name DESC") or an array (e.g. array('id ASC', 'name DESC')).
     *        The method will automatically quote the column names unless a column contains some parenthesis
     *        (which means the column contains a DB expression).
     * @return Command the command object itself
     */
    public function order($columns)
    {
        if(empty($columns)){
            return $this;
        }

        if (is_string($columns) && strpos($columns, '(') !== false)
            $this->_query['order'] = $columns;
        else {
            if (!is_array($columns))
                $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($columns as $i => $column) {
                if (is_object($column))
                    $columns[$i] = ( string )$column;
                else if (strpos($column, '(') === false) {
                    if (preg_match('/^(.*?)\s+(asc|desc)$/i', $column, $matches))
                        $columns[$i] = $this->_connection->quoteColumnName($matches[1]) . ' ' . strtoupper($matches[2]);
                    else
                        $columns[$i] = $this->_connection->quoteColumnName($column);
                }
            }
            $this->_query['order'] = implode(', ', $columns);
        }
        return $this;
    }

    /**
     * Returns the ORDER BY part in the query.
     *
     * @return string the ORDER BY part (without 'ORDER BY' ) in the query.
     */
    public function getOrder()
    {
        return isset($this->_query['order']) ? $this->_query['order'] : '';
    }

    /**
     * Sets the ORDER BY part in the query.
     *
     * @param mixed $value the ORDER BY part. Please refer to {@link order()} for details
     *        on how to specify this parameter.
     */
    public function setOrder($value)
    {
        $this->order($value);
    }

    /**
     * Sets the LIMIT part of the query.
     *
     * @param integer $limit the limit
     * @param integer $offset the offset
     * @return Command the command object itself
     */
    public function limit($limit, $offset = null)
    {
        $this->_query['limit'] = ( int )$limit;
        if ($offset !== null)
            $this->offset($offset);
        return $this;
    }

    /**
     * Returns the LIMIT part in the query.
     *
     * @return string the LIMIT part (without 'LIMIT' ) in the query.
     */
    public function getLimit()
    {
        return isset($this->_query['limit']) ? $this->_query['limit'] : -1;
    }

    /**
     * Sets the LIMIT part in the query.
     *
     * @param integer $value the LIMIT part. Please refer to {@link limit()} for details
     *        on how to specify this parameter.
     */
    public function setLimit($value)
    {
        $this->limit($value);
    }

    /**
     * Sets the OFFSET part of the query.
     *
     * @param integer $offset the offset
     * @return Command the command object itself
     */
    public function offset($offset)
    {
        $this->_query['offset'] = ( int )$offset;
        return $this;
    }

    /**
     * Returns the OFFSET part in the query.
     *
     * @return string the OFFSET part (without 'OFFSET' ) in the query.
     */
    public function getOffset()
    {
        return isset($this->_query['offset']) ? $this->_query['offset'] : -1;
    }

    /**
     * Sets the OFFSET part in the query.
     *
     * @param integer $value the OFFSET part. Please refer to {@link offset()} for details
     *        on how to specify this parameter.
     */
    public function setOffset($value)
    {
        $this->offset($value);
    }

    /**
     * Appends a SQL statement using UNION operator.
     *
     * @param string $sql the SQL statement to be appended using UNION
     * @return Command the command object itself
     */
    public function union($sql)
    {
        if (isset($this->_query['union']) && is_string($this->_query['union']))
            $this->_query['union'] = array(
                $this->_query['union']
            );

        $this->_query['union'][] = $sql;

        return $this;
    }

    /**
     * Returns the UNION part in the query.
     *
     * @return mixed the UNION part (without 'UNION' ) in the query.
     *         This can be either a string or an array representing multiple union parts.
     */
    public function getUnion()
    {
        return isset($this->_query['union']) ? $this->_query['union'] : '';
    }

    /**
     * Sets the UNION part in the query.
     *
     * @param mixed $value the UNION part. This can be either a string or an array
     *        representing multiple SQL statements to be unioned together.
     */
    public function setUnion($value)
    {
        $this->_query['union'] = $value;
    }

    /**
     *
     * @var string
     */
    protected $tableName;

    /**
     *
     * @var string
     */
    protected $tablePrimary;

    /**
     *
     * @param string $tableName
     * @param string $tablePrimary
     */
    public function setTableInfo($tableName, $tablePrimary = null)
    {
        $this->tableName = $tableName;
        if (!empty($this->tableName)) {
            $this->from($this->tableName);
        }
        $this->tablePrimary = $tablePrimary;
    }

    /**
     *
     * @param array $columns
     * @param string $table
     * @return int
     */
    public function insert($columns, $table = null)
    {
        if (!empty($table)) {
            $this->tableName = $table;
        }

        $params = array();
        $names = array();
        $placeholders = array();
        foreach ($columns as $name => $value) {
            $names[] = $this->_connection->quoteColumnName($name);
            $placeholders[] = ':' . $name;
            $params[':' . $name] = $value;
        }
        $sql = 'INSERT INTO ' . $this->_connection->quoteTableName($this->tableName) . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->setText($sql)->execute($params);
        return $this->_connection->getLastInsertID();
    }

    public function insertIgnore($columns, $table = null)
    {
        if ($this->getConnection()->getDriverName() == 'mysql') {
            if (!empty($table)) {
                $this->tableName = $table;
            }

            $params = array();
            $names = array();
            $placeholders = array();
            foreach ($columns as $name => $value) {
                $names[] = $this->_connection->quoteColumnName($name);
                $placeholders[] = ':' . $name;
                $params[':' . $name] = $value;
            }
            $sql = 'INSERT IGNORE INTO ' . $this->_connection->quoteTableName($this->tableName) . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')';
            return $this->setText($sql)->execute($params);
        } else {
            throw new \PDOException('Connection driver can not support the insertIgnore()');
        }
    }

    public function insertDuplicate($columns, $duplicates, $table = null)
    {
        if ($this->getConnection()->getDriverName() == 'mysql') {
            if (!empty($table)) {
                $this->tableName = $table;
            }

            $params = array();
            $names = array();
            $placeholders = array();
            foreach ($columns as $name => $value) {
                $names[] = $this->_connection->quoteColumnName($name);
                $placeholders[] = ':' . $name;
                $params[':' . $name] = $value;
                if (in_array($name, $duplicates)) {
                    $_duplicates[] = $this->_connection->quoteColumnName($name) . '=:' . $name;
                }
            }

            $sqlInsertId = "";
            if (!empty($columns[$this->tablePrimary])) {
                $sqlInsertId = $this->tablePrimary . ' = LAST_INSERT_ID(' . $this->tablePrimary . '),';
            }

            $sql = 'INSERT INTO ' . $this->_connection->quoteTableName($this->tableName) . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ') ON DUPLICATE KEY UPDATE  ' . $sqlInsertId . ' ' . implode(',', $_duplicates);
            $this->setText($sql)->execute($params);
            if (!empty($columns[$this->tablePrimary])) {
                return $columns[$this->tablePrimary];
            } else {
                return $this->_connection->getLastInsertID();
            }
        } else {
            throw new \PDOException('Connection driver can not support the insertDuplicate()');
        }
    }

    /**
     * Creates and executes an UPDATE SQL statement.
     * The method will properly escape the column names and bind the values to be updated.
     *
     * @param string $table the table to be updated.
     * @param array $columns the column data (name=>value) to be updated.
     * @param mixed $conditions the conditions that will be put in the WHERE part. Please
     *        refer to {@link where} on how to specify conditions.
     * @param array $params the parameters to be bound to the query.
     * @return integer number of rows affected by the execution.
     */
    public function update($columns, $conditions = '', $params = array(), $table = null)
    {
        if (empty($table)) {
            $table = $this->tableName;
        }
        if(!is_array($params)){
            $params = [];
        }
        $lines = array();
        foreach ($columns as $name => $value) {

            $lines[] = $this->_connection->quoteColumnName($name) . '=:' . $name;
            $params[':' . $name] = $value;
        }
        $sql = 'UPDATE ' . $this->_connection->quoteTableName($table) . ' SET ' . implode(', ', $lines);
        if (($where = $this->processConditions($conditions)) != '')
            $sql .= ' WHERE ' . $where;
        return $this->setText($sql)->execute($params);
    }

    /**
     * Creates and executes a DELETE SQL statement.
     *
     * @param string $table the table where the data will be deleted from.
     * @param mixed $conditions the conditions that will be put in the WHERE part. Please
     *        refer to {@link where} on how to specify conditions.
     * @param array $params the parameters to be bound to the query.
     * @return integer number of rows affected by the execution.
     */
    public function delete($conditions = '', $params = array(), $table = null)
    {
        if (empty($table)) {
            $table = $this->tableName;
        }
        $sql = 'DELETE FROM ' . $this->_connection->quoteTableName($table);
        if (($where = $this->processConditions($conditions)) != '')
            $sql .= ' WHERE ' . $where;
        return $this->setText($sql)->execute($params);
    }


    /**
     * Generates the condition string that will be put in the WHERE part
     *
     * @param mixed $conditions the conditions that will be put in the WHERE part.
     * @return string the condition string to put in the WHERE part
     */
    private function processConditions($conditions)
    {
        if (!is_array($conditions))
            return $conditions;
        else if ($conditions === array())
            return '';
        $n = count($conditions);
        $operator = strtoupper($conditions[0]);
        if ($operator === 'OR' || $operator === 'AND') {
            $parts = array();
            for ($i = 1; $i < $n; ++$i) {
                $condition = $this->processConditions($conditions[$i]);
                if ($condition !== '')
                    $parts[] = '(' . $condition . ')';
            }
            return $parts === array() ? '' : implode(' ' . $operator . ' ', $parts);
        }

        if (!isset($conditions[1], $conditions[2]))
            return '';

        $column = $conditions[1];
        if (strpos($column, '(') === false)
            $column = $this->_connection->quoteColumnName($column);

        $values = $conditions[2];
        if (!is_array($values))
            $values = array(
                $values
            );

        if ($operator === 'IN' || $operator === 'NOT IN') {
            if ($values === array())
                return $operator === 'IN' ? '0=1' : '';
            foreach ($values as $i => $value) {
                if (is_string($value))
                    $values[$i] = $this->_connection->quoteValue($value);
                else
                    $values[$i] = ( string )$value;
            }
            return $column . ' ' . $operator . ' (' . implode(', ', $values) . ')';
        }

        if ($operator === 'LIKE' || $operator === 'NOT LIKE' || $operator === 'OR LIKE' || $operator === 'OR NOT LIKE') {
            if ($values === array())
                return $operator === 'LIKE' || $operator === 'OR LIKE' ? '0=1' : '';

            if ($operator === 'LIKE' || $operator === 'NOT LIKE')
                $andor = ' AND ';
            else {
                $andor = ' OR ';
                $operator = $operator === 'OR LIKE' ? 'LIKE' : 'NOT LIKE';
            }
            $expressions = array();
            foreach ($values as $value)
                $expressions[] = $column . ' ' . $operator . ' ' . $this->_connection->quoteValue($value);
            return implode($andor, $expressions);
        }

        throw new \PDOException(sprintf('未知符号 "%s".', $operator));
    }

    /**
     * Appends an JOIN part to the query.
     *
     * @param string $type the join type ('join', 'left join', 'right join', 'cross join', 'natural join')
     * @param string $table the table to be joined.
     *        Table name can contain schema prefix (e.g. 'public.tbl_user') and/or table alias (e.g. 'tbl_user u').
     *        The method will automatically quote the table name unless it contains some parenthesis
     * @param mixed $conditions the join condition that should appear in the ON part.
     *        Please refer to {@link where} on how to specify conditions.
     * @param array $params the parameters (name=>value) to be bound to the query
     * @return Command the command object itself
     */
    private function joinInternal($type, $table, $conditions = '', $params = array())
    {
        if (strpos($table, '(') === false) {
            if (preg_match('/^(.*?)(?i:\s+as\s+|\s+)(.*)$/', $table, $matches)) // with alias
                $table = $this->_connection->quoteTableName($matches[1]) . ' ' . $this->_connection->quoteTableName($matches[2]);
            else
                $table = $this->_connection->quoteTableName($table);
        }

        $conditions = $this->processConditions($conditions);
        if ($conditions != '')
            $conditions = ' ON ' . $conditions;

        if (isset($this->_query['join']) && is_string($this->_query['join']))
            $this->_query['join'] = array(
                $this->_query['join']
            );

        $this->_query['join'][] = strtoupper($type) . ' ' . $table . $conditions;

        foreach ($params as $name => $value)
            $this->params[$name] = $value;
        return $this;
    }

    public function queryAllByPk($pkValueArr, $columns = '*')
    {
        if (!empty($pkValueArr) && is_array($pkValueArr)) {
            $condition = "{$this->tablePrimary} in ('" . implode("','", $pkValueArr) . "')";
            return $this->select($columns)->where($condition)->queryAll(true);
        }
        return array();
    }

    /**
     * select by table primary key
     *
     * @param mix $pkValue
     * @param string $columns
     * @param string $table table name
     * @param string $tablePrimary table primary Field name
     * @return array
     */
    public function queryRowByPk($pkValue, $columns = '*', $table = null, $tablePrimary = null)
    {
        if (!empty($table)) {
            $this->from($table);
        }

        if (!empty($tablePrimary)) {
            $condition = "{$tablePrimary}='{$pkValue}'";
        } else {
            $condition = "{$this->tablePrimary}='{$pkValue}'";
        }

        return $this->select($columns)->where($condition)->queryRow(true);
    }


    public function deleteByPk($pkValue, $table = null, $tablePrimary = null)
    {
        if (empty($table)) {
            $table = $this->tableName;
        }

        if (!empty($tablePrimary)) {
            $conditions = "{$tablePrimary}='{$pkValue}'";
        } else {
            $conditions = "{$this->tablePrimary}='{$pkValue}'";
        }

        $sql = 'DELETE FROM ' . $this->_connection->quoteTableName($table);
        if (($where = $this->processConditions($conditions)) != '')
            $sql .= ' WHERE ' . $where;

        return $this->setText($sql)->execute();
    }

    public function updateByPk($columns, $pkValue, $table = null, $tablePrimary = null)
    {
        if (empty($table)) {
            $table = $this->tableName;
        }

        if (!empty($tablePrimary)) {
            $conditions = "{$tablePrimary}='{$pkValue}'";
        } else {
            $conditions = "{$this->tablePrimary}='{$pkValue}'";
        }

        return $this->update($columns, $conditions, array(), $table);
    }
}
