<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->viewer()->create(['password' => 'Eski12345']);

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'Eski12345',
            'password' => 'Yeni12345',
            'password_confirmation' => 'Yeni12345',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Yeni12345', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Eski12345']);

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'yanlis',
            'password' => 'Yeni12345',
            'password_confirmation' => 'Yeni12345',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Eski12345', $user->fresh()->password));
    }
}
