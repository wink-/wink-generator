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
            __DIR__ . '/../config/generator.php' => config_path('generator.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/generator.php',
            'generator'
        );
    }
}
