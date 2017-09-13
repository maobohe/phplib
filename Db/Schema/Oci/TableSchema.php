<?php
namespace Lib\Db\Schema\Oci;

class TableSchema extends \Lib\Db\Schema\TableSchema
{
    /**
     * @var string name of the schema (database) that this table belongs to.
     * Defaults to null, meaning no schema (or the current database).
     */
    public $schemaName;
}
