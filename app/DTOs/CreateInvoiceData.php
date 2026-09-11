<?php

namespace App\DTOs;

final readonly class CreateInvoiceData
{
    /**
     * @param  InvoiceLineData[]  $lines
     */
    public function __construct(
        public int $clientId,
        public string $invoiceNumber,
        public string $issueDate,
        public string $dueDate,
        public string $currency,
        public float $exchangeRate,
        public int $receivableAccountId,
        public ?int $taxPayableAccountId,
        public array $lines,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            clientId: (int) $data['client_id'],
            invoiceNumber: $data['invoice_number'],
            issueDate: $data['issue_date'],
            dueDate: $data['due_date'],
            currency: $data['currency'] ?? 'USD',
            exchangeRate: (float) ($data['exchange_rate'] ?? 1),
            receivableAccountId: (int) $data['receivable_account_id'],
            taxPayableAccountId: isset($data['tax_payable_account_id']) ? (int) $data['tax_payable_account_id'] : null,
            lines: array_map(
                fn (array $line) => InvoiceLineData::fromArray($line),
                $data['lines'],
            ),
        );
    }
}
