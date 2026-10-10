<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Yerel kullanım için demo hesaplar. İlk girişten sonra şifreleri değiştirin.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@socialhub.local'],
            ['name' => 'SocialHub Yönetici', 'password' => 'Admin123!', 'role' => UserRole::Admin, 'is_active' => true, 'email_verified_at' => now()],
        );

        User::updateOrCreate(
            ['email' => 'viewer@socialhub.local'],
            ['name' => 'SocialHub İzleyici', 'password' => 'Viewer123!', 'role' => UserRole::Viewer, 'is_active' => true, 'email_verified_at' => now()],
        );
    }
}
