<?php

namespace Wink\Generator\Schema;

use InvalidArgumentException;
use Illuminate\Database\Connection;

class SchemaReaderFactory
{
    public static function make(Connection $connection): SchemaReader
    {
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => new SQLiteSchemaReader($connection),
            'mysql' => new MySQLSchemaReader($connection),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}")
        };
    }
}
