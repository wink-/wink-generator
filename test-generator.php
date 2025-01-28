<?php

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Console\Application;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Wink\Generator\Console\Commands\GenerateModelsCommand;

require 'vendor/autoload.php';

// Create container
$container = new Container;
Container::setInstance($container);

// Create and register config
$config = new Repository([
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'database' => 'model_generator_test',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
        ],
    ],
    'generator' => [
        'excluded_tables' => [
            'migrations',
            'failed_jobs',
            'password_resets',
            'cache',
            'cache_locks',
            'sessions',
            'personal_access_tokens',
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
        ],
    ],
]);
$container->instance('config', $config);

// Set up facades
Facade::setFacadeApplication($container);
Facade::clearResolvedInstances();

// Bind Filesystem
$container->singleton('files', function () {
    return new Filesystem;
});

// Set up database
$capsule = new Capsule($container);
$capsule->addConnection($config->get('database.connections.mysql'), 'mysql');
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Bind DatabaseManager
$container->singleton('db', function ($container) use ($capsule) {
    return $capsule->getDatabaseManager();
});

// Bind path functions
$container->instance('path', __DIR__);
$container->instance('path.database', __DIR__ . '/database');
$container->instance('path.app', __DIR__ . '/app');

// Create application
$events = new Dispatcher($container);
$app = new Application($container, $events, 'Version 1.0');

// Add our command
$command = new GenerateModelsCommand;
$app->add($command);

// Run the command
$app->run();
