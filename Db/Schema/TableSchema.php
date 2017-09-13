<?php
namespace Lib\Db\Schema;

class TableSchema
{
    /**
     * @var string name of this table.
     */
    public $name;
    /**
     * @var string raw name of this table. This is the quoted version of table name with optional schema name. It can be directly used in SQLs.
     */
    public $rawName;
    /**
     * @var string|array primary key name of this table. If composite key, an array of key names is returned.
     */
    public $primaryKey;
    /**
     * @var string sequence name for the primary key. Null if no sequence.
     */
    public $sequenceName;
    /**
     * @var array foreign keys of this table. The array is indexed by column name. Each value is an array of foreign table name and foreign column name.
     */
    public $foreignKeys = array();
    /**
     * @var array column metadata of this table. Each array element is a CDbColumnSchema object, indexed by column names.
     */
    public $columns = array();

    /**
     * Gets the named column metadata.
     * This is a convenient method for retrieving a named column even if it does not exist.
     * @param string $name column name
     * @return ColumnSchema metadata of the named column. Null if the named column does not exist.
     */
    public function getColumn($name)
    {
        return isset($this->columns[$name]) ? $this->columns[$name] : null;
    }

    /**
     * @return array list of column names
     */
    public function getColumnNames()
    {
        return array_keys($this->columns);
    }
}
