<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PhilippineSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(DemoUserSeeder::class);
    }
}
