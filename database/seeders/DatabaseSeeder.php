<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local and demo environments only. On the live server run
     * `php artisan kertai:install` instead.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Refusing to load demo data in production. Use php artisan kertai:install.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
