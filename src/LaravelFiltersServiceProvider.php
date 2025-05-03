<?php

namespace HumamK98\LaravelFilters;

use Illuminate\Support\ServiceProvider;
use HumamK98\LaravelFilters\Generators\FilterGenerator;
use HumamK98\LaravelFilters\Filters\DynamicFilterResolver;

class LaravelFiltersServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register the filter generator
        $this->app->singleton(FilterGenerator::class, function ($app) {
            return new FilterGenerator();
        });
        
        // Register the dynamic filter resolver
        $this->app->singleton(DynamicFilterResolver::class, function ($app) {
            return new DynamicFilterResolver($app->make(FilterGenerator::class));
        });
        
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-filters.php', 'laravel-filters');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Optional configuration publishing
        $this->publishes([
            __DIR__.'/../config/laravel-filters.php' => config_path('laravel-filters.php'),
        ], 'laravel-filters-config');
        
        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // You can add Artisan commands here if needed
            ]);
        }
    }
}