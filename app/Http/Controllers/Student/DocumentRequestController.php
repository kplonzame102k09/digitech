<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\DocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentRequestController extends Controller
{
    /**
     * Display a listing of the current student's document requests.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', DocumentRequest::class);

        $documentRequests = DocumentRequest::query()
            ->where('studentId', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'documentRequests' => $documentRequests->map(fn (DocumentRequest $doc): array => [
                'id' => $doc->id,
                'studentId' => $doc->studentId,
                'documentType' => $doc->documentType,
                'purpose' => $doc->purpose,
                'copies' => $doc->copies,
                'notes' => $doc->notes,
                'status' => $doc->status,
                'requestDate' => $doc->requestDate?->toIso8601String(),
                'reviewNotes' => $doc->reviewNotes,
                'rejectionReason' => $doc->rejectionReason,
                'releaseMethod' => $doc->releaseMethod,
                'releaseDate' => $doc->releaseDate?->toIso8601String(),
                'reviewedAt' => $doc->reviewedAt?->toIso8601String(),
                'reviewedBy' => $doc->reviewedBy,
                'createdBy' => $doc->createdBy,
                'createdAt' => $doc->created_at?->toIso8601String(),
                'updatedAt' => $doc->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Store a newly created document request in storage.
     */
    public function store(StoreDocumentRequestRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('create', DocumentRequest::class);

        $validated = $request->validated();

        $documentRequest = DocumentRequest::query()->create([
            'id' => Str::uuid()->toString(),
            'studentId' => $user->user_id,
            'documentType' => $validated['documentType'],
            'purpose' => $validated['purpose'],
            'copies' => $validated['copies'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'Pending',
            'requestDate' => now(),
            'createdBy' => $user->user_id,
        ]);

        return response()->json([
            'ok' => true,
            'documentRequest' => [
                'id' => $documentRequest->id,
                'studentId' => $documentRequest->studentId,
                'documentType' => $documentRequest->documentType,
                'purpose' => $documentRequest->purpose,
                'copies' => $documentRequest->copies,
                'notes' => $documentRequest->notes,
                'status' => $documentRequest->status,
                'requestDate' => $documentRequest->requestDate?->toIso8601String(),
                'reviewNotes' => $documentRequest->reviewNotes,
                'rejectionReason' => $documentRequest->rejectionReason,
                'releaseMethod' => $documentRequest->releaseMethod,
                'releaseDate' => $documentRequest->releaseDate?->toIso8601String(),
                'reviewedAt' => $documentRequest->reviewedAt?->toIso8601String(),
                'reviewedBy' => $documentRequest->reviewedBy,
                'createdBy' => $documentRequest->createdBy,
                'createdAt' => $documentRequest->created_at?->toIso8601String(),
                'updatedAt' => $documentRequest->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Display the specified document request.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $documentRequest = DocumentRequest::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        $this->authorize('view', $documentRequest);

        return response()->json([
            'ok' => true,
            'documentRequest' => [
                'id' => $documentRequest->id,
                'studentId' => $documentRequest->studentId,
                'documentType' => $documentRequest->documentType,
                'purpose' => $documentRequest->purpose,
                'copies' => $documentRequest->copies,
                'notes' => $documentRequest->notes,
                'status' => $documentRequest->status,
                'requestDate' => $documentRequest->requestDate?->toIso8601String(),
                'reviewNotes' => $documentRequest->reviewNotes,
                'rejectionReason' => $documentRequest->rejectionReason,
                'releaseMethod' => $documentRequest->releaseMethod,
                'releaseDate' => $documentRequest->releaseDate?->toIso8601String(),
                'reviewedAt' => $documentRequest->reviewedAt?->toIso8601String(),
                'reviewedBy' => $documentRequest->reviewedBy,
                'createdBy' => $documentRequest->createdBy,
                'createdAt' => $documentRequest->created_at?->toIso8601String(),
                'updatedAt' => $documentRequest->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Students cannot update document requests after submission.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'Students cannot update document requests after submission.',
        ], 403);
    }

    /**
     * Remove the specified document request from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $documentRequest = DocumentRequest::query()
            ->where('id', $id)
            ->where('studentId', $user->user_id)
            ->firstOrFail();

        $this->authorize('delete', $documentRequest);

        // Only allow deletion of pending requests
        if ($documentRequest->status !== 'Pending') {
            return response()->json([
                'ok' => false,
                'error' => 'Only pending document requests can be deleted.',
            ], 422);
        }

        $documentRequest->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Document request deleted successfully.',
        ]);
    }
}
