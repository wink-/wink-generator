<?php

namespace Wink\Generator\Schema;

use Illuminate\Database\Connection;

abstract class AbstractSchemaReader implements SchemaReader
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Quote a table name according to the database driver.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return $this->connection->getQueryGrammar()->wrap($identifier);
    }

    /**
     * Get the raw PDO connection.
     */
    protected function getPdo()
    {
        return $this->connection->getPdo();
    }

    /**
     * Get the database name.
     */
    protected function getDatabaseName(): string
    {
        return $this->connection->getDatabaseName();
    }
}
