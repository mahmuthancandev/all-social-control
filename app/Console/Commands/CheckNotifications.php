<?php

namespace App\Console\Commands;

use App\Services\NotificationRuleEvaluator;
use Illuminate\Console\Command;

class CheckNotifications extends Command
{
    protected $signature = 'socialhub:check-notifications';

    protected $description = 'Bildirim kurallarını (hatırlatma, izlenme ve takipçi hedefleri) değerlendirir';

    public function handle(NotificationRuleEvaluator $evaluator): int
    {
        $this->info($evaluator->run().' bildirim oluşturuldu.');

        return self::SUCCESS;
    }
}
