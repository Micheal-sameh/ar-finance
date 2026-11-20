<?php

use App\Modules\Booking\Providers\BookingServiceProvider;
use App\Modules\Donations\Providers\DonationsServiceProvider;
use App\Modules\Treasury\Providers\TreasuryServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    BookingServiceProvider::class,
    DonationsServiceProvider::class,
    TreasuryServiceProvider::class,
];
