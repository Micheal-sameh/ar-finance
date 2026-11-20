<?php

namespace App\Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Treasury\Enums\TreasuryTransferStatus;
use App\Modules\Treasury\Enums\TreasuryTransferType;
use App\Modules\Treasury\Http\Requests\AcceptTreasuryTransferRequest;
use App\Modules\Treasury\Http\Requests\FundTenantRequest;
use App\Modules\Treasury\Http\Requests\ListTreasuryTransfersRequest;
use App\Modules\Treasury\Http\Requests\MoveTreasuryFundsRequest;
use App\Modules\Treasury\Http\Requests\RejectTreasuryTransferRequest;
use App\Modules\Treasury\Http\Requests\RequestTreasuryTransferRequest;
use App\Modules\Treasury\Http\Requests\VoidTreasuryTransferRequest;
use App\Modules\Treasury\Http\Resources\TreasuryTransferResource;
use App\Modules\Treasury\Models\TreasuryTransfer;
use App\Modules\Treasury\Services\TreasuryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $treasury,
    ) {}

    public function index(ListTreasuryTransfersRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('Treasury/Index', [
            'summary' => $this->treasury->dashboard(),
            'transfers' => $this->treasury->paginate($filters)
                ->through(fn (TreasuryTransfer $transfer) => (new TreasuryTransferResource($transfer))->resolve()),
            'filters' => $filters,
            'options' => $this->treasury->formOptions() + [
                'types' => array_map(fn (TreasuryTransferType $type) => ['value' => $type->value, 'label' => $type->label()], TreasuryTransferType::cases()),
                'statuses' => array_map(fn (TreasuryTransferStatus $status) => ['value' => $status->value, 'label' => $status->label()], TreasuryTransferStatus::cases()),
            ],
            'canRequest' => $request->user()->can('request', TreasuryTransfer::class),
            'canManage' => $request->user()->can('manage', TreasuryTransfer::class),
        ]);
    }

    public function request(RequestTreasuryTransferRequest $request): RedirectResponse
    {
        try {
            $transfer = $this->treasury->request($request->toDto(), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$transfer->type->label()} requested — the treasurer has to accept it before anything is posted.");
    }

    public function fund(FundTenantRequest $request): RedirectResponse
    {
        try {
            $this->treasury->fund($request->toDto(), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Funding posted in both ledgers.');
    }

    public function move(MoveTreasuryFundsRequest $request): RedirectResponse
    {
        try {
            $this->treasury->move($request->toDto(), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Money moved.');
    }

    public function accept(AcceptTreasuryTransferRequest $request, TreasuryTransfer $treasuryTransfer): RedirectResponse
    {
        try {
            $this->treasury->accept($treasuryTransfer->id, $request->acceptedAmount(), $request->treasuryAccountId(), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Accepted and posted in both ledgers.');
    }

    public function reject(RejectTreasuryTransferRequest $request, TreasuryTransfer $treasuryTransfer): RedirectResponse
    {
        try {
            $this->treasury->reject($treasuryTransfer->id, $request->reason(), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Request rejected — nothing was posted.');
    }

    public function void(VoidTreasuryTransferRequest $request, TreasuryTransfer $treasuryTransfer): RedirectResponse
    {
        try {
            $this->treasury->void($treasuryTransfer->id, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer voided and its entries reversed in both ledgers.');
    }
}
