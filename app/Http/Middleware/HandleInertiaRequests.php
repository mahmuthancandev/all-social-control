<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use App\Models\Comment;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'timezone' => config('app.timezone'),
                'demo_mode' => (bool) config('socialhub.demo_mode'),
            ],
            'auth' => [
                'user' => $user?->toSharedArray(),
            ],
            'counts' => fn () => $user ? [
                'notifications' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                'comments' => Comment::whereNull('read_at')->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
