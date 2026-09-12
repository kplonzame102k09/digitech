<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Display the current user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        return response()->json([
            'ok' => true,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Update the current user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validated();

        // A dedicated photo upload writes the new file; a plain string here is
        // the client bouncing an existing photo path back, so it is normalised
        // into the storage-relative form before it can be persisted.
        if (isset($validated['photo']) && is_string($validated['photo'])) {
            $validated['photo'] = $this->normalizePhotoPath($validated['photo']);
        }

        // Remove empty values to keep existing data
        $updateData = array_filter($validated, fn ($value) => $value !== null && $value !== '');

        $changed = array_filter(
            $updateData,
            fn ($value, $key) => (string) $user->getAttribute($key) !== (string) $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed !== []) {
            $user->update($changed);

            $fields = array_keys($changed);
            AuditLog::record([
                'entity' => AuditLog::ENTITY_USER,
                'recordId' => $user->user_id,
                'action' => 'profile.updated',
                'from' => json_encode(array_intersect_key($user->getOriginal(), array_flip($fields)), JSON_UNESCAPED_SLASHES),
                'to' => json_encode($changed, JSON_UNESCAPED_SLASHES),
                'notes' => ucfirst($user->role).' profile updated ('.implode(', ', $fields).').',
                'actorId' => $user->user_id,
            ]);
        }

        return response()->json([
            'ok' => true,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Profile endpoints are singletons, no index or destroy needed.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'Use the show endpoint to get the current user profile.',
        ], 405);
    }

    /**
     * Profile endpoints are singletons, no store needed.
     */
    public function store(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'Use the update endpoint to modify the current user profile.',
        ], 405);
    }

    /**
     * Profile endpoints are singletons, no destroy needed.
     */
    public function destroy(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'User profiles cannot be deleted via this endpoint.',
        ], 405);
    }

    /**
     * Handle profile photo upload.
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $photo = $request->file('photo');
        $path = $photo->store('profile-photos', 'public');

        // Replacing a photo removes the previous one so uploads never pile up.
        $previous = $user->photo;
        if (is_string($previous) && $previous !== ''
            && ! Str::startsWith($previous, ['http://', 'https://', 'data:', '/'])
            && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        $user->update(['photo' => $path]);

        return response()->json([
            'ok' => true,
            'photo' => Storage::url($path),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->user_id,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'middleName' => $user->middleName,
            'email' => $user->email,
            'username' => $user->username,
            'contact' => $user->contact,
            'role' => $user->role,
            'status' => $user->status ?: 'active',
            'birthDate' => $user->birthDate?->format('Y-m-d'),
            'birthPlace' => $user->birthPlace,
            'barangay' => $user->barangay,
            'city' => $user->city,
            'province' => $user->province,
            'region' => $user->region,
            'photo' => $user->photo ? $this->publicPhotoUrl($user->photo) : '',
            'strand' => $user->strand,
            'address' => $user->address,
            'childId' => $user->childId,
            'childIds' => $user->childIds ?? [],
            'guardianName' => $user->guardianName,
            'guardianContact' => $user->guardianContact,
            'mustChangePassword' => (bool) $user->mustChangePassword,
            'employeeId' => $user->employeeId,
            'department' => $user->department,
            'createdAt' => $user->created_at?->toIso8601String(),
            'updatedAt' => $user->updated_at?->toIso8601String(),
            ...$this->extraPayload($user),
        ];
    }

    private function extraPayload(User $user): array
    {
        $extra = is_array($user->profile_extra) ? $user->profile_extra : [];
        $payload = [];

        // These fields are now direct columns on the users table
        foreach (['occupation', 'emergencyContact', 'specialization'] as $key) {
            if (($user->$key ?? null) !== null) {
                $payload[$key] = $user->$key;
            }
        }

        return $payload;
    }

    private function publicPhotoUrl(string $path): string
    {
        return Str::startsWith($path, ['http://', 'https://', '/'])
            ? $path
            : asset('storage/'.ltrim($path, '/'));
    }

    private function normalizePhotoPath(mixed $photo): ?string
    {
        if (! is_string($photo) || trim($photo) === '') {
            return null;
        }

        if (Str::startsWith($photo, ['http://', 'https://', 'data:'])) {
            return $photo;
        }

        return preg_replace('#^/?storage(?:/|$)#', '', $photo);
    }
}
