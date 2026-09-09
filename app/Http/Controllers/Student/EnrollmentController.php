<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnrollmentController extends Controller
{
    /**
     * Display a listing of the current student's enrollments.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $enrollments = Enrollment::query()
            ->where('studentId', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'enrollments' => $enrollments->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'studentId' => $enrollment->studentId,
                'status' => $enrollment->status,
                'programType' => $enrollment->programType,
                'gradeLevel' => $enrollment->gradeLevel,
                'strand' => $enrollment->strand,
                'track' => $enrollment->track,
                'schoolYear' => $enrollment->schoolYear,
                'trainingLevel' => $enrollment->trainingLevel,
                'assignedTeacherId' => $enrollment->assignedTeacherId,
                'assignedSection' => $enrollment->assignedSection,
                'reviewNotes' => $enrollment->reviewNotes,
                'rejectionReason' => $enrollment->rejectionReason,
                'reviewedAt' => $enrollment->reviewedAt?->toIso8601String(),
                'reviewedBy' => $enrollment->reviewedBy,
                'createdAt' => $enrollment->created_at?->toIso8601String(),
                'updatedAt' => $enrollment->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Store a newly created enrollment in storage.
     */
    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        // Check for duplicate active enrollment for the same school year
        if (Enrollment::hasDuplicateActive($user->user_id, $validated['schoolYear'])) {
            return response()->json([
                'ok' => false,
                'error' => 'You already have an active enrollment for this school year.',
            ], 422);
        }

        $enrollment = Enrollment::query()->create([
            'id' => Str::uuid()->toString(),
            'studentId' => $user->user_id,
            'status' => 'Draft',
            'programType' => $validated['programType'],
            'gradeLevel' => $validated['gradeLevel'],
            'strand' => $validated['strand'],
            'track' => $validated['track'] ?? null,
            'schoolYear' => $validated['schoolYear'],
            'trainingLevel' => $validated['trainingLevel'] ?? null,
        ]);

        // Update user profile with enrollment details
        $user->update([
            'contact' => $validated['contact'],
            'birthDate' => $validated['birthDate'],
            'address' => $validated['address'],
            'guardianName' => $validated['guardianName'],
            'guardianContact' => $validated['guardianContact'],
            'strand' => $validated['strand'],
        ]);

        return response()->json([
            'ok' => true,
            'enrollment' => [
                'id' => $enrollment->id,
                'studentId' => $enrollment->studentId,
                'status' => $enrollment->status,
                'programType' => $enrollment->programType,
                'gradeLevel' => $enrollment->gradeLevel,
                'strand' => $enrollment->strand,
                'track' => $enrollment->track,
                'schoolYear' => $enrollment->schoolYear,
                'trainingLevel' => $enrollment->trainingLevel,
                'createdAt' => $enrollment->created_at?->toIso8601String(),
                'updatedAt' => $enrollment->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Display the specified enrollment.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $enrollment = Enrollment::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        return response()->json([
            'ok' => true,
            'enrollment' => [
                'id' => $enrollment->id,
                'studentId' => $enrollment->studentId,
                'status' => $enrollment->status,
                'programType' => $enrollment->programType,
                'gradeLevel' => $enrollment->gradeLevel,
                'strand' => $enrollment->strand,
                'track' => $enrollment->track,
                'schoolYear' => $enrollment->schoolYear,
                'trainingLevel' => $enrollment->trainingLevel,
                'assignedTeacherId' => $enrollment->assignedTeacherId,
                'assignedSection' => $enrollment->assignedSection,
                'reviewNotes' => $enrollment->reviewNotes,
                'rejectionReason' => $enrollment->rejectionReason,
                'reviewedAt' => $enrollment->reviewedAt?->toIso8601String(),
                'reviewedBy' => $enrollment->reviewedBy,
                'createdAt' => $enrollment->created_at?->toIso8601String(),
                'updatedAt' => $enrollment->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the specified enrollment in storage.
     */
    public function update(UpdateEnrollmentRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $enrollment = Enrollment::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        $validated = $request->validated();

        $enrollmentStatus = $enrollment->status;
        $isMutableEnrollment = in_array($enrollmentStatus, [Enrollment::DRAFT, Enrollment::SUBMITTED], true);

        // Check for duplicate if changing school year
        if (isset($validated['schoolYear']) && $validated['schoolYear'] !== $enrollment->schoolYear) {
            if (Enrollment::hasDuplicateActive($user->user_id, $validated['schoolYear'], $id)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'You already have an active enrollment for this school year.',
                ], 422);
            }
        }

        // Students may only edit the status of enrollments still in Draft/Submitted;
        // approval decisions are owned by admins.
        if (isset($validated['status'])) {
            $validated['status'] = $isMutableEnrollment ? $validated['status'] : $enrollmentStatus;
        }

        $enrollment->update($validated);

        // Update user profile if enrollment details changed
        $profileFields = [
            'contact' => $validated['contact'] ?? $request->input('contact'),
            'birthDate' => $validated['birthDate'] ?? $request->input('birthDate'),
            'address' => $validated['address'] ?? $request->input('address'),
            'guardianName' => $validated['guardianName'] ?? $request->input('guardianName'),
            'guardianContact' => $validated['guardianContact'] ?? $request->input('guardianContact'),
            'strand' => $validated['strand'] ?? $request->input('strand'),
        ];

        $profileChanges = array_filter($profileFields, fn ($value) => $value !== null);

        if ($profileChanges !== []) {
            $user->update($profileChanges);
        }

        return response()->json([
            'ok' => true,
            'enrollment' => [
                'id' => $enrollment->id,
                'studentId' => $enrollment->studentId,
                'status' => $enrollment->status,
                'programType' => $enrollment->programType,
                'gradeLevel' => $enrollment->gradeLevel,
                'strand' => $enrollment->strand,
                'track' => $enrollment->track,
                'schoolYear' => $enrollment->schoolYear,
                'trainingLevel' => $enrollment->trainingLevel,
                'assignedTeacherId' => $enrollment->assignedTeacherId,
                'assignedSection' => $enrollment->assignedSection,
                'reviewNotes' => $enrollment->reviewNotes,
                'rejectionReason' => $enrollment->rejectionReason,
                'reviewedAt' => $enrollment->reviewedAt?->toIso8601String(),
                'reviewedBy' => $enrollment->reviewedBy,
                'createdAt' => $enrollment->created_at?->toIso8601String(),
                'updatedAt' => $enrollment->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Remove the specified enrollment from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $enrollment = Enrollment::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        // Only allow deletion of draft enrollments
        if ($enrollment->status !== 'Draft') {
            return response()->json([
                'ok' => false,
                'error' => 'Only draft enrollments can be deleted.',
            ], 422);
        }

        $enrollment->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Enrollment deleted successfully.',
        ]);
    }
}
