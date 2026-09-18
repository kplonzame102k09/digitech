<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountRequestApproved;
use App\Mail\AccountRequestDenied;
use App\Models\AccountRequest;
use App\Models\User;
use App\Support\ReferenceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        // Search by name, email, or request ID
        if ($request->has('search')) {
            $search = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('request_id', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min(50, $perPage));

        $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
            'pagination' => [
                'total' => $requests->total(),
                'perPage' => $requests->perPage(),
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'hasMorePages' => $requests->hasMorePages(),
            ],
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
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'adminNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $accountRequest = DB::transaction(function () use ($id, $validated) {
            $accountRequest = AccountRequest::query()->lockForUpdate()->findOrFail($id);

            if ($accountRequest->status !== 'pending') {
                return null;
            }

            if (User::query()->where('email', $accountRequest->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'A user with this email already exists.',
                ]);
            }

            $contact = Str::limit($accountRequest->contact ?? '', 20, '');

            $year = now()->year;
            $prefix = match ($accountRequest->role) {
                'student' => "STU-{$year}",
                'parent'   => "PAR-{$year}",
                'guest'    => "GST-{$year}",
                default    => 'USR',
            };

            do {
                $userId = $prefix . '-' . ReferenceCode::generate(6);
            } while (User::query()->where('user_id', $userId)->exists());

            $user = User::query()->create([
                'user_id'           => $userId,
                'role'              => $accountRequest->role,
                'status'            => 'active',
                'firstName'         => $accountRequest->firstName,
                'middleName'        => $accountRequest->middleName,
                'lastName'          => $accountRequest->lastName,
                'email'             => $accountRequest->email,
                'username'          => $validated['username'],
                'password'          => Hash::make($validated['password']),
                'contact'           => $contact,
                'birthDate'         => null,
                'birthPlace'        => null,
                'barangay'          => null,
                'city'              => null,
                'province'          => null,
                'region'            => null,
                'strand'            => $accountRequest->strand,
                'mustChangePassword' => true,
            ]);

            $accountRequest->update([
                'status'     => 'approved',
                'adminNotes' => $validated['adminNotes'] ?? null,
            ]);

            return [
                'accountRequest' => $accountRequest,
                'user'           => $user,
                'password'       => $validated['password'],
            ];
        });

        if ($accountRequest === null) {
            return response()->json([
                'ok'    => false,
                'error' => 'This request has already been processed.',
            ], 400);
        }

        try {
            Mail::to($accountRequest['accountRequest']->email)->send(
                new AccountRequestApproved(
                    name: trim($accountRequest['user']->firstName.' '.$accountRequest['user']->lastName),
                    userId: $accountRequest['user']->user_id,
                    username: $accountRequest['user']->username,
                    password: $accountRequest['password'],
                )
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send approval email', [
                'email' => $accountRequest['accountRequest']->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Account request approved and user account created.',
            'user'    => [
                'id'       => $accountRequest['user']->user_id,
                'username' => $accountRequest['user']->username,
                'email'    => $accountRequest['user']->email,
                'role'     => $accountRequest['user']->role,
            ],
        ]);
    }

    /**
     * Reject an account request.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'adminNotes' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $accountRequest = DB::transaction(function () use ($id, $validated) {
            $accountRequest = AccountRequest::query()->lockForUpdate()->findOrFail($id);

            if ($accountRequest->status !== 'pending') {
                return null;
            }

            $accountRequest->update([
                'status'     => 'denied',
                'adminNotes' => trim($validated['adminNotes']),
            ]);

            return $accountRequest;
        });

        if ($accountRequest === null) {
            return response()->json([
                'ok'    => false,
                'error' => 'This request has already been processed.',
            ], 400);
        }

        try {
            Mail::to($accountRequest->email)->send(
                new AccountRequestDenied(
                    name: trim($accountRequest->firstName.' '.$accountRequest->lastName),
                    reason: $accountRequest->adminNotes,
                )
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send rejection email', [
                'email' => $accountRequest->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Account request rejected.',
        ]);
    }
}
