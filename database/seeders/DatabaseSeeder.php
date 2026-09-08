<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $rolePassword = env('ROLE_PASSWORD', 'ADMIN@TEACHER123');

        $admin = User::updateOrCreate(
            ['user_id' => 'ADMIN-000001'],
            [
                'role' => 'admin',
                'status' => 'active',
                'firstName' => 'System',
                'lastName' => 'Administrator',
                'contact' => '09123456789',
                'birthDate' => '1990-01-01',
                'birthPlace' => 'Lucena City',
                'barangay' => 'Barangay 1',
                'city' => 'Lucena',
                'province' => 'Quezon',
                'region' => 'CALABARZON',
                'email' => 'admin@digitech.edu',
                'password' => Hash::make('admin123'),
                'rolePassword' => Hash::make($rolePassword),
            ]
        );
    }
}
