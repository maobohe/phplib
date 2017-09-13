<?php
namespace Lib\Db\Schema\Oci;

class ColumnSchema extends \Lib\Db\Schema\ColumnSchema
{
    /**
     * Extracts the PHP type from DB type.
     * @param string $dbType DB type
     * @return string
     */
    protected function extractOraType($dbType)
    {
        if (strpos($dbType, 'FLOAT') !== false) return 'double';

        if (strpos($dbType, 'NUMBER') !== false || strpos($dbType, 'INTEGER') !== false) {
            if (strpos($dbType, '(') && preg_match('/\((.*)\)/', $dbType, $matches)) {
                $values = explode(',', $matches[1]);
                if (isset($values[1]) and (((int)$values[1]) > 0))
                    return 'double';
                else
                    return 'integer';
            } else
                return 'double';
        } else
            return 'string';
    }

    /**
     * Extracts the PHP type from DB type.
     * @param string $dbType DB type
     */
    protected function extractType($dbType)
    {
        $this->type = $this->extractOraType($dbType);
    }

    /**
     * Extracts the default value for the column.
     * The value is typecasted to correct PHP type.
     * @param mixed $defaultValue the default value obtained from metadata
     */
    protected function extractDefault($defaultValue)
    {
        if (stripos($defaultValue, 'timestamp') !== false)
            $this->defaultValue = null;
        else
            parent::extractDefault($defaultValue);
    }
}
