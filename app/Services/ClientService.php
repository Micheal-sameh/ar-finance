<?php

namespace App\Services;

use App\DTOs\CreateClientData;
use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->clients->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->clients->all();
    }

    public function create(CreateClientData $data): Client
    {
        return $this->clients->create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'tax_number' => $data->taxNumber,
            'address' => $data->address,
            'currency' => $data->currency,
        ]);
    }

    public function update(Client $client, CreateClientData $data): Client
    {
        return $this->clients->update($client, [
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'tax_number' => $data->taxNumber,
            'address' => $data->address,
            'currency' => $data->currency,
        ]);
    }

    public function delete(Client $client): void
    {
        if ($this->clients->hasInvoices($client)) {
            throw new RuntimeException("Client {$client->name} cannot be deleted: it has invoices on file.");
        }

        $this->clients->delete($client);
    }
}
