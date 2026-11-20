<?php

namespace App\Modules\Treasury\Providers;

use App\Modules\Treasury\Models\TreasuryTransfer;
use App\Modules\Treasury\Policies\TreasuryTransferPolicy;
use App\Modules\Treasury\Repositories\Contracts\TreasuryTransferRepositoryInterface;
use App\Modules\Treasury\Repositories\Eloquent\EloquentTreasuryTransferRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Self-contained registration for the Treasury module
 * (app/Modules/Treasury): repository binding, policy and routes.
 */
class TreasuryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TreasuryTransferRepositoryInterface::class, EloquentTreasuryTransferRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(TreasuryTransfer::class, TreasuryTransferPolicy::class);

        // TreasuryTransfer has no tenant scope (it lives in two ledgers), so
        // binding goes through the repository, which only finds transfers
        // the current tenant is a party to — anything else is a 404.
        Route::bind('treasury_transfer', fn (string $value) => $this->app->make(TreasuryTransferRepositoryInterface::class)->find((int) $value) ?? abort(404));

        Route::middleware('web')->group(__DIR__.'/../routes/web.php');
    }
}
