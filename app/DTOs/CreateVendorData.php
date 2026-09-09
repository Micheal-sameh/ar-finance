<?php

namespace App\DTOs;

final readonly class CreateVendorData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $taxNumber,
        public ?string $paymentTerms,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            taxNumber: $data['tax_number'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
        );
    }
}
