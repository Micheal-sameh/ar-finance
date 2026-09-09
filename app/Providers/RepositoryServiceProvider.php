<?php

namespace App\Providers;

use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\JournalRepositoryInterface;
use App\Repositories\Eloquent\EloquentAccountRepository;
use App\Repositories\Eloquent\EloquentJournalRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccountRepositoryInterface::class, EloquentAccountRepository::class);
        $this->app->bind(JournalRepositoryInterface::class, EloquentJournalRepository::class);
    }
}
