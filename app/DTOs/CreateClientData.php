<?php

namespace App\DTOs;

final readonly class CreateClientData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $taxNumber,
        public ?string $address,
        public string $currency,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            taxNumber: $data['tax_number'] ?? null,
            address: $data['address'] ?? null,
            currency: $data['currency'] ?? 'EGP',
        );
    }
}
