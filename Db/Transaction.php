<?php
namespace Lib\Db;

class Transaction
{
    private $_connection = null;
    private $_active;

    /**
     * Constructor.
     * @param Connection $connection the connection associated with this transaction
     * @see Lib_Db_Connection::beginTransaction
     */
    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
        $this->_active = true;
    }

    /**
     * Commits a transaction.
     */
    public function commit()
    {
        if ($this->_active && $this->_connection->getActive()) {
//			writelog('application', 'Committing transaction','system.db.Lib_Db_Transaction');
            $this->_connection->getPdoInstance()->commit();
            $this->_active = false;
        } else
            throw new \PDOException('Transaction 没有激活不能提交和回滚');
    }

    /**
     * Rolls back a transaction.
     */
    public function rollback()
    {
        if ($this->_active && $this->_connection->getActive()) {
//			writelog('application', 'Rolling back transaction','system.db.Lib_Db_Transaction');
            $this->_connection->getPdoInstance()->rollBack();
            $this->_active = false;
        } else
            throw new \PDOException('Transaction 没有激活不能提交和回滚');
    }

    /**
     * @return Connection the DB connection for this transaction
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @return boolean whether this transaction is active
     */
    public function getActive()
    {
        return $this->_active;
    }

    /**
     * @param boolean $value whether this transaction is active
     */
    protected function setActive($value)
    {
        $this->_active = $value;
    }
}
