<?php

use App\Models\Notification;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

function pushNotif(User $user, array $row): void
{
    app(PortalDataService::class)->putCollection('notifications', [$row], $user);
}

function exists(string $id): bool
{
    return Notification::query()->whereKey($id)->exists();
}

DB::beginTransaction();

echo '== #2: recipient forgery guard =='.PHP_EOL;
$student = asUser('STU-2026-000001');

pushNotif($student, ['id' => 'N-SELF-1', 'userId' => 'STU-2026-000001', 'title' => 'Hi', 'message' => 'self', 'read' => false, 'source' => 'portal']);
check('student -> self persists', exists('N-SELF-1'));

pushNotif($student, ['id' => 'N-OTHER-1', 'userId' => 'STU-2026-000002', 'title' => 'Forged', 'message' => 'x', 'read' => false, 'source' => 'grade']);
check('student -> other student blocked', ! exists('N-OTHER-1'));

pushNotif($student, ['id' => 'N-ADMIN-1', 'userId' => 'ADMIN-2026-000001', 'title' => 'Sub', 'message' => 'submitted', 'read' => false, 'source' => 'document']);
check('student -> admin persists (relay)', exists('N-ADMIN-1'));

$parent = asUser('PAR-2026-000001');
pushNotif($parent, ['id' => 'N-PADM-1', 'userId' => 'ADMIN-2026-000001', 'title' => 'Link', 'message' => 'request', 'read' => false, 'source' => 'parentLinkRequest']);
check('parent -> admin persists (relay)', exists('N-PADM-1'));

pushNotif($parent, ['id' => 'N-POTH-1', 'userId' => 'STU-2026-000003', 'title' => 'Forged', 'message' => 'x', 'read' => false, 'source' => 'grade']);
check('parent -> unrelated student blocked', ! exists('N-POTH-1'));

$teacher = asUser('TCH-2026-000001');
pushNotif($teacher, ['id' => 'N-TSTU-1', 'userId' => 'STU-2026-000002', 'title' => 'Grade', 'message' => 'released', 'read' => false, 'source' => 'grade']);
check('teacher -> assigned student persists', exists('N-TSTU-1'));

pushNotif($teacher, ['id' => 'N-TSTU-2', 'userId' => 'STU-2026-000003', 'title' => 'Attendance', 'message' => 'updated', 'read' => false, 'source' => 'attendance']);
check('teacher -> second assigned student persists', exists('N-TSTU-2'));

pushNotif($teacher, ['id' => 'N-TPAR-1', 'userId' => 'PAR-2026-000001', 'title' => 'Report', 'message' => 'for child', 'read' => false, 'source' => 'competency']);
check('teacher -> parent of assigned student persists', exists('N-TPAR-1'));

$unrelated = User::create([
    'user_id' => 'STU-2099-000001',
    'role' => 'student',
    'status' => 'active',
    'firstName' => 'Stranger',
    'lastName' => 'Smith',
    'contact' => '00000000000',
    'birthDate' => '2000-01-01',
    'birthPlace' => 'N/A',
    'barangay' => 'N/A',
    'city' => 'N/A',
    'province' => 'N/A',
    'region' => 'N/A',
    'email' => 'stranger.smith@student.local',
    'password' => bcrypt('password'),
]);
pushNotif($teacher, ['id' => 'N-TUNR-1', 'userId' => 'STU-2099-000001', 'title' => 'Forged', 'message' => 'x', 'read' => false, 'source' => 'grade']);
check('teacher -> unassigned student blocked', ! exists('N-TUNR-1'));

pushNotif($teacher, ['id' => 'N-TADM-1', 'userId' => 'ADMIN-2026-000001', 'title' => 'Announcement published', 'message' => 'teacher broadcast', 'read' => false, 'source' => 'announcement']);
check('teacher -> admin persists (observer relay)', exists('N-TADM-1'));

$guest = asUser('GST-2026-000001');
pushNotif($guest, ['id' => 'N-GSELF-1', 'userId' => 'GST-2026-000001', 'title' => 'Hi', 'message' => 'guest', 'read' => false, 'source' => 'portal']);
check('guest -> self persists', exists('N-GSELF-1'));

pushNotif($guest, ['id' => 'N-GADM-1', 'userId' => 'ADMIN-2026-000001', 'title' => 'Document request', 'message' => 'guest doc request', 'read' => false, 'source' => 'document']);
check('guest -> admin persists (doc request relay)', exists('N-GADM-1'));

pushNotif($guest, ['id' => 'N-GBLK-1', 'userId' => 'STU-2026-000002', 'title' => 'Forged', 'message' => 'x', 'read' => false, 'source' => 'grade']);
check('guest -> other student still blocked', ! exists('N-GBLK-1'));

echo '== #1: recordId round-trip + server dedupe =='.PHP_EOL;
pushNotif($teacher, ['id' => 'N-REC-1', 'userId' => 'STU-2026-000001', 'title' => 'Grade', 'message' => 'sem1', 'read' => false, 'source' => 'grade', 'recordId' => 'GRD-XYZ']);
$svc = app(PortalDataService::class);
$rows = $svc->getCollection('notifications', User::where('user_id', 'STU-2026-000001')->first());
$rt = collect($rows)->firstWhere('id', 'N-REC-1');
check('recordId survives read round-trip', ($rt['recordId'] ?? null) === 'GRD-XYZ', (string) ($rt['recordId'] ?? 'null'));

pushNotif($teacher, [
    'id' => 'N-REC-1',
    'userId' => 'STU-2026-000001',
    'title' => 'Grade',
    'message' => 'updated',
    'read' => false,
    'source' => 'grade',
    'recordId' => 'GRD-XYZ',
]);
$rows = $svc->getCollection('notifications', User::where('user_id', 'STU-2026-000001')->first());
$dup = collect($rows)->filter(fn ($n) => ($n['source'] ?? '') === 'grade' && ($n['recordId'] ?? null) === 'GRD-XYZ');
check('re-notify upserts rather than stacks', $dup->count() === 1, "count={$dup->count()}");

DB::rollBack();
$unrelated->delete();

echo PHP_EOL."{$pass} passed, {$fail} failed".PHP_EOL;
exit($fail === 0 ? 0 : 1);
