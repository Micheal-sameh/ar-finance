<?php

namespace Tests\Feature;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\JournalEntry;
use App\Models\JournalLine;
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

    private function postManualEntry(string $date, array $lines): JournalEntry
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
            'csv' => 'not-a-date,Bad row,abc',
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
        $transactionId = BankTransaction::where('description', 'Customer payment')->firstOrFail()->id;

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
        $transactionId = BankTransaction::where('description', 'Customer payment')->firstOrFail()->id;

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
        $transactionId = BankTransaction::where('description', 'Bank fee')->firstOrFail()->id;

        $response = $this->actingAs($this->user)->post(route('bank-transactions.create-and-match', $transactionId), [
            'offset_account_id' => $this->expense->id,
            'description' => 'Bank fee',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bankGlAccount->id, 'credit' => 25]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->expense->id, 'debit' => 25]);

        $transaction = BankTransaction::find($transactionId);
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
        $transactionId = BankTransaction::where('description', 'Customer payment')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('bank-transactions.match', $transactionId), ['journal_line_id' => $bankLine->id]);

        $response = $this->actingAs($this->user)->post(route('bank-transactions.unmatch', $transactionId));
        $response->assertRedirect();

        $this->assertDatabaseHas('bank_transactions', ['id' => $transactionId, 'matched_journal_line_id' => null]);
    }

    private function postBankDeposit(string $date, float $amount, string $description = 'Customer payment'): JournalLine
    {
        $entry = $this->postManualEntry($date, [
            ['accountId' => $this->bankGlAccount->id, 'debit' => $amount, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => $amount],
        ]);
        $entry->update(['description' => $description]);

        return $entry->lines->firstWhere('account_id', $this->bankGlAccount->id);
    }

    private function importMatched(string $date, float $amount, JournalLine $line): BankTransaction
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => $date,
            'description' => 'Statement line',
            'amount' => $amount,
        ]);

        $this->actingAs($this->user)->post(route('bank-transactions.match', $transaction->id), ['journal_line_id' => $line->id]);

        return $transaction->refresh();
    }

    public function test_finishing_a_balanced_reconciliation_locks_the_matched_transactions(): void
    {
        $transaction = $this->importMatched('2026-01-10', 500, $this->postBankDeposit('2026-01-10', 500));

        $response = $this->actingAs($this->user)->post(route('bank-reconciliations.store', $this->bankAccount->id), [
            'statement_date' => '2026-01-31',
            'statement_ending_balance' => 500,
        ]);

        $reconciliation = BankReconciliation::firstOrFail();
        $response->assertRedirect(route('bank-reconciliations.show', $reconciliation));
        $this->assertEquals(0, $reconciliation->opening_balance);
        $this->assertSame($reconciliation->id, $transaction->refresh()->bank_reconciliation_id);

        $this->actingAs($this->user)->post(route('bank-transactions.unmatch', $transaction->id))->assertSessionHas('error');
        $this->assertNotNull($transaction->refresh()->matched_journal_line_id);
    }

    public function test_finishing_is_rejected_when_the_statement_balance_differs(): void
    {
        $this->importMatched('2026-01-10', 500, $this->postBankDeposit('2026-01-10', 500));

        $response = $this->actingAs($this->user)->post(route('bank-reconciliations.store', $this->bankAccount->id), [
            'statement_date' => '2026-01-31',
            'statement_ending_balance' => 450,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bank_reconciliations', 0);
    }

    public function test_finishing_is_rejected_while_transactions_up_to_the_date_are_unmatched(): void
    {
        $this->importMatched('2026-01-10', 500, $this->postBankDeposit('2026-01-10', 500));
        BankTransaction::create(['bank_account_id' => $this->bankAccount->id, 'date' => '2026-01-20', 'description' => 'Bank fee', 'amount' => -25]);

        $response = $this->actingAs($this->user)->post(route('bank-reconciliations.store', $this->bankAccount->id), [
            'statement_date' => '2026-01-31',
            'statement_ending_balance' => 475,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bank_reconciliations', 0);
    }

    public function test_next_reconciliation_opens_at_the_previous_ending_balance(): void
    {
        $this->importMatched('2026-01-10', 500, $this->postBankDeposit('2026-01-10', 500));
        $this->actingAs($this->user)->post(route('bank-reconciliations.store', $this->bankAccount->id), [
            'statement_date' => '2026-01-31',
            'statement_ending_balance' => 500,
        ]);
        $this->importMatched('2026-02-10', 200, $this->postBankDeposit('2026-02-10', 200));

        $this->actingAs($this->user)->post(route('bank-reconciliations.store', $this->bankAccount->id), [
            'statement_date' => '2026-02-28',
            'statement_ending_balance' => 700,
        ])->assertSessionMissing('error');

        $this->assertEquals(500, BankReconciliation::orderByDesc('id')->firstOrFail()->opening_balance);

        $this->actingAs($this->user)->post(route('bank-reconciliations.store', $this->bankAccount->id), [
            'statement_date' => '2026-02-15',
            'statement_ending_balance' => 700,
        ])->assertSessionHas('error');
    }

    public function test_report_lists_ledger_items_the_bank_has_not_cleared(): void
    {
        $this->importMatched('2026-01-10', 500, $this->postBankDeposit('2026-01-10', 500));
        $this->postBankDeposit('2026-01-30', 300, 'Deposit in transit');
        $this->actingAs($this->user)->post(route('bank-reconciliations.store', $this->bankAccount->id), [
            'statement_date' => '2026-01-31',
            'statement_ending_balance' => 500,
        ]);

        $this->actingAs($this->user)
            ->get(route('bank-reconciliations.show', BankReconciliation::firstOrFail()))
            ->assertInertia(fn ($page) => $page
                ->component('Banking/BankAccounts/Reconciliation')
                ->where('report.outstanding_net', 300)
                ->where('report.adjusted_bank_balance', 800)
                ->where('report.ledger_balance', 800)
                ->where('report.difference', 0)
                ->has('report.outstanding_deposits', 1));
    }

    public function test_auto_match_pairs_same_amount_lines_close_in_date_and_skips_the_rest(): void
    {
        $near = $this->postBankDeposit('2026-01-10', 500, 'Customer payment');
        $this->postBankDeposit('2026-03-10', 250, 'Too far away');

        $nearTransaction = BankTransaction::create(['bank_account_id' => $this->bankAccount->id, 'date' => '2026-01-12', 'description' => 'Customer payment', 'amount' => 500]);
        $farTransaction = BankTransaction::create(['bank_account_id' => $this->bankAccount->id, 'date' => '2026-01-12', 'description' => 'Too far away', 'amount' => 250]);

        $this->actingAs($this->user)->post(route('bank-accounts.auto-match', $this->bankAccount->id))->assertSessionHas('success');

        $this->assertSame($near->id, $nearTransaction->refresh()->matched_journal_line_id);
        $this->assertNull($farTransaction->refresh()->matched_journal_line_id);
    }
}
