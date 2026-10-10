<?php

namespace Tests;

use App\Enums\AccountStatus;
use App\Enums\ContentType;
use App\Enums\Platform;
use App\Enums\TargetStatus;
use App\Models\MediaFile;
use App\Models\Post;
use App\Models\PostPlatformTarget;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function viewer(): User
    {
        return User::factory()->viewer()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function account(Platform $platform = Platform::YouTube, array $attributes = []): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'platform' => $platform,
            'handle' => 'test_'.Str::lower(Str::random(6)),
            'status' => AccountStatus::Connected,
            'connection_type' => 'demo',
            'is_demo' => true,
        ], $attributes));
    }

    protected function media(string $type = 'video'): MediaFile
    {
        return MediaFile::create([
            'disk' => 'media',
            'path' => 'test/'.Str::random(8).($type === 'video' ? '.mp4' : '.png'),
            'original_name' => $type === 'video' ? 'klip.mp4' : 'kapak.png',
            'mime_type' => $type === 'video' ? 'video/mp4' : 'image/png',
            'extension' => $type === 'video' ? 'mp4' : 'png',
            'type' => $type,
            'size_bytes' => 1000,
        ]);
    }

    protected function target(SocialAccount $account, ContentType $type, ?MediaFile $media = null, TargetStatus $status = TargetStatus::Draft): PostPlatformTarget
    {
        $post = Post::create(['title' => 'Test gönderisi', 'body' => 'Açıklama', 'hashtags' => ['test'], 'status' => 'draft']);

        return PostPlatformTarget::create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'platform' => $account->platform,
            'content_type' => $type,
            'title' => 'Başlık',
            'caption' => 'Açıklama',
            'hashtags' => ['test'],
            'media_file_id' => $media?->id,
            'status' => $status,
            'is_demo' => $account->is_demo,
        ]);
    }
}
