<?php

namespace App\Repositories\Contracts;

use App\Models\ReceivedPayment;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReceivedPaymentRepositoryInterface
{
    /**
     * @param  array{from?: string, to?: string, search?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?ReceivedPayment;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ReceivedPayment;
}
