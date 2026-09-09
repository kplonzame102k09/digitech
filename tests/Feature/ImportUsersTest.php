<?php

namespace Tests\Feature;

use App\Jobs\ImportUsers;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ImportUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_queues_one_job_per_ten_imported_users(): void
    {
        $admin = User::factory()->create([
            'user_id' => 'ADM-2026-000001',
            'role' => 'admin',
            'firstName' => 'Admin',
            'lastName' => 'User',
            'contact' => '09123456789',
            'birthDate' => '2000-01-01',
            'birthPlace' => 'Manila',
            'barangay' => '000000001',
            'city' => '000000001',
            'province' => '000000001',
            'region' => '01',
        ]);
        $users = collect(range(1, 100))
            ->map(fn (int $number): array => [
                'id' => "STU-2026-{$number}",
                'email' => "student{$number}@example.test",
                'password' => 'temporary-password',
                'role' => 'student',
            ])
            ->all();

        Bus::fake();

        $response = $this->actingAs($admin)->postJson('/api/portal/users/import', [
            'users' => $users,
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('users', 100);

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->name === 'Import users'
            && $batch->jobs->count() === 10
            && $batch->jobs->every(fn (mixed $job): bool => $job instanceof ImportUsers)
        );
    }
}
