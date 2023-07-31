<?php

namespace NiclasTimm\LaravelDbImporter;

use Illuminate\Support\ServiceProvider;
use NiclasTimm\LaravelDbImporter\Console\ImportDb;

class DbImporterServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'dbimporter');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('dbimporter.php'),
            ], 'config');

            $this->commands([
                ImportDb::class,
            ]);

        }
    }
}