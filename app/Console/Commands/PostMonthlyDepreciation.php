<?php

namespace App\Console\Commands;

use App\Services\FixedAssetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class PostMonthlyDepreciation extends Command
{
    protected $signature = 'depreciation:run {--month= : Period to post, as Y-m; defaults to the current month}';

    protected $description = 'Post this month\'s depreciation journal entry for every fixed asset';

    public function handle(FixedAssetService $fixedAssets): int
    {
        // Console runs have no authenticated user, but tenant scoping
        // (BelongsToTenant/TenantScope) and journal "created_by" both
        // key off auth()->user() — so we impersonate the system user for
        // the duration of this command, same as a real request would.
        Auth::onceUsingId(config('app.system_user_id'));

        if (! Auth::check()) {
            $this->error('Cannot post depreciation: system user (app.system_user_id) not found.');

            return self::FAILURE;
        }

        $results = $fixedAssets->runMonthlyDepreciationForAll($this->option('month'));

        $posted = 0;
        foreach ($results as $result) {
            if ($result['posted']) {
                $posted++;
                $this->info("Posted {$result['asset']->name}: {$result['amount']}");
            } else {
                $this->line("Skipped {$result['asset']->name}: {$result['message']}");
            }
        }

        $this->info('Posted depreciation for '.$posted.' of '.count($results).' asset(s).');

        return self::SUCCESS;
    }
}
