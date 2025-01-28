<?php

namespace Wink\Generator\Schema;

use Illuminate\Database\Connection;

class MySQLSchemaReader extends AbstractSchemaReader
{

    public function getTables(): array
    {
        $tables = $this->connection->select('SHOW TABLES');
        $firstKey = 'Tables_in_' . $this->getDatabaseName();

        return array_map(function ($table) use ($firstKey) {
            return $table->{$firstKey};
        }, $tables);
    }

    public function getColumns(string $table): array
    {
        $columnsInfo = $this->connection->select('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table));

        return array_map(function ($column) {
            return (object) [
                'name' => $column->Field,
                'type' => $this->normalizeColumnType($column->Type),
            ];
        }, $columnsInfo);
    }

    protected function normalizeColumnType(string $type): string
    {
        // Extract base type without length/precision
        preg_match('/^([a-zA-Z]+)/', $type, $matches);
        $baseType = strtolower($matches[1]);

        // Map MySQL types to our normalized types
        $typeMap = [
            'tinyint' => 'boolean', // Often used for boolean
            'smallint' => 'integer',
            'mediumint' => 'integer',
            'int' => 'integer',
            'bigint' => 'integer',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'date' => 'date',
            'time' => 'time',
            'year' => 'integer',
            'char' => 'string',
            'varchar' => 'string',
            'text' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'json' => 'json',
            'enum' => 'string',
        ];

        return $typeMap[$baseType] ?? $baseType;
    }
}
