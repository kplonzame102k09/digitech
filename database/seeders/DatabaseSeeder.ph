<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate([
            'user_id' => 'ADMIN-000001',
            'role' => 'admin',
            'firstName' => 'System',
            'lastName' => 'Administrator',
            'middleName' => null,
            'contact' => '09123456789',
            'birthDate' => '1990-01-01',
            'birthPlace' => 'Lucena City',
            'barangay' => 'Barangay 1',
            'city' => 'Lucena',
            'province' => 'Quezon',
            'region' => 'CALABARZON',
            'email' => 'admin@digitech.edu',
            'password' => Hash::make('admin123'),
            'rolePassword' => Hash::make('ADMIN@TEACHER123'),
        ]);
    }
}
