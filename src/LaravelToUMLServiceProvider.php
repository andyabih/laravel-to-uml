<?php

namespace Andyabih\LaravelToUML;

use Illuminate\Support\ServiceProvider;

class LaravelToUMLServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->bind('laravel-to-uml', function($app) {
            return new LaravelToUML();
        });
        
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-to-uml.php', 'laravel-to-uml');
    }

    public function boot() {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-to-uml');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-to-uml.php' => config_path('laravel-to-uml.php'),
            ], 'config');
        }
    }
}