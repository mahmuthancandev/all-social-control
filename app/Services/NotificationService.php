<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Ücretsiz panel içi bildirimler. Her kullanıcının kendi okundu/okunmadı durumu vardır.
 */
class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  'all'|'admins'  $audience
     */
    public function notify(
        string $type,
        string $title,
        ?string $body = null,
        string $level = 'info',
        ?string $url = null,
        array $data = [],
        ?string $dedupeKey = null,
        string $audience = 'all',
    ): int {
        $users = User::query()
            ->where('is_active', true)
            ->when($audience === 'admins', fn ($q) => $q->where('role', UserRole::Admin->value))
            ->pluck('id');

        $created = 0;
        foreach ($users as $userId) {
            if ($dedupeKey && AppNotification::where('user_id', $userId)->where('dedupe_key', $dedupeKey)->exists()) {
                continue;
            }

            try {
                AppNotification::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'level' => $level,
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                    'data' => $data ?: null,
                    'dedupe_key' => $dedupeKey,
                ]);
                $created++;
            } catch (QueryException) {
                // Eşzamanlı aynı bildirim (unique dedupe_key) — sessizce atla.
            }
        }

        return $created;
    }
}
