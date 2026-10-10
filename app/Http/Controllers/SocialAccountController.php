<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\Platform;
use App\Jobs\SyncAccountJob;
use App\Models\AccountMetricSnapshot;
use App\Models\ExternalPost;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use App\Services\Import\MetricsCsvImporter;
use App\Services\Platforms\LinkPreviewService;
use App\Services\Platforms\PlatformManager;
use App\Services\Sync\AccountSyncService;
use App\Support\Audit;
use App\Support\Range;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SocialAccountController extends Controller
{
    public function __construct(private PlatformManager $platforms) {}

    public function index(Request $request): Response
    {
        $accounts = SocialAccount::with('projects:id,name,color')->orderBy('platform')->orderBy('handle')->get()
            ->map(fn (SocialAccount $a) => $a->toSummary() + [
                'projects' => $a->projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'color' => $p->color]),
                'capabilities' => $this->platforms->capabilities($a)->toArray(),
            ]);

        return Inertia::render('accounts/Index', [
            'accounts' => $accounts,
            'platforms' => Platform::options(),
            'overview' => $this->platforms->overview(),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'youtubeApiKeySet' => Settings::has('youtube_api_key'),
        ]);
    }

    public function show(Request $request, SocialAccount $account, AnalyticsService $analytics): Response
    {
        $days = Range::days($request);
        $collection = collect([$account]);

        return Inertia::render('accounts/Show', [
            'account' => $account->toSummary() + [
                'projects' => $account->projects()->get(['projects.id', 'name', 'color']),
                'granted_scopes' => $account->granted_scopes ?? [],
                'token_expires_at' => $account->credential?->expires_at?->toIso8601String(),
            ],
            'capabilities' => $this->platforms->capabilities($account)->toArray(),
            'range' => $days,
            'kpis' => $analytics->kpis($collection, $days),
            'followersSeries' => $analytics->timeSeries($collection, 'followers', $days, 'account'),
            'viewsSeries' => $analytics->timeSeries($collection, 'views', $days, 'account'),
            'posts' => ExternalPost::where('social_account_id', $account->id)->latest('published_at')->limit(30)->get()->map->toSummary(),
            'snapshots' => AccountMetricSnapshot::where('social_account_id', $account->id)->latest('captured_on')->limit(14)->get()
                ->map(fn ($s) => ['captured_on' => $s->captured_on->toDateString(), 'source' => $s->source] + $s->only(AccountMetricSnapshot::METRICS)),
            'syncLogs' => $account->syncLogs()->latest()->limit(10)->get(['id', 'type', 'status', 'items_count', 'message', 'finished_at', 'created_at']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Manuel veya API anahtarlı (herkese açık) hesap ekleme. OAuth için oauth.redirect rotası kullanılır.
     */
    public function store(Request $request, AccountSyncService $sync): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::enum(Platform::class)],
            'handle' => ['required', 'string', 'max:120', 'regex:/^@?[A-Za-z0-9._\-]+$/'],
            'display_name' => ['nullable', 'string', 'max:160'],
            'connection_type' => ['required', Rule::in(['manual', 'api_key'])],
            'followers_count' => ['nullable', 'integer', 'min:0'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ], [], ['handle' => 'kullanıcı adı', 'platform' => 'platform']);

        $platform = Platform::from($data['platform']);
        if ($data['connection_type'] === 'api_key' && $platform !== Platform::YouTube) {
            return back()->with('error', 'API anahtarıyla herkese açık veri okuma yalnızca YouTube için desteklenir.');
        }
        if ($data['connection_type'] === 'api_key' && ! Settings::has('youtube_api_key')) {
            return back()->with('error', 'Önce Ayarlar sayfasından ücretsiz YouTube API anahtarını girin.');
        }

        $handle = ltrim($data['handle'], '@');
        if (SocialAccount::where('platform', $platform->value)->where('handle', $handle)->where('is_demo', false)->exists()) {
            return back()->with('error', 'Bu hesap zaten ekli.');
        }

        $account = SocialAccount::create([
            'platform' => $platform,
            'handle' => $handle,
            'display_name' => $data['display_name'] ?? null,
            'profile_url' => $platform->profileUrl($handle),
            'connection_type' => $data['connection_type'],
            'status' => $data['connection_type'] === 'manual' ? AccountStatus::Manual : AccountStatus::Connected,
            'followers_count' => $data['followers_count'] ?? null,
        ]);
        $account->projects()->sync($data['project_ids'] ?? []);

        if ($data['connection_type'] === 'manual' && isset($data['followers_count'])) {
            AccountMetricSnapshot::updateOrCreate(
                ['social_account_id' => $account->id, 'captured_on' => today()->toDateString()],
                ['followers' => $data['followers_count'], 'source' => 'manual'],
            );
        }

        Audit::log('account.created', $account, ['platform' => $platform->value, 'type' => $data['connection_type']]);

        if ($data['connection_type'] === 'api_key') {
            $result = $sync->sync($account->fresh());
            if ($result['status'] === 'failed') {
                return redirect()->route('accounts.show', $account)->with('error', 'Hesap eklendi ancak veriler alınamadı: '.$result['message']);
            }
        }

        return redirect()->route('accounts.show', $account)->with('success', 'Hesap eklendi.');
    }

    public function update(Request $request, SocialAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:160'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $account->update(['display_name' => $data['display_name'] ?? $account->display_name]);
        $account->projects()->sync($data['project_ids'] ?? []);
        Audit::log('account.updated', $account);

        return back()->with('success', 'Hesap güncellendi.');
    }

    public function destroy(SocialAccount $account): RedirectResponse
    {
        Audit::log('account.deleted', $account, ['platform' => $account->platform->value, 'handle' => $account->handle]);
        // Tokenlar (account_credentials) ve tüm ölçümler cascade ile silinir.
        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Hesap ve kayıtlı bağlantı bilgileri silindi. Platform tarafındaki uygulama iznini de kaldırmanız önerilir.');
    }

    public function sync(Request $request, SocialAccount $account, AccountSyncService $sync): RedirectResponse
    {
        if ($account->connection_type === 'manual') {
            return back()->with('error', 'Manuel hesaplar senkronize edilmez; metrik girişi veya CSV içe aktarma kullanın.');
        }

        if ($request->boolean('queue')) {
            SyncAccountJob::dispatch($account->id);

            return back()->with('success', 'Senkronizasyon kuyruğa alındı.');
        }

        $result = $sync->sync($account->load('credential'));

        return back()->with($result['status'] === 'failed' ? 'error' : 'success', $result['status'] === 'failed'
            ? 'Senkronizasyon başarısız: '.$result['message']
            : 'Senkronizasyon tamamlandı.'.($result['status'] === 'partial' ? ' (Kısmi: '.$result['message'].')' : ''));
    }

    public function storeMetrics(Request $request, SocialAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'captured_on' => ['required', 'date', 'before_or_equal:today'],
            'followers' => ['nullable', 'integer', 'min:0'],
            'views' => ['nullable', 'integer', 'min:0'],
            'reach' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'likes' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
        ], [], ['captured_on' => 'tarih']);

        $metrics = collect($data)->except('captured_on')->filter(fn ($v) => $v !== null)->all();
        if ($metrics === []) {
            return back()->with('error', 'En az bir metrik girin.');
        }

        AccountMetricSnapshot::updateOrCreate(
            ['social_account_id' => $account->id, 'captured_on' => $data['captured_on']],
            $metrics + ['source' => 'manual', 'is_demo' => $account->is_demo],
        );

        if (isset($metrics['followers'])) {
            $latest = AccountMetricSnapshot::where('social_account_id', $account->id)->whereNotNull('followers')->latest('captured_on')->value('followers');
            $account->update(['followers_count' => $latest, 'last_synced_at' => now()]);
        }

        return back()->with('success', 'Metrikler kaydedildi.');
    }

    public function importCsv(Request $request, SocialAccount $account, MetricsCsvImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ], [], ['file' => 'CSV dosyası']);

        try {
            $result = $importer->import($account, $request->file('file')->getRealPath());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        Audit::log('account.csv_imported', $account, ['rows' => $result['rows']]);

        return back()->with('success', "{$result['rows']} günlük kayıt içe aktarıldı (".implode(', ', $result['columns']).').'.($result['skipped'] ? " {$result['skipped']} satır atlandı." : ''));
    }

    /**
     * Gönderi linkini resmi oEmbed ile ekler (başlık/kapak); sayılar isteğe bağlı elle girilir.
     */
    public function importLink(Request $request, SocialAccount $account, LinkPreviewService $preview): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:1000'],
            'published_at' => ['nullable', 'date', 'before_or_equal:now'],
            'views' => ['nullable', 'integer', 'min:0'],
            'likes' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
        ], [], ['url' => 'gönderi linki']);

        if ($preview->detectPlatform($data['url']) !== $account->platform) {
            return back()->with('error', 'Link bu hesabın platformuna ait değil.');
        }

        $info = $preview->preview($data['url']);
        $post = ExternalPost::updateOrCreate(
            ['social_account_id' => $account->id, 'external_id' => $info['external_id']],
            [
                'title' => $info['title'],
                'url' => $data['url'],
                'thumbnail_url' => $info['thumbnail_url'],
                'published_at' => $data['published_at'] ?? now(),
                'views' => $data['views'] ?? null,
                'likes' => $data['likes'] ?? null,
                'comments' => $data['comments'] ?? null,
                'shares' => $data['shares'] ?? null,
                'source' => 'manual',
                'is_demo' => $account->is_demo,
            ],
        );

        return back()->with('success', 'Gönderi eklendi'.($post->title ? ': '.$post->title : ' (başlık alınamadı).'));
    }
}
