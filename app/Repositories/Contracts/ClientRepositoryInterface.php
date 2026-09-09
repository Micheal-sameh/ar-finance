<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int $id): ?Client;

    public function create(array $attributes): Client;

    public function update(Client $client, array $attributes): Client;

    public function delete(Client $client): bool;

    public function hasInvoices(Client $client): bool;
}
