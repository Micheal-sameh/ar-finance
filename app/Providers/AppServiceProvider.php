<?php

namespace App\Providers;

use App\Listeners\AssignDefaultsToNewAvarewaseUsers;
use App\Services\ExchangeRates\ExchangeRateProviderInterface;
use App\Services\ExchangeRates\FreeCurrencyApiProvider;
use App\Support\ResilientVite;
use Avarewase\SsoClient\Events\AvarewaseUserAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ( app()->environment('production')) {
            URL::forceHttps();
        }
        $this->app->singleton(Vite::class, ResilientVite::class);
        $this->app->bind(ExchangeRateProviderInterface::class, FreeCurrencyApiProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        JsonResource::withoutWrapping();

        Event::listen(AvarewaseUserAuthenticated::class, AssignDefaultsToNewAvarewaseUsers::class);
    }
}
