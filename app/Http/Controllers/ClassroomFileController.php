<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomActivity;
use App\Models\ClassroomMessage;
use App\Models\ClassroomSubmission;
use App\Policies\ClassroomWorkPolicy;
use App\Rules\TrustedUploadFile;
use App\Support\FileSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'file' => ['required', 'file', new TrustedUploadFile(['pdf', 'jpeg', 'jpg', 'png', 'webp']), 'max:5120'],
            'classroom_id' => ['required', 'string'],
        ]);

        $classroom = Classroom::query()->findOrFail($validated['classroom_id']);

        $allowed = $this->work->ownsClassroom($user, $classroom)
            || $this->work->isRosterStudent($user, $classroom)
            || $user->isAdmin();
        abort_unless($allowed, 403);

        $file = $request->file('file');

        try {
            $path = $file->storeAs(
                'classroom-files',
                Str::random(40).'.'.(FileSignature::detectFile((string) $file->getRealPath()) ?? 'bin'),
                'private'
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => 'Could not save the uploaded file. Please try again.',
            ], 500);
        }
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

        $classroom = $this->classroomForFile($fileName);

        if (! $classroom) {
            return false;
        }

        if ($this->work->ownsClassroom($user, $classroom)) {
            return true;
        }

        // Roster students may read anything shared in their own classroom
        // (classmates' submissions, and message attachments).
        return $user->isStudent() && $this->work->isRosterStudent($user, $classroom);
    }

    /**
     * Resolve the classroom a stored file belongs to.
     *
     * Both lookups are needed. A file is referenced either by a submission in
     * the classroom or by a message in the channel, and message attachments
     * are never linked to a ClassroomSubmission — checking submissions alone
     * would make every attached file 403 on download even though the upload
     * succeeded.
     */
    private function classroomForFile(string $fileName): ?Classroom
    {
        // Exact-basename match on the fileUrl columns (LIKE-escaped).
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $fileName).'%';

        $submission = ClassroomSubmission::query()
            ->where('fileUrl', 'like', $like)
            ->with('activity')
            ->first();

        if ($submission?->activity?->classroom) {
            return $submission->activity->classroom;
        }

        $message = ClassroomMessage::query()
            ->where('fileUrl', 'like', $like)
            ->with('classroom')
            ->first();

        return $message?->classroom;
    }
}
