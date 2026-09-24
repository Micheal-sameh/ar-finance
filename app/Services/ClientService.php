<?php

namespace App\Services;

use App\DTOs\CreateClientData;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * @phpstan-type ClientSummary array{invoice_count: int, total_invoiced: float, total_paid: float, outstanding: float}
 */
class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {}

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

    /**
     * @return Collection<int, Invoice>
     */
    public function invoiceHistory(Client $client): Collection
    {
        return $client->invoices()
            ->with(['lines', 'receivableAccount'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return ClientSummary
     */
    public function summarize(Collection $invoices): array
    {
        $billed = $invoices->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Paid, InvoiceStatus::Overdue]);
        $paid = $invoices->where('status', InvoiceStatus::Paid);
        $outstanding = $invoices->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue]);

        return [
            'invoice_count' => $invoices->count(),
            'total_invoiced' => round($billed->sum(fn (Invoice $invoice) => $invoice->total()), 2),
            'total_paid' => round($paid->sum(fn (Invoice $invoice) => $invoice->total()), 2),
            'outstanding' => round($outstanding->sum(fn (Invoice $invoice) => $invoice->total()), 2),
        ];
    }

    public function delete(Client $client): void
    {
        if ($this->clients->hasInvoices($client)) {
            throw new RuntimeException("Client {$client->name} cannot be deleted: it has invoices on file.");
        }

        $this->clients->delete($client);
    }
}
