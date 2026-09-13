<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomActivity;
use App\Models\ClassroomSubmission;
use App\Policies\ClassroomWorkPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassroomFileController extends Controller
{
    public function __construct(private ClassroomWorkPolicy $work) {}

    /**
     * Upload a classroom/activity file to the private disk.
     * Teachers (own classroom work) and roster students may upload.
     */
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:5120'],
            'classroom_id' => ['required', 'string'],
        ]);

        $classroom = Classroom::query()->findOrFail($validated['classroom_id']);

        $allowed = $this->work->ownsClassroom($user, $classroom)
            || $this->work->isRosterStudent($user, $classroom)
            || $user->isAdmin();
        abort_unless($allowed, 403);

        $path = $request->file('file')->store('classroom-files', 'private');
        $fileName = basename($path);

        return response()->json([
            'ok' => true,
            'fileUrl' => url('/api/classroom-files/'.rawurlencode($fileName)),
        ]);
    }

    public function download(Request $request, string $file): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $fileName = basename($file);
        $path = 'classroom-files/'.$fileName;
        $disk = Storage::disk('private');
        abort_unless($disk->exists($path), 404);

        abort_unless($this->canAccess($user, $fileName), 403);

        return $disk->download($path);
    }

    private function canAccess($user, string $fileName): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Exact-basename match on submission fileUrls (LIKE-escaped).
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $fileName).'%';
        $submission = ClassroomSubmission::query()
            ->where('fileUrl', 'like', $like)
            ->with('activity')
            ->first();

        if (! $submission || ! $submission->activity) {
            return false;
        }

        $classroom = $submission->activity->classroom;
        if (! $classroom) {
            return false;
        }

        if ($this->work->ownsClassroom($user, $classroom)) {
            return true;
        }

        // Roster students may view/download classmates' submissions (classroom work).
        if ($user->isStudent() && $this->work->isRosterStudent($user, $classroom)) {
            return true;
        }

        return false;
    }
}
