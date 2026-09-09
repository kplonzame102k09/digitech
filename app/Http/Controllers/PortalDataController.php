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
            'value' => ['required', 'array'],
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
        $validated = $request->validate([
            'users' => ['required', 'array', 'max:1000'],
            'users.*.id' => ['required', 'string', 'max:100'],
            'users.*.role' => ['nullable', 'in:admin,teacher,student,parent,guest'],
            'users.*.email' => ['nullable', 'email', 'max:255'],
            'users.*.password' => ['nullable', 'string', 'min:6'],
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
