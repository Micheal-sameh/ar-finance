<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clients\SaveClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly ExchangeRateService $exchangeRates,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        return Inertia::render('Contacts/Clients/Index', [
            'clients' => $this->clients->paginate($request->only(['search'])),
            'filters' => $request->only(['search']),
            'currencyOptions' => $this->exchangeRates->currencyOptions(),
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
