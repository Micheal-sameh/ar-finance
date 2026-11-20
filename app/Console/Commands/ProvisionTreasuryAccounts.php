<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Treasury\Services\TreasuryAccountProvisioner;
use Illuminate\Console\Command;

class ProvisionTreasuryAccounts extends Command
{
    protected $signature = 'treasury:provision-accounts {--tenant= : Only this tenant id; defaults to every tenant}';

    protected $description = 'Create the treasury ledger accounts and GL mappings every tenant needs, skipping any that already exist';

    public function handle(TreasuryAccountProvisioner $provisioner): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->line("<options=bold>{$tenant->name}</>".($tenant->is_treasury ? ' (treasury)' : ''));

            foreach ($provisioner->provision($tenant) as $result) {
                $label = match ($result['status']) {
                    'created' => '<info>created</info>',
                    'linked' => '<info>linked to existing account</info>',
                    default => 'already mapped — skipped',
                };

                $this->line("  {$result['role']}: {$label} ({$result['account']})");
            }
        }

        return self::SUCCESS;
    }
}
