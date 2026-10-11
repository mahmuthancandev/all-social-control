<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Services\Demo\DemoDataGenerator;
use App\Services\Media\MediaService;
use App\Services\Platforms\PlatformManager;
use App\Services\Platforms\QuotaTracker;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(PlatformManager $platforms, QuotaTracker $quota, DemoDataGenerator $demo): Response
    {
        return Inertia::render('settings/Index', [
            'settings' => Settings::publicView(),
            'platforms' => $platforms->overview(),
            'quota' => collect(Platform::cases())->map(fn (Platform $p) => [
                'platform' => $p->value,
                'label' => $p->label(),
                'limit' => $quota->dailyLimit($p),
                'used' => $quota->used($p),
            ])->filter(fn ($q) => $q['limit'] !== null)->values(),
            'system' => [
                'demo_data' => $demo->exists(),
                'ffmpeg' => MediaService::ffmpegAvailable(),
                'queue_pending' => DB::table('jobs')->count(),
                'queue_failed' => DB::table('failed_jobs')->count(),
                'scheduler_last_run' => Cache::get('socialhub:scheduler:last_run'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'app_url' => config('app.url'),
                'timezone' => config('app.timezone'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'youtube_client_id' => ['nullable', 'string', 'max:255'],
            'youtube_client_secret' => ['nullable', 'string', 'max:255'],
            'youtube_api_key' => ['nullable', 'string', 'max:255'],
            'youtube_daily_quota' => ['nullable', 'integer', 'min:100', 'max:10000000'],
            'youtube_upload_cost' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'instagram_app_id' => ['nullable', 'string', 'max:255'],
            'instagram_app_secret' => ['nullable', 'string', 'max:255'],
            'instagram_public_media_base_url' => ['nullable', 'url:https', 'max:500'],
            'tiktok_client_key' => ['nullable', 'string', 'max:255'],
            'tiktok_client_secret' => ['nullable', 'string', 'max:255'],
            'tiktok_publish_mode' => ['nullable', Rule::in(['inbox', 'direct'])],
            'x_api_enabled' => ['nullable', 'boolean'],
            'x_client_id' => ['nullable', 'string', 'max:255'],
            'x_client_secret' => ['nullable', 'string', 'max:255'],
            'oauth_redirect_base_url' => ['nullable', 'url:https', 'max:500'],
        ];
        $data = $request->validate($rules);

        $changed = [];
        foreach ($data as $key => $value) {
            $secret = Settings::DEFINITIONS[$key][1] ?? false;
            // Gizli alanlar boş gönderildiyse mevcut değer korunur; silmek için "clear" listesi kullanılır.
            if ($secret && ($value === null || $value === '')) {
                continue;
            }
            Settings::set($key, $value);
            $changed[] = $key;
        }

        foreach ((array) $request->input('clear', []) as $key) {
            if (array_key_exists($key, Settings::DEFINITIONS)) {
                Settings::set($key, null);
                $changed[] = $key;
            }
        }

        Audit::log('settings.updated', null, ['keys' => array_values(array_unique($changed))]);

        return back()->with('success', 'Ayarlar kaydedildi.');
    }

    public function generateDemo(DemoDataGenerator $demo): RedirectResponse
    {
        if ($demo->exists()) {
            return back()->with('error', 'Demo veriler zaten mevcut.');
        }
        $demo->generate();
        Audit::log('demo.generated');

        return back()->with('success', 'Demo veriler oluşturuldu (DEMO etiketli).');
    }

    public function purgeDemo(DemoDataGenerator $demo): RedirectResponse
    {
        $demo->purge();
        Audit::log('demo.purged');

        return back()->with('success', 'Tüm demo veriler silindi. Gerçek verileriniz etkilenmedi.');
    }
}
