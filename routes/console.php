<?php

use App\Models\SyncLog;
use Illuminate\Support\Facades\Schedule;

/*
| SocialHub zamanlanmış görevleri.
| Windows'ta start.bat (schedule:work) veya Görev Zamanlayıcı (schedule:run) ile çalışır.
| Bilgisayar kapalıyken hiçbir görev çalışmaz; zamanlanmış paylaşımlar garanti edilmez.
*/

Schedule::command('socialhub:dispatch-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('socialhub:check-notifications')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('socialhub:sync-accounts')->hourly()->withoutOverlapping();
Schedule::command('socialhub:refresh-tokens')->dailyAt('03:15');
Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::call(fn () => SyncLog::where('created_at', '<', now()->subDays(90))->delete())->daily()->name('prune-sync-logs');
