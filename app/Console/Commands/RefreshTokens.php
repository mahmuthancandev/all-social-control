<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\AccountCredential;
use App\Services\NotificationService;
use App\Services\Platforms\PlatformManager;
use Illuminate\Console\Command;
use Throwable;

class RefreshTokens extends Command
{
    protected $signature = 'socialhub:refresh-tokens';

    protected $description = 'Süresi yaklaşan OAuth tokenlarını yeniler';

    public function handle(PlatformManager $platforms, NotificationService $notifications): int
    {
        $credentials = AccountCredential::with('socialAccount')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->get();

        foreach ($credentials as $credential) {
            $account = $credential->socialAccount;
            if (! $account || $account->is_demo) {
                continue;
            }

            try {
                $tokens = $platforms->driver($account->platform)->refreshToken($credential);
                $credential->update([
                    'access_token' => $tokens->accessToken,
                    'refresh_token' => $tokens->refreshToken ?? $credential->refresh_token,
                    'expires_at' => $tokens->expiresAt,
                    'refresh_expires_at' => $tokens->refreshExpiresAt ?? $credential->refresh_expires_at,
                ]);
                $this->line("Yenilendi: {$account->platform->label()} @{$account->handle}");
            } catch (Throwable $e) {
                $account->update(['status' => AccountStatus::NeedsReauth, 'last_error' => $e->getMessage()]);
                $notifications->notify(
                    'account.reauth_required',
                    "{$account->platform->label()} hesabı tekrar bağlanmalı",
                    "@{$account->handle}: ".$e->getMessage(),
                    'warning',
                    '/accounts/'.$account->id,
                    dedupeKey: 'reauth:'.$account->id.':'.now()->toDateString(),
                    audience: 'admins',
                );
                $this->warn("Yenilenemedi: @{$account->handle}");
            }
        }

        return self::SUCCESS;
    }
}
