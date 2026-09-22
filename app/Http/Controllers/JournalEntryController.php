<?php

namespace App\Http\Controllers;

use App\Exceptions\UnbalancedJournalEntryException;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Journals\StoreJournalEntryRequest;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalEntryController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly JournalService $journals,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        return Inertia::render('Accounting/Journals/Index', [
            'entries' => $this->journals->paginate($request->only(['source_type', 'created_by', 'from', 'to', 'search'])),
            'filters' => $request->only(['source_type', 'created_by', 'from', 'to', 'search']),
            'authorOptions' => User::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $entries = $this->journals->paginate($request->only(['source_type', 'created_by', 'from', 'to', 'search']), $this->exportMaxRows());

        $rows = collect($entries->items())->map(fn (JournalEntry $entry) => [
            $entry->date->toDateString(),
            $entry->description,
            $entry->reference,
            $entry->source_type->value,
            (float) $entry->lines->sum('debit'),
        ]);

        return $this->exportXlsx('journal-entries.xlsx', ['Date', 'Description', 'Reference', 'Source', 'Amount'], $rows);
    }

    public function create(): Response
    {
        $this->authorize('create', JournalEntry::class);

        return Inertia::render('Accounting/Journals/Create');
    }

    public function show(JournalEntry $journal): Response
    {
        $this->authorize('view', $journal);

        return Inertia::render('Accounting/Journals/Show', [
            'entry' => $this->journals->find($journal->id),
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        try {
            $entry = $this->journals->postJournalEntry($request->toDto());
        } catch (UnbalancedJournalEntryException $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('journals.show', $entry)->with('success', 'Journal entry posted.');
    }
}
