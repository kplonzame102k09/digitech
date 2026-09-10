<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function __construct(private PortalDataService $portal) {}

    /**
     * Change the current user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), 'different:current_password'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'ok' => false,
                'error' => 'The current password you entered does not match our records.',
            ], 422);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'mustChangePassword' => false,
        ])->save();

        $request->session()->regenerate();

        AuditLog::record([
            'entity' => AuditLog::ENTITY_USER,
            'recordId' => $user->user_id,
            'action' => 'password.changed',
            'notes' => 'Password changed from the profile page.',
            'actorId' => $user->user_id,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Your password has been updated.',
            'user' => $this->portal->userToJs($user),
        ]);
    }

    /**
     * Recent activity entries for the current user.
     */
    public function activity(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 401);

        $entries = AuditLog::byActor($user->user_id, 50)->map(fn (AuditLog $log): array => [
            'id' => $log->id,
            'entity' => $log->entity,
            'action' => $log->action,
            'notes' => $log->notes,
            'date' => $log->createdAt?->toIso8601String(),
        ])->values()->all();

        return response()->json([
            'ok' => true,
            'activity' => $entries,
        ]);
    }
}
