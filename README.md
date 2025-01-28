# wink-generator

## Overview

wink-generator is a Laravel package designed to automate the generation of models and policies based on your database schema. This package simplifies the process of maintaining consistent and up-to-date models and policies, ensuring that your application remains organized and efficient.

## Features

- Automatically generate models and policies from your database schema.
- Customizable configuration options to tailor the generated code to your project's needs.
- Easy integration with existing Laravel applications.

## Installation

### Step 1: Install via Composer

Run the following command to install the package via Composer:

```bash
composer require wink-/wink-generator
```

### Step 2: Publish Configuration

Publish the configuration file to customize the package settings:

```bash
php artisan vendor:publish --provider="Wink\Generator\GeneratorServiceProvider"
```

## Configuration

The configuration file is located at `config/generator.php`. You can customize the following options:

- `models_namespace`: The namespace for generated models.
- `policies_namespace`: The namespace for generated policies.
- `schema_reader`: The schema reader to use (e.g., MySQL, SQLite).

Example configuration:

```php
return [
    'models_namespace' => 'App\Models',
    'policies_namespace' => 'App\Policies',
    'schema_reader' => 'mysql',
];
```

## Usage

### Generating Models and Policies

Run the following Artisan command to generate models and policies based on your database schema:

```bash
php artisan wink:generate-models
```

### Options

The following options are available for the `wink:generate-models` command:

- `--connection`: The database connection to use (default: `config("database.default")`).
- `--directory`: The directory to output generated files (relative to Models path, default: `GeneratedModels`).
- `--factories`: Generate factories for the models.
- `--policies`: Generate policies for the models.
- `--controllers`: Generate controllers for the models.
- `--resource`: Generate resource controllers (implies `--controllers`).

### Customizing the Generation Process

You can customize the generation process by modifying the configuration file and creating custom schema readers if needed.

## Example

Here's an example of how to use the wink-generator package in your Laravel application:

1. **Install the package** using Composer:

    ```bash
    composer require winkk/wink-generator
    ```

2. **Publish the configuration** file:

    ```bash
    php artisan vendor:publish --provider="Winkk\Generator\GeneratorServiceProvider"
    ```

3. **Customize the configuration** file located at `config/generator.php`:

    ```php
    return [
        'models_namespace' => 'App\Models',
        'policies_namespace' => 'App\Policies',
        'schema_reader' => 'mysql',
    ];
    ```

4. **Generate models and policies** using the Artisan command with options:

    ```bash
    php artisan wink:generate-models --connection=mysql --directory=GeneratedModels --factories --policies --controllers --resource
    ```

## Contributing

Contributions are welcome! Please follow the [contribution guidelines](CONTRIBUTING.md) to get started.

## License

This package is open-source software licensed under the [MIT license](LICENSE).

## Contact

For any questions or support, please contact [support email].
