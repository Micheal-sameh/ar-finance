<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * The real KPI dashboard lands in a later phase; for now route
     * straight to the Chart of Accounts, the first real screen.
     */
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('accounts.index');
    }
}
