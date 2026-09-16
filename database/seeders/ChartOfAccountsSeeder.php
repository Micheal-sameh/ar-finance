<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

/**
 * Seeds the standard chart of accounts for a tenant. Codes follow the
 * nesting convention documented on Account::codeNestsUnder() — a child
 * takes over the parent's first trailing zero.
 *
 * 3100 (Net Income) and 3200 (Retained Earnings) are structural only: this
 * system has no period-close step that posts into them (see the
 * "Retained Earnings (current period)" comment in ReportService), so
 * keeping their balances equal to revenue-minus-expenses for the current
 * and prior years respectively is a reporting convention, not something
 * this seeder enforces.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(int $tenantId): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Assets', 'type' => AccountType::Asset, 'parent' => null],
            ['code' => '1100', 'name' => 'Fixed Assets', 'type' => AccountType::Asset, 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Current Assets', 'type' => AccountType::Asset, 'parent' => '1000'],

            ['code' => '2000', 'name' => 'Liabilities', 'type' => AccountType::Liability, 'parent' => null],
            ['code' => '2100', 'name' => 'Long-Term Liabilities', 'type' => AccountType::Liability, 'parent' => '2000'],
            ['code' => '2200', 'name' => 'Current Liabilities', 'type' => AccountType::Liability, 'parent' => '2000'],

            ['code' => '3000', 'name' => 'Equity', 'type' => AccountType::Equity, 'parent' => null],
            ['code' => '3100', 'name' => 'Net Income', 'type' => AccountType::Equity, 'parent' => '3000'],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => AccountType::Equity, 'parent' => '3000'],

            ['code' => '4000', 'name' => 'Revenue', 'type' => AccountType::Revenue, 'parent' => null],

            ['code' => '5000', 'name' => 'Expenses', 'type' => AccountType::Expense, 'parent' => null],
            ['code' => '5100', 'name' => 'Direct Expenses', 'type' => AccountType::Expense, 'parent' => '5000'],
            ['code' => '5200', 'name' => 'Indirect Expenses', 'type' => AccountType::Expense, 'parent' => '5000'],
            ['code' => '5300', 'name' => 'Other Expenses', 'type' => AccountType::Expense, 'parent' => '5000'],
        ];

        $idsByCode = [];

        foreach ($accounts as $account) {
            $idsByCode[$account['code']] = Account::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['type']->defaultNormalBalance(),
                    'parent_id' => $account['parent'] ? $idsByCode[$account['parent']] : null,
                    'is_active' => true,
                ],
            )->id;
        }
    }
}
