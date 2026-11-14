<?php

namespace App\Modules\Donations\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesPdf;
use App\Http\Controllers\Controller;
use App\Modules\Donations\Http\Requests\CancelDonationRequest;
use App\Modules\Donations\Http\Requests\ListDonationsRequest;
use App\Modules\Donations\Http\Requests\ShowDonationReceiptRequest;
use App\Modules\Donations\Http\Requests\StoreDonationRequest;
use App\Modules\Donations\Http\Resources\DonationResource;
use App\Modules\Donations\Models\Donation;
use App\Modules\Donations\Services\DonationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonationController extends Controller
{
    use GeneratesPdf;

    public function __construct(
        private readonly DonationService $donations,
    ) {}

    public function index(ListDonationsRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('Donations/Index', [
            'donations' => $this->donations->paginate($filters)
                ->through(fn (Donation $donation) => (new DonationResource($donation))->resolve()),
            'filters' => $filters,
            'options' => $this->donations->formOptions(),
            'canCreate' => $request->user()->can('donations.create'),
        ]);
    }

    public function store(StoreDonationRequest $request): RedirectResponse
    {
        try {
            $donation = $this->donations->create($request->toDto(), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()
            ->with('success', "Donation recorded — receipt #{$donation->receipt_number}.")
            ->with('receipt_url', route('donations.receipt', $donation));
    }

    public function cancel(CancelDonationRequest $request, Donation $donation): RedirectResponse
    {
        try {
            $this->donations->cancel($donation, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Donation cancelled and its journal entries reversed.');
    }

    public function receipt(ShowDonationReceiptRequest $request, Donation $donation): StreamedResponse|RedirectResponse
    {
        try {
            $data = $this->donations->receiptData($donation);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $filename = $this->donations->receiptFilename($donation);
        $response = $this->downloadPdf($filename, 'pdf.donation-receipt', $data);

        if ($request->inline()) {
            $response->headers->set('Content-Disposition', "inline; filename=\"{$filename}\"");
        }

        return $response;
    }
}
