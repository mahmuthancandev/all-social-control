<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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

        // Yönetici yetkisi: hesap/paylaşım/kullanıcı/ayar yönetimi.
        Gate::define('admin', fn (User $user) => $user->is_active && $user->isAdmin());
    }
}
