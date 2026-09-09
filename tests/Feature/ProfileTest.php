<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_fetch_their_profile(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->getJson('/student/api/profile');

        $response->assertOk();
        $response->assertJsonPath('user.id', $student->user_id);
        $response->assertJsonPath('user.role', 'student');
    }

    public function test_student_can_update_profile_fields(): void
    {
        $student = User::factory()->create(['role' => 'student', 'contact' => '09111111111']);

        $response = $this->actingAs($student)->putJson('/student/api/profile', ['contact' => '09999999999']);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'contact' => '09999999999',
        ]);
    }

    public function test_photo_upload_rejects_invalid_mimes(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'not an image');

        $response = $this->actingAs($student)->postJson('/student/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photo');
    }

    public function test_photo_upload_accepts_valid_image_and_stores_in_public_disk(): void
    {
        Storage::fake('public');
        $student = User::factory()->create(['role' => 'student', 'photo' => null]);
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(100);

        $response = $this->actingAs($student)->postJson('/student/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertOk();
        $storedPath = 'profile-photos/'.$file->hashName();
        Storage::disk('public')->assertExists($storedPath);
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'photo' => $storedPath,
        ]);
    }
}
