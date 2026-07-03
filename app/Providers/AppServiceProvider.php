<?php

namespace App\Providers;

use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageGenerationManager::class, function ($app) {
            return new ImageGenerationManager($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
