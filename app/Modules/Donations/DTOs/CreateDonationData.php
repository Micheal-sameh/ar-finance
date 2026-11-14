<?php

namespace App\Modules\Donations\DTOs;

use App\Modules\Donations\Enums\DonationPaymentMethod;

final readonly class CreateDonationData
{
    public function __construct(
        public ?int $donorId,
        public int $costCenterId,
        public float $amount,
        public ?string $currency,
        public ?float $exchangeRate,
        public string $donatedAt,
        public DonationPaymentMethod $paymentMethod,
        public ?string $paymentReference,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            donorId: isset($data['donor_id']) && $data['donor_id'] !== '' ? (int) $data['donor_id'] : null,
            costCenterId: (int) $data['cost_center_id'],
            amount: (float) $data['amount'],
            currency: ! empty($data['currency']) ? strtoupper((string) $data['currency']) : null,
            exchangeRate: isset($data['exchange_rate']) && $data['exchange_rate'] !== '' ? (float) $data['exchange_rate'] : null,
            donatedAt: $data['donated_at'],
            paymentMethod: DonationPaymentMethod::from($data['payment_method']),
            paymentReference: isset($data['payment_reference']) && trim((string) $data['payment_reference']) !== '' ? trim((string) $data['payment_reference']) : null,
            notes: isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        );
    }
}
