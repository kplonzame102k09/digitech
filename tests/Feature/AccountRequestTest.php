<?php

namespace Tests\Feature;

use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_creation_page_loads(): void
    {
        $this->get(route('account.request'))->assertOk()->assertSee('Request an account');
    }

    public function test_store_creates_pending_student_request(): void
    {
        $payload = [
            'firstName' => 'Juan',
            'middleName' => 'Reyes',
            'lastName' => 'Dela Cruz',
            'role' => 'student',
            'email' => 'juan.request@student.local',
            'contact' => '09171234567',
            'strand' => 'TVET Cookery NC II',
            'purpose' => 'I am enrolling as a first year TVET student for SY 2026-2027.',
        ];

        $response = $this->postJson(route('account-requests.store'), $payload);

        $response->assertCreated()->assertJson(['ok' => true]);
        $this->assertDatabaseCount('account_requests', 1);

        $request = AccountRequest::first();
        $this->assertEquals('student', $request->role);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals('juan.request@student.local', $request->email);
        $this->assertStringStartsWith('STU-REQ-', $request->request_id);
    }

    public function test_store_rejects_admin_and_teacher_roles(): void
    {
        $payload = [
            'firstName' => 'Bad',
            'lastName' => 'Role',
            'role' => 'admin',
            'email' => 'bad.role@student.local',
            'purpose' => 'Testing that admin and teacher roles are rejected for self-service requests.',
        ];

        $this->postJson(route('account-requests.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_store_requires_purpose(): void
    {
        $payload = [
            'firstName' => 'No',
            'lastName' => 'Purpose',
            'role' => 'guest',
            'email' => 'no.purpose@student.local',
            'purpose' => 'short',
        ];

        $this->postJson(route('account-requests.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('purpose');
    }

    public function test_store_rejects_existing_user_email(): void
    {
        User::factory()->create(['firstName' => 'Existing', 'lastName' => 'User', 'email' => 'exists@student.local', 'role' => 'student']);

        $payload = [
            'firstName' => 'New',
            'lastName' => 'Person',
            'role' => 'student',
            'email' => 'EXISTS@student.local',
            'purpose' => 'I would like an account but the email is already in use here.',
        ];

        $this->postJson(route('account-requests.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_store_rejects_duplicate_pending_request(): void
    {
        AccountRequest::create([
            'request_id' => 'GST-REQ-DUP01',
            'role' => 'guest',
            'status' => 'pending',
            'firstName' => 'Pending',
            'lastName' => 'Person',
            'email' => 'dup@student.local',
            'purpose' => 'An already pending request for this email should be detected.',
        ]);

        $payload = [
            'firstName' => 'Pending',
            'lastName' => 'Person',
            'role' => 'guest',
            'email' => 'dup@student.local',
            'purpose' => 'A second submission with the exact same pending email must be blocked.',
        ];

        $this->postJson(route('account-requests.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}