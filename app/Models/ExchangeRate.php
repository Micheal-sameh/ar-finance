<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'currency_code',
        'rate',
        'buy_rate',
        'sell_rate',
        'rate_date',
        'source',
        'fetched_at',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'buy_rate' => 'decimal:8',
        'sell_rate' => 'decimal:8',
        'rate_date' => 'date',
        'fetched_at' => 'datetime',
    ];
}
