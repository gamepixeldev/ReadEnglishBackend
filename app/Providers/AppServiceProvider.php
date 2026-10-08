<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Artisan starts a child PHP process and otherwise drops PHPRC on Windows.
        if ($this->app->environment('local')) {
            ServeCommand::$passthroughVariables[] = 'PHPRC';
        }
    }
}
