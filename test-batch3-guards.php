<?php

use App\Models\Attendance;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Requirement;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pass = 0;
$fail = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  PASS  {$label}".($detail !== '' ? "  [{$detail}]" : '').PHP_EOL;
    } else {
        $fail++;
        echo "  FAIL  {$label}  [{$detail}]".PHP_EOL;
    }
}

function asUser(string $portalId): User
{
    $u = User::where('user_id', $portalId)->firstOrFail();
    Auth::login($u);

    return $u;
}

function putFor(string $key, User $user, array $rows): ?int
{
    try {
        app(PortalDataService::class)->putCollection($key, $rows, $user);

        return 200;
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }
}

function freshValue2(string $model, string $id, string $field)
{
    return app($model)::find($id)?->getAttribute($field);
}

function pushMerged(string $key, User $user, array $newRows): ?int
{
    $outer = app(PortalDataService::class);
    $existing = (array) $outer->getCollection($key, $user);
    $rows = array_merge(
        array_map(static fn ($r): array => (array) $r, $existing),
        $newRows,
    );

    try {
        $outer->putCollection($key, $rows, $user);

        return 200;
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }
}

DB::beginTransaction();

$svc = app(PortalDataService::class);

echo '== Document type enum + duplicate + cancellation =='.PHP_EOL;
$student = asUser('STU-2026-000001');
$reqId = 0;
function newDocId(int &$i): string
{
    $i++;

    return 'REQT-NEW-'.$i.'-'.uniqid();
}

$payload = [[
    'id' => newDocId($reqId),
    'studentId' => 'STU-2026-000001',
    'documentType' => 'Bogus Type',
    'purpose' => 'Scholarship application',
    'copies' => 1,
    'notes' => '',
    'status' => 'Pending',
]];
putFor('documentRequests', $student, $payload);
$created = DocumentRequest::where('purpose', 'Scholarship application')
    ->where('studentId', 'STU-2026-000001')->latest('id')->first();
check('bogus document type normalised', (string) optional($created)->documentType === 'Form 137', (string) optional($created)->documentType);

$payload = [[
    'id' => newDocId($reqId),
    'studentId' => 'STU-2026-000001',
    'documentType' => 'Form 137',
    'purpose' => 'Scholarship application',
    'copies' => 1,
    'status' => 'Pending',
]];
$code = putFor('documentRequests', $student, $payload);
check('duplicate open request -> 422', $code === 422, "got {$code}");

// cancel own open request
$mine = DocumentRequest::where('studentId', 'STU-2026-000001')
    ->where('status', 'Pending')->latest('id')->first();
if ($mine) {
    $payload = [[
        'id' => $mine->id,
        'studentId' => 'STU-2026-000001',
        'documentType' => $mine->documentType,
        'purpose' => $mine->purpose,
        'copies' => 1,
        'status' => 'Cancelled',
    ]];
    putFor('documentRequests', $student, $payload);
    check('requester can cancel open request', freshValue2(DocumentRequest::class, $mine->id, 'status') === 'Cancelled');
} else {
    check('requester can cancel open request', false, 'no pending row found');
}

echo '== Enrollment duplicate + transition + teacher-exists =='.PHP_EOL;
$student2 = asUser('STU-2026-000002');
// A fresh draft for a past school year with no existing row is allowed.
$payload = [[
    'id' => 'ENR-NEW-2025',
    'studentId' => 'STU-2026-000002',
    'status' => 'Draft',
    'gradeLevel' => 'Grade 11',
    'schoolYear' => '2025-2026',
]];
check('new enrollment for untaken school year allowed', pushMerged('enrollments', $student2, $payload) === 200);

// STU-2 already owns an Enrolled row for 2026-2027; a second one collides.
$payload = [[
    'id' => 'ENR-DUP-2026',
    'studentId' => 'STU-2026-000002',
    'status' => 'Draft',
    'gradeLevel' => 'Grade 11',
    'schoolYear' => '2026-2027',
]];
check('duplicate enrollment (unique year) -> 422', ($dupCode = pushMerged('enrollments', $student2, $payload)) === 422, "got {$dupCode}");

// App-level duplicate guard: Draft/Submitted for the same year.
$student1 = asUser('STU-2026-000001');
$payload = [[
    'id' => 'ENR-A-DRAFT',
    'studentId' => 'STU-2026-000001',
    'status' => 'Draft',
    'gradeLevel' => 'Grade 12',
    'schoolYear' => '2024-2025',
]];
pushMerged('enrollments', $student1, $payload);
$payload2 = [[
    'id' => 'ENR-B-DRAFT',
    'studentId' => 'STU-2026-000001',
    'status' => 'Draft',
    'gradeLevel' => 'Grade 12',
    'schoolYear' => '2024-2025',
]];
check('duplicate pending enrollment (app guard) -> 422', pushMerged('enrollments', $student1, $payload2) === 422);

$admin = asUser('ADMIN-2026-000001');
Enrollment::create([
    'id' => 'ENR-ENR-1',
    'studentId' => 'STU-2026-000001',
    'status' => 'Enrolled',
    'gradeLevel' => 'Grade 12',
    'schoolYear' => '2023-2024',
]);
$payload = [[
    'id' => 'ENR-ENR-1',
    'studentId' => 'STU-2026-000001',
    'status' => 'Draft',
]];
pushMerged('enrollments', $admin, $payload);
check('enrolled record cannot regress to Draft', freshValue2(Enrollment::class, 'ENR-ENR-1', 'status') === 'Enrolled');

$payload = [[
    'id' => 'ENR-ENR-1',
    'studentId' => 'STU-2026-000001',
    'status' => 'Enrolled',
    'assignedTeacherId' => 'STU-2026-000001',
]];
pushMerged('enrollments', $admin, $payload);
check('non-teacher assignedTeacherId stripped', freshValue2(Enrollment::class, 'ENR-ENR-1', 'assignedTeacherId') !== 'STU-2026-000001', (string) freshValue2(Enrollment::class, 'ENR-ENR-1', 'assignedTeacherId'));

$payload = [[
    'id' => 'ENR-ENR-1',
    'studentId' => 'STU-2026-000001',
    'status' => 'Enrolled',
    'assignedTeacherId' => 'TCH-2026-000001',
]];
pushMerged('enrollments', $admin, $payload);
check('real teacher assignedTeacherId kept', freshValue2(Enrollment::class, 'ENR-ENR-1', 'assignedTeacherId') === 'TCH-2026-000001');

echo '== Grades range clamp =='.PHP_EOL;
$teacher = asUser('TCH-2026-000001');
Grade::create([
    'id' => 'GRD-CLAMP-1',
    'studentId' => 'STU-2026-000001',
    'subject' => 'Mathematics',
    'semester' => '1st',
    'prelim' => 150,
    'midterm' => null,
    'finals' => null,
    'units' => 3,
    'schoolYear' => '2026-2027',
    'teacherId' => 'TCH-2026-000001',
]);
$payload = [[
    'id' => 'GRD-CLAMP-1',
    'studentId' => 'STU-2026-000001',
    'subject' => 'Mathematics',
    'semester' => '1st',
    'prelim' => 150,
    'midterm' => -5,
    'finals' => 90,
    'units' => 3,
    'schoolYear' => '2026-2027',
    'teacherId' => 'TCH-2026-000001',
]];
pushMerged('grades', $teacher, $payload);
$g = Grade::find('GRD-CLAMP-1');
check('prelim 150 clamped to 100', (float) $g->prelim === 100.0, (string) $g->prelim);
check('midterm -5 clamped to 0', (float) $g->midterm === 0.0, (string) $g->midterm);

echo '== Attendance status enum =='.PHP_EOL;
$teacher3 = asUser('TCH-2026-000001');
Attendance::create([
    'id' => 'ATD-ENUM-1',
    'studentId' => 'STU-2026-000003',
    'date' => now()->toDateString(),
    'status' => 'Present',
    'subject' => 'PE',
    'recordedBy' => 'TCH-2026-000001',
]);
$payload = [[
    'id' => 'ATD-ENUM-1',
    'studentId' => 'STU-2026-000003',
    'date' => now()->toDateString(),
    'status' => 'Tardy',
    'subject' => 'PE',
    'recordedBy' => 'TCH-2026-000001',
]];
pushMerged('attendance', $teacher3, $payload);
check('unknown attendance status normalised', freshValue2(Attendance::class, 'ATD-ENUM-1', 'status') === 'Present');

echo '== Requirements admin status enum =='.PHP_EOL;
Requirement::create([
    'id' => 'REQ-ADM-1',
    'studentId' => 'STU-2026-000001',
    'name' => 'Signed form',
    'status' => 'Submitted',
]);
$admin4 = asUser('ADMIN-2026-000001');
$payload = [[
    'id' => 'REQ-ADM-1',
    'studentId' => 'STU-2026-000001',
    'status' => 'Bogus',
]];
pushMerged('requirements', $admin4, $payload);
check('bogus admin requirement status ignored', freshValue2(Requirement::class, 'REQ-ADM-1', 'status') === 'Submitted');

DB::rollBack();

echo PHP_EOL."{$pass} passed, {$fail} failed".PHP_EOL;
exit($fail === 0 ? 0 : 1);
