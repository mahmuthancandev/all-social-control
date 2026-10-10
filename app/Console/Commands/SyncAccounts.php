<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Jobs\SyncAccountJob;
use App\Models\SocialAccount;
use App\Services\Demo\DemoDataGenerator;
use Illuminate\Console\Command;

class SyncAccounts extends Command
{
    protected $signature = 'socialhub:sync-accounts {--now : Kuyruğa almadan hemen çalıştır}';

    protected $description = 'Bağlı hesapların verilerini kuyruk üzerinden senkronize eder';

    public function handle(DemoDataGenerator $demo): int
    {
        $demo->tick();

        $accounts = SocialAccount::whereIn('connection_type', ['oauth', 'api_key', 'demo'])
            ->where('status', '!=', AccountStatus::NeedsReauth->value)
            ->pluck('id');

        foreach ($accounts as $id) {
            $this->option('now') ? SyncAccountJob::dispatchSync($id) : SyncAccountJob::dispatch($id);
        }

        $this->info($accounts->count().' hesap senkronizasyona alındı.');

        return self::SUCCESS;
    }
}
