<?php

namespace App\Providers;

use App\Services\Color\DataPaths;
use App\Services\Color\SeedHexIndex;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DataPaths::class, fn (): DataPaths => DataPaths::make());
        $this->app->singleton(SeedHexIndex::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
