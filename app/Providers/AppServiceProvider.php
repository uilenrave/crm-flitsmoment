<?php

namespace App\Providers;

use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Pagination\Paginator;
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
        // Eigen, app-gestylede paginatie i.p.v. Laravels Tailwind-default (de app draait geen Tailwind,
        // waardoor de standaard pijl-SVG's onbeperkt groot renderden en de knoppen dubbel toonden).
        Paginator::defaultView('vendor.pagination.app');
        Paginator::defaultSimpleView('vendor.pagination.app');
    }
}
