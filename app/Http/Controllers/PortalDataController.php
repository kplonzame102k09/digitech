<?php

namespace App\Http\Controllers;

use App\Jobs\ImportUsers;
use App\Models\Requirement;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalDataController extends Controller
{
    public function __construct(private PortalDataService $portal) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'collections' => $this->portal->allCollections($request->user()),
        ]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'key' => $key,
            'value' => $this->portal->getCollection($key, $request->user()),
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
        $this->authorize('create', User::class);

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
        $this->authorize('viewAny', User::class);

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

    public function uploadRequirementFile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'student'], true), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:5120'],
            'studentId' => ['sometimes', 'nullable', 'string'],
        ]);

        // Requirement files live on the private disk and are only reachable
        // through the ownership-checked download route.
        $path = $request->file('file')->store('requirement-files', 'private');

        $fileName = basename($path);

        return response()->json([
            'ok' => true,
            'fileUrl' => url('/api/portal/requirements/files/'.rawurlencode($fileName)),
            'studentId' => $user->role === 'student' ? $user->user_id : ($validated['studentId'] ?? null),
        ]);
    }

    public function downloadRequirementFile(Request $request, string $file): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 401);

        $fileName = basename($file);
        $path = 'requirement-files/'.$fileName;
        $disk = Storage::disk('private');
        abort_unless($disk->exists($path), 404);

        if (! $user->isAdmin() && ! $this->canAccessRequirementFile($user, $fileName)) {
            abort(403);
        }

        return $disk->download($path);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'teacher'], true), 403);

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

        // Replacing a photo removes the previous one so uploads never pile up.
        $previous = $user->photo;
        if (is_string($previous) && $previous !== ''
            && ! str_starts_with($previous, ['http://', 'https://', 'data:'])
            && ! str_starts_with($previous, '/')) {
            Storage::disk('public')->delete($previous);
        }

        $user->update(['photo' => $path]);

        return response()->json([
            'ok' => true,
            'photo' => Storage::url($path),
            'user' => $this->portal->userToJs($user),
        ]);
    }

    private function canUpdateCollection(User $user, string $key): bool
    {
        return $this->portal->canWriteCollection($user, $key);
    }

    private function canAccessRequirementFile(User $user, string $fileName): bool
    {
        // Turn the LIKE wildcards into literals so a file named "a_b.pdf" or
        // "50%.pdf" cannot bleed into other rows, then require an exact
        // basename match so a partial name like "res.pdf" never resolves to
        // an unrelated student's "thesis-res.pdf".
        $like = '%'.addcslashes($fileName, '%_\\').'%';

        $requirement = Requirement::query()
            ->where('fileUrl', 'like', $like)
            ->get()
            ->first(fn (Requirement $row): bool => basename((string) $row->fileUrl) === $fileName);

        // Only an actual requirement row can authorize a download. The old
        // fallback that let any student grab an unlinked (orphan) upload is
        // deliberately gone.
        if (! $requirement) {
            return false;
        }

        $studentId = $requirement->studentId;

        if ($user->isStudent() && $user->user_id === $studentId) {
            return true;
        }

        if ($user->isParent()) {
            $childIds = collect([$user->childId])
                ->concat($user->childIds ?? [])
                ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
                ->all();

            if (in_array($studentId, $childIds, true)) {
                return true;
            }

            $pivotChildIds = DB::table('parent_student')
                ->join('users as linked', 'linked.id', '=', 'parent_student.student_id')
                ->where('parent_student.parent_id', $user->getKey())
                ->pluck('linked.user_id')
                ->all();

            return in_array($studentId, $pivotChildIds, true);
        }

        if ($user->isTeacher()) {
            return in_array($studentId, $this->portal->getEnrolledStudentIds($user) ?? [], true);
        }

        return false;
    }
}
