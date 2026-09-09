<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_who_must_change_password_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->create(['mustChangePassword' => true]);

        $this->actingAs($user)
            ->get('/student/dashboard')
            ->assertRedirect(route('auth.password.change'));

        $this->actingAs($user)
            ->get(route('auth.password.change'))
            ->assertOk()
            ->assertSee('Change password');
    }

    public function test_user_can_change_password_and_clears_the_flag(): void
    {
        $user = User::factory()->create([
            'password' => 'current-secure-password',
            'mustChangePassword' => true,
        ]);

        $this->actingAs($user)
            ->post(route('auth.password.update'), [
                'current_password' => 'current-secure-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect('/student/dashboard')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'mustChangePassword' => false,
        ]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'current-secure-password',
            'mustChangePassword' => true,
        ]);

        $this->actingAs($user)
            ->from(route('auth.password.change'))
            ->post(route('auth.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('auth.password.change'))
            ->assertSessionHasErrors('current_password');
    }
}
