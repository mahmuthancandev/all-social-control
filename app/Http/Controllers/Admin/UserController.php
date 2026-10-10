<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $role = $request->query('role');

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when(in_array($role, ['admin', 'viewer'], true), fn ($q) => $q->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role],
            'roles' => UserRole::options(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        Audit::log('user.created', $user, ['email' => $user->email, 'role' => $user->role->value]);

        return back()->with('success', "{$user->name} kullanıcısı oluşturuldu.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $demotingOrDisabling = $user->isAdmin()
            && ($data['role'] !== UserRole::Admin->value || ! ($data['is_active'] ?? true));

        if ($demotingOrDisabling && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Sistemde en az bir aktif yönetici kalmalıdır.');
        }

        if ($user->is($actor) && ! ($data['is_active'] ?? true)) {
            return back()->with('error', 'Kendi hesabınızı devre dışı bırakamazsınız.');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $changes = array_keys($user->getDirty());
        $user->save();

        Audit::log('user.updated', $user, ['changed' => $changes]);

        return back()->with('success', "{$user->name} güncellendi.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Kendi hesabınızı silemezsiniz.');
        }

        if ($user->isAdmin() && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Son aktif yönetici silinemez.');
        }

        Audit::log('user.deleted', $user, ['email' => $user->email]);
        $user->delete();

        return back()->with('success', 'Kullanıcı silindi.');
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->isAdmin()
            && $user->is_active
            && User::where('role', UserRole::Admin->value)->where('is_active', true)->count() <= 1;
    }
}
