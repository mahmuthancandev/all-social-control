<?php

namespace App\Services\Platforms\Contracts;

use App\Enums\Platform;
use App\Models\AccountCredential;
use App\Models\Comment;
use App\Models\PostPlatformTarget;
use App\Models\SocialAccount;
use App\Services\Platforms\Data\CapabilitySet;
use App\Services\Platforms\Data\ProfileData;
use App\Services\Platforms\Data\PublishResult;
use App\Services\Platforms\Data\RemoteComment;
use App\Services\Platforms\Data\RemotePost;
use App\Services\Platforms\Data\TokenSet;

/**
 * Tüm sosyal medya servislerinin ortak sözleşmesi.
 * Desteklenmeyen işlemler UnsupportedOperationException fırlatır; asla sahte başarı döndürmez.
 */
interface SocialPlatformInterface
{
    public function platform(): Platform;

    /** OAuth için uygulama kimlik bilgileri girilmiş mi? */
    public function isConfigured(): bool;

    /** Kurulum için kullanıcıya gösterilecek kısa rehber ve uyarılar. */
    public function setupNotes(): array;

    public function usesPkce(): bool;

    /** @return array<int, string> */
    public function oauthScopes(): array;

    public function authorizationUrl(string $state, ?string $codeChallenge, string $redirectUri): string;

    public function exchangeCode(string $code, ?string $codeVerifier, string $redirectUri): TokenSet;

    public function refreshToken(AccountCredential $credential): TokenSet;

    public function capabilities(SocialAccount $account): CapabilitySet;

    public function fetchProfile(SocialAccount $account): ProfileData;

    /** @return array<int, RemotePost> */
    public function fetchRecentPosts(SocialAccount $account, int $limit = 25): array;

    /** @return array<int, RemoteComment> */
    public function fetchComments(SocialAccount $account, string $postExternalId, int $limit = 50): array;

    /** Cevap yorumunun platformdaki kimliğini döndürür. */
    public function replyToComment(SocialAccount $account, Comment $comment, string $text): string;

    public function publish(PostPlatformTarget $target): PublishResult;
}
