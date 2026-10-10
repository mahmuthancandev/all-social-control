<?php

namespace Database\Seeders;

use App\Services\Demo\DemoDataGenerator;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(DemoDataGenerator $generator): void
    {
        if ($generator->exists()) {
            $this->command?->info('Demo veriler zaten mevcut, atlandı.');

            return;
        }

        $generator->generate();
    }
}
