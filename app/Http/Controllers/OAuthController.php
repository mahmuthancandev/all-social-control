<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\Platform;
use App\Models\AccountCredential;
use App\Models\SocialAccount;
use App\Services\NotificationService;
use App\Services\Platforms\OAuthRedirect;
use App\Services\Platforms\PlatformManager;
use App\Services\Sync\AccountSyncService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Resmi OAuth 2.0 akışı. Sosyal medya şifreleri hiçbir zaman panele girilmez.
 * - state: CSRF'e karşı rastgele, tek kullanımlık, 10 dakika geçerli
 * - PKCE (S256): destekleyen platformlarda (YouTube, X)
 */
class OAuthController extends Controller
{
    private const SESSION_KEY = 'oauth_flow';

    public function redirect(Request $request, string $platform, PlatformManager $platforms): SymfonyResponse
    {
        $platform = Platform::tryFrom($platform) ?? abort(404);
        $driver = $platforms->driver($platform);

        if (! $driver->isConfigured()) {
            return redirect()->route('settings.index')->with('error', "{$platform->label()} için önce Ayarlar sayfasında uygulama bilgilerini girin.");
        }

        $state = Str::random(48);
        $verifier = $driver->usesPkce() ? Str::random(96) : null;
        $challenge = $verifier ? rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=') : null;

        $request->session()->put(self::SESSION_KEY, [
            'platform' => $platform->value,
            'state' => $state,
            'verifier' => $verifier,
            'account_id' => $request->integer('account') ?: null,
            'project_id' => $request->integer('project') ?: null,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        try {
            $url = $driver->authorizationUrl($state, $challenge, OAuthRedirect::uri($platform));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return Inertia::location($url);
    }

    public function callback(Request $request, string $platform, PlatformManager $platforms, AccountSyncService $sync, NotificationService $notifications): RedirectResponse
    {
        $platform = Platform::tryFrom($platform) ?? abort(404);
        $flow = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($flow)
            || ($flow['platform'] ?? null) !== $platform->value
            || ! is_string($request->query('state'))
            || ! hash_equals((string) $flow['state'], (string) $request->query('state'))
            || ($flow['expires_at'] ?? 0) < now()->timestamp
        ) {
            Audit::log('oauth.state_mismatch', null, ['platform' => $platform->value]);

            return redirect()->route('accounts.index')->with('error', 'Güvenlik doğrulaması başarısız (state uyuşmadı veya süresi doldu). Lütfen bağlantıyı tekrar başlatın.');
        }

        if ($request->filled('error')) {
            $reason = Str::limit((string) ($request->query('error_description') ?: $request->query('error')), 200);

            return redirect()->route('accounts.index')->with('error', "{$platform->label()} bağlantısı iptal edildi: {$reason}");
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('accounts.index')->with('error', 'Yetkilendirme kodu alınamadı.');
        }

        $driver = $platforms->driver($platform);

        try {
            $tokens = $driver->exchangeCode($code, $flow['verifier'] ?? null, OAuthRedirect::uri($platform));

            $account = DB::transaction(function () use ($tokens, $platform, $flow) {
                // Önce geçici bir hesap kaydı oluşturup profil çekmek için tokenı bağla.
                $account = $flow['account_id']
                    ? SocialAccount::where('platform', $platform->value)->findOrFail($flow['account_id'])
                    : new SocialAccount(['platform' => $platform, 'handle' => 'baglaniyor-'.Str::random(6)]);

                $account->fill([
                    'connection_type' => 'oauth',
                    'status' => AccountStatus::Connected,
                    'granted_scopes' => $tokens->scopes,
                    'is_demo' => false,
                    'last_error' => null,
                ])->save();

                AccountCredential::updateOrCreate(
                    ['social_account_id' => $account->id],
                    [
                        'access_token' => $tokens->accessToken,
                        'refresh_token' => $tokens->refreshToken,
                        'token_type' => $tokens->tokenType,
                        'expires_at' => $tokens->expiresAt,
                        'refresh_expires_at' => $tokens->refreshExpiresAt,
                        'scopes' => $tokens->scopes,
                        'meta' => $tokens->meta ?: null,
                    ],
                );

                return $account;
            });

            $profile = $driver->fetchProfile($account->load('credential'));

            // Aynı platform hesabı daha önce eklendiyse onu güncelle, geçici kaydı kaldır.
            $existing = SocialAccount::where('platform', $platform->value)
                ->where('external_id', $profile->externalId)
                ->whereKeyNot($account->id)
                ->first();
            if ($existing) {
                DB::transaction(function () use ($existing, $account) {
                    $existing->credential()->delete();
                    $account->credential()->update(['social_account_id' => $existing->id]);
                    $existing->fill([
                        'connection_type' => 'oauth',
                        'status' => AccountStatus::Connected,
                        'granted_scopes' => $account->granted_scopes,
                        'last_error' => null,
                    ])->save();
                    $account->delete();
                });
                $account = $existing;
            }

            $account->fill([
                'external_id' => $profile->externalId,
                'handle' => $profile->handle,
                'display_name' => $profile->displayName,
                'avatar_url' => $profile->avatarUrl,
                'profile_url' => $profile->profileUrl,
                'followers_count' => $profile->followers,
                'posts_count' => $profile->postsCount,
            ])->save();

            if ($flow['project_id']) {
                $account->projects()->syncWithoutDetaching([$flow['project_id']]);
            }

            Audit::log('oauth.connected', $account, ['platform' => $platform->value, 'scopes' => $tokens->scopes]);
            $notifications->notify('account.connected', "{$platform->label()} hesabı bağlandı", '@'.$account->handle, 'success', '/accounts/'.$account->id, audience: 'admins');

            $sync->sync($account->fresh('credential'));
        } catch (Throwable $e) {
            Log::warning('OAuth hatası', ['platform' => $platform->value, 'error' => $e->getMessage()]);
            if (isset($account) && $account->exists && Str::startsWith($account->handle, 'baglaniyor-')) {
                $account->delete();
            }

            return redirect()->route('accounts.index')->with('error', "{$platform->label()} bağlantısı kurulamadı: ".Str::limit($e->getMessage(), 300));
        }

        $missing = array_diff($driver->oauthScopes(), $account->granted_scopes ?? []);

        return redirect()->route('accounts.show', $account)->with('success', "{$platform->label()} hesabı bağlandı.".($missing ? ' Bazı izinler verilmedi; ilgili özellikler pasif görünecek.' : ''));
    }
}
