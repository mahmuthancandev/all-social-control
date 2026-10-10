<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-pass'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'Secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        // Doğru şifre bile kilit süresince reddedilir.
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_audit_log_never_contains_passwords(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Yeni Kişi',
            'email' => 'yeni@example.com',
            'role' => 'viewer',
            'password' => 'Gizli12345',
            'password_confirmation' => 'Gizli12345',
            'is_active' => true,
        ]);

        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('Gizli12345', json_encode($log->properties) ?: '');
        }
    }
}
