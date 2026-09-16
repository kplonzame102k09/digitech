<?php

namespace App\Http\Controllers;

use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:student,parent,guest'],
            'email' => ['required', 'email', 'max:255'],
            'username' => ['nullable', 'string', 'max:50'],
            'contact' => ['nullable', 'string', 'max:30'],
            'strand' => ['nullable', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $email = strtolower(trim($validated['email']));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json([
                'ok' => false,
                'errors' => ['email' => ['An account with that email already exists. Sign in instead.']],
            ], 422);
        }

        if (AccountRequest::query()
            ->where('status', 'pending')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists()) {
            return response()->json([
                'ok' => false,
                'errors' => ['email' => ['A pending request already exists for that email.']],
            ], 422);
        }

        $requestId = $this->generateRequestId($validated['role']);

        $accountRequest = AccountRequest::query()->create([
            'request_id' => $requestId,
            'role' => $validated['role'],
            'status' => 'pending',
            'firstName' => trim($validated['firstName']),
            'middleName' => isset($validated['middleName']) ? trim($validated['middleName']) : null,
            'lastName' => trim($validated['lastName']),
            'email' => $email,
            'username' => isset($validated['username']) ? trim($validated['username']) : null,
            'contact' => isset($validated['contact']) ? trim($validated['contact']) : null,
            'strand' => isset($validated['strand']) ? trim($validated['strand']) : null,
            'purpose' => trim($validated['purpose']),
        ]);

        return response()->json([
            'ok' => true,
            'request' => $accountRequest->only(['request_id', 'role', 'status', 'email']),
        ], 201);
    }

    private function generateRequestId(string $role): string
    {
        $prefix = match ($role) {
            'student' => 'STU',
            'parent' => 'PRT',
            'guest' => 'GST',
        };

        do {
            $requestId = $prefix.'-REQ-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (AccountRequest::query()->where('request_id', $requestId)->exists());

        return $requestId;
    }
}