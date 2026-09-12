<?php

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
        echo "  PASS  {$label}";
        if ($detail !== '') {
            echo '  ['.$detail.']';
        }
        echo PHP_EOL;
    } else {
        $fail++;
        echo "  FAIL  {$label}  [{$detail}]".PHP_EOL;
    }
}

function bootFor(string $portalId): array
{
    $user = User::where('user_id', $portalId)->firstOrFail();
    Auth::login($user);

    return [app(PortalDataService::class), $user];
}

DB::beginTransaction();

echo '== Admin scoping =='.PHP_EOL;
[$svc, $admin] = bootFor('ADMIN-2026-000001');
$adminBoot = $svc->bootPayload($admin);
check('admin sees all 7 users', count($adminBoot['collections']['users']) === 7, (string) count($adminBoot['collections']['users']));
check('admin sees all 3 enrollments', count($adminBoot['collections']['enrollments']) === 3, (string) count($adminBoot['collections']['enrollments']));
check('admin sees all audit logs (0 or more)', is_array($adminBoot['collections']['auditLogs']));

echo '== Teacher scoping (assigned-only) =='.PHP_EOL;
[$svc, $teacher] = bootFor('TCH-2026-000001');
$teacherIds = $svc->getEnrolledStudentIds($teacher);
check('teacher has 3 assigned students', count($teacherIds) === 3, implode(',', $teacherIds));
$teacherBoot = $svc->bootPayload($teacher);
$tUsers = $teacherBoot['collections']['users'];
$tUserIds = array_column($tUsers, 'id');
check('teacher users = self + 3 students', count($tUserIds) === 4, implode(',', $tUserIds));
check('teacher enrollments = all 3 (assigned)', count($teacherBoot['collections']['enrollments']) === 3, (string) count($teacherBoot['collections']['enrollments']));
check('teacher has no parent/admin in user list', ! in_array('ADMIN-2026-000001', $tUserIds, true) && ! in_array('PAR-2026-000001', $tUserIds, true));

echo '== Teacher can write auditLogs (gate aligned) =='.PHP_EOL;
check('teacher may NOT write auditLogs (S3 forgery fix)', ! $svc->canWriteCollection($teacher, 'auditLogs'));
check('teacher may write users', $svc->canWriteCollection($teacher, 'users'));
check('guest may write documentRequests', app(PortalDataService::class)->canWriteCollection(User::where('user_id', 'GST-2026-000001')->first(), 'documentRequests'));

echo '== Student scoping =='.PHP_EOL;
[$svc, $student] = bootFor('STU-2026-000001');
$sBoot = $svc->bootPayload($student);
$sUserIds = array_column($sBoot['collections']['users'], 'id');
check('student users = self only', $sUserIds === ['STU-2026-000001'], implode(',', $sUserIds));
check('student enrollments = own only', count($sBoot['collections']['enrollments']) === 1, (string) count($sBoot['collections']['enrollments']));
check('student announcements audience-filtered', collect($sBoot['collections']['announcements'])->every(fn ($a) => in_array($a['audience'] ?? 'All', ['All', 'all', 'Students', 'Student'], true)));

echo '== Parent scoping =='.PHP_EOL;
[$svc, $parent] = bootFor('PAR-2026-000001');
$pBoot = $svc->bootPayload($parent);
$pUserIds = array_column($pBoot['collections']['users'], 'id');
sort($pUserIds);
check('parent users = self + child', $pUserIds === ['PAR-2026-000001', 'STU-2026-000001'], implode(',', $pUserIds));
check('parent enrollments = child only', count($pBoot['collections']['enrollments']) === 1, (string) count($pBoot['collections']['enrollments']));

echo '== Guest scoping =='.PHP_EOL;
[$svc, $guest] = bootFor('GST-2026-000001');
$gBoot = $svc->bootPayload($guest);
$gUserIds = array_column($gBoot['collections']['users'], 'id');
check('guest users = self only', $gUserIds === ['GST-2026-000001'], implode(',', $gUserIds));

echo '== Enrollment hijack prevention =='.PHP_EOL;
[$svc, $student] = bootFor('STU-2026-000001');
$other = DB::table('enrollments')->where('studentId', 'STU-2026-000002')->first();
$before = DB::table('enrollments')->where('id', $other->id)->first()->studentId;
$svc->putCollection('enrollments', [
    ['id' => $other->id, 'studentId' => 'STU-2026-000001', 'status' => 'Submitted'],
], $student);
$after = DB::table('enrollments')->where('id', $other->id)->first()->studentId;
check('student cannot hijack another students enrollment', $before === $after && $before === 'STU-2026-000002', "before={$before} after={$after}");

echo '== Teacher cannot publish grades =='.PHP_EOL;
[$svc, $teacher] = bootFor('TCH-2026-000001');
$gradeId = 'GRD-TEST-0001';
$svc->putCollection('grades', [
    ['id' => $gradeId, 'studentId' => 'STU-2026-000001', 'subject' => 'Math', 'schoolYear' => '2026-2027', 'semester' => '1st Semester', 'prelim' => 90, 'published' => true],
], $teacher);
$savedGrade = DB::table('grades')->where('id', $gradeId)->first();
check('teacher-created grade is unpublished', (int) $savedGrade->published === 0, "published={$savedGrade->published}");
check('teacher-created grade is self-attributed', $savedGrade->teacherId === 'TCH-2026-000001', $savedGrade->teacherId);
check('grade finalGrade computed (weighted)', abs(((float) $savedGrade->finalGrade) - 90) < 0.01, (string) $savedGrade->finalGrade);

echo '== Grade duplicate key -> 422 =='.PHP_EOL;
$dup = false;
try {
    $svc->putCollection('grades', [
        ['id' => 'GRD-TEST-0002', 'studentId' => 'STU-2026-000001', 'subject' => 'Math', 'schoolYear' => '2026-2027', 'semester' => '1st Semester', 'prelim' => 91],
    ], $teacher);
} catch (HttpException $e) {
    $dup = $e->getStatusCode() === 422;
}
check('duplicate gradeKey returns 422', $dup);

echo '== syncOwnProfile email validation =='.PHP_EOL;
[$svc, $student] = bootFor('STU-2026-000001');
$badEmail = false;
try {
    $svc->putCollection('users', [
        ['id' => 'STU-2026-000001', 'email' => 'maria.reyes@student.local'],
    ], $student);
} catch (HttpException $e) {
    $badEmail = $e->getStatusCode() === 422;
}
check('taken email on own profile -> 422', $badEmail);

$invalidEmail = false;
try {
    $svc->putCollection('users', [
        ['id' => 'STU-2026-000001', 'email' => 'not-an-email'],
    ], $student);
} catch (HttpException $e) {
    $invalidEmail = $e->getStatusCode() === 422;
}
check('malformed email on own profile -> 422', $invalidEmail);

DB::rollBack();

echo PHP_EOL."{$pass} passed, {$fail} failed".PHP_EOL;
exit($fail === 0 ? 0 : 1);
