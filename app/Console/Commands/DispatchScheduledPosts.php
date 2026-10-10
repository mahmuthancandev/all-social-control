<?php

namespace App\Console\Commands;

use App\Services\Publishing\PublishingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchScheduledPosts extends Command
{
    protected $signature = 'socialhub:dispatch-scheduled';

    protected $description = 'Zamanı gelen planlı paylaşımları yayın kuyruğuna alır';

    public function handle(PublishingService $publishing): int
    {
        Cache::forever('socialhub:scheduler:last_run', now()->toIso8601String());
        $count = $publishing->dispatchDue();
        $this->info("{$count} planlı hedef işlendi.");

        return self::SUCCESS;
    }
}
