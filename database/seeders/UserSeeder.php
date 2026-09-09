<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('portal.seed_admin.email');
        $password = config('portal.seed_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD must be configured before seeding the admin account.');
        }

        User::updateOrCreate(
            ['user_id' => config('portal.seed_admin.user_id')],
            [
                'role' => 'admin',
                'status' => 'active',
                'firstName' => 'System',
                'lastName' => 'Administrator',
                'middleName' => null,
                'contact' => '00000000000',
                'birthDate' => '1970-01-01',
                'birthPlace' => 'N/A',
                'barangay' => 'N/A',
                'city' => 'N/A',
                'province' => 'N/A',
                'region' => 'N/A',
                'email' => $email,
                'password' => Hash::make($password),
            ],
        );
    }
}
