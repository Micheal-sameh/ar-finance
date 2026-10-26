<?php

namespace App\Repositories\Contracts;

use App\Models\Bill;
use App\Models\BillPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BillRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?Bill;

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): Bill;

    public function updateStatus(Bill $bill, string $status, ?\DateTimeInterface $paidAt = null): Bill;

    /**
     * Approved/paid bills dated in a range, lines eager-loaded — for the
     * VAT return report (input VAT).
     */
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPayment(Bill $bill, array $attributes): BillPayment;

    /**
     * Locks the bill row for the rest of the transaction and returns it
     * fresh with lines and payments — two people paying at once must not
     * both see the same balance.
     */
    public function findForPayment(int $id): ?Bill;

    /**
     * Approved and partially paid bills, with lines and payments loaded.
     */
    public function payableByIds(array $ids): Collection;

    public function postedBetween(string $from, string $to): Collection;

    /**
     * Approved and partially paid bills (posted, still owing something),
     * vendor, lines and payments eager-loaded — the candidate set for AP
     * aging, which buckets each bill's remaining balance.
     */
    public function outstanding(): Collection;
}
