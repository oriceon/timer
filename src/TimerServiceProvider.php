<?php

namespace Oriceon\Timer;

use Illuminate\Support\ServiceProvider;

class TimerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('timer.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'timer');

        // Register the main class to use with the facade
        $this->app->singleton('timer', function () {
            return new Timer;
        });
    }
}
