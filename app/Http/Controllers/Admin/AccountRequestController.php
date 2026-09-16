<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountRequestController extends Controller
{
    /**
     * Display a listing of account requests.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountRequest::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'ok' => true,
            'requests' => $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'request_id' => $request->request_id,
                    'role' => $request->role,
                    'status' => $request->status,
                    'firstName' => $request->firstName,
                    'middleName' => $request->middleName,
                    'lastName' => $request->lastName,
                    'email' => $request->email,
                    'contact' => $request->contact,
                    'strand' => $request->strand,
                    'purpose' => $request->purpose,
                    'adminNotes' => $request->adminNotes,
                    'createdAt' => $request->created_at?->toIso8601String(),
                    'updatedAt' => $request->updated_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Display a specific account request.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $accountRequest = AccountRequest::findOrFail($id);

        return response()->json([
            'ok' => true,
            'request' => [
                'id' => $accountRequest->id,
                'request_id' => $accountRequest->request_id,
                'role' => $accountRequest->role,
                'status' => $accountRequest->status,
                'firstName' => $accountRequest->firstName,
                'middleName' => $accountRequest->middleName,
                'lastName' => $accountRequest->lastName,
                'email' => $accountRequest->email,
                'username' => $accountRequest->username,
                'contact' => $accountRequest->contact,
                'strand' => $accountRequest->strand,
                'purpose' => $accountRequest->purpose,
                'adminNotes' => $accountRequest->adminNotes,
                'createdAt' => $accountRequest->created_at?->toIso8601String(),
                'updatedAt' => $accountRequest->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Approve an account request and create a user account.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $accountRequest = AccountRequest::findOrFail($id);

        if ($accountRequest->status !== 'pending') {
            return response()->json([
                'ok' => false,
                'error' => 'This request has already been processed.',
            ], 400);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'adminNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Check if email already exists in users table
        if (User::query()->where('email', $accountRequest->email)->exists()) {
            return response()->json([
                'ok' => false,
                'error' => 'A user with this email already exists.',
            ], 422);
        }

        // Generate user_id
        $prefix = match ($accountRequest->role) {
            'student' => 'STU-2026',
            'parent' => 'PAR-2026',
            'guest' => 'GST-2026',
            default => 'USR',
        };

        do {
            $userId = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (User::query()->where('user_id', $userId)->exists());

        // Create the user account
        $user = User::query()->create([
            'user_id' => $userId,
            'role' => $accountRequest->role,
            'status' => 'active',
            'firstName' => $accountRequest->firstName,
            'middleName' => $accountRequest->middleName,
            'lastName' => $accountRequest->lastName,
            'email' => $accountRequest->email,
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'contact' => $accountRequest->contact ?? '',
            'birthDate' => '1970-01-01',
            'birthPlace' => 'N/A',
            'barangay' => 'N/A',
            'city' => 'N/A',
            'province' => 'N/A',
            'region' => 'N/A',
            'strand' => $accountRequest->strand,
            'mustChangePassword' => true,
        ]);

        // Update the account request status
        $accountRequest->update([
            'status' => 'approved',
            'adminNotes' => $validated['adminNotes'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Account request approved and user account created.',
            'user' => [
                'id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Reject an account request.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $accountRequest = AccountRequest::findOrFail($id);

        if ($accountRequest->status !== 'pending') {
            return response()->json([
                'ok' => false,
                'error' => 'This request has already been processed.',
            ], 400);
        }

        $validated = $request->validate([
            'adminNotes' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $accountRequest->update([
            'status' => 'denied',
            'adminNotes' => $validated['adminNotes'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Account request rejected.',
        ]);
    }
}
