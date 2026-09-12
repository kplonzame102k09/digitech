<?php

/**
 * Batch 6 — admin user-sync guards.
 *
 * Verifies delete-with-records 422, last-active-admin protection, and
 * self-deactivation/demotion rejection. Rolls back after every test.
 *
 * Run with:  php test-batch6-guards.php
 */

use App\Models\Enrollment;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$admin = User::where('user_id', 'ADMIN-2026-000001')->first();
$service = app(PortalDataService::class);

$passed = 0;
$failed = 0;

function check(string $label, bool $ok): void
{
    global $passed, $failed;
    if ($ok) {
        echo "  PASS  {$label}\n";
        $passed++;
    } else {
        echo "  FAIL  {$label}\n";
        $failed++;
    }
}

function adminPayload(): array
{
    return [[
        'id' => 'ADMIN-2026-000001',
        'firstName' => 'Registrar',
        'lastName' => 'Admin',
        'role' => 'admin',
        'status' => 'active',
        'email' => 'admin@gmail.com',
    ]];
}

/**
 * Build a full mirror of the users table (so nothing else is treated as
 * deleted), omitting $omitIds and merging $overrides per user_id.
 */
function mirrorPayload(array $omitIds = [], array $overrides = []): array
{
    $mirror = DB::table('users')
        ->get(['user_id', 'firstName', 'lastName', 'role', 'status', 'email'])
        ->reject(fn ($u) => in_array($u->user_id, $omitIds, true))
        ->map(fn ($u) => [
            'id' => $u->user_id,
            'firstName' => $u->firstName ?: 'User',
            'lastName' => $u->lastName ?: 'Account',
            'role' => $u->role ?: 'student',
            'status' => $u->status ?: 'active',
            'email' => $u->email ?: strtolower($u->user_id).'@digitech.local',
        ])
        ->values()
        ->all();

    foreach ($overrides as $over) {
        $found = false;
        foreach ($mirror as &$entry) {
            if ($entry['id'] === $over['id']) {
                $entry = array_merge($entry, $over);
                $found = true;
                break;
            }
        }
        unset($entry);
        if (! $found) {
            $mirror[] = $over;
        }
    }

    return $mirror;
}

function tryStatus(callable $fn): ?string
{
    try {
        $fn();

        return null;
    } catch (HttpException $e) {
        return $e->getStatusCode() === 422 ? '422' : (string) $e->getStatusCode();
    }
}

// -------------------------------------------------------------------------
echo "== Delete user WITH related records -> 422 + data intact ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
try {
    User::unguard();
    User::create([
        'user_id' => 'STU-B6-000001', 'role' => 'student', 'status' => 'active',
        'firstName' => 'Batch6', 'lastName' => 'Student', 'contact' => '00000000000',
        'birthDate' => '2000-01-01', 'birthPlace' => 'N/A', 'barangay' => 'N/A',
        'city' => 'N/A', 'province' => 'N/A', 'region' => 'N/A',
        'email' => 'b6student@digitech.local', 'password' => 'temporarypass123',
    ]);
    $student = User::where('user_id', 'STU-B6-000001')->first();

    Enrollment::unguard();
    Enrollment::create([
        'id' => 'ENR-B6-0001', 'studentId' => $student->user_id,
        'status' => 'Draft', 'programType' => 'SHS',
        'schoolYear' => '2033-2034', 'gradeLevel' => 'Grade 11',
    ]);

    $payload = mirrorPayload(omitIds: ['STU-B6-000001']);
    $code = tryStatus(fn () => $service->putCollection('users', $payload, $admin));
    check('Delete-with-records returns 422', $code === '422');
    check('Student still exists after blocked delete', User::where('user_id', 'STU-B6-000001')->exists());
    check('Enrollment still exists after blocked delete', Enrollment::where('id', 'ENR-B6-0001')->exists());
} finally {
    DB::rollBack();
    User::reguard();
    Enrollment::reguard();
}

// -------------------------------------------------------------------------
echo "\n== Delete user with NO records -> succeeds ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
try {
    User::unguard();
    User::create([
        'user_id' => 'STU-B6-000002', 'role' => 'student', 'status' => 'active',
        'firstName' => 'Clean', 'lastName' => 'Student', 'contact' => '00000000000',
        'birthDate' => '2000-01-01', 'birthPlace' => 'N/A', 'barangay' => 'N/A',
        'city' => 'N/A', 'province' => 'N/A', 'region' => 'N/A',
        'email' => 'b6clean@digitech.local', 'password' => 'temporarypass123',
    ]);
    User::reguard();

    $payload = mirrorPayload(omitIds: ['STU-B6-000002']);
    $code = tryStatus(fn () => $service->putCollection('users', $payload, $admin));
    check('Clean delete returns ok', $code === null);
    check('Clean student actually deleted', ! User::where('user_id', 'STU-B6-000002')->exists());
} finally {
    DB::rollBack();
    User::reguard();
}

// -------------------------------------------------------------------------
echo "\n== Self demotion / deactivation -> 422 ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
try {
    $demote = adminPayload();
    $demote[0]['role'] = 'student';
    $code = tryStatus(fn () => $service->putCollection('users', $demote, $admin));
    check('Self-demotion returns 422', $code === '422');

    $deact = adminPayload();
    $deact[0]['status'] = 'inactive';
    $code = tryStatus(fn () => $service->putCollection('users', $deact, $admin));
    check('Self-deactivation returns 422', $code === '422');

    check('Admin still active admin after both', User::where('user_id', 'ADMIN-2026-000001')
        ->where('role', 'admin')->where('status', 'active')->exists());
} finally {
    DB::rollBack();
}

// -------------------------------------------------------------------------
echo "\n== Demote a SECOND admin while actor stays -> allowed ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
try {
    User::unguard();
    User::create([
        'user_id' => 'ADM-B6-000002', 'role' => 'admin', 'status' => 'active',
        'firstName' => 'Second', 'lastName' => 'Admin', 'contact' => '00000000000',
        'birthDate' => '2000-01-01', 'birthPlace' => 'N/A', 'barangay' => 'N/A',
        'city' => 'N/A', 'province' => 'N/A', 'region' => 'N/A',
        'email' => 'b6admin2@digitech.local', 'password' => 'temporarypass123',
    ]);
    User::reguard();

    // Demote the second admin in a full mirror; the portal must keep one admin.
    $payload = mirrorPayload(overrides: [[
        'id' => 'ADM-B6-000002', 'firstName' => 'Second', 'lastName' => 'Admin',
        'role' => 'student', 'status' => 'active',
        'email' => 'b6admin2@digitech.local',
    ]]);
    $code = tryStatus(fn () => $service->putCollection('users', $payload, $admin));
    check('Demote non-self admin returns ok', $code === null);
    check('Exactly one active admin remains', User::where('role', 'admin')->where('status', 'active')->count() === 1);
} finally {
    DB::rollBack();
    User::reguard();
}

// -------------------------------------------------------------------------
echo "\n".str_repeat('=', 50)."\n";
echo 'Batch 6: '.$passed.' passed, '.$failed." failed\n";
exit($failed ? 1 : 0);
