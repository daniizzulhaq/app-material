<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,        // admin@admin.com / password
        ]);
    }
}
