<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalDataController extends Controller
{
    public function __construct(private PortalDataService $portal) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'collections' => $this->portal->allCollections(),
        ]);
    }

    public function show(string $key): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'key' => $key,
            'value' => $this->portal->getCollection($key),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($this->canUpdateCollection($user, $key), 403);

        return response()->json([
            'ok' => true,
            'key' => $key,
            'value' => $this->portal->putCollection($key, $validated['value'], $user),
        ]);
    }

    public function boot(Request $request): JsonResponse
    {
        return response()->json(
            $this->portal->bootPayload($request->user())
        );
    }

    private function canUpdateCollection(User $user, string $key): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return match ($user->role) {
            'teacher' => in_array($key, ['announcements', 'attendance', 'competencies', 'grades', 'notifications'], true),
            'student' => in_array($key, ['documentRequests', 'enrollments', 'notifications', 'requirements', 'users'], true),
            'parent' => in_array($key, ['notifications', 'parentLinkRequests', 'users'], true),
            default => false,
        };
    }
}
