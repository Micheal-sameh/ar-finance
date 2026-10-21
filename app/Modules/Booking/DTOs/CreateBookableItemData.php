<?php

namespace App\Modules\Booking\DTOs;

use App\Modules\Booking\Enums\BookingRateType;

final readonly class CreateBookableItemData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isExclusive,
        public ?float $depositPercent,
        public int $accountId,
        public ?int $deferredRevenueAccountId,
        public ?int $depositPaymentAccountId,
        public int $costCenterId,
        /** @var array<int, array{booking_type_id: int, rate_type: string, price: float}> */
        public array $bookingTypes = [],
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            isExclusive: (bool) ($data['is_exclusive'] ?? true),
            depositPercent: isset($data['deposit_percent']) && $data['deposit_percent'] !== '' ? (float) $data['deposit_percent'] : null,
            accountId: (int) $data['account_id'],
            deferredRevenueAccountId: isset($data['deferred_revenue_account_id']) ? (int) $data['deferred_revenue_account_id'] : null,
            depositPaymentAccountId: isset($data['deposit_payment_account_id']) ? (int) $data['deposit_payment_account_id'] : null,
            costCenterId: (int) $data['cost_center_id'],
            bookingTypes: array_map(fn (array $type) => [
                'booking_type_id' => (int) $type['booking_type_id'],
                'rate_type' => BookingRateType::from($type['rate_type'])->value,
                'price' => (float) $type['price'],
            ], $data['booking_types'] ?? []),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
