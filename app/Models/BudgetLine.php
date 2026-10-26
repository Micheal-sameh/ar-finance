<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    use BelongsToTenant;

    public const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    protected $fillable = [
        'tenant_id',
        'budget_id',
        'account_id',
        'cost_center_id',
        'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
    ];

    protected $casts = [
        'm1' => 'float', 'm2' => 'float', 'm3' => 'float', 'm4' => 'float', 'm5' => 'float', 'm6' => 'float',
        'm7' => 'float', 'm8' => 'float', 'm9' => 'float', 'm10' => 'float', 'm11' => 'float', 'm12' => 'float',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return array<int, float> Month number (1-12) => amount.
     */
    public function monthlyAmounts(): array
    {
        $amounts = [];

        foreach (self::MONTHS as $month) {
            $amounts[$month] = (float) $this->getAttribute("m{$month}");
        }

        return $amounts;
    }
}
