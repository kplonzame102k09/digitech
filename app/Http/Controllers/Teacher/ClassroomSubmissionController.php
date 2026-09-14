<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\ClassroomActivity;
use App\Models\ClassroomSubmission;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Policies\ClassroomWorkPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassroomSubmissionController extends Controller
{
    public function __construct(private ClassroomWorkPolicy $work) {}

    private function ownedClassroom(User $user, string $id): Classroom
    {
        $classroom = Classroom::query()->findOrFail($id);
        abort_unless($this->work->ownsClassroom($user, $classroom), 403);

        return $classroom;
    }

    private function rosterIds(Classroom $classroom): array
    {
        return $classroom->students()->pluck('users.user_id')->all();
    }

    public function index(Request $request, string $id): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);

        $activityIds = ClassroomActivity::query()
            ->where('classroom_id', $classroom->id)
            ->pluck('id')
            ->all();

        $submissions = ClassroomSubmission::query()
            ->whereIn('activity_id', $activityIds === [] ? ['__none__'] : $activityIds)
            ->with(['activity', 'student'])
            ->orderByDesc('submittedAt')
            ->get();

        return response()->json([
            'ok' => true,
            'subject' => $classroom->subject,
            'submissions' => $submissions->map(fn (ClassroomSubmission $s) => $this->serialize($s))->values(),
        ]);
    }

    public function show(Request $request, string $id, string $submissionId): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);
        $submission = $this->scopedSubmission($classroom, $submissionId);
        $submission->load(['activity', 'student']);

        $data = $this->serialize($submission, true);
        $data['subject'] = $classroom->subject;

        return response()->json(['ok' => true, 'submission' => $data]);
    }

    /**
     * Score a submission (0-100). Does NOT create a Grade row per activity;
     * term grades live in the gradebook (one row per student per fixed subject).
     */
    public function score(Request $request, string $id, string $submissionId): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);
        $submission = $this->scopedSubmission($classroom, $submissionId);

        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $submission->score = round((float) $validated['score'], 2);
        $submission->status = ClassroomSubmission::GRADED;
        $submission->gradedAt = now();
        $submission->gradedBy = $request->user()->user_id;
        $submission->save();

        AuditLog::record([
            'entity' => 'classroom',
            'recordId' => $classroom->id,
            'action' => 'classroom.submission_scored',
            'actorId' => $request->user()->user_id,
            'notes' => "Submission {$submission->id} scored {$submission->score}",
        ]);

        if ((bool) (SystemSetting::getInstance()->notifyStudents ?? true)) {
            Notification::alert(
                (string) $submission->studentId,
                'Submission graded',
                ($submission->activity?->title ?? 'An activity')." was scored {$submission->score} in {$classroom->name}.",
                'grade',
                (string) $submission->id,
            );
        }

        return response()->json(['ok' => true, 'submission' => $this->serialize($submission)]);
    }

    public function return(Request $request, string $id, string $submissionId): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);
        $submission = $this->scopedSubmission($classroom, $submissionId);

        $submission->status = ClassroomSubmission::RETURNED;
        $submission->save();

        AuditLog::record([
            'entity' => AuditLog::ENTITY_SUBMISSION,
            'recordId' => (string) $submission->id,
            'action' => 'submission.returned',
            'notes' => 'Submission returned to '.(string) $submission->studentId.'.',
            'actorId' => $request->user()->user_id,
        ]);

        if ((bool) (SystemSetting::getInstance()->notifyStudents ?? true)) {
            Notification::alert(
                (string) $submission->studentId,
                'Submission returned',
                ($submission->activity?->title ?? 'An activity')." was returned in {$classroom->name}.",
                'grade',
                (string) $submission->id,
            );
        }

        return response()->json(['ok' => true, 'submission' => $this->serialize($submission)]);
    }

    /**
     * Gradebook: one row per roster student for the FIXED classroom subject.
     * Per-term averages come from ClassroomGradeService (mean of scored
     * submissions in Prelim|Midterm|Finals activities); final is the simple
     * mean of present terms with CHED GWA.
     */
    public function gradebook(Request $request, string $id): JsonResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);

        $validated = $request->validate([
            'semester' => ['nullable', 'string', 'max:30'],
            'schoolYear' => ['nullable', 'string', 'max:30'],
        ]);

        $students = $classroom->students()->orderBy('firstName')->orderBy('lastName')->get();
        $grading = new \App\Services\ClassroomGradeService();

        $rows = $students->map(function (User $s) use ($classroom, $validated, $grading) {
            $q = Grade::query()
                ->where('studentId', $s->user_id)
                ->where('subject', $classroom->subject);
            if (! empty($validated['semester'])) {
                $q->where('semester', $validated['semester']);
            }
            if (! empty($validated['schoolYear'])) {
                $q->where('schoolYear', $validated['schoolYear']);
            }
            $grade = $q->orderByDesc('updated_at')->first();

            $computed = $grading->forStudent($classroom, $s->user_id);

            return [
                'studentId' => $s->user_id,
                'firstName' => $s->firstName,
                'lastName' => $s->lastName,
                'photo' => $s->photo,
                'prelimAvg' => $computed['averages']['Prelim'],
                'midtermAvg' => $computed['averages']['Midterm'],
                'finalsAvg' => $computed['averages']['Finals'],
                'termCounts' => $computed['counts'],
                'computedFinal' => $computed['final'],
                'computedGwa' => $computed['gwa'],
                'suggestedScore' => $computed['final'],
                'grade' => $grade ? [
                    'id' => $grade->id,
                    'prelim' => $grade->prelim !== null ? (float) $grade->prelim : null,
                    'midterm' => $grade->midterm !== null ? (float) $grade->midterm : null,
                    'finals' => $grade->finals !== null ? (float) $grade->finals : null,
                    'finalGrade' => $grade->finalGrade !== null ? (float) $grade->finalGrade : null,
                    'units' => (float) $grade->units,
                    'semester' => $grade->semester,
                    'schoolYear' => $grade->schoolYear,
                    'published' => (bool) $grade->published,
                ] : null,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'subject' => $classroom->subject,
            'rows' => $rows,
        ]);
    }

    /**
     * Write a term grade for a roster student in the fixed subject.
     * Classroom rule: final is the SIMPLE mean of present prelim/midterm/
     * finals (not the weighted Grade::previewFinal used elsewhere).
     * Pass {"autofill": true} to copy the student's per-term activity
     * averages into prelim/midterm/finals before computing.
     */
    public function gradeStudent(Request $request, string $id, string $studentId): JsonResponse
    {
        $user = $request->user();
        $classroom = $this->ownedClassroom($user, $id);

        abort_unless($classroom->students()->where('users.user_id', $studentId)->exists(), 422, 'Student is not in this classroom.');

        $validated = $request->validate([
            'prelim' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midterm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'finals' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'units' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'semester' => ['nullable', 'string', 'max:30'],
            'schoolYear' => ['nullable', 'string', 'max:30'],
            'autofill' => ['nullable', 'boolean'],
        ]);

        $semester = $validated['semester'] ?? Grade::SEMESTER_FIRST;
        $schoolYear = $validated['schoolYear'] ?? ((string) now()->year.'-'.((int) now()->year + 1));

        if (! empty($validated['autofill'])) {
            $computed = (new \App\Services\ClassroomGradeService())->forStudent($classroom, $studentId);
            $validated['prelim'] = $computed['averages']['Prelim'];
            $validated['midterm'] = $computed['averages']['Midterm'];
            $validated['finals'] = $computed['averages']['Finals'];
        }

        $grade = Grade::query()
            ->where('studentId', $studentId)
            ->where('subject', $classroom->subject)
            ->where('semester', $semester)
            ->where('schoolYear', $schoolYear)
            ->first();

        if (! $grade) {
            $grade = new Grade([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'studentId' => $studentId,
                'subject' => $classroom->subject,
                'semester' => $semester,
                'schoolYear' => $schoolYear,
                'teacherId' => $user->user_id,
                'units' => $validated['units'] ?? 3,
                'published' => false,
            ]);
        }

        $grade->prelim = $validated['prelim'] ?? $grade->prelim;
        $grade->midterm = $validated['midterm'] ?? $grade->midterm;
        $grade->finals = $validated['finals'] ?? $grade->finals;
        if (array_key_exists('units', $validated) && $validated['units'] !== null) {
            $grade->units = $validated['units'];
        }
        $grade->teacherId = $user->user_id;
        $grade->updatedBy = $user->user_id;
        $grade->finalGrade = \App\Services\ClassroomGradeService::meanOfPresent(
            $grade->prelim !== null ? (float) $grade->prelim : null,
            $grade->midterm !== null ? (float) $grade->midterm : null,
            $grade->finals !== null ? (float) $grade->finals : null,
        );
        $grade->remarks = $grade->finalGrade !== null && $grade->finalGrade >= 75 ? 'Passed' : 'Failed';
        $grade->save();

        AuditLog::record([
            'entity' => AuditLog::ENTITY_GRADE,
            'recordId' => (string) $grade->id,
            'action' => 'grade.updated',
            'notes' => "Grade saved for {$studentId} in {$classroom->subject}.",
            'actorId' => $user->user_id,
        ]);

        if ((bool) (SystemSetting::getInstance()->notifyStudents ?? true)) {
            Notification::alert(
                (string) $studentId,
                'Grade updated',
                "Your {$classroom->subject} grade was updated by your teacher.",
                'grade',
                (string) $grade->id,
            );
        }

        return response()->json(['ok' => true, 'grade' => [
            'id' => $grade->id,
            'prelim' => $grade->prelim !== null ? (float) $grade->prelim : null,
            'midterm' => $grade->midterm !== null ? (float) $grade->midterm : null,
            'finals' => $grade->finals !== null ? (float) $grade->finals : null,
            'finalGrade' => $grade->finalGrade !== null ? (float) $grade->finalGrade : null,
            'gwa' => $grade->finalGrade !== null ? Grade::convertToGwa((float) $grade->finalGrade) : null,
            'remarks' => $grade->remarks,
        ]]);
    }

    public function export(Request $request, string $id): StreamedResponse
    {
        $classroom = $this->ownedClassroom($request->user(), $id);

        $activityIds = ClassroomActivity::query()
            ->where('classroom_id', $classroom->id)
            ->pluck('id', 'title');

        $submissions = ClassroomSubmission::query()
            ->whereIn('activity_id', $activityIds->keys()->all() === [] ? ['__none__'] : $activityIds->keys()->all())
            ->with(['student', 'activity'])
            ->orderBy('studentId')
            ->get();

        $fileName = 'classroom-'.$classroom->id.'-submissions.csv';

        return response()->streamDownload(function () use ($submissions, $classroom): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student ID', 'Student', 'Activity', 'Term', 'Subject', 'Status', 'Score', 'File', 'Submitted', 'Graded By']);
            foreach ($submissions as $s) {
                fputcsv($out, [
                    $s->studentId,
                    trim(($s->student?->firstName ?? '').' '.($s->student?->lastName ?? '')),
                    $s->activity?->title ?? '',
                    $s->activity?->term ?? 'Prelim',
                    $classroom->subject,
                    $s->status,
                    $s->score,
                    $s->fileUrl ?? '',
                    $s->submittedAt?->toDateTimeString() ?? '',
                    $s->gradedBy ?? '',
                ]);
            }
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function scopedSubmission(Classroom $classroom, string $submissionId): ClassroomSubmission
    {
        $activityIds = ClassroomActivity::query()
            ->where('classroom_id', $classroom->id)
            ->pluck('id')
            ->all();

        return ClassroomSubmission::query()
            ->where('id', $submissionId)
            ->whereIn('activity_id', $activityIds === [] ? ['__none__'] : $activityIds)
            ->firstOrFail();
    }

    private function serialize(ClassroomSubmission $s, bool $detail = false): array
    {
        $data = [
            'id' => $s->id,
            'activityId' => $s->activity_id,
            'activityTitle' => $s->activity?->title,
            'activityTerm' => $s->activity?->term ?? 'Prelim',
            'studentId' => $s->studentId,
            'studentName' => $s->student ? trim($s->student->firstName.' '.$s->student->lastName) : $s->studentId,
            'studentPhoto' => $s->student?->photo,
            'fileUrl' => $s->fileUrl,
            'notes' => $s->notes,
            'status' => $s->status,
            'score' => $s->score !== null ? (float) $s->score : null,
            'submittedAt' => $s->submittedAt?->toIso8601String(),
            'gradedAt' => $s->gradedAt?->toIso8601String(),
            'gradedBy' => $s->gradedBy,
        ];

        if ($detail) {
            $data['studentEmail'] = $s->student?->email;
        }

        return $data;
    }
}
