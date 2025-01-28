<?php

namespace Wink\Generator\Schema;

interface SchemaReader
{
    /**
     * Get all tables from the database.
     *
     * @return array<string>
     */
    public function getTables(): array;

    /**
     * Get column information for a table.
     *
     * @param string $table
     * @return array<\stdClass> Each object should have 'name' and 'type' properties
     */
    public function getColumns(string $table): array;
}
