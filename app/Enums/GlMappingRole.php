<?php

namespace App\Enums;

/**
 * The jobs a ledger account can be given in the tenant's GL mapping. Each
 * role names where a kind of posting lands by default (receivables, VAT,
 * payroll, ...), so forms can prefill it instead of asking every time.
 */
enum GlMappingRole: string
{
    case Receivable = 'receivable';
    case Revenue = 'revenue';
    case TaxPayable = 'tax_payable';
    case Payable = 'payable';
    case PurchaseExpense = 'purchase_expense';
    case TaxReceivable = 'tax_receivable';
    case PayrollExpense = 'payroll_expense';
    case PayrollPayable = 'payroll_payable';
    case PayrollDeductions = 'payroll_deductions';
    case FixedAsset = 'fixed_asset';
    case Depreciation = 'depreciation';
    case AccumulatedDepreciation = 'accumulated_depreciation';
    case FxGainLoss = 'fx_gain_loss';
    case BookingRevenue = 'booking_revenue';
    case BookingDeferredRevenue = 'booking_deferred_revenue';
    case BookingDepositPayment = 'booking_deposit_payment';

    public function label(): string
    {
        return match ($this) {
            self::Receivable => 'Accounts receivable',
            self::Revenue => 'Sales revenue',
            self::TaxPayable => 'Output VAT (tax payable)',
            self::Payable => 'Accounts payable',
            self::PurchaseExpense => 'Purchases / default expense',
            self::TaxReceivable => 'Input VAT (tax receivable)',
            self::PayrollExpense => 'Salaries expense',
            self::PayrollPayable => 'Salaries payable',
            self::PayrollDeductions => 'Payroll deductions payable',
            self::FixedAsset => 'Fixed assets',
            self::Depreciation => 'Depreciation expense',
            self::AccumulatedDepreciation => 'Accumulated depreciation',
            self::FxGainLoss => 'Exchange gain / loss',
            self::BookingRevenue => 'Booking revenue',
            self::BookingDeferredRevenue => 'Deferred booking revenue',
            self::BookingDepositPayment => 'Booking deposits received into',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Receivable => 'Where the amount customers owe you is recorded when you issue an invoice.',
            self::Revenue => 'The income account new invoice lines are credited to.',
            self::TaxPayable => 'The liability that collects the VAT you charge customers.',
            self::Payable => 'Where the amount you owe suppliers is recorded for bills and expenses.',
            self::PurchaseExpense => 'The expense account new bill and expense lines are charged to.',
            self::TaxReceivable => 'The asset that holds the VAT you can reclaim on supplier bills.',
            self::PayrollExpense => 'The expense that receives the gross pay of each payroll run.',
            self::PayrollPayable => 'The liability for net pay owed to employees until it is paid.',
            self::PayrollDeductions => 'The liability for tax and insurance withheld from pay.',
            self::FixedAsset => 'The balance-sheet account that holds the cost of new assets.',
            self::Depreciation => 'The expense that receives each depreciation charge.',
            self::AccumulatedDepreciation => 'The account that builds up total depreciation and reduces asset value.',
            self::FxGainLoss => 'Where unrealized exchange gains and losses are posted at revaluation.',
            self::BookingRevenue => 'The income account new bookable items credit once a booking has taken place.',
            self::BookingDeferredRevenue => 'Where money received in advance for bookings is held until the booking date.',
            self::BookingDepositPayment => 'The bank or cash account that new bookable items receive deposits into.',
        };
    }

    /**
     * Account types a role may be mapped to.
     *
     * @return array<int, AccountType>
     */
    public function allowedTypes(): array
    {
        return match ($this) {
            self::Receivable, self::TaxReceivable, self::FixedAsset, self::AccumulatedDepreciation, self::BookingDepositPayment => [AccountType::Asset],
            self::Payable, self::TaxPayable, self::PayrollPayable, self::PayrollDeductions, self::BookingDeferredRevenue => [AccountType::Liability],
            self::Revenue, self::BookingRevenue => [AccountType::Revenue],
            self::PurchaseExpense, self::PayrollExpense, self::Depreciation => [AccountType::Expense],
            self::FxGainLoss => [AccountType::Revenue, AccountType::Expense],
        };
    }

    /**
     * Section of the mapping screen the role is listed under.
     */
    public function group(): string
    {
        return match ($this) {
            self::Receivable, self::Revenue, self::TaxPayable => 'Sales',
            self::Payable, self::PurchaseExpense, self::TaxReceivable => 'Purchases',
            self::PayrollExpense, self::PayrollPayable, self::PayrollDeductions => 'Payroll',
            self::FixedAsset, self::Depreciation, self::AccumulatedDepreciation => 'Fixed assets',
            self::FxGainLoss => 'Currency',
            self::BookingRevenue, self::BookingDeferredRevenue, self::BookingDepositPayment => 'Booking',
        };
    }
}
