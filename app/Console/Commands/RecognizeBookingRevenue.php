<?php

namespace App\Console\Commands;

use App\Modules\Booking\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class RecognizeBookingRevenue extends Command
{
    protected $signature = 'bookings:recognize-revenue';

    protected $description = "Move every finished booking's collected money out of deferred revenue into actual revenue";

    public function handle(BookingService $bookings): int
    {
        // Console runs have no authenticated user, but journal "created_by"
        // and tenant scoping both key off auth()->user() — so we
        // impersonate the system user for the duration of this command,
        // same as PostMonthlyDepreciation.
        Auth::onceUsingId(config('app.system_user_id'));

        if (! Auth::check()) {
            $this->error('Cannot recognize booking revenue: system user (app.system_user_id) not found.');

            return self::FAILURE;
        }

        $results = $bookings->recognizeRevenueForEndedBookings();

        $recognized = 0;
        foreach ($results as $result) {
            if ($result['recognized']) {
                $recognized++;
                $this->info("Recognized revenue for Booking #{$result['booking']->id}");
            } else {
                $this->line("Skipped Booking #{$result['booking']->id}: {$result['message']}");
            }
        }

        $this->info('Recognized revenue for '.$recognized.' of '.count($results).' booking(s).');

        return self::SUCCESS;
    }
}
