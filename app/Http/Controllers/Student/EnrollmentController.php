<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Models\Enrollment;
use Illuminate\Database\QueryException;
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
        $this->authorize('viewAny', Enrollment::class);

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

        $this->authorize('create', Enrollment::class);

        $validated = $request->validated();

        if (Enrollment::hasDuplicateActive($user->user_id, $validated['schoolYear'])) {
            return response()->json([
                'ok' => false,
                'error' => 'You already have an active enrollment for this school year.',
            ], 422);
        }

        if (Enrollment::hasPending($user->user_id, $validated['schoolYear'])) {
            return response()->json([
                'ok' => false,
                'error' => 'You already have a pending enrollment for this school year.',
            ], 422);
        }

        try {
            $enrollment = Enrollment::query()->create([
                'id' => Str::uuid()->toString(),
                'studentId' => $user->user_id,
                'status' => $validated['status'] ?? 'Draft',
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
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')
                || str_contains($e->getMessage(), 'UNIQUE constraint')
                || str_contains($e->getMessage(), 'unique constraint')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'You already have a pending enrollment for this school year.',
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Display the specified enrollment.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $enrollment = Enrollment::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        $this->authorize('view', $enrollment);

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

        $enrollment = Enrollment::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        $this->authorize('update', $enrollment);

        $validated = $request->validated();

        $enrollmentStatus = $enrollment->status;
        $isMutableEnrollment = in_array($enrollmentStatus, [Enrollment::DRAFT, Enrollment::SUBMITTED], true);

        if (! $isMutableEnrollment) {
            return response()->json([
                'ok' => false,
                'error' => 'Only draft or submitted enrollments can be edited.',
            ], 422);
        }

        // Check for duplicate if changing school year
        if (isset($validated['schoolYear']) && $validated['schoolYear'] !== $enrollment->schoolYear) {
            if (Enrollment::hasDuplicateActive($user->user_id, $validated['schoolYear'], $id)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'You already have an active enrollment for this school year.',
                ], 422);
            }

            if (Enrollment::hasPending($user->user_id, $validated['schoolYear'], $id)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'You already have a pending enrollment for this school year.',
                ], 422);
            }
        }

        $enrollment->update($validated);

        // Update user profile if enrollment details changed. Only values that
        // passed the UpdateEnrollmentRequest rules may reach the user record;
        // the raw request fallback previously let unvalidated input through.
        $profileFields = [
            'contact' => $validated['contact'] ?? null,
            'birthDate' => $validated['birthDate'] ?? null,
            'address' => $validated['address'] ?? null,
            'guardianName' => $validated['guardianName'] ?? null,
            'guardianContact' => $validated['guardianContact'] ?? null,
            'strand' => $validated['strand'] ?? null,
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

        $enrollment = Enrollment::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        $this->authorize('delete', $enrollment);

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
