<?php

namespace Wink\Generator\Schema;

use Illuminate\Database\Connection;

class SQLiteSchemaReader extends AbstractSchemaReader
{

    public function getTables(): array
    {
        $tables = $this->connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        return array_map(function ($table) {
            return $table->name;
        }, $tables);
    }

    public function getColumns(string $table): array
    {
        $columnsInfo = $this->connection->select(
            "PRAGMA table_info(" . $this->quoteIdentifier($table) . ")"
        );

        return array_map(function ($column) {
            return (object) [
                'name' => $column->name,
                'type' => strtolower($column->type),
            ];
        }, $columnsInfo);
    }
}
