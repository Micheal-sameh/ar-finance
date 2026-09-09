<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vendors\SaveVendorRequest;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorService $vendors,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vendor::class);

        return Inertia::render('Contacts/Vendors/Index', [
            'vendors' => $this->vendors->paginate($request->only(['search'])),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(SaveVendorRequest $request): RedirectResponse
    {
        $this->vendors->create($request->toDto());

        return redirect()->route('vendors.index')->with('success', 'Vendor created.');
    }

    public function update(SaveVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $this->vendors->update($vendor, $request->toDto());

        return redirect()->route('vendors.index')->with('success', 'Vendor updated.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $this->authorize('delete', $vendor);

        try {
            $this->vendors->delete($vendor);
        } catch (RuntimeException $e) {
            return redirect()->route('vendors.index')->with('error', $e->getMessage());
        }

        return redirect()->route('vendors.index')->with('success', 'Vendor deleted.');
    }
}
