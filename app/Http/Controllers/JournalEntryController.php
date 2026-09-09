<?php

namespace App\Http\Controllers;

use App\Exceptions\UnbalancedJournalEntryException;
use App\Http\Requests\Journals\StoreJournalEntryRequest;
use App\Models\JournalEntry;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        return Inertia::render('Accounting/Journals/Index', [
            'entries' => $this->journals->paginate($request->only(['source_type', 'from', 'to', 'search'])),
            'filters' => $request->only(['source_type', 'from', 'to', 'search']),
        ]);
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
