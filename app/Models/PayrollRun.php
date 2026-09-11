<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'expense_account_id',
        'payable_account_id',
        'deductions_payable_account_id',
        'paid_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'pay_date' => 'date',
        'status' => PayrollRunStatus::class,
        'paid_at' => 'datetime',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function deductionsPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deductions_payable_account_id');
    }

    public function totalGross(): float
    {
        return round((float) $this->payslips->sum('gross_pay'), 2);
    }

    public function totalDeductions(): float
    {
        return round((float) $this->payslips->sum('deductions'), 2);
    }

    public function totalNet(): float
    {
        return round((float) $this->payslips->sum('net_pay'), 2);
    }
}
