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
    case BookingCashPayment = 'booking_cash_payment';
    case BookingBankPayment = 'booking_bank_payment';
    case BookingPosPayment = 'booking_pos_payment';
    case BookingInstapayPayment = 'booking_instapay_payment';
    case DonationRevenue = 'donation_revenue';
    case DonationCashPayment = 'donation_cash_payment';
    case DonationBankPayment = 'donation_bank_payment';
    case DonationPosPayment = 'donation_pos_payment';
    case DonationInstapayPayment = 'donation_instapay_payment';
    case TreasuryCash = 'treasury_cash';
    case TreasuryReceivable = 'treasury_receivable';
    case TreasuryPayable = 'treasury_payable';
    case TreasuryHeld = 'treasury_held';
    case TreasuryAdvances = 'treasury_advances';

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
            self::BookingCashPayment => 'Booking cash payments received into',
            self::BookingBankPayment => 'Booking bank transfers received into',
            self::BookingPosPayment => 'Booking POS (card) payments received into',
            self::BookingInstapayPayment => 'Booking InstaPay payments received into',
            self::DonationRevenue => 'Donations income',
            self::DonationCashPayment => 'Cash donations received into',
            self::DonationBankPayment => 'Bank-transfer donations received into',
            self::DonationPosPayment => 'POS (card) donations received into',
            self::DonationInstapayPayment => 'InstaPay donations received into',
            self::TreasuryCash => 'Cash account for treasury transfers',
            self::TreasuryReceivable => 'Due from treasury (money held by the treasurer)',
            self::TreasuryPayable => 'Due to treasury (funding to repay)',
            self::TreasuryHeld => 'Held for tenants (treasury ledger)',
            self::TreasuryAdvances => 'Advances to tenants (treasury ledger)',
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
            self::BookingCashPayment => 'The cash account a booking is paid into when the customer pays in cash.',
            self::BookingBankPayment => 'The bank account a booking is paid into when the customer pays by bank transfer.',
            self::BookingPosPayment => 'The account card payments taken on the POS machine are recorded in, usually a bank or card-clearing account.',
            self::BookingInstapayPayment => 'The bank account a booking is paid into when the customer pays by InstaPay.',
            self::DonationRevenue => 'The income account every donation is credited to.',
            self::DonationCashPayment => 'The cash account a donation is paid into when the donor gives cash.',
            self::DonationBankPayment => 'The bank account a donation is paid into when the donor pays by bank transfer.',
            self::DonationPosPayment => 'The account donations taken on the POS machine are recorded in, usually a bank or card-clearing account.',
            self::DonationInstapayPayment => 'The bank account a donation is paid into when the donor sends it by InstaPay, without coming to the church.',
            self::TreasuryCash => 'The cash or bank account money leaves when you deposit with the treasurer, and arrives in when the treasurer funds you or pays out your balance.',
            self::TreasuryReceivable => 'The asset that records the money you have handed to the treasurer and can still ask back.',
            self::TreasuryPayable => 'The liability that records money the treasurer funded you with and you still have to repay.',
            self::TreasuryHeld => 'Treasury tenant only: the liability for the money tenants have deposited and can ask back.',
            self::TreasuryAdvances => 'Treasury tenant only: the asset for money the treasurer funded tenants with and is waiting to get back.',
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
            self::Receivable, self::TaxReceivable, self::FixedAsset, self::AccumulatedDepreciation, self::BookingCashPayment, self::BookingBankPayment, self::BookingPosPayment, self::BookingInstapayPayment, self::DonationCashPayment, self::DonationBankPayment, self::DonationPosPayment, self::DonationInstapayPayment, self::TreasuryCash, self::TreasuryReceivable, self::TreasuryAdvances => [AccountType::Asset],
            self::Payable, self::TaxPayable, self::PayrollPayable, self::PayrollDeductions, self::TreasuryPayable, self::TreasuryHeld => [AccountType::Liability],
            self::Revenue, self::BookingRevenue, self::DonationRevenue => [AccountType::Revenue],
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
            self::BookingRevenue, self::BookingCashPayment, self::BookingBankPayment, self::BookingPosPayment, self::BookingInstapayPayment => 'Booking',
            self::DonationRevenue, self::DonationCashPayment, self::DonationBankPayment, self::DonationPosPayment, self::DonationInstapayPayment => 'Donations',
            self::TreasuryCash, self::TreasuryReceivable, self::TreasuryPayable, self::TreasuryHeld, self::TreasuryAdvances => 'Treasury',
        };
    }

    /**
     * The optional module this role belongs to — the mapping screen only
     * lists the role for tenants that have the module switched on.
     */
    public function module(): ?TenantModule
    {
        return match ($this) {
            self::DonationRevenue, self::DonationCashPayment, self::DonationBankPayment, self::DonationPosPayment, self::DonationInstapayPayment => TenantModule::Donations,
            self::TreasuryCash, self::TreasuryReceivable, self::TreasuryPayable, self::TreasuryHeld, self::TreasuryAdvances => TenantModule::Treasury,
            default => null,
        };
    }
}
