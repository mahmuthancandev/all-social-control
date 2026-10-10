<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Services\Sync\AccountSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAccountJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public int $accountId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [120, 600];
    }

    public function uniqueId(): string
    {
        return 'sync-account-'.$this->accountId;
    }

    public function handle(AccountSyncService $sync): void
    {
        $account = SocialAccount::with('credential')->find($this->accountId);
        if ($account) {
            $sync->sync($account);
        }
    }
}
