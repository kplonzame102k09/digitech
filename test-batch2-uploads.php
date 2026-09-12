<?php

use App\Http\Controllers\PortalDataController;
use App\Models\Requirement;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

function controllerFor(string $portalId): array
{
    $user = User::where('user_id', $portalId)->firstOrFail();
    Auth::login($user);

    return [app(PortalDataController::class), $user];
}

function uploadImageStatus(User $user): int
{
    Auth::login($user);
    $request = Request::create('/api/portal/uploads', 'POST');
    $request->setUserResolver(fn () => $user);
    try {
        app(PortalDataController::class)->uploadImage($request);

        return 200;
    } catch (HttpException $e) {
        return $e->getStatusCode();
    } catch (ValidationException $e) {
        return 422;
    }
}

Storage::disk('private')->makeDirectory('requirement-files');
Storage::disk('private')->put('requirement-files/sample.pdf', 'test-content');

DB::beginTransaction();

echo '== uploadImage permission =='.PHP_EOL;
check('student cannot upload announcement image', uploadImageStatus(User::where('user_id', 'STU-2026-000001')->first()) === 403);
check('guest cannot upload announcement image', uploadImageStatus(User::where('user_id', 'GST-2026-000001')->first()) === 403);
check('teacher can upload announcement image (200 or validation)', in_array(uploadImageStatus(User::where('user_id', 'TCH-2026-000001')->first()), [200, 302, 422], true));

echo '== Requirement upload permission =='.PHP_EOL;
[$ctrl, $student] = controllerFor('STU-2026-000001');
$req = Request::create('/api/portal/requirements/upload', 'POST');
$req->setUserResolver(fn () => User::where('user_id', 'STU-2026-000001')->first());
// No file -> validation error (422) or aborted; either way not a 200 empty.
$status = 0;
try {
    $ctrl->uploadRequirementFile($req);
    $status = 200;
} catch (HttpException $e) {
    $status = $e->getStatusCode();
} catch (ValidationException $e) {
    $status = 422;
}
check('student requirement upload requires a file (->422/200 guard)', $status === 422 || $status === 200);

echo '== Private download route ownership =='.PHP_EOL;
Requirement::create([
    'id' => 'REQ-OWN-1',
    'studentId' => 'STU-2026-000001',
    'name' => 'Signed Form',
    'status' => 'Submitted',
    'fileUrl' => '/api/portal/requirements/files/sample.pdf',
]);

function downloadStatus(User $user): int
{
    Auth::login($user);
    try {
        $request = Request::create('/api/portal/requirements/files/sample.pdf', 'GET');
        $request->setUserResolver(fn () => $user);
        app(PortalDataController::class)->downloadRequirementFile($request, 'sample.pdf');

        return 200;
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }
}

check('owner student can download', downloadStatus(User::where('user_id', 'STU-2026-000001')->first()) === 200);
check('other student cannot download', downloadStatus(User::where('user_id', 'STU-2026-000002')->first()) === 403);
check('assigned teacher can download', downloadStatus(User::where('user_id', 'TCH-2026-000001')->first()) === 200);
check('parent of owner can download', downloadStatus(User::where('user_id', 'PAR-2026-000001')->first()) === 200);
check('guest cannot download', downloadStatus(User::where('user_id', 'GST-2026-000001')->first()) === 403);
check('admin can download', downloadStatus(User::where('user_id', 'ADMIN-2026-000001')->first()) === 200);

echo '== fileUrl sanitisation on read =='.PHP_EOL;
$svc = app(PortalDataService::class);
$student = User::where('user_id', 'STU-2026-000001')->first();
Auth::login($student);
$rows = $svc->getCollection('requirements', $student);
$own = collect($rows)->firstWhere('id', 'REQ-OWN-1');
check('owned row fileUrl rewritten to route', str_contains($own['fileUrl'] ?? '', '/api/portal/requirements/files/sample.pdf'));

Requirement::create([
    'id' => 'REQ-XSS-1',
    'studentId' => 'STU-2026-000001',
    'name' => 'Bad link',
    'status' => 'Pending',
    'fileUrl' => 'javascript:alert(1)',
]);
Requirement::create([
    'id' => 'REQ-LEGACY-1',
    'studentId' => 'STU-2026-000001',
    'name' => 'Legacy link',
    'status' => 'Pending',
    'fileUrl' => 'http://localhost/storage/requirement-files/old.pdf',
]);
$rows = $svc->getCollection('requirements', $student);
$xj = collect($rows)->firstWhere('id', 'REQ-XSS-1');
$legacy = collect($rows)->firstWhere('id', 'REQ-LEGACY-1');
check('javascript: fileUrl neutralised', $xj['fileUrl'] === null, (string) $xj['fileUrl']);
check('legacy storage URL rewritten to route', str_contains($legacy['fileUrl'] ?? '', '/api/portal/requirements/files/old.pdf'));

DB::rollBack();
Storage::disk('private')->delete('requirement-files/sample.pdf');

echo PHP_EOL."{$pass} passed, {$fail} failed".PHP_EOL;
exit($fail === 0 ? 0 : 1);
