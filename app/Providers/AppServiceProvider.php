<?php

namespace App\Providers;

use App\Services\ImageGeneration\ImageGenerationManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use App\Models\Booking;
use App\Observers\BookingObserver;
use Illuminate\Support\Facades\RateLimiter;
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

        // Herbereken de zero-emissiezone-vlag bij nieuwe of adres-gewijzigde boekingen.
        Booking::observe(BookingObserver::class);

        // Rate limit voor de booth-API: 60 req/min per token (val terug op IP als er geen token is).
        RateLimiter::for('booth', function (Request $request) {
            $token = $request->bearerToken();
            return Limit::perMinute(60)->by($token ? sha1($token) : $request->ip());
        });
    }
}
