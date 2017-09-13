<?php
namespace Lib\Db\Schema\Pgsql;

class TableSchema extends \Lib\Db\Schema\TableSchema
{
    /**
     * @var string name of the schema that this table belongs to.
     */
    public $schemaName;
}
