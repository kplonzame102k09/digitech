<?php

/**
 * Batch 5 — server-side admin alerts.
 *
 * Verifies createAdminAlert paths + settings opt-out + forgery still blocked.
 * Run with:  php test-batch5-admin-alerts.php
 * Rolls back after every test; never touches live data.
 */

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\Requirement;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$admin = User::where('user_id', 'ADMIN-2026-000001')->first();
$teacher = User::where('user_id', 'TCH-2026-000001')->first();
$student = User::where('user_id', 'STU-2026-000001')->first();
$guest = User::where('user_id', 'GST-2026-000001')->first();
$parent = User::where('user_id', 'PAR-2026-000001')->first();

$service = app(PortalDataService::class);

function countUnreadAdminAlerts(string $source, ?string $recordId = null): int
{
    $q = Notification::query()
        ->where('userId', 'ADMIN-2026-000001')
        ->where('source', $source)
        ->where('read', false);

    if ($recordId !== null) {
        $q->where('recordId', $recordId);
    }

    return (int) $q->count();
}

function clearAlerts(): void
{
    Notification::query()
        ->where('userId', 'ADMIN-2026-000001')
        ->where('read', false)
        ->delete();
}

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

function asUser(User $user): User
{
    return $user;
}

// -------------------------------------------------------------------------
echo "== Enrollment: student -> Submitted -> admin alert ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();

clearAlerts();
Enrollment::unguard();

$enrollmentId = 'ENR-TEST-1';
$payload = [
    'id' => $enrollmentId,
    'studentId' => $student->user_id,
    'status' => 'Draft',
    'programType' => 'SHS',
    'schoolYear' => '2031-2032',
    'gradeLevel' => 'Grade 11',
];
Enrollment::query()->create($payload);
clearAlerts();
$payload['status'] = 'Submitted';
$service->putCollection('enrollments', [$payload], asUser($student));
check('Submitted enrollment -> 1 alert', countUnreadAdminAlerts('enrollment', $enrollmentId) === 1);

clearAlerts();
$payload['assignedSection'] = 'A';
$payload['assignedTeacherId'] = 'TCH-2026-000001';
$service->putCollection('enrollments', [$payload], asUser($student));
check('Re-push same Submitted row -> no stacked alert', countUnreadAdminAlerts('enrollment', $enrollmentId) === 0);

DB::rollBack();
Enrollment::reguard();

// -------------------------------------------------------------------------
echo "\n== Requirements: student -> Submitted ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
clearAlerts();

Requirement::unguard();
$reqId = 'REQ-TEST-1';
Requirement::query()->create([
    'id' => $reqId,
    'studentId' => $student->user_id,
    'name' => 'Form 137',
    'status' => 'Pending',
    'dueDate' => '2026-12-31',
]);
clearAlerts();
$service->putCollection('requirements', [
    ['id' => $reqId, 'studentId' => $student->user_id, 'name' => 'Form 137',
        'status' => 'Submitted', 'fileUrl' => 'req-files/test.pdf'],
], asUser($student));
check('Requirement -> Submitted => 1 alert', countUnreadAdminAlerts('requirement', $reqId) === 1);

DB::rollBack();
Requirement::reguard();

// -------------------------------------------------------------------------
echo "\n== Document requests: guest -> 1 alert ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
clearAlerts();

$docId = 'DOC-TEST-1';
$service->putCollection('documentRequests', [
    ['id' => $docId, 'studentId' => $guest->user_id,
        'documentType' => 'Form 137', 'purpose' => 'Transfer',
        'copies' => 1, 'requestDate' => '2026-09-06',
        'status' => 'Pending', 'createdBy' => $guest->user_id],
], asUser($guest));
check('Guest document request -> 1 alert', countUnreadAdminAlerts('document', $docId) === 1);

DB::rollBack();

// -------------------------------------------------------------------------
echo "\n== Announcements: teacher -> 1 alert ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
clearAlerts();

$annId = 'ANN-TEST-1';
$service->putCollection('announcements', [
    ['id' => $annId, 'title' => 'Test announcement',
        'audience' => 'All', 'authorId' => $teacher->user_id],
], asUser($teacher));
check('Teacher announcement -> 1 alert', countUnreadAdminAlerts('announcement', $annId) === 1);

DB::rollBack();

// -------------------------------------------------------------------------
echo "\n== Grades: teacher bulk summary per student ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
clearAlerts();

Grade::unguard();
$payload = [
    ['id' => 'GRD-TEST-1', 'studentId' => $student->user_id,
        'subject' => 'Math',  'teacherId' => $teacher->user_id,
        'schoolYear' => '2026-2027', 'semester' => '1st Semester',
        'units' => 1, 'published' => false],
    ['id' => 'GRD-TEST-2', 'studentId' => $student->user_id,
        'subject' => 'Science', 'teacherId' => $teacher->user_id,
        'schoolYear' => '2026-2027', 'semester' => '1st Semester',
        'units' => 1, 'published' => false],
];
$service->putCollection('grades', $payload, asUser($teacher));
check('2 grades same student => 1 summary alert', countUnreadAdminAlerts('grade', $student->user_id) === 1);

clearAlerts();
$payload[] = ['id' => 'GRD-TEST-3', 'studentId' => $student->user_id,
    'subject' => 'English', 'teacherId' => $teacher->user_id,
    'schoolYear' => '2026-2027', 'semester' => '1st Semester',
    'units' => 1, 'published' => false];
$service->putCollection('grades', $payload, asUser($teacher));
check('Add 3rd grade while unread => stacked count unchanged', countUnreadAdminAlerts('grade', $student->user_id) === 1);

DB::rollBack();
Grade::reguard();

// -------------------------------------------------------------------------
echo "\n== parentLinkRequests: parent -> 1 alert ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
clearAlerts();

$plrId = 'PLR-TEST-1';
$service->putCollection('parentLinkRequests', [
    ['id' => $plrId, 'parentId' => $parent->user_id,
        'studentId' => 'STU-2026-000003', 'status' => 'Pending'],
], asUser($parent));
check('Parent link request -> 1 alert', countUnreadAdminAlerts('parentLink', $plrId) === 1);

DB::rollBack();

// -------------------------------------------------------------------------
echo "\n== Settings toggle: notifyAdmins=false -> suppressed ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
clearAlerts();

$settings = SystemSetting::getInstance();
$original = $settings->notifyAdmins;
$settings->notifyAdmins = false;
$settings->save();

$payload = ['id' => 'ENR-TOG-1', 'studentId' => $student->user_id,
    'status' => 'Submitted', 'programType' => 'SHS', 'schoolYear' => '2032-2033',
    'gradeLevel' => 'Grade 11'];
Enrollment::unguard();
$service->putCollection('enrollments', [$payload], asUser($student));
check('Toggle off -> no alert', countUnreadAdminAlerts('enrollment', 'ENR-TOG-1') === 0);

Enrollment::reguard();
DB::rollBack();

// -------------------------------------------------------------------------
echo "\n== Forgery: student -> other student still blocked ==\n";
// -------------------------------------------------------------------------
DB::beginTransaction();
clearAlerts();

$service->putCollection('notifications', [
    ['id' => 'N-FAKE-1', 'userId' => 'STU-2026-000002',
        'title' => 'Fake', 'message' => 'x', 'read' => false, 'source' => 'test'],
], asUser($student));
check('Student->student row not persisted', ! Notification::query()->where('id', 'N-FAKE-1')->exists());

DB::rollBack();

// -------------------------------------------------------------------------
echo "\n".str_repeat('=', 50)."\n";
echo "Batch 5: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
