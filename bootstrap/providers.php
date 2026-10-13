<?php

use App\Modules\Booking\Providers\BookingServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    BookingServiceProvider::class,
];
