<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // APP_URL https ise (VPS) üretilen tüm adresler https olsun: OAuth yönlendirmeleri,
        // imzalı medya adresleri ve kuyruk işlerinde (CLI) üretilen bağlantılar dahil.
        if (str_starts_with(strtolower((string) config('app.url')), 'https://')) {
            URL::forceScheme('https');
        }

        // Yönetici yetkisi: hesap/paylaşım/kullanıcı/ayar yönetimi.
        Gate::define('admin', fn (User $user) => $user->is_active && $user->isAdmin());
    }
}
