<?php

namespace Wink\Generator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Wink\Generator\Schema\SchemaReaderFactory;

class GenerateModelsCommand extends Command
{
    protected $signature = 'wink:generate-models
                            {--connection= : The database connection to use (default: config("database.default"))}
                            {--directory=GeneratedModels : The directory to output generated files (relative to Models path)}
                            {--factories : Generate factories for the models}
                            {--policies : Generate policies for the models}
                            {--controllers : Generate controllers for the models}
                            {--resource : Generate resource controllers (implies --controllers)}';

    protected $description = 'Generate models, factories, policies, and controllers from an existing database connection';

    public function handle()
    {
        $app = app();
        $connectionName = $this->option('connection') ?? $app['config']->get('database.default');
        $modelsPath = $app['config']->get('generator.models_path', 'Models');
        $directory = $this->option('directory') ?? 'GeneratedModels';
        $namespaceDirectory = str_replace('/', '\\', $directory);
        $outputDirectory = $app['path.app'] . '/' . $modelsPath . '/' . $directory;
        $generateFactories = $this->option('factories');
        $generatePolicies = $this->option('policies');
        $generateControllers = $this->option('controllers') || $this->option('resource');
        $generateResourceControllers = $this->option('resource');

        $excludedTables = $app['config']->get('generator.excluded_tables', []);

        try {
            DB::connection($connectionName)->getPdo();
        } catch (\Exception $e) {
            $this->error("Could not connect to database: " . $e->getMessage());
            return;
        }

        $connection = DB::connection($connectionName);
        $schemaReader = SchemaReaderFactory::make($connection);

        $filteredTableNames = collect($schemaReader->getTables())
            ->reject(function ($tableName) use ($excludedTables) {
                return in_array($tableName, $excludedTables);
            })->toArray();

        File::makeDirectory($outputDirectory, 0755, true, true);
        if ($generateFactories) {
            File::makeDirectory($app['path.database'] . '/factories/' . str_replace($app['path.app'] . '/', '', $outputDirectory), 0755, true, true);
        }
        if ($generatePolicies) {
            File::makeDirectory($app['path.app'] . '/Policies/' . str_replace($app['path.app'] . '/', '', $outputDirectory), 0755, true, true);
        }
        if ($generateControllers) {
            File::makeDirectory($app['path.app'] . '/Http/Controllers/' . str_replace($app['path.app'] . '/', '', $outputDirectory), 0755, true, true);
        }

        $routeContent = "<?php\n\n";

        foreach ($filteredTableNames as $tableName) {
            $modelName = Str::studly(Str::singular($tableName));
            $this->info("Generating model for table: {$tableName} as {$modelName}");

            $columns = $schemaReader->getColumns($tableName);
            $modelContent = $this->generateModelContent($modelName, $connectionName, $tableName, $columns, $namespaceDirectory);
            File::put($outputDirectory . '/' . $modelName . '.php', $modelContent);
            $this->info("Model {$modelName} generated at: " . $outputDirectory . '/' . $modelName . '.php');

            if ($generateFactories) {
                $factoryContent = $this->generateFactoryContent($modelName, str_replace($app['path.app'] . '/', '', $outputDirectory));
                File::put($app['path.database'] . '/factories/' . str_replace($app['path.app'] . '/', '', $outputDirectory) . '/' . $modelName . 'Factory.php', $factoryContent);
                $this->info("Factory {$modelName}Factory generated.");
            }

            if ($generatePolicies) {
                $policyContent = $this->generatePolicyContent($modelName, str_replace($app['path.app'] . '/', '', $outputDirectory));
                File::put($app['path.app'] . '/Policies/' . str_replace($app['path.app'] . '/', '', $outputDirectory) . '/' . $modelName . 'Policy.php', $policyContent);
                $this->info("Policy {$modelName}Policy generated.");
            }

            if ($generateControllers) {
                $controllerPath = str_replace($app['path.app'] . '/', '', $outputDirectory);
                $controllerNamespace = str_replace('/', '\\', $controllerPath);
                $controllerContent = $this->generateControllerContent($modelName, $controllerNamespace, $generateResourceControllers);
                $controllerFilename = $app['path.app'] . '/Http/Controllers/' . $controllerPath . '/' . $modelName . 'Controller.php';
                File::put($controllerFilename, $controllerContent);
                $this->info("Controller {$modelName}Controller generated at: " . $controllerFilename);

                if ($generateResourceControllers) {
                    $routeName = Str::kebab(Str::pluralStudly($modelName));
                    $routeContent .= "Route::resource('{$routeName}', \\App\\Http\\Controllers\\" . str_replace($app['path.app'] . '/', '', $outputDirectory) . "\\{$modelName}Controller::class);\n";
                }
            }
        }

        if ($generateControllers && $generateResourceControllers) {
            File::put($outputDirectory . '/GeneratedRoutes.php', $routeContent);
            $this->info("Generated routes appended to: " . $outputDirectory . '/GeneratedRoutes.php');
        }

        $this->info('All models and related files generated successfully!');
    }

    protected function mapColumnTypeToPhpAndCast(string $columnType): array
    {
        $typeMap = [
            'bigint' => ['int', 'integer'],
            'int' => ['int', 'integer'],
            'integer' => ['int', 'integer'],
            'smallint' => ['int', 'integer'],
            'tinyint' => ['int', 'integer'],
            'decimal' => ['float', 'decimal'],
            'float' => ['float', 'float'],
            'double' => ['float', 'float'],
            'boolean' => ['bool', 'boolean'],
            'datetime' => ['string', 'datetime'],
            'date' => ['string', 'date'],
            'time' => ['string', 'datetime'],
            'timestamp' => ['string', 'timestamp'],
            'json' => ['array', 'json'],
            'text' => ['string', null],
            'string' => ['string', null],
            'varchar' => ['string', null],
            'char' => ['string', null],
            'uuid' => ['string', null],
        ];

        $type = strtolower($columnType);
        return $typeMap[$type] ?? ['mixed', null];
    }

    protected function generateModelContent(string $modelName, string $connectionName, string $tableName, array $columns, string $namespaceDirectory): string
    {
        $properties = [];
        $fillable = [];
        $casts = [];

        foreach ($columns as $column) {
            [$phpType, $cast] = $this->mapColumnTypeToPhpAndCast($column->type);
            $properties[] = " * @property {$phpType} \${$column->name}";

            if (!in_array($column->name, ['id', 'created_at', 'updated_at'])) {
                $fillable[] = "'" . $column->name . "'";
            }

            if ($cast !== null) {
                $casts[] = "'" . $column->name . "' => '" . $cast . "'";
            }
        }

        $propertiesStr = implode("\n", $properties);
        $fillableStr = implode(", ", $fillable);
        $castsStr = implode(",\n        ", $casts);

        return <<<PHP
<?php

namespace App\Models\\{$namespaceDirectory};

use Illuminate\Database\Eloquent\Model;

/**
{$propertiesStr}
 */
class {$modelName} extends Model
{
    protected \$connection = '{$connectionName}';
    protected \$table = '{$tableName}';
    protected \$fillable = [{$fillableStr}];
    protected \$casts = [
        {$castsStr}
    ];
}
PHP;
    }

    protected function generateFactoryContent(string $modelName, string $namespaceDirectory): string
    {
        $model = $modelName;
        $password = Hash::make('password');

        return <<<PHP
<?php

namespace Database\Factories\\{$namespaceDirectory};

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\\{$namespaceDirectory}\\{$model};

class {$modelName}Factory extends Factory
{
    protected \$model = {$model}::class;

    public function definition(): array
    {
        \$columns = \$this->getColumnsForTable($modelName);
        \$attributes = [];

        foreach (\$columns as \$column) {
            \$attributes[\$column->name] = \$this->getFakerDataForColumn(\$column);
        }

        return \$attributes;
    }

    protected function getColumnsForTable(string \$modelName): array
    {
        // Retrieve the schema reader instance
        \$schemaReader = SchemaReaderFactory::make(DB::connection(\$this->option('connection') ?? config('database.default')));

        // Get the table name from the model name
        \$tableName = Str::snake(Str::plural($modelName));

        // Get the columns for the table
        return \$schemaReader->getColumns($tableName);
    }

    protected function getFakerDataForColumn(object \$column): mixed
    {
        switch (\$column->type) {
            case 'string':
                return fake()->word;
            case 'text':
                return fake()->paragraph;
            case 'integer':
                return fake()->randomNumber;
            case 'boolean':
                return fake()->boolean;
            case 'date':
                return fake()->date;
            case 'datetime':
                return fake()->dateTime;
            case 'time':
                return fake()->time;
            case 'float':
                return fake()->randomFloat;
            case 'decimal':
                return fake()->randomFloat;
            case 'json':
                return fake()->json;
            case 'uuid':
                return fake()->uuid;
            default:
                return null;
        }
    }
}
PHP;
    }

    protected function generatePolicyContent(string $modelName, string $namespaceDirectory): string
    {
        return <<<PHP
<?php

namespace App\Policies\\{$namespaceDirectory};

use Illuminate\Auth\Access\HandlesAuthorization;

class {$modelName}Policy
{
    use HandlesAuthorization;

    public function viewAny(\$user)
    {
        return true;
    }

    public function view(\$user, \$model)
    {
        return true;
    }

    public function create(\$user)
    {
        return true;
    }

    public function update(\$user, \$model)
    {
        return true;
    }

    public function delete(\$user, \$model)
    {
        return true;
    }
}
PHP;
    }

    protected function generateControllerContent(string $modelName, string $namespaceDirectory, bool $resource = false): string
    {
        if ($resource) {
            return <<<PHP
<?php

namespace App\Http\Controllers\\{$namespaceDirectory};

use App\Models\\{$namespaceDirectory}\\{$modelName};
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class {$modelName}Controller extends Controller
{
    public function index()
    {
        return {$modelName}::all();
    }

    public function store(Request \$request)
    {
        return {$modelName}::create(\$request->all());
    }

    public function show({$modelName} \$model)
    {
        return \$model;
    }

    public function update(Request \$request, {$modelName} \$model)
    {
        \$model->update(\$request->all());
        return \$model;
    }

    public function destroy({$modelName} \$model)
    {
        \$model->delete();
        return response()->noContent();
    }
}
PHP;
        }

        return <<<PHP
<?php

namespace App\Http\Controllers\\{$namespaceDirectory};

use App\Models\\{$namespaceDirectory}\\{$modelName};
use App\Http\Controllers\Controller;

class {$modelName}Controller extends Controller
{
    // Add your controller methods here
}
PHP;
    }
}
