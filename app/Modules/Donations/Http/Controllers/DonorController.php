<?php

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donations\Http\Requests\ListDonorsRequest;
use App\Modules\Donations\Http\Requests\SaveDonorRequest;
use App\Modules\Donations\Http\Resources\DonorResource;
use App\Modules\Donations\Models\Donor;
use App\Modules\Donations\Services\DonorService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DonorController extends Controller
{
    public function __construct(
        private readonly DonorService $donors,
    ) {}

    public function index(ListDonorsRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('Donations/Donors', [
            'donors' => $this->donors->paginate($filters)
                ->through(fn (Donor $donor) => (new DonorResource($donor))->resolve()),
            'filters' => $filters,
            'canCreate' => $request->user()->can('donations.create'),
            'canManage' => $request->user()->can('donations.manage'),
        ]);
    }

    public function store(SaveDonorRequest $request): RedirectResponse
    {
        $this->donors->create($request->toDto());

        return back()->with('success', 'Donor added.');
    }

    public function update(SaveDonorRequest $request, Donor $donor): RedirectResponse
    {
        $this->donors->update($donor, $request->toDto());

        return back()->with('success', 'Donor updated.');
    }
}
