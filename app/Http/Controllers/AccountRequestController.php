<?php

namespace App\Http\Controllers;

use App\Mail\AccountRequestReceived;
use App\Models\AccountRequest;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\PortalDataService;
use App\Support\ReferenceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $this->notifyAdminsOfRequest($accountRequest);

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

    /**
     * Let every active admin know a new account request landed: an in-app
     * notification (honouring the "notify admins" toggle) and an email. Mail
     * and audit failures are logged, never fatal.
     */
    private function notifyAdminsOfRequest(AccountRequest $accountRequest): void
    {
        $portal = app(PortalDataService::class);
        $requesterName = trim($accountRequest->firstName.' '.$accountRequest->lastName);

        $portal->notifyAdmins(
            'New account request',
            $requesterName.' ('.$accountRequest->role.') submitted an account request ('.$accountRequest->request_id.').',
            Notification::SOURCE_ACCOUNT_REQUEST,
            (string) $accountRequest->id,
        );

        foreach ($portal->adminRecipients() as $admin) {
            try {
                Mail::to($admin['email'])->send(new AccountRequestReceived(
                    name: $admin['name'],
                    role: (string) $accountRequest->role,
                    email: $accountRequest->email,
                    requestId: $accountRequest->request_id,
                    purpose: $accountRequest->purpose,
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify admin about an account request', [
                    'email' => $admin['email'],
                    'request' => $accountRequest->request_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        AuditLog::record([
            'entity' => AuditLog::ENTITY_ACCOUNT_REQUEST,
            'recordId' => $accountRequest->request_id,
            'action' => 'submitted',
            'notes' => $requesterName.' requested a '.$accountRequest->role.' account.',
            'actorId' => User::query()->where('role', 'admin')->where('status', 'active')->value('user_id') ?? null,
        ]);
    }
}