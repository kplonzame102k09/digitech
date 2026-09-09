<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_user_can_log_in_with_their_account_password(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'password' => 'a-secure-password',
        ]);

        $response = $this->post('/login', [
            'role' => 'student',
            'user_id' => $user->user_id,
            'password' => 'a-secure-password',
        ]);

        $response->assertRedirect('/student/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'status' => 'inactive',
            'password' => 'a-secure-password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'role' => 'student',
            'user_id' => $user->user_id,
            'password' => 'a-secure-password',
        ]);

        $response->assertRedirect('/login')->assertSessionHasErrors('user_id');
        $this->assertGuest();
    }

    public function test_public_registration_rejects_privileged_roles(): void
    {
        $response = $this->from('/auth/signup')->post('/signup', [
            'role' => 'admin',
            'firstName' => 'Admin',
            'lastName' => 'Attempt',
            'contact' => '09123456789',
            'birthDate' => '2000-01-01',
            'birthPlace' => 'Manila',
            'barangay' => '000000001',
            'city' => '000000001',
            'province' => '000000001',
            'region' => '01',
            'email' => 'admin-attempt@example.test',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ]);

        $response->assertRedirect('/auth/signup')->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'admin-attempt@example.test']);
    }
}
