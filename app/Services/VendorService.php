<?php

namespace App\Services;

use App\DTOs\CreateVendorData;
use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * @phpstan-type VendorSummary array{bill_count: int, total_billed: float, total_paid: float, outstanding: float}
 */
class VendorService
{
    public function __construct(
        private readonly VendorRepositoryInterface $vendors,
    ) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->vendors->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->vendors->all();
    }

    public function create(CreateVendorData $data): Vendor
    {
        return $this->vendors->create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data->name,
            'email' => $data->email,
            'tax_number' => $data->taxNumber,
            'payment_terms' => $data->paymentTerms,
        ]);
    }

    public function update(Vendor $vendor, CreateVendorData $data): Vendor
    {
        return $this->vendors->update($vendor, [
            'name' => $data->name,
            'email' => $data->email,
            'tax_number' => $data->taxNumber,
            'payment_terms' => $data->paymentTerms,
        ]);
    }

    /**
     * @return Collection<int, Bill>
     */
    public function billHistory(Vendor $vendor): Collection
    {
        return $vendor->bills()
            ->with(['lines', 'payableAccount', 'purchaseOrder'])
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function purchaseOrderHistory(Vendor $vendor): Collection
    {
        return $vendor->purchaseOrders()
            ->with('lines')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return VendorSummary
     */
    public function summarize(Collection $bills): array
    {
        $billed = $bills->whereIn('status', [BillStatus::Approved, BillStatus::Paid]);
        $paid = $bills->where('status', BillStatus::Paid);
        $outstanding = $bills->where('status', BillStatus::Approved);

        return [
            'bill_count' => $bills->count(),
            'total_billed' => round($billed->sum(fn (Bill $bill) => $bill->total()), 2),
            'total_paid' => round($paid->sum(fn (Bill $bill) => $bill->total()), 2),
            'outstanding' => round($outstanding->sum(fn (Bill $bill) => $bill->total()), 2),
        ];
    }

    public function delete(Vendor $vendor): void
    {
        if ($this->vendors->hasActivity($vendor)) {
            throw new RuntimeException("Vendor {$vendor->name} cannot be deleted: it has expenses, purchase orders, or bills on file.");
        }

        $this->vendors->delete($vendor);
    }
}
