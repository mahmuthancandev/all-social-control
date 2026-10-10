<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Login', [
            'demoAccounts' => config('socialhub.show_demo_credentials')
                ? [
                    ['role' => 'Yönetici', 'email' => 'admin@socialhub.local', 'password' => 'Admin123!'],
                    ['role' => 'İzleyici', 'email' => 'viewer@socialhub.local', 'password' => 'Viewer123!'],
                ]
                : [],
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login', $user, userId: $user->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            Audit::log('auth.logout', $user, userId: $user->id);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
