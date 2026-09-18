<?php

namespace App\Http\Controllers;

use App\Models\AccountRequest;
use App\Models\User;
use App\Support\ReferenceCode;
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
            'contact' => ['nullable', 'string', 'max:20'],
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

        $middleName = isset($validated['middleName']) && trim($validated['middleName']) !== '' ? trim($validated['middleName']) : null;
        $contact = isset($validated['contact']) && trim($validated['contact']) !== '' ? trim($validated['contact']) : null;

        $accountRequest = AccountRequest::query()->create([
            'request_id' => $requestId,
            'role' => $validated['role'],
            'status' => 'pending',
            'firstName' => trim($validated['firstName']),
            'middleName' => $middleName,
            'lastName' => trim($validated['lastName']),
            'email' => $email,
            'contact' => $contact,
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
            $requestId = $prefix.'-REQ-'.ReferenceCode::generate(6);
        } while (AccountRequest::query()->where('request_id', $requestId)->exists());

        return $requestId;
    }
}