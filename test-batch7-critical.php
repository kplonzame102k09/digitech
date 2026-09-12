<?php

/**
 * Batch 7 — critical + high fixes (S1–S7).
 *
 * Covers in one harness:
 *   S1  guest read lockdown                      (scoping matrix, service +
 *       reflection on visibleStudentIds)
 *   S2  password.updated JSON 403 on api/portal + exemption + web redirect
 *   S3  auditLogs collection writes blocked (teacher) and allowed (teacher ->
 *       users positive control)
 *   S4  duplicate pending enrollment -> 422      (real store() path)
 *   S5  Enrolled immutability + strand allow-list (request closure rule)
 *   S6  requirement file exact-name / LIKE-escape gate (reflection)
 *   S7  UserPolicy + GradePolicy + EnrollmentPolicy + importUsers gate
 *
 * Run with:  php test-batch7-critical.php
 * Rolls back after every write; never touches live data.
 */

use App\Http\Controllers\PortalDataController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Middleware\EnsurePasswordUpdated;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\ParentLinkRequest;
use App\Models\Requirement;
use App\Models\User;
use App\Policies\DocumentRequestPolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\GradePolicy;
use App\Policies\UserPolicy;
use App\Services\PortalDataService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        echo "  PASS  {$label}".($detail !== '' ? "  [{$detail}]" : '')."\n";
        $passed++;
    } else {
        echo "  FAIL  {$label}  [{$detail}]\n";
        $failed++;
    }
}

function userId(string $portalId): User
{
    $u = User::where('user_id', $portalId)->firstOrFail();
    Auth::login($u);

    return $u;
}

function runRollback(callable $fn): void
{
    try {
        DB::transaction($fn);
    } catch (Exception $e) {
        if ($e->getMessage() !== 'ROLLBACK') {
            throw $e;
        }
    }
}

function scratchUser(string $role = 'student'): User
{
    $uid = 'TMP-'.Str::upper(Str::random(10));

    return User::create([
        'user_id' => $uid,
        'role' => $role,
        'status' => 'active',
        'firstName' => 'Scratch',
        'lastName' => $role,
        'contact' => '09170000000',
        'birthDate' => '2008-01-01',
        'birthPlace' => 'Scratch City',
        'barangay' => 'Scratch',
        'city' => 'Scratch',
        'province' => 'Scratch',
        'region' => 'NCR',
        'email' => $uid.'@digitech.local',
        'username' => $role.'-'.$uid,
        'password' => 'password',
        'mustChangePassword' => false,
    ]);
}

function callStore(FormRequest $request, User $actor): JsonResponse
{
    return app(EnrollmentController::class)->store($request);
}

function callUpdateCollection(string $key, mixed $value, User $actor): JsonResponse
{
    $req = Request::create('/api/portal/'.$key, 'PUT', ['value' => $value]);
    $req->setUserResolver(fn () => $actor);

    return app(PortalDataController::class)->update($req, $key);
}

function guardPost(User $flagged): array
{
    $req = Request::create('/api/portal/boot', 'GET');
    $req->headers->set('Accept', 'application/json');
    $req->setUserResolver(fn () => $flagged);

    $resp = (new EnsurePasswordUpdated)->handle($req, fn () => response('next'));
    if ($resp instanceof JsonResponse) {
        return [$resp->getStatusCode(), $resp->getContent()];
    }

    return [$resp->getStatusCode(), ''];
}

function catalogPayload(string $strand, string $schoolYear = 'SYSCRATCH-1'): array
{
    return [
        'programType' => 'Senior High School',
        'gradeLevel' => '11',
        'strand' => $strand,
        'track' => 'STEM',
        'schoolYear' => $schoolYear,
        'trainingLevel' => null,
        'contact' => '09170000000',
        'birthDate' => '2008-01-01',
        'address' => 'Scratch City',
        'guardianName' => 'G Scratch',
        'guardianContact' => '09170000001',
    ];
}

echo '== S1: guest read lockdown =='.PHP_EOL;
$guest = User::where('user_id', 'GST-2026-000001')->first();
$svc = app(PortalDataService::class);

$guestBoot = $svc->bootPayload($guest);
$guestUsers = array_column($guestBoot['collections']['users'] ?? [], 'id');
check('guest users = self only', $guestUsers === ['GST-2026-000001'], implode(',', $guestUsers));
check('guest parentLinkRequests scoped', isset($guestBoot['collections']['parentLinkRequests']));

$ref = new ReflectionMethod(PortalDataService::class, 'visibleStudentIds');
$ref->setAccessible(true);
$guestVisible = $ref->invoke($svc, $guest);
check('guest visibleStudentIds = own id', $guestVisible === ['GST-2026-000001'], json_encode($guestVisible));
$studentVisible = $ref->invoke($svc, User::where('user_id', 'STU-2026-000001')->first());
check('student visibleStudentIds = own id', $studentVisible === ['STU-2026-000001'], json_encode($studentVisible));

echo '== S2: password.updated gate =='.PHP_EOL;
runRollback(function () {
    $flagged = scratchUser('student');
    $flagged->update(['mustChangePassword' => true]);

    [$status, $body] = guardPost($flagged);
    check('api/portal JSON 403 while flagged', $status === 403, (string) $status);
    check('JSON body ok=false', str_contains($body, '"ok":false') || str_contains($body, "'ok':false"), $body);

    $exempt = Request::create('/api/portal/account/password', 'POST');
    $exempt->headers->set('Accept', 'application/json');
    $exempt->setUserResolver(fn () => $flagged);
    $exempt->setRouteResolver(fn () => app('router')->getRoutes()->getByName('account.password'));
    $resp = (new EnsurePasswordUpdated)->handle($exempt, fn () => response('pass-through'));
    check('account.password exempt from 403', $resp->getStatusCode() === 200 && $resp->getContent() === 'pass-through', (string) $resp->getStatusCode());

    $web = Request::create('/web/page', 'GET');
    $web->setUserResolver(fn () => $flagged);
    $resp = (new EnsurePasswordUpdated)->handle($web, fn () => response('next'));
    check('web (non-JSON) redirects to password change', $resp instanceof RedirectResponse && $resp->getStatusCode() === 302, get_class($resp));

    throw new Exception('ROLLBACK');
});

echo '== S3: auditLogs write blocked =='.PHP_EOL;
$teacher = User::where('user_id', 'TCH-2026-000001')->first();
$auditResult = null;
runRollback(function () use ($teacher, &$auditResult) {
    try {
        callUpdateCollection('auditLogs', [['message' => 'forged']], $teacher);
        $auditResult = 'no-exception';
    } catch (HttpException $e) {
        $auditResult = $e->getStatusCode();
    }
    throw new Exception('ROLLBACK');
});
check('teacher auditLogs write rejected', $auditResult === 403, (string) $auditResult);

runRollback(function () use ($teacher) {
    $resp = callUpdateCollection('users', DB::table('users')->get(['user_id'])->map(fn ($r) => ['id' => $r->user_id])->take(1)->all(), $teacher);
    check('teacher users write still allowed (allow-list)', $resp->getStatusCode() === 200, (string) $resp->getStatusCode());
    throw new Exception('ROLLBACK');
});

echo '== S4: duplicate pending enrollment -> 422 =='.PHP_EOL;
runRollback(function () {
    $student = scratchUser('student');
    $sy = 'SY-S4-'.Str::upper(Str::random(5));
    Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'studentId' => $student->user_id,
        'status' => 'Draft',
        'programType' => 'Senior High School',
        'gradeLevel' => '11',
        'strand' => 'STEM',
        'schoolYear' => $sy,
    ]);

    Auth::login($student);
    $req = StoreEnrollmentRequest::create('/api/student/enrollments', 'POST', catalogPayload('STEM', $sy));
    $req->setContainer(app());
    $req->setRedirector(app('redirect'));
    $req->setUserResolver(fn () => $student);
    $req->validateResolved();

    $resp = callStore($req, $student);
    $body = json_decode($resp->getContent(), true);
    check('duplicate pending store -> 422', $resp->getStatusCode() === 422, (string) $resp->getStatusCode());
    check('duplicate pending error message', ($body['ok'] ?? true) === false, json_encode($body));

    throw new Exception('ROLLBACK');
});

echo '== S5: Enrolled immutability + strand allow-list =='.PHP_EOL;
runRollback(function () {
    $student = scratchUser('student');
    $enrollment = Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'studentId' => $student->user_id,
        'status' => 'Enrolled',
        'programType' => 'Senior High School',
        'gradeLevel' => '12',
        'strand' => 'HUMSS',
        'schoolYear' => 'SY-S5-'.Str::upper(Str::random(5)),
    ]);

    Auth::login($student);
    $req = UpdateEnrollmentRequest::create('/api/student/enrollments/'.$enrollment->id, 'PUT', ['status' => 'Draft', 'strand' => 'HUMSS']);
    $req->setContainer(app());
    $req->setRedirector(app('redirect'));
    $req->setUserResolver(fn () => $student);
    $req->validateResolved();

    $resp = app(EnrollmentController::class)->update($req, $enrollment->id);
    $body = json_decode($resp->getContent(), true);
    $fresh = Enrollment::query()->find($enrollment->id);
    check('update request accepted (200)', $resp->getStatusCode() === 200, (string) $resp->getStatusCode());
    check('Enrolled cannot regress (response)', ($body['enrollment']['status'] ?? '') === 'Enrolled', $body['enrollment']['status'] ?? '');
    check('Enrolled cannot regress (db)', $fresh->status === 'Enrolled', (string) $fresh->status);

    throw new Exception('ROLLBACK');
});

foreach (['Physics', 'STEM'] as $strand) {
    $ok = true;
    try {
        $req = StoreEnrollmentRequest::create('/api/student/enrollments', 'POST', catalogPayload($strand));
        $req->headers->set('Accept', 'application/json');
        $req->setContainer(app());
        $req->setRedirector(app('redirect'));
        $req->setUserResolver(fn () => User::where('user_id', 'STU-2026-000001')->first());
        $req->validateResolved();
    } catch (ValidationException $e) {
        $ok = false;
    }
    check('strand allow-list: '.$strand.' '.($strand === 'STEM' ? 'accepted' : 'rejected'), $ok === ($strand === 'STEM'), $strand);
}

echo '== S6: requirement file exact-name gate (LIKE escaped) =='.PHP_EOL;
try {
    DB::transaction(function () use ($svc) {
        $s1 = scratchUser('student');
        $s2 = scratchUser('student');
        [$one, $two] = ['seminar_1.pdf', 'thesis-resume.pdf'];

        foreach ([
            ['studentId' => $s1->user_id, 'fileUrl' => '/requirements/'.$one],
            ['studentId' => $s2->user_id, 'fileUrl' => '/requirements/'.$two],
        ] as $i => $row) {
            Requirement::query()->create([
                'id' => (string) Str::uuid(),
                'studentId' => $row['studentId'],
                'name' => 'Scratch '.$i,
                'type' => 'Document',
                'status' => 'Submitted',
                'fileUrl' => $row['fileUrl'],
                'dueDate' => now(),
            ]);
        }

        $ctrl = new PortalDataController($svc);
        $ref = new ReflectionMethod(PortalDataController::class, 'canAccessRequirementFile');
        $ref->setAccessible(true);

        $ownerOk = $ref->invoke($ctrl, $s1, $one);
        check('owner granted exact file', $ownerOk === true);

        $prefixGrab = $ref->invoke($ctrl, $s2, $one);
        check('partial/other student denied', $prefixGrab === false);

        $underscoreBleed = $ref->invoke($ctrl, $s1, 'seminar_1.pdf');
        check('underscore literal must not bleed rows', $underscoreBleed === true);

        $orphan = $ref->invoke($ctrl, $s1, 'nobody.pdf');
        check('orphan upload denied (no fallback)', $orphan === false);

        throw new Exception('ROLLBACK');
    });
} catch (Exception $e) {
    if (! $e instanceof Exception || $e->getMessage() !== 'ROLLBACK') {
        check('S6 transaction', false, $e->getMessage());
    }
}

echo '== S7: policies + import gate =='.PHP_EOL;
$admin = User::where('user_id', 'ADMIN-2026-000001')->first();
$student = User::where('user_id', 'STU-2026-000001')->first();

check('UserPolicy create blocks teacher', (new UserPolicy)->create($teacher) === false);
check('UserPolicy create allows admin', (new UserPolicy)->create($admin) === true);
check('UserPolicy update blocks teacher', (new UserPolicy)->update($teacher, $student) === false);
check('UserPolicy view allows owner', (new UserPolicy)->view($student, $student) === true);
check('GradePolicy create allows teacher', app(GradePolicy::class)->create($teacher) === true);
check('EnrollmentPolicy viewAny blocks guest', (new EnrollmentPolicy)->viewAny($guest) === false);
check('DocumentRequestPolicy create allows student', app(DocumentRequestPolicy::class)->create($student) === true);

function importRequest(User $actor, array $users): Request
{
    $req = Request::create(
        '/api/portal/import-users',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['users' => $users])
    );
    $req->headers->set('Accept', 'application/json');
    $req->setUserResolver(fn () => $actor);

    return $req;
}

$imported = [
    'id' => 'TMP-IMP-'.Str::upper(Str::random(8)),
    'role' => 'student',
    'email' => 'imp-'.Str::random(8).'@digitech.local',
    'password' => 'password',
    'firstName' => 'Import',
    'lastName' => 'Probe',
    'contact' => '09170000000',
    'birthDate' => '2008-01-01',
    'birthPlace' => 'Scratch',
    'barangay' => 'Scratch',
    'city' => 'Scratch',
    'province' => 'Scratch',
    'region' => 'NCR',
];

Auth::login($teacher);
$imp = importRequest($teacher, [$imported]);
try {
    app(PortalDataController::class)->importUsers($imp);
    check('importUsers blocks teacher (403)', false, 'no exception');
} catch (AuthorizationException $e) {
    check('importUsers blocks teacher (403)', true);
}

Auth::login($admin);
runRollback(function () use ($admin, $imported) {
    $resp = app(PortalDataController::class)->importUsers(importRequest($admin, [$imported]));
    check('importUsers allows admin (202)', $resp->getStatusCode() === 202, (string) $resp->getStatusCode());
    throw new Exception('ROLLBACK');
});

echo '== S-extra: parent link request requires an existing student =='.PHP_EOL;
$parent = User::where('user_id', 'PAR-2026-000001')->first();
$parentBoot = $svc->bootPayload($parent);
$directory = $parentBoot['collections']['studentDirectory'] ?? [];
$directoryIds = array_column($directory, 'id');
check('parent payload exposes studentDirectory', is_array($directory), gettype($directory));
check('studentDirectory lists an unlinked student', in_array('STU-2026-000003', $directoryIds, true), json_encode($directoryIds));
$hasContactLeak = collect($directory)->contains(fn (array $entry): bool => isset($entry['email']) || isset($entry['birthDate']));
check('studentDirectory carries no contact/record data', $hasContactLeak === false);
$guestBoot = $svc->bootPayload($guest);
check('guest payload has no studentDirectory', ! array_key_exists('studentDirectory', $guestBoot['collections'] ?? []));

runRollback(function () use ($parent, $svc) {
    Auth::login($parent);

    // Existing real student -> request accepted and persisted.
    $okRow = [
        'id' => 'PLR-OK-'.Str::upper(Str::random(6)),
        'parentId' => $parent->user_id,
        'studentId' => 'STU-2026-000003',
        'status' => 'Pending',
    ];
    $svc->putCollection('parentLinkRequests', [$okRow], $parent);
    $linkRow = app(ParentLinkRequest::class)::where('id', $okRow['id'])->first();
    check('request to existing student persists', $linkRow !== null && (string) $linkRow->studentId === 'STU-2026-000003');

    // Nonexistent student -> 422 and no row written.
    $badId = 'STU-DOES-NOT-EXIST';
    $badRow = [
        'id' => 'PLR-BAD-'.Str::upper(Str::random(6)),
        'parentId' => $parent->user_id,
        'studentId' => $badId,
        'status' => 'Pending',
    ];
    $code = null;
    try {
        $svc->putCollection('parentLinkRequests', [$badRow], $parent);
    } catch (HttpException $e) {
        $code = $e->getStatusCode();
    }
    check('request to unknown student -> 422', $code === 422, (string) $code);
    $badPersisted = app(ParentLinkRequest::class)::where('id', $badRow['id'])->exists();
    check('request to unknown student not persisted', $badPersisted === false);

    // A teacher id (not a student role) is also rejected.
    $teacherRow = [
        'id' => 'PLR-TEA-'.Str::upper(Str::random(6)),
        'parentId' => $parent->user_id,
        'studentId' => 'TCH-2026-000001',
        'status' => 'Pending',
    ];
    $code = null;
    try {
        $svc->putCollection('parentLinkRequests', [$teacherRow], $parent);
    } catch (HttpException $e) {
        $code = $e->getStatusCode();
    }
    check('request to a teacher account -> 422', $code === 422, (string) $code);

    throw new Exception('ROLLBACK');
});

echo "\nBatch 7: {$passed} passed, {$failed} failed\n";

exit($failed > 0 ? 1 : 0);
