<?php

namespace App\Modules\Donations\Providers;

use App\Modules\Donations\Models\Donation;
use App\Modules\Donations\Models\Donor;
use App\Modules\Donations\Policies\DonationPolicy;
use App\Modules\Donations\Policies\DonorPolicy;
use App\Modules\Donations\Repositories\Contracts\DonationRepositoryInterface;
use App\Modules\Donations\Repositories\Contracts\DonorRepositoryInterface;
use App\Modules\Donations\Repositories\Eloquent\EloquentDonationRepository;
use App\Modules\Donations\Repositories\Eloquent\EloquentDonorRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Self-contained registration for the Donations module
 * (app/Modules/Donations): repository bindings, policies — since the models
 * live outside App\Models, Laravel's policy auto-discovery can't find them —
 * and the module's routes.
 */
class DonationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DonationRepositoryInterface::class, EloquentDonationRepository::class);
        $this->app->bind(DonorRepositoryInterface::class, EloquentDonorRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Donation::class, DonationPolicy::class);
        Gate::policy(Donor::class, DonorPolicy::class);

        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
