<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    private const WEAK_PASSWORDS = ['password', 'admin', 'administrator', '12345678', 'changeme'];

    public function run(): void
    {
        $email = config('portal.seed_admin.email');
        $password = config('portal.seed_admin.password');

        if (! is_string($email) || $email === '') {
            throw new RuntimeException('ADMIN_SEED_EMAIL must be configured before seeding the admin account.');
        }

        if (! is_string($password) || $password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('ADMIN_SEED_PASSWORD must be configured before seeding the admin account in production.');
            }

            $password = Str::password(20);
            $this->command?->warn('Generated admin password (shown once): '.$password);
        }

        if (app()->environment('production') && in_array(strtolower($password), self::WEAK_PASSWORDS, true)) {
            throw new RuntimeException('ADMIN_SEED_PASSWORD is a known weak password. Choose a strong one before seeding in production.');
        }

        // Create only: re-running the seeder must never reset an existing
        // administrator password to a value known from configuration.
        User::query()->firstOrCreate(
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
                'mustChangePassword' => true,
            ],
        );
    }
}
