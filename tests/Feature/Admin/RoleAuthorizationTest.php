<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_see_dashboard(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.role', 'viewer')
                ->where('auth.user.is_admin', false)
                ->where('recentActivity', []));
    }

    public function test_shared_user_props_do_not_leak_password_hash(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->missing('auth.user.password')->missing('auth.user.remember_token'));
    }

    public function test_viewer_cannot_access_user_management(): void
    {
        $viewer = User::factory()->viewer()->create();
        $target = User::factory()->create();

        $this->actingAs($viewer)->get('/admin/users')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/users', [
            'name' => 'X', 'email' => 'x@example.com', 'role' => 'admin',
            'password' => 'Abcdef123', 'password_confirmation' => 'Abcdef123',
        ])->assertForbidden();
        $this->actingAs($viewer)->put("/admin/users/{$target->id}", ['name' => 'Y', 'email' => $target->email, 'role' => 'admin'])->assertForbidden();
        $this->actingAs($viewer)->delete("/admin/users/{$target->id}")->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'viewer']);
    }

    public function test_guest_cannot_access_admin_routes(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Index')
                ->has('users.data', 4)
                ->has('roles', 2));
    }

    public function test_admin_can_create_user_with_hashed_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Yeni İzleyici',
            'email' => 'izleyici@example.com',
            'role' => 'viewer',
            'password' => 'Sifre1234',
            'password_confirmation' => 'Sifre1234',
            'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $created = User::where('email', 'izleyici@example.com')->firstOrFail();
        $this->assertNotSame('Sifre1234', $created->password);
        $this->assertTrue(password_verify('Sifre1234', $created->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $created->id]);
    }

    public function test_user_creation_validates_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => '',
            'email' => $admin->email,
            'role' => 'superuser',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'role', 'password']);
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($admin)->put("/admin/users/{$viewer->id}", [
            'name' => $viewer->name,
            'email' => $viewer->email,
            'role' => 'admin',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($viewer->fresh()->isAdmin());
    }

    public function test_admin_can_delete_other_user(): void
    {
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($admin)->delete("/admin/users/{$viewer->id}")->assertRedirect();

        $this->assertModelMissing($viewer);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertSessionHas('error');

        $this->assertModelExists($admin);
    }

    public function test_last_active_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'viewer',
            'is_active' => true,
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_inactive_admin_loses_admin_gate(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $this->assertFalse($admin->can('admin'));
    }
}
