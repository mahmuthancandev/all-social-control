<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'users_total' => User::count(),
                'admins_total' => User::where('role', 'admin')->count(),
                'active_users' => User::where('is_active', true)->count(),
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => config('database.default'),
                'queue' => config('queue.default'),
                'timezone' => config('app.timezone'),
            ],
            'recentActivity' => $request->user()->isAdmin()
                ? AuditLog::with('user:id,name')
                    ->latest('created_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (AuditLog $log) => [
                        'id' => $log->id,
                        'action' => $log->action,
                        'user' => $log->user?->name,
                        'created_at' => $log->created_at?->toIso8601String(),
                    ])
                : [],
        ]);
    }
}
