<?php

use App\Modules\Booking\Providers\BookingServiceProvider;
use App\Modules\Donations\Providers\DonationsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    BookingServiceProvider::class,
    DonationsServiceProvider::class,
];
