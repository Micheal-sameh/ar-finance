<?php

namespace App\Providers;

use App\Listeners\SyncAvarewaseRoleClaims;
use App\Support\ResilientVite;
use Avarewase\SsoClient\Events\AvarewaseUserAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Vite::class, ResilientVite::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        JsonResource::withoutWrapping();

        Event::listen(AvarewaseUserAuthenticated::class, SyncAvarewaseRoleClaims::class);
    }
}
