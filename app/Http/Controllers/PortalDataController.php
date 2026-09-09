<?php

namespace App\Http\Controllers;

use App\Jobs\ImportUsers;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class PortalDataController extends Controller
{
    public function __construct(private PortalDataService $portal) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'collections' => $this->portal->allCollections(),
        ]);
    }

    public function show(string $key): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'key' => $key,
            'value' => $this->portal->getCollection($key),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($this->canUpdateCollection($user, $key), 403);

        return response()->json([
            'ok' => true,
            'key' => $key,
            'value' => $this->portal->putCollection($key, $validated['value'], $user),
        ]);
    }

    public function importUsers(Request $request): JsonResponse
    {
        // Imports run synchronously (QUEUE_CONNECTION=sync); hashing the
        // passwords of up to 1000 users exceeds the default 30s PHP limit.
        set_time_limit(600);

        $validated = $request->validate([
            'users' => ['required', 'array', 'max:1000'],
            'users.*.id' => ['nullable', 'string', 'max:100'],
            'users.*.role' => ['nullable', 'string', 'max:40'],
            'users.*.email' => ['nullable', 'string', 'max:255'],
            'users.*.password' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $jobs = collect($validated['users'])
            ->chunk(10)
            ->map(fn (Collection $users): ImportUsers => new ImportUsers($users->all()))
            ->all();

        $batch = Bus::batch($jobs)
            ->name('Import users')
            ->dispatch();

        return response()->json([
            'batchId' => $batch->id,
            'ok' => true,
            'users' => count($validated['users']),
        ], 202);
    }

    public function importStatus(Request $request, string $batchId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $batch = Bus::findBatch($batchId);
        abort_unless($batch instanceof Batch, 404);

        return response()->json([
            'cancelled' => $batch->cancelled(),
            'failedJobs' => $batch->failedJobs,
            'finished' => $batch->finished(),
            'pendingJobs' => $batch->pendingJobs,
            'processedJobs' => $batch->processedJobs(),
            'totalJobs' => $batch->totalJobs,
        ]);
    }

    public function boot(Request $request): JsonResponse
    {
        return response()->json(
            $this->portal->bootPayload($request->user())
        );
    }

    public function uploadImage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->role === 'teacher', 403);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('image')->store('uploads', 'public');

        return response()->json([
            'ok' => true,
            'photo' => Storage::url($path),
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 401);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('photo')->store('profile-photos', 'public');
        $user->update(['photo' => $path]);

        return response()->json([
            'ok' => true,
            'photo' => Storage::url($path),
            'user' => $this->portal->userToJs($user),
        ]);
    }

    private function canUpdateCollection(User $user, string $key): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return match ($user->role) {
            'teacher' => in_array($key, ['announcements', 'attendance', 'competencies', 'grades', 'notifications'], true),
            'student' => in_array($key, ['documentRequests', 'enrollments', 'notifications', 'requirements', 'users'], true),
            'parent' => in_array($key, ['notifications', 'parentLinkRequests', 'users'], true),
            default => false,
        };
    }
}
