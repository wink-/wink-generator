<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    |
    | List of tables to exclude from model generation
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Base Models Path
    |--------------------------------------------------------------------------
    |
    | This value determines the base path where models will be generated.
    | The default Laravel convention is 'app/Models'.
    |
    */
    'models_path' => 'Models',

    /*
    |--------------------------------------------------------------------------
    | Base Factories Path
    |--------------------------------------------------------------------------
    |
    | This value determines the base path where factories will be generated.
    | The default Laravel convention is 'database/factories'.
    |
    */
    'factories_path' => 'database/factories',

    /*
    |--------------------------------------------------------------------------
    | Base Policies Path
    |--------------------------------------------------------------------------
    |
    | This value determines the base path where policies will be generated.
    | The default Laravel convention is 'app/Policies'.
    |
    */
    'policies_path' => 'Policies',

    /*
    |--------------------------------------------------------------------------
    | Base Controllers Path
    |--------------------------------------------------------------------------
    |
    | This value determines the base path where controllers will be generated.
    | The default Laravel convention is 'app/Http/Controllers'.
    |
    */
    'controllers_path' => 'Http/Controllers',
];
