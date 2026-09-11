<?php

namespace Tests\Feature;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JournalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $bankGlAccount;

    private Account $revenue;

    private Account $expense;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->bankGlAccount = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
        $this->revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');
        $this->expense = $this->account('6000', 'Bank Fees', AccountType::Expense, 'debit');

        $this->bankAccount = BankAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Checking',
            'account_id' => $this->bankGlAccount->id,
        ]);
    }

    private function account(string $code, string $name, AccountType $type, string $normalBalance): Account
    {
        return Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
        ]);
    }

    private function postManualEntry(string $date, array $lines): \App\Models\JournalEntry
    {
        auth()->login($this->user);

        return app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: $date,
            description: 'Test entry',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $this->user->id,
            lines: array_map(fn ($line) => new JournalLineData(...$line), $lines),
        ));
    }

    public function test_import_parses_csv_and_creates_unmatched_transactions(): void
    {
        $response = $this->actingAs($this->user)->post(route('bank-accounts.import', $this->bankAccount->id), [
            'csv' => "2026-01-05,Customer payment,500\n2026-01-06,Bank fee,-25",
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bank_transactions', ['description' => 'Customer payment', 'amount' => 500, 'matched_journal_line_id' => null]);
        $this->assertDatabaseHas('bank_transactions', ['description' => 'Bank fee', 'amount' => -25, 'matched_journal_line_id' => null]);
    }

    public function test_import_rejects_malformed_rows(): void
    {
        $response = $this->actingAs($this->user)->post(route('bank-accounts.import', $this->bankAccount->id), [
            'csv' => "not-a-date,Bad row,abc",
        ]);

        $response->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('bank_transactions', 0);
    }

    public function test_matching_links_a_transaction_to_an_existing_journal_line_with_the_same_amount(): void
    {
        $entry = $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->bankGlAccount->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);
        $bankLine = $entry->lines->firstWhere('account_id', $this->bankGlAccount->id);

        $this->actingAs($this->user)->post(route('bank-accounts.import', $this->bankAccount->id), [
            'csv' => '2026-01-10,Customer payment,500',
        ]);
        $transactionId = \App\Models\BankTransaction::where('description', 'Customer payment')->firstOrFail()->id;

        $response = $this->actingAs($this->user)->post(route('bank-transactions.match', $transactionId), [
            'journal_line_id' => $bankLine->id,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('bank_transactions', ['id' => $transactionId, 'matched_journal_line_id' => $bankLine->id]);
    }

    public function test_matching_rejects_amount_mismatch(): void
    {
        $entry = $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->bankGlAccount->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);
        $bankLine = $entry->lines->firstWhere('account_id', $this->bankGlAccount->id);

        $this->actingAs($this->user)->post(route('bank-accounts.import', $this->bankAccount->id), [
            'csv' => '2026-01-10,Customer payment,499',
        ]);
        $transactionId = \App\Models\BankTransaction::where('description', 'Customer payment')->firstOrFail()->id;

        $response = $this->actingAs($this->user)->post(route('bank-transactions.match', $transactionId), [
            'journal_line_id' => $bankLine->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bank_transactions', ['id' => $transactionId, 'matched_journal_line_id' => null]);
    }

    public function test_create_and_match_posts_a_balanced_entry_and_matches_it(): void
    {
        $this->actingAs($this->user)->post(route('bank-accounts.import', $this->bankAccount->id), [
            'csv' => '2026-01-12,Bank fee,-25',
        ]);
        $transactionId = \App\Models\BankTransaction::where('description', 'Bank fee')->firstOrFail()->id;

        $response = $this->actingAs($this->user)->post(route('bank-transactions.create-and-match', $transactionId), [
            'offset_account_id' => $this->expense->id,
            'description' => 'Bank fee',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bankGlAccount->id, 'credit' => 25]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->expense->id, 'debit' => 25]);

        $transaction = \App\Models\BankTransaction::find($transactionId);
        $this->assertNotNull($transaction->matched_journal_line_id);
    }

    public function test_unmatch_clears_the_link(): void
    {
        $entry = $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->bankGlAccount->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);
        $bankLine = $entry->lines->firstWhere('account_id', $this->bankGlAccount->id);

        $this->actingAs($this->user)->post(route('bank-accounts.import', $this->bankAccount->id), [
            'csv' => '2026-01-10,Customer payment,500',
        ]);
        $transactionId = \App\Models\BankTransaction::where('description', 'Customer payment')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('bank-transactions.match', $transactionId), ['journal_line_id' => $bankLine->id]);

        $response = $this->actingAs($this->user)->post(route('bank-transactions.unmatch', $transactionId));
        $response->assertRedirect();

        $this->assertDatabaseHas('bank_transactions', ['id' => $transactionId, 'matched_journal_line_id' => null]);
    }
}
