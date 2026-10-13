<?php

namespace App\Modules\Booking\Providers;

use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Policies\BookableItemPolicy;
use App\Modules\Booking\Policies\BookingPolicy;
use App\Modules\Booking\Repositories\Contracts\BookableItemRepositoryInterface;
use App\Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use App\Modules\Booking\Repositories\Eloquent\EloquentBookableItemRepository;
use App\Modules\Booking\Repositories\Eloquent\EloquentBookingRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Self-contained registration for the Booking module (app/Modules/Booking):
 * repository bindings, policies — since the models live outside App\Models,
 * Laravel's policy auto-discovery can't find them — and the module's routes.
 */
class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookableItemRepositoryInterface::class, EloquentBookableItemRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(BookableItem::class, BookableItemPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
