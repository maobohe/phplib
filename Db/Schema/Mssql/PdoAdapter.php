<?php
namespace \Lib\Db\Schema\Mssql;

class PdoAdapter extends \PDO
{
    /**
     * Get the last inserted id value
     * MSSQL doesn't support sequence, so, argument is ignored
     *
     * @param string|null sequence name. Defaults to null
     * @return integer last inserted id
     */
    public function lastInsertId($sequence = NULL)
    {
        $value = $this->query('SELECT SCOPE_IDENTITY()')->fetchColumn();
        $value = preg_replace('/[,.]0+$/', '', $value); // issue 2312
        return strtr($value, array(',' => '', '.' => ''));
    }

    /**
     * Begin a transaction
     *
     * Is is necessary to override pdo's method, as mssql pdo drivers
     * does not support transaction
     *
     * @return boolean
     */
    public function beginTransaction()
    {
        $this->exec('BEGIN TRANSACTION');
        return true;
    }

    /**
     * Commit a transaction
     *
     * Is is necessary to override pdo's method, as mssql pdo drivers
     * does not support transaction
     *
     * @return boolean
     */
    public function commit()
    {
        $this->exec('COMMIT TRANSACTION');
        return true;
    }

    /**
     * Rollback a transaction
     *
     * Is is necessary to override pdo's method, ac mssql pdo drivers
     * does not support transaction
     *
     * @return boolean
     */
    public function rollBack()
    {
        $this->exec('ROLLBACK TRANSACTION');
        return true;
    }
}
