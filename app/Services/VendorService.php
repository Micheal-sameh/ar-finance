<?php

namespace App\Services;

use App\DTOs\CreateVendorData;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class VendorService
{
    public function __construct(
        private readonly VendorRepositoryInterface $vendors,
    ) {
    }

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

    public function delete(Vendor $vendor): void
    {
        if ($this->vendors->hasExpenses($vendor)) {
            throw new RuntimeException("Vendor {$vendor->name} cannot be deleted: it has expenses on file.");
        }

        $this->vendors->delete($vendor);
    }
}
