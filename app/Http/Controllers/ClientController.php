<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Clients\SaveClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly ClientService $clients,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        return Inertia::render('Contacts/Clients/Index', [
            'clients' => $this->clients->paginate($request->only(['currency', 'search'])),
            'filters' => $request->only(['currency', 'search']),
            'currencyOptions' => $this->exchangeRates->currencyOptions(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Client::class);

        $clients = $this->clients->paginate($request->only(['currency', 'search']), $this->exportMaxRows());

        $rows = collect($clients->items())->map(fn (Client $client) => [
            $client->name,
            $client->email,
            $client->phone,
            $client->currency,
        ]);

        return $this->exportXlsx('clients.xlsx', ['Name', 'Email', 'Phone', 'Currency'], $rows);
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);

        $invoices = $this->clients->invoiceHistory($client);

        return Inertia::render('Contacts/Clients/Show', [
            'client' => $client,
            'invoices' => $invoices,
            'summary' => $this->clients->summarize($invoices),
        ]);
    }

    public function store(SaveClientRequest $request): RedirectResponse
    {
        $this->clients->create($request->toDto());

        return redirect()->route('clients.index')->with('success', 'Client created.');
    }

    public function update(SaveClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $this->clients->update($client, $request->toDto());

        return redirect()->route('clients.index')->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        try {
            $this->clients->delete($client);
        } catch (RuntimeException $e) {
            return redirect()->route('clients.index')->with('error', $e->getMessage());
        }

        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }
}
