<?php

namespace App\Services\Platforms;

use App\Enums\AccountStatus;
use App\Models\AccountCredential;
use App\Models\Comment;
use App\Models\PostPlatformTarget;
use App\Models\SocialAccount;
use App\Services\Platforms\Contracts\SocialPlatformInterface;
use App\Services\Platforms\Data\CapabilitySet;
use App\Services\Platforms\Data\PublishResult;
use App\Services\Platforms\Data\RemoteComment;
use App\Services\Platforms\Data\TokenSet;
use App\Services\Platforms\Exceptions\PlatformException;
use App\Services\Platforms\Exceptions\QuotaExceededException;
use App\Services\Platforms\Exceptions\TokenExpiredException;
use App\Services\Platforms\Exceptions\UnsupportedOperationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class AbstractPlatformService implements SocialPlatformInterface
{
    public function __construct(protected QuotaTracker $quota) {}

    public function usesPkce(): bool
    {
        return false;
    }

    public function capabilities(SocialAccount $account): CapabilitySet
    {
        return match ($account->connection_type) {
            'manual' => CapabilitySet::none('Manuel hesap: API bağlantısı yok. Metrikler manuel giriş veya CSV ile eklenir; paylaşım Paylaşım Asistanı ile yapılır.'),
            'api_key' => $this->apiKeyCapabilities($account),
            'oauth' => $account->status === AccountStatus::Connected
                ? $this->oauthCapabilities($account, $account->granted_scopes ?? [])
                : CapabilitySet::none('Bağlantı aktif değil: '.$account->status->label().'. Hesabı yeniden bağlayın.'),
            default => CapabilitySet::none('Bilinmeyen bağlantı türü.'),
        };
    }

    /**
     * @param  array<int, string>  $scopes
     */
    abstract protected function oauthCapabilities(SocialAccount $account, array $scopes): CapabilitySet;

    protected function apiKeyCapabilities(SocialAccount $account): CapabilitySet
    {
        return CapabilitySet::none($this->platform()->label().' için API anahtarıyla bağlantı desteklenmiyor.');
    }

    public function fetchComments(SocialAccount $account, string $postExternalId, int $limit = 50): array
    {
        throw new UnsupportedOperationException($this->platform()->label().' resmi API\'si yorum okumayı desteklemiyor.');
    }

    public function replyToComment(SocialAccount $account, Comment $comment, string $text): string
    {
        throw new UnsupportedOperationException($this->platform()->label().' resmi API\'si yorumlara cevap vermeyi desteklemiyor.');
    }

    public function refreshToken(AccountCredential $credential): TokenSet
    {
        throw new TokenExpiredException($this->platform()->label().' bağlantısının süresi doldu; hesabı yeniden bağlayın.');
    }

    public function publish(PostPlatformTarget $target): PublishResult
    {
        throw new UnsupportedOperationException($this->platform()->label().' için otomatik yayın desteklenmiyor.');
    }

    /**
     * Geçerli (gerekirse yenilenmiş) erişim tokenı.
     */
    protected function accessToken(SocialAccount $account): string
    {
        $credential = $account->credential;

        if (! $credential || ! $credential->access_token) {
            throw new TokenExpiredException('Hesap için kayıtlı bağlantı bulunamadı; yeniden bağlayın.');
        }

        if ($credential->isExpired()) {
            $tokens = $this->refreshToken($credential);
            $credential->fill([
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken ?? $credential->refresh_token,
                'expires_at' => $tokens->expiresAt,
                'refresh_expires_at' => $tokens->refreshExpiresAt ?? $credential->refresh_expires_at,
                'scopes' => $tokens->scopes ?: $credential->scopes,
            ])->save();
        }

        return $credential->access_token;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->connectTimeout(10)
            ->withUserAgent('SocialHub/1.0 (+self-hosted)');
    }

    /**
     * Yanıtı kontrol eder ve platform hatalarını anlamlı istisnalara çevirir.
     * Hata mesajlarına asla token veya istek gövdesi yazılmaz.
     */
    protected function check(Response $response, string $context): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $message = $this->extractError($response);

        Log::warning('Platform API hatası', [
            'platform' => $this->platform()->value,
            'context' => $context,
            'status' => $status,
            'error' => Str::limit($message, 300),
        ]);

        if ($status === 401) {
            throw new TokenExpiredException("{$context}: yetki reddedildi ({$message}). Hesabı yeniden bağlayın.");
        }

        if ($status === 429 || Str::contains(Str::lower($message), ['quota', 'ratelimit', 'rate limit', 'rate_limit'])) {
            if ($status === 403 || Str::contains(Str::lower($message), 'quota')) {
                throw new QuotaExceededException("{$context}: API kotası doldu ({$message}).");
            }
            $e = new PlatformException("{$context}: istek sınırına ulaşıldı, daha sonra tekrar denenecek.");
            $e->retryable = true;
            throw $e;
        }

        $e = new PlatformException("{$context} başarısız (HTTP {$status}): {$message}");
        $e->retryable = $status >= 500;
        throw $e;
    }

    protected function extractError(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            $candidates = [
                data_get($json, 'error.message'),
                data_get($json, 'error_description'),
                data_get($json, 'detail'),
                data_get($json, 'title'),
                is_string(data_get($json, 'error')) ? data_get($json, 'error') : null,
            ];
            foreach ($candidates as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }
        }

        return Str::limit(strip_tags($response->body()), 200) ?: 'Bilinmeyen hata';
    }

    /**
     * @param  array<int, RemoteComment>  $comments
     * @return array<int, RemoteComment>
     */
    protected function limit(array $comments, int $limit): array
    {
        return array_slice($comments, 0, $limit);
    }

    protected function mediaPath(PostPlatformTarget $target): string
    {
        $media = $target->mediaFile;
        if (! $media) {
            throw new PlatformException('Bu içerik türü için medya dosyası seçilmemiş.');
        }

        $path = Storage::disk($media->disk)->path($media->path);
        if (! is_file($path)) {
            throw new PlatformException('Medya dosyası diskte bulunamadı: '.$media->original_name);
        }

        return $path;
    }
}
