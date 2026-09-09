<?php

namespace App\Models\Concerns;

/**
 * Consistent money formatting for any model holding currency amounts.
 * Mix in and call formattedAmount('debit'), formattedAmount('credit'), etc.
 */
trait HasMoney
{
    public function formattedAmount(string $column): string
    {
        $amount = (float) ($this->{$column} ?? 0);
        $currency = $this->currency ?? $this->tenant?->base_currency ?? 'USD';

        return number_format($amount, 2).' '.$currency;
    }
}
