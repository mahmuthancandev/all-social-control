<?php

namespace App\Services\Demo;

use App\Enums\AccountStatus;
use App\Enums\ContentType;
use App\Enums\Platform;
use App\Enums\TargetStatus;
use App\Models\AccountMetricSnapshot;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\ExternalPost;
use App\Models\MediaFile;
use App\Models\NotificationRule;
use App\Models\Post;
use App\Models\PostMetricSnapshot;
use App\Models\PostPlatformTarget;
use App\Models\Project;
use App\Models\PublicationJob;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gerçek API bağlantısı olmadan panelin tüm modüllerini denemek için DEMO veri üretir.
 * Tüm kayıtlar is_demo=true ile işaretlenir ve arayüzde "DEMO" etiketiyle gösterilir.
 */
class DemoDataGenerator
{
    private const DAYS = 120;

    private const PROJECTS = [
        ['name' => 'Neon Racer', 'type' => 'game', 'category' => 'Yarış', 'color' => '#22d3ee', 'description' => 'Neon ışıklı şehirlerde geçen arcade yarış oyunu.', 'platforms' => ['youtube', 'tiktok', 'instagram', 'x']],
        ['name' => 'Pixel Quest', 'type' => 'game', 'category' => 'RPG', 'color' => '#a78bfa', 'description' => 'Piksel sanatlı, hikâye odaklı macera oyunu.', 'platforms' => ['youtube', 'tiktok', 'instagram']],
        ['name' => 'FitTrack', 'type' => 'app', 'category' => 'Sağlık & Fitness', 'color' => '#34d399', 'description' => 'Antrenman ve beslenme takip uygulaması.', 'platforms' => ['youtube', 'instagram', 'x']],
    ];

    private const TITLES = [
        'Yeni güncelleme fragmanı', 'Geliştirici günlüğü #%d', 'En hızlı tur rekoru', 'Gizli bölüm keşfi',
        'Topluluk sorularını yanıtlıyoruz', 'Sezon %d başladı!', 'Kamera arkası: tasarım süreci', 'İpuçları ve taktikler',
        'Yeni karakter tanıtımı', 'Çok oyunculu mod testi', 'Haftalık meydan okuma', 'Oyuncu yorumları',
    ];

    public function generate(): void
    {
        mt_srand(20261010);

        DB::transaction(function () {
            $admin = User::where('role', 'admin')->orderBy('id')->first();
            $accounts = collect();

            foreach (self::PROJECTS as $i => $def) {
                $project = Project::create([
                    'name' => $def['name'],
                    'type' => $def['type'],
                    'category' => $def['category'],
                    'color' => $def['color'],
                    'description' => $def['description'],
                    'is_demo' => true,
                ]);

                foreach ($def['platforms'] as $p) {
                    $platform = Platform::from($p);
                    $handle = Str::slug($def['name'], '').($platform === Platform::YouTube ? '' : '_'.($def['type'] === 'game' ? 'game' : 'app'));
                    $account = SocialAccount::create([
                        'platform' => $platform,
                        'handle' => $handle,
                        'display_name' => $def['name'],
                        'external_id' => 'demo_'.$p.'_'.$i,
                        'profile_url' => $platform->profileUrl($handle),
                        'status' => AccountStatus::Connected,
                        'connection_type' => 'demo',
                        'is_demo' => true,
                    ]);
                    $project->socialAccounts()->attach($account);
                    $accounts->push($account);

                    $this->metrics($account, $i);
                    $this->externalPosts($account, $def['name']);
                }

                $this->media($project, $admin);
                $this->panelPosts($project, $admin);
            }

            $this->rules($admin, $accounts);
            $this->notifications($accounts);
        });
    }

    /**
     * Demo hesaplar için bugünün ölçümünü ekler (scheduler her gün çağırır).
     */
    public function tick(): void
    {
        SocialAccount::where('is_demo', true)->get()->each(function (SocialAccount $account) {
            $last = AccountMetricSnapshot::where('social_account_id', $account->id)->latest('captured_on')->first();
            if (! $last || $last->captured_on->isToday()) {
                return;
            }
            $days = (int) $last->captured_on->diffInDays(today());
            for ($d = 1; $d <= $days; $d++) {
                $date = $last->captured_on->copy()->addDays($d);
                $followers = $last->followers === null ? null : (int) ($last->followers * (1 + mt_rand(-2, 12) / 1000));
                $views = $last->views === null ? null : (int) max(50, $last->views * (mt_rand(80, 125) / 100));
                $last = AccountMetricSnapshot::create($this->dailyRow($account, $date->toDateString(), $followers, $views));
            }
            $account->update(['followers_count' => $last->followers, 'last_synced_at' => now()]);
        });
    }

    public function purge(): void
    {
        DB::transaction(function () {
            $media = MediaFile::where('is_demo', true)->get();
            foreach ($media as $m) {
                Storage::disk($m->disk)->delete(array_filter([$m->path, $m->thumbnail_path]));
            }
            MediaFile::where('is_demo', true)->delete();
            Post::where('is_demo', true)->delete();
            SocialAccount::where('is_demo', true)->delete(); // snapshot, yorum, hedef vb. cascade silinir
            Project::where('is_demo', true)->delete();
            AppNotification::where('type', 'like', 'demo.%')->delete();
            NotificationRule::whereNull('social_account_id')->where('created_by', null)->delete();
        });
    }

    public function exists(): bool
    {
        return SocialAccount::where('is_demo', true)->exists();
    }

    private function metrics(SocialAccount $account, int $projectIndex): void
    {
        $base = match ($account->platform) {
            Platform::YouTube => [18000, 9000, 4200][$projectIndex],
            Platform::TikTok => [52000, 31000, 0][$projectIndex],
            Platform::Instagram => [14500, 8800, 12300][$projectIndex],
            Platform::X => [6100, 0, 3900][$projectIndex],
        };
        $viewsBase = (int) ($base * match ($account->platform) {
            Platform::TikTok => 1.6, Platform::YouTube => 0.9, Platform::Instagram => 0.5, Platform::X => 0.7,
        });

        $start = CarbonImmutable::today()->subDays(self::DAYS);
        $followers = (int) ($base * 0.72);
        $rows = [];
        for ($d = 0; $d <= self::DAYS; $d++) {
            $date = $start->addDays($d);
            $followers = max(0, (int) round($followers * (1 + (mt_rand(-3, 9) / 1000)) + mt_rand(0, 12)));
            $weekend = $date->isWeekend() ? 1.25 : 1.0;
            $spike = mt_rand(1, 30) === 1 ? mt_rand(200, 400) / 100 : 1;
            $views = (int) ($viewsBase * $weekend * $spike * mt_rand(70, 130) / 100 * (0.7 + $d / self::DAYS * 0.6));
            $rows[] = $this->dailyRow($account, $date->toDateString(), $followers, $views) + ['created_at' => now(), 'updated_at' => now()];
        }
        AccountMetricSnapshot::insert($rows);
        $account->update(['followers_count' => $followers, 'posts_count' => mt_rand(40, 180), 'last_synced_at' => now()->subMinutes(mt_rand(5, 90))]);

        SyncLog::create([
            'social_account_id' => $account->id, 'type' => 'full', 'status' => 'success', 'items_count' => mt_rand(10, 40),
            'message' => 'DEMO senkronizasyon', 'started_at' => now()->subMinutes(30), 'finished_at' => now()->subMinutes(29),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyRow(SocialAccount $account, string $date, ?int $followers, ?int $views): array
    {
        $platform = $account->platform;
        $likes = $views === null ? null : (int) ($views * mt_rand(35, 90) / 1000);

        return [
            'social_account_id' => $account->id,
            'captured_on' => $date,
            'followers' => $followers,
            'following' => $platform === Platform::YouTube ? null : 150 + $account->id * 3,
            'posts_count' => null,
            'views' => $views,
            // Erişim: yalnızca Instagram bu metriği sunar; diğerlerinde NULL (0 değil).
            'reach' => $platform === Platform::Instagram && $views ? (int) ($views * 0.8) : null,
            'impressions' => $platform === Platform::X && $views ? $views : null,
            'likes' => $likes,
            'comments' => $views === null ? null : (int) ($views * mt_rand(3, 12) / 1000),
            'shares' => $platform === Platform::X || $views === null ? ($views ? (int) ($views * 0.004) : null) : (int) ($views * mt_rand(2, 8) / 1000),
            'source' => 'demo',
            'is_demo' => true,
        ];
    }

    private function externalPosts(SocialAccount $account, string $projectName): void
    {
        $types = match ($account->platform) {
            Platform::YouTube => [ContentType::YouTubeVideo, ContentType::YouTubeShort, ContentType::YouTubeShort],
            Platform::Instagram => [ContentType::InstagramReel, ContentType::InstagramPost],
            Platform::TikTok => [ContentType::TikTokVideo],
            Platform::X => [ContentType::XPost, ContentType::XMedia],
        };

        $count = mt_rand(18, 28);
        for ($n = 0; $n < $count; $n++) {
            // Akşam saatlerinde (18-21) daha iyi performans: "en iyi saat" analizinin anlamlı sonuç vermesi için.
            $hour = [9, 12, 13, 15, 18, 19, 19, 20, 20, 21, 22][mt_rand(0, 10)];
            $publishedAt = CarbonImmutable::now()->subDays(mt_rand(1, self::DAYS))->setTime($hour, mt_rand(0, 59));
            $eveningBoost = $hour >= 18 && $hour <= 21 ? 1.8 : 1.0;
            $weekendBoost = $publishedAt->isWeekend() ? 1.3 : 1.0;
            $baseViews = ($account->followers_count ?? 5000) * (mt_rand(8, 60) / 100);
            $views = (int) ($baseViews * $eveningBoost * $weekendBoost * (mt_rand(1, 12) === 1 ? 4 : 1));
            $type = $types[array_rand($types)];
            $title = sprintf(self::TITLES[array_rand(self::TITLES)], mt_rand(1, 30));
            $externalId = 'demo_'.Str::lower(Str::random(11));

            $post = ExternalPost::create([
                'social_account_id' => $account->id,
                'external_id' => $externalId,
                'content_type' => $type,
                'title' => $projectName.' — '.$title,
                'url' => null,
                'published_at' => $publishedAt,
                'views' => $views,
                'likes' => (int) ($views * mt_rand(30, 110) / 1000),
                'comments' => (int) ($views * mt_rand(2, 15) / 1000),
                // YouTube Data API paylaşım sayısı vermez → NULL.
                'shares' => $account->platform === Platform::YouTube ? null : (int) ($views * mt_rand(1, 10) / 1000),
                'source' => 'demo',
                'is_demo' => true,
            ]);

            PostMetricSnapshot::create([
                'external_post_id' => $post->id,
                'captured_on' => today()->toDateString(),
                'views' => $post->views,
                'likes' => $post->likes,
                'comments' => $post->comments,
                'shares' => $post->shares,
                'source' => 'demo',
                'is_demo' => true,
            ]);

            if (in_array($account->platform, [Platform::YouTube, Platform::Instagram], true) && $n < 6) {
                $this->comments($account, $post);
            }
        }
    }

    private function comments(SocialAccount $account, ExternalPost $post): void
    {
        $bodies = [
            'Harika görünüyor! Ne zaman çıkıyor?', 'Bu güncelleme çok iyi olmuş 🔥', 'Android sürümü var mı?',
            'Grafikler çok güzel', 'Level 3\'te bir hata var sanırım', 'Türkçe dil desteği gelecek mi?',
            'Müzikler muhteşem 👏', 'Arkadaşlarımla oynuyoruz, çok eğlenceli', 'Bir sonraki video ne zaman?',
        ];
        $n = mt_rand(1, 4);
        for ($i = 0; $i < $n; $i++) {
            $at = $post->published_at->copy()->addHours(mt_rand(1, 72));
            Comment::create([
                'social_account_id' => $account->id,
                'external_post_id' => $post->id,
                'external_id' => 'demo_c_'.Str::lower(Str::random(12)),
                'author_name' => ['oyuncu', 'gamer', 'fan', 'kullanici'][mt_rand(0, 3)].mt_rand(10, 9999),
                'body' => $bodies[array_rand($bodies)],
                'like_count' => mt_rand(0, 50),
                'commented_at' => $at->isFuture() ? now()->subMinutes(mt_rand(5, 300)) : $at,
                'read_at' => mt_rand(0, 2) === 0 ? null : now(),
                'is_demo' => true,
            ]);
        }
    }

    private function media(Project $project, ?User $admin): void
    {
        foreach (['Kapak görseli', 'Fragman kapağı', 'Sosyal medya afişi'] as $i => $label) {
            $isVideo = $i === 1;
            $svg = $this->svg($project->name, $label, $project->color, $isVideo ? 1080 : 1080, $isVideo ? 1920 : 1080);
            $path = 'demo/'.$project->slug.'-'.Str::slug($label).'.svg';
            Storage::disk('media')->put($path, $svg);

            MediaFile::create([
                'project_id' => $project->id,
                'uploaded_by' => $admin?->id,
                'folder' => $isVideo ? 'Fragmanlar' : 'Görseller',
                'disk' => 'media',
                'path' => $isVideo ? 'demo/'.$project->slug.'-fragman.mp4' : $path,
                'original_name' => $isVideo ? $project->slug.'-fragman.mp4' : $project->slug.'-'.Str::slug($label).'.svg',
                'mime_type' => $isVideo ? 'video/mp4' : 'image/svg+xml',
                'extension' => $isVideo ? 'mp4' : 'svg',
                'type' => $isVideo ? 'video' : 'image',
                'size_bytes' => $isVideo ? 18_400_000 + $project->id * 1000 : strlen($svg),
                'width' => $isVideo ? 1080 : 1080,
                'height' => $isVideo ? 1920 : 1080,
                'duration_seconds' => $isVideo ? 42.5 : null,
                'thumbnail_path' => $isVideo ? $path : null,
                'checksum' => hash('sha256', $project->slug.$label),
                'is_demo' => true,
            ]);
        }
    }

    private function panelPosts(Project $project, ?User $admin): void
    {
        $accounts = $project->socialAccounts()->get();
        $video = MediaFile::where('project_id', $project->id)->where('type', 'video')->first();
        $image = MediaFile::where('project_id', $project->id)->where('type', 'image')->first();

        $scenarios = [
            ['title' => 'Yeni sezon fragmanı', 'status' => TargetStatus::Scheduled, 'at' => now()->addDays(2)->setTime(19, 0)],
            ['title' => 'Haftalık geliştirici günlüğü', 'status' => TargetStatus::Scheduled, 'at' => now()->addDays(5)->setTime(20, 30)],
            ['title' => 'Topluluk etkinliği duyurusu', 'status' => TargetStatus::Draft, 'at' => null],
            ['title' => 'Güncelleme 1.4 yayında', 'status' => TargetStatus::Published, 'at' => now()->subDays(3)->setTime(19, 15)],
            ['title' => 'Oyuncu yorumları derlemesi', 'status' => TargetStatus::Failed, 'at' => now()->subDay()->setTime(18, 0)],
            ['title' => 'Kamera arkası kısa video', 'status' => TargetStatus::ManualRequired, 'at' => now()->subHours(2)],
        ];

        foreach ($scenarios as $s) {
            $post = Post::create([
                'project_id' => $project->id,
                'created_by' => $admin?->id,
                'title' => $project->name.': '.$s['title'],
                'body' => $project->description.' Detaylar profilimizdeki linkte!',
                'hashtags' => [Str::slug($project->name, ''), 'indiegame', 'gamedev'],
                'status' => 'draft',
                'scheduled_at' => $s['at'],
                'is_demo' => true,
            ]);
            if ($video) {
                $post->media()->attach($video->id, ['position' => 0]);
            }
            if ($image) {
                $post->media()->attach($image->id, ['position' => 1]);
            }

            foreach ($accounts as $account) {
                $type = match ($account->platform) {
                    Platform::YouTube => ContentType::YouTubeShort,
                    Platform::Instagram => ContentType::InstagramReel,
                    Platform::TikTok => ContentType::TikTokVideo,
                    Platform::X => ContentType::XMedia,
                };
                $status = $s['status'];
                // X API kapalı → X hedefleri hiçbir zaman "API ile yayınlandı" olamaz.
                if ($account->platform === Platform::X && $status === TargetStatus::Published) {
                    $status = TargetStatus::ManuallyPublished;
                }
                if ($account->platform === Platform::X && $status === TargetStatus::Failed) {
                    $status = TargetStatus::ManualRequired;
                }

                $target = PostPlatformTarget::create([
                    'post_id' => $post->id,
                    'social_account_id' => $account->id,
                    'platform' => $account->platform,
                    'content_type' => $type,
                    'title' => $type->requirements()['needs_title'] ? $s['title'] : null,
                    'caption' => $post->body,
                    'hashtags' => $post->hashtags,
                    'media_file_id' => $type === ContentType::XPost ? null : ($video?->id ?? $image?->id),
                    'publish_mode' => $account->platform === Platform::X ? 'manual' : 'api',
                    'status' => $status,
                    'scheduled_at' => $s['at'],
                    'published_at' => $status->isPublished() ? $s['at'] : null,
                    'external_id' => $status === TargetStatus::Published ? 'demo_'.Str::lower(Str::random(10)) : null,
                    'error_message' => $status === TargetStatus::Failed ? 'DEMO: Platform geçici olarak yanıt vermedi (HTTP 503). 3 deneme sonrası başarısız.' : null,
                    'attempts' => $status === TargetStatus::Failed ? 3 : ($status === TargetStatus::Published ? 1 : 0),
                    'is_demo' => true,
                ]);

                if ($status->isPublished() || $status === TargetStatus::Failed) {
                    PublicationJob::create([
                        'post_platform_target_id' => $target->id,
                        'mode' => $status === TargetStatus::ManuallyPublished ? 'manual' : 'demo',
                        'status' => $status === TargetStatus::Failed ? 'failed' : 'succeeded',
                        'attempt' => max(1, $target->attempts),
                        'started_at' => $s['at'],
                        'finished_at' => $s['at'],
                        'message' => $status === TargetStatus::Failed ? $target->error_message : 'DEMO simülasyonu',
                    ]);
                }
            }

            $post->refreshStatus();
        }
    }

    private function rules(?User $admin, $accounts): void
    {
        NotificationRule::create(['type' => 'schedule_reminder', 'minutes_before' => 30, 'is_active' => true, 'created_by' => $admin?->id]);
        NotificationRule::create(['type' => 'views_threshold', 'threshold' => 10000, 'is_active' => true, 'created_by' => $admin?->id]);
        $first = $accounts->first();
        if ($first) {
            NotificationRule::create([
                'type' => 'followers_threshold',
                'social_account_id' => $first->id,
                'threshold' => (int) (ceil(($first->fresh()->followers_count ?? 1000) / 1000) * 1000),
                'is_active' => true,
                'created_by' => $admin?->id,
            ]);
        }
    }

    private function notifications($accounts): void
    {
        $items = [
            ['demo.publish', 'success', '[DEMO] YouTube: video yayınlandı', 'Neon Racer: Güncelleme 1.4 yayında'],
            ['demo.failed', 'error', '[DEMO] Instagram: yayın başarısız', 'Platform geçici olarak yanıt vermedi (HTTP 503).'],
            ['demo.milestone', 'success', '[DEMO] TikTok videosu 50.000 izlenmeyi geçti', 'Neon Racer — En hızlı tur rekoru'],
            ['demo.reminder', 'info', '[DEMO] Zamanlanmış paylaşım yaklaşıyor', 'Pixel Quest: Yeni sezon fragmanı — 2 gün sonra 19:00'],
        ];

        foreach (User::where('is_active', true)->pluck('id') as $userId) {
            foreach ($items as $i => [$type, $level, $title, $body]) {
                AppNotification::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'level' => $level,
                    'title' => $title,
                    'body' => $body,
                    'url' => '/posts',
                    'read_at' => $i > 1 ? now() : null,
                    'created_at' => now()->subHours($i * 5),
                    'updated_at' => now()->subHours($i * 5),
                ]);
            }
        }
    }

    private function svg(string $name, string $label, string $color, int $w, int $h): string
    {
        $name = htmlspecialchars($name, ENT_XML1);
        $label = htmlspecialchars($label, ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$color}"/><stop offset="1" stop-color="#0f121b"/></linearGradient></defs>
  <rect width="100%" height="100%" fill="url(#g)"/>
  <text x="50%" y="46%" font-family="Segoe UI, sans-serif" font-size="96" font-weight="700" fill="#fff" text-anchor="middle">{$name}</text>
  <text x="50%" y="54%" font-family="Segoe UI, sans-serif" font-size="48" fill="#e2e8f0" text-anchor="middle">{$label}</text>
  <text x="50%" y="92%" font-family="Segoe UI, sans-serif" font-size="40" fill="#f0abfc" text-anchor="middle">DEMO</text>
</svg>
SVG;
    }
}
