<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\TargetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Instagram (Meta) medyayı indirebilsin diye süreli, imzalı adres. Giriş gerektirmez; imza/süre geçersizse 403.
Route::get('/public-media/{media}', PublicMediaController::class)
    ->middleware(['signed', 'throttle:120,1'])
    ->name('public-media.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // ---- Görüntüleme (ADMIN + VIEWER) ----
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/accounts', [SocialAccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/{account}', [SocialAccountController::class, 'show'])->name('accounts.show');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/{media}/file', [MediaController::class, 'file'])->name('media.file');
    Route::get('/media/{media}/thumbnail', [MediaController::class, 'thumbnail'])->name('media.thumbnail');
    Route::get('/media/{media}/download', [MediaController::class, 'download'])->name('media.download');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [PostController::class, 'create'])->middleware('can:admin')->name('posts.create');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/comments', [CommentController::class, 'index'])->name('comments.index');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/export/daily', [AnalyticsController::class, 'exportDaily'])->middleware('throttle:20,1')->name('analytics.export.daily');
    Route::get('/analytics/export/posts', [AnalyticsController::class, 'exportPosts'])->middleware('throttle:20,1')->name('analytics.export.posts');
    Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');

    // Bildirimler: her kullanıcı kendi okundu durumunu yönetir.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'toggleRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/read', [NotificationController::class, 'destroyRead'])->name('notifications.destroy-read');

    Route::get('/settings/password', [PasswordController::class, 'edit'])->name('settings.password');
    Route::put('/settings/password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('settings.password.update');

    // ---- Yalnızca ADMIN ----
    Route::middleware('can:admin')->group(function () {
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::post('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update'); // multipart (logo) için POST
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('/accounts', [SocialAccountController::class, 'store'])->name('accounts.store');
        Route::put('/accounts/{account}', [SocialAccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [SocialAccountController::class, 'destroy'])->name('accounts.destroy');
        Route::post('/accounts/{account}/sync', [SocialAccountController::class, 'sync'])->middleware('throttle:10,1')->name('accounts.sync');
        Route::post('/accounts/{account}/metrics', [SocialAccountController::class, 'storeMetrics'])->name('accounts.metrics');
        Route::post('/accounts/{account}/import', [SocialAccountController::class, 'importCsv'])->name('accounts.import');
        Route::post('/accounts/{account}/links', [SocialAccountController::class, 'importLink'])->middleware('throttle:30,1')->name('accounts.links');

        Route::get('/oauth/{platform}/redirect', [OAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('oauth.redirect');
        Route::get('/oauth/{platform}/callback', [OAuthController::class, 'callback'])->middleware('throttle:10,1')->name('oauth.callback');

        Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:30,1')->name('media.store');
        Route::put('/media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
        Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
        Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('/posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('posts.duplicate');

        Route::post('/targets/{target}/publish', [TargetController::class, 'publish'])->middleware('throttle:30,1')->name('targets.publish');
        Route::post('/targets/{target}/retry', [TargetController::class, 'retry'])->middleware('throttle:30,1')->name('targets.retry');
        Route::post('/targets/{target}/mark-published', [TargetController::class, 'markPublished'])->name('targets.mark-published');
        Route::post('/targets/{target}/cancel', [TargetController::class, 'cancel'])->name('targets.cancel');
        Route::post('/targets/{target}/reschedule', [TargetController::class, 'reschedule'])->name('targets.reschedule');

        Route::post('/comments/{comment}/reply', [CommentController::class, 'reply'])->middleware('throttle:20,1')->name('comments.reply');
        Route::patch('/comments/{comment}/read', [CommentController::class, 'markRead'])->name('comments.read');
        Route::post('/comments/read-all', [CommentController::class, 'markAllRead'])->name('comments.read-all');
        Route::post('/comments/sync', [CommentController::class, 'sync'])->middleware('throttle:5,1')->name('comments.sync');

        Route::post('/notification-rules', [NotificationController::class, 'storeRule'])->name('notification-rules.store');
        Route::patch('/notification-rules/{rule}', [NotificationController::class, 'toggleRule'])->name('notification-rules.toggle');
        Route::delete('/notification-rules/{rule}', [NotificationController::class, 'destroyRule'])->name('notification-rules.destroy');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/demo', [SettingsController::class, 'generateDemo'])->name('settings.demo.generate');
        Route::delete('/settings/demo', [SettingsController::class, 'purgeDemo'])->name('settings.demo.purge');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});
