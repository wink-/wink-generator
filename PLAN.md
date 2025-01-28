**Phase 1: Package Foundation and Setup**

1.  **Initialize Laravel Package:**
    *   Run the Composer command in your terminal:
        ```bash
        composer create-project --prefer-dist laravel/laravel wink-generator-dev  # Use a temporary name for development
        cd wink-generator-dev
        ```
    *   This creates a fresh Laravel application. We'll develop your package *within* this application for easier testing initially.  Later, we'll extract it into a standalone package structure.

2.  **Set up `composer.json`:**
    *   Open `composer.json` in your project root.
    *   Modify the `name`, `description`, `authors`, and `require` sections to reflect your package. Example:

    ```json
    {
        "name": "wink/generator",
        "description": "Laravel package to generate models, factories, policies, and controllers from existing database schemas.",
        "type": "library",
        "license": "MIT",
        "authors": [
            {
                "name": "Your Name",  // Replace with your name
                "email": "your.email@example.com" // Replace with your email
            }
        ],
        "require": {
            "php": "^8.0",
            "illuminate/database": "^11.0", // Adjust Laravel version compatibility
            "illuminate/console": "^11.0",
            "illuminate/support": "^11.0" // For Str and File facades
        },
        "autoload": {
            "psr-4": {
                "Wink\\Generator\\": "src/"
            }
        },
        "extra": {
            "laravel": {
                "providers": [
                    "Wink\\Generator\\GeneratorServiceProvider"
                ]
            }
        },
        "minimum-stability": "dev",
        "prefer-stable": true
    }
    ```

3.  **Create Service Provider:**
    *   Create a directory `src` in your project root. Inside `src`, create `GeneratorServiceProvider.php`:

    ```php
    <?php

    namespace Wink\Generator;

    use Illuminate\Support\ServiceProvider;
    use Wink\Generator\Console\Commands\GenerateModelsCommand;

    class GeneratorServiceProvider extends ServiceProvider
    {
        public function boot()
        {
            if ($this->app->runningInConsole()) {
                $this->commands([
                    GenerateModelsCommand::class,
                ]);
            }

            $this->publishes([
                __DIR__.'/../config/generator.php' => config_path('generator.php'),
            ], 'config');
        }

        public function register()
        {
            $this->mergeConfigFrom(
                __DIR__.'/../config/generator.php', 'generator'
            );
        }
    }
    ```

4.  **Create Artisan Command:**
    *   Create directories `src/Console/Commands`. Inside `Commands`, create `GenerateModelsCommand.php`:

    ```php
    <?php

    namespace Wink\Generator\Console\Commands;

    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Str;

    class GenerateModelsCommand extends Command
    {
        protected $signature = 'wink:generate-models
                                {--connection= : The database connection to use (default: config("database.default"))}
                                {--directory=Generated : The directory to output generated files (relative to app path)}
                                {--factories : Generate factories for the models}
                                {--policies : Generate policies for the models}
                                {--controllers : Generate controllers for the models}
                                {--resource : Generate resource controllers (implies --controllers)}';

        protected $description = 'Generate models, factories, policies, and controllers from an existing database connection';

        public function handle()
        {
            $connectionName = $this->option('connection') ?? config('database.default');
            $outputDirectory = app_path($this->option('directory') ?? 'Generated');
            $generateFactories = $this->option('factories');
            $generatePolicies = $this->option('policies');
            $generateControllers = $this->option('controllers') || $this->option('resource');
            $generateResourceControllers = $this->option('resource');

            $excludedTables = config('generator.excluded_tables', []);

            try {
                DB::connection($connectionName)->getPdo(); // Test connection
            } catch (\Exception $e) {
                $this->error("Could not connect to database: " . $e->getMessage());
                return;
            }

            $tableNames = Schema::connection($connectionName)->getAllTables();
            $filteredTableNames = collect($tableNames)
                ->map(function ($tableName) { // Adjust based on getAllTables output if needed, might be an object
                    return is_array($tableName) ? reset($tableName) : $tableName; // Assuming table name might be in an array in some DB drivers
                })
                ->reject(function ($tableName) use ($excludedTables) {
                    return in_array($tableName, $excludedTables);
                })->toArray();


            File::makeDirectory($outputDirectory, 0755, true, true);
            if ($generateFactories) {
                File::makeDirectory(database_path('factories/' . str_replace(app_path() . '/', '', $outputDirectory)), 0755, true, true); // Factory directory in database/factories
            }
            if ($generatePolicies) {
                File::makeDirectory(app_path('Policies/' . str_replace(app_path() . '/', '', $outputDirectory)), 0755, true, true); // Policy directory in app/Policies
            }
            if ($generateControllers) {
                File::makeDirectory(app_path('Http/Controllers/' . str_replace(app_path() . '/', '', $outputDirectory)), 0755, true, true); // Controller directory in app/Http/Controllers
            }


            $routeContent = "<?php\n\n"; // Initialize route content

            foreach ($filteredTableNames as $tableName) {
                $modelName = Str::studly(Str::singular($tableName));
                $this->info("Generating model for table: {$tableName} as {$modelName}");

                // --- Model Generation Logic (Phase 2 - Step 1) --- //
                $columns = Schema::connection($connectionName)->getColumns($tableName); // Get column info
                $modelContent = $this->generateModelContent($modelName, $connectionName, $tableName, $columns);
                File::put($outputDirectory . '/' . $modelName . '.php', $modelContent);
                $this->info("Model {$modelName} generated at: " . $outputDirectory . '/' . $modelName . '.php');


                // --- Factory Generation (Phase 2 - Step 2) --- //
                if ($generateFactories) {
                    $factoryContent = $this->generateFactoryContent($modelName, str_replace(app_path() . '/', '', $outputDirectory)); // Pass directory for namespace
                    File::put(database_path('factories/' . str_replace(app_path() . '/', '', $outputDirectory)) . '/' . $modelName . 'Factory.php', $factoryContent);
                    $this->info("Factory {$modelName}Factory generated.");
                }

                // --- Policy Generation (Phase 2 - Step 3) --- //
                if ($generatePolicies) {
                    $policyContent = $this->generatePolicyContent($modelName, str_replace(app_path() . '/', '', $outputDirectory)); // Pass directory for namespace
                    File::put(app_path('Policies/' . str_replace(app_path() . '/', '', $outputDirectory)) . '/' . $modelName . 'Policy.php', $policyContent);
                    $this->info("Policy {$modelName}Policy generated.");
                }

                // --- Controller Generation (Phase 2 - Step 4) --- //
                if ($generateControllers) {
                    $controllerContent = $this->generateControllerContent($modelName, str_replace(app_path() . '/', '', $outputDirectory), $generateResourceControllers); // Pass directory for namespace, resource flag
                    $controllerFilename = app_path('Http/Controllers/' . str_replace(app_path() . '/', '', $outputDirectory)) . '/' . $modelName . 'Controller.php';
                    File::put($controllerFilename, $controllerContent);
                    $this->info("Controller {$modelName}Controller generated at: " . $controllerFilename);

                    if ($generateResourceControllers) {
                        $routeName = Str::kebab(Str::pluralStudly($modelName));
                        $routeContent .= "Route::resource('{$routeName}', \\App\\Http\\Controllers\\" . str_replace(app_path() . '/', '', $outputDirectory) . "\\{$modelName}Controller::class);\n";
                    }
                }
            }

            // --- Generated Routes File (Phase 2 - Step 5) --- //
            if ($generateControllers && $generateResourceControllers) {
                File::put($outputDirectory . '/GeneratedRoutes.php', $routeContent);
                $this->info("Generated routes appended to: " . $outputDirectory . '/GeneratedRoutes.php');
            }


            $this->info('All models and related files generated successfully!');
        }


        // --- Phase 2: Code Generation Methods (Implement next) --- //

        protected function mapColumnTypeToPhpAndCast(string $columnType): array { /* ... as defined previously ... */ }
        protected function generateModelContent(string $modelName, string $connectionName, string $tableName, array $columns): string { /* ... as defined previously ... */ }
        protected function generateFactoryContent(string $modelName, string $namespaceDirectory): string { /* ... Factory stub generation ... */ return "<?php\n\nnamespace Database\Factories\\" . $namespaceDirectory . ";\n...\n"; } // Basic stub
        protected function generatePolicyContent(string $modelName, string $namespaceDirectory): string { /* ... Policy stub generation ... */ return "<?php\n\nnamespace App\Policies\\" . $namespaceDirectory . ";\n...\n"; } // Basic stub
        protected function generateControllerContent(string $modelName, string $namespaceDirectory, bool $resource = false): string { /* ... Controller stub generation ... */ return "<?php\n\nnamespace App\Http\Controllers\\" . $namespaceDirectory . ";\n...\n"; } // Basic stub
    }
    ```

5.  **Create Config File:**
    *   Create a directory `config` in your package root (alongside `src`). Inside `config`, create `generator.php`:

    ```php
    <?php

    return [
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
    ];
    ```

**Phase 2: Implement Code Generation Logic (Focus on Model first, then others)**

1.  **Implement `mapColumnTypeToPhpAndCast()`:**
    *   Copy the `mapColumnTypeToPhpAndCast()` method we defined earlier into your `GenerateModelsCommand.php` class.

2.  **Implement `generateModelContent()`:**
    *   Copy the `generateModelContent()` method we defined earlier into your `GenerateModelsCommand.php` class.

3.  **Implement Stub Generation for Factory, Policy, Controller:**
    *   For now, implement very basic stub versions of `generateFactoryContent()`, `generatePolicyContent()`, and `generateControllerContent()` in your `GenerateModelsCommand.php`.  Just focus on namespace and class name for now. Example for `generateFactoryContent()`:

    ```php
    protected function generateFactoryContent(string $modelName, string $namespaceDirectory): string
    {
        $namespace = "Database\Factories\\" . $namespaceDirectory;
        return "<?php\n\nnamespace {$namespace};\n\nuse Illuminate\Database\Eloquent\Factories\Factory;\nuse App\Models\\Generated\\{$modelName};\n\nclass {$modelName}Factory extends Factory\n{\n    protected \$model = {$modelName}::class;\n\n    public function definition(): array\n    {\n        return [\n            // Define factory attributes here\n        ];\n    }\n}\n";
    }
    ```
    *   Create similar basic stubs for `generatePolicyContent()` and `generateControllerContent()`.  Focus on getting the file creation working first, then we'll flesh out the content.

**Phase 3: Basic Testing and Iteration**

1.  **Configure Database Connection:**
    *   In your `wink-generator-dev` Laravel application's `.env` file, configure a database connection (e.g., MySQL, SQLite) that has some existing tables (not Laravel default tables). You can create a test database if needed.

2.  **Run the Artisan Command:**
    *   In your terminal, navigate to your `wink-generator-dev` project directory.
    *   Run your new Artisan command: `php artisan wink:generate-models` (or with options, e.g., `php artisan wink:generate-models --connection=your_test_connection --directory=GeneratedModels --factories --policies --controllers --resource`).

3.  **Check Generated Files:**
    *   Verify that the models, factories, policies, and controllers (if options were used) are generated in the `app/Generated`, `database/factories/Generated`, `app/Policies/Generated`, and `app/Http/Controllers/Generated` directories (or the directory you specified with `--directory`).
    *   Inspect the generated model files, especially for `$connection`, `$table`, `$fillable`, `$casts`, and `@property` docblocks.  Check if they are based on your database schema.
    *   Check if the basic factory, policy, and controller stubs are created.
    *   If you used `--resource`, check for `GeneratedRoutes.php` in the output directory.

4.  **Iterate and Refine:**
    *   If there are errors or issues, debug your code.
    *   Refine the `generateModelContent()` method to improve the generated model code (add more properties, better data type mapping, etc.).
    *   Flesh out the `generateFactoryContent()`, `generatePolicyContent()`, and `generateControllerContent()` methods to generate more complete stubs.
    *   Add more options to your Artisan command as needed.
    *   Start writing tests (Phase 3 - Testing & Refinement section in the overall plan).


# Laravel Package File Structure

A Laravel package typically follows a structured directory layout to ensure organization and maintainability. Below is a standard file structure for a Laravel package:

```
src/
├── Console/
│   └── Commands/
│       └── GenerateModelsCommand.php
├── GeneratorServiceProvider.php
├── Schema/
│   ├── AbstractSchemaReader.php
│   ├── MySQLSchemaReader.php
│   ├── SchemaReader.php
│   ├── SchemaReaderFactory.php
│   └── SQLiteSchemaReader.php
config/
│   └── generator.php
database/
├── migrations/
│   └── create_example_table.php
├── seeds/
│   └── ExampleTableSeeder.php
tests/
│   └── Feature/
│       └── ExampleTest.php
resources/
│   └── views/
│       └── example.blade.php
routes/
│   └── web.php
public/
│   └── assets/
│       └── css/
│           └── example.css
│       └── js/
│           └── example.js
```

### Explanation:

1. **src/**: Contains the core source code of the package.
   - **Console/Commands/**: Artisan commands.
   - **GeneratorServiceProvider.php**: Service provider for the package.
   - **Schema/**: Schema-related classes.

2. **config/**: Configuration files.
   - **generator.php**: Configuration for the package.

3. **database/**: Database-related files.
   - **migrations/**: Database migration files.
   - **seeds/**: Database seeder files.

4. **tests/**: Test files.
   - **Feature/**: Feature tests.

5. **resources/**: Resource files.
   - **views/**: Blade templates.

6. **routes/**: Route files.
   - **web.php**: Web routes.

7. **public/**: Public assets.
   - **assets/**: Static assets like CSS and JavaScript.

### Additional Files:

- **.gitignore**: Specifies intentionally untracked files to ignore.
- **CHANGELOG.md**: Keeps track of changes.
- **CONTRIBUTING.md**: Guidelines for contributing to the package.
- **LICENSE**: License file.
- **PLAN.md**: Project plan.
- **README.md**: Project documentation.
- **composer.json**: Composer configuration.
- **composer.lock**: Composer lock file.
- **setup-test-db.php**: Script for setting up the test database.
- **test-generator.php**: Test script for the generator.
