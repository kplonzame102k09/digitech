<?php

/**
 * Program catalogue add-on (admin Programs & TVET page).
 *
 * Verifies the unified strand/track catalogue source:
 *   - config() fallback when no admin override is saved
 *   - DB `programs` override gates BOTH strand and track
 *   - tvetQualifications / tvetLevels are managed independently
 *   - settings round-trip exposes the new keys
 *   - sync layer + FormRequest closure read from the same source
 *   - non-admin writes to settings are still 403
 *
 * Run with:  php test-programs-catalogue.php
 * All writes happen inside one transaction that is rolled back.
 */

use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

function runRollback(callable $fn): void
{
    DB::beginTransaction();
    try {
        $fn();
        DB::rollBack();
    } catch (Exception $e) {
        DB::rollBack();
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

function catalogPayload(string $strand, string $track): array
{
    return [
        'programType' => 'Senior High',
        'gradeLevel' => '11',
        'strand' => $strand,
        'track' => $track,
        'schoolYear' => 'SY-PROG-'.Str::upper(Str::random(5)),
        'trainingLevel' => null,
        'contact' => '09170000000',
        'birthDate' => '2008-01-01',
        'address' => 'Scratch City',
        'guardianName' => 'G Scratch',
        'guardianContact' => '09170000001',
    ];
}

function tvetPayload(string $qualification, string $level = 'NC II'): array
{
    return [
        'programType' => 'TVET',
        'gradeLevel' => '11',
        'strand' => null,
        'track' => $qualification,
        'schoolYear' => 'SY-PROG-'.Str::upper(Str::random(5)),
        'trainingLevel' => $level,
        'contact' => '09170000000',
        'birthDate' => '2008-01-01',
        'address' => 'Scratch City',
        'guardianName' => 'G Scratch',
        'guardianContact' => '09170000001',
    ];
}

runRollback(function () {
    $svc = app(PortalDataService::class);
    $guest = scratchUser('guest');
    $blocked = false;
    try {
        $svc->putCollection('settings', ['programs' => ['X']], $guest);
    } catch (HttpException $e) {
        $blocked = $e->getStatusCode() === 403;
    }
    check('guest settings write blocked (403)', $blocked === true);

    throw new Exception('ROLLBACK');
});

$svc = app(PortalDataService::class);

echo '== TVET qualification normalisation =='.PHP_EOL;
try {
    DB::beginTransaction();
    $admin = scratchUser('admin');
    $result = $svc->putCollection('settings', [
        'tvetQualifications' => [
            ['name' => '', 'description' => 'nameless should be dropped', 'levels' => []],
            ['name' => 'CSS NC II', 'description' => '  padded description  ', 'levels' => ['NC II', 'NC II', '']],
            ['name' => 'CSS NC II', 'description' => 'duplicate should be dropped', 'levels' => []],
            'Legacy Plain String',
        ],
    ], $admin);

    $rows = $result['tvetQualifications'] ?? null;
    check('nameless and duplicate rows dropped', is_array($rows) && count($rows) === 2, json_encode($rows));
    check('legacy string upgraded to row', ($rows[1]['name'] ?? '') === 'Legacy Plain String' && ($rows[1]['description'] ?? 'x') === '' && ($rows[1]['levels'] ?? null) === [], json_encode($rows[1] ?? null));
    check('description trimmed', ($rows[0]['description'] ?? '') === 'padded description', json_encode($rows[0] ?? null));
    check('levels deduped and blank-dropped', ($rows[0]['levels'] ?? null) === ['NC II'], json_encode($rows[0] ?? null));
    check('catalogue names from rows dedupe', $svc->catalogue('tvetQualifications') === ['CSS NC II', 'Legacy Plain String'], implode('|', $svc->catalogue('tvetQualifications')));

    DB::rollBack();
} catch (Exception $e) {
    try {
        DB::rollBack();
    } catch (Exception $ignored) {
    }
    check('tvet normalisation transaction', false, $e->getMessage());
}

$svc = app(PortalDataService::class);

echo '== Programme catalogue fallback (no override saved) =='.PHP_EOL;
runRollback(function () use ($svc) {
    SystemSetting::getInstance()->update([
        'programs' => null,
        'tvetQualifications' => null,
        'tvetLevels' => null,
    ]);

    $strands = $svc->catalogue('strands');
    $tracks = $svc->catalogue('tracks');
    check('strand fallback = config strands', $strands === config('portal.strands'), implode(',', $strands));
    check('track fallback = config tracks', $tracks === config('portal.tracks'), implode(',', $tracks));
    check('TVL falls back to track list', in_array('TVL', $tracks, true));
    check('Physics not offered by default', ! in_array('Physics', $strands, true));
    check('tvet qualifications un-gated (empty)', $svc->catalogue('tvetQualifications') === []);
    check('tvet levels un-gated (empty)', $svc->catalogue('tvetLevels') === []);

    throw new Exception('ROLLBACK');
});

echo '== Programme row normalisation =='.PHP_EOL;
try {
    DB::beginTransaction();
    $admin = scratchUser('admin');
    $result = $svc->putCollection('settings', [
        'programs' => [
            ['name' => 'ICT', 'description' => '  Info & communications technology  ', 'category' => 'TechPro'],
            ['name' => '', 'description' => 'nameless should be dropped'],
            ['name' => 'ICT', 'description' => 'duplicate should be dropped'],
            'Legacy Plain Strand',
            ['name' => 'HUMSS', 'description' => 'Humanities', 'category' => 'Academics'],
            ['name' => 'Stem-A', 'description' => 'unknown bucket defaults', 'category' => 'weird bucket'],
        ],
    ], $admin);

    $rows = $result['programs'] ?? null;
    check('program blank and duplicate rows dropped', is_array($rows) && count($rows) === 4, json_encode($rows));
    check('program name trimmed', ($rows[0]['name'] ?? '') === 'ICT', json_encode($rows[0] ?? null));
    check('program description trimmed', ($rows[0]['description'] ?? '') === 'Info & communications technology', json_encode($rows[0] ?? null));
    check('program category label normalised', ($rows[0]['category'] ?? '') === 'techpro', json_encode($rows[0] ?? null));
    check('program legacy string upgraded to row', ($rows[1]['name'] ?? '') === 'Legacy Plain Strand' && ($rows[1]['description'] ?? 'x') === '', json_encode($rows[1] ?? null));
    check('program legacy string defaults to academics', ($rows[1]['category'] ?? '') === 'academics', json_encode($rows[1] ?? null));
    check('program category case folded', ($rows[2]['category'] ?? '') === 'academics', json_encode($rows[2] ?? null));
    check('program unknown category defaults to academics', ($rows[3]['category'] ?? '') === 'academics', json_encode($rows[3] ?? null));
    check('program rows expose no levels key', ! array_key_exists('levels', $rows[0] ?? []), json_encode($rows[0] ?? null));
    check('catalogue names from program rows', $svc->catalogue('strands') === ['ICT', 'Legacy Plain Strand', 'HUMSS', 'Stem-A'], implode('|', $svc->catalogue('strands')));

    DB::rollBack();
} catch (Exception $e) {
    try {
        DB::rollBack();
    } catch (Exception $ignored) {
    }
    check('program row normalisation transaction', false, $e->getMessage());
}

echo '== Unified override + settings round-trip =='.PHP_EOL;
try {
    DB::beginTransaction();
    $admin = scratchUser('admin');
    Auth::login($admin);

    $result = $svc->putCollection('settings', [
        'programs' => [
            ['name' => 'ICT', 'description' => 'Info and communications technology', 'category' => 'techpro'],
            ['name' => 'ABM-2', 'description' => ''],
        ],
        'tvetQualifications' => [
            ['name' => 'Computer Systems Servicing NC II', 'description' => 'Covers computer assembly, networking and maintenance.', 'levels' => ['NC I', 'NC II']],
            ['name' => 'Food and Beverage NC II', 'description' => 'Food and beverage services in dining establishments.', 'levels' => ['NC II']],
        ],
        'tvetLevels' => ['NC I', 'NC II'],
    ], $admin);

    check('settings payload exposes programs rows', ($result['programs'] ?? null) === [['name' => 'ICT', 'description' => 'Info and communications technology', 'category' => 'techpro'], ['name' => 'ABM-2', 'description' => '', 'category' => 'academics']], json_encode($result['programs'] ?? null));
    check('settings payload exposes tvetQualifications', count($result['tvetQualifications'] ?? []) === 2, json_encode($result['tvetQualifications'] ?? null));
    check('tvet qualification description round-trips', ($result['tvetQualifications'][0]['description'] ?? '') === 'Covers computer assembly, networking and maintenance.', json_encode($result['tvetQualifications'][0] ?? null));
    check('tvet qualification levels round-trips', ($result['tvetQualifications'][0]['levels'] ?? null) === ['NC I', 'NC II'], json_encode($result['tvetQualifications'][0] ?? null));
    check('settings payload exposes tvetLevels', ($result['tvetLevels'] ?? null) === ['NC I', 'NC II'], json_encode($result['tvetLevels'] ?? null));

    check('strand reads unified override', $svc->catalogue('strands') === ['ICT', 'ABM-2'], implode(',', $svc->catalogue('strands')));
    check('track reads unified override', $svc->catalogue('tracks') === ['ICT', 'ABM-2'], implode(',', $svc->catalogue('tracks')));
    check('tvet qualification names exposed by catalogue', $svc->catalogue('tvetQualifications') === ['Computer Systems Servicing NC II', 'Food and Beverage NC II'], implode('|', $svc->catalogue('tvetQualifications')));
    check('tvet levels override round-trips', $svc->catalogue('tvetLevels') === ['NC I', 'NC II']);

    DB::rollBack();
} catch (Exception $e) {
    try {
        DB::rollBack();
    } catch (Exception $ignored) {
    }
    check('settings override transaction', false, $e->getMessage());
}

echo '== Sync-layer gate honours unified list =='.PHP_EOL;
try {
    DB::beginTransaction();
    $admin = scratchUser('admin');
    $svc->putCollection('settings', [
        'programs' => ['ICT', 'ABM-2'],
        'tvetQualifications' => [
            ['name' => 'Computer Systems Servicing NC II', 'description' => '', 'levels' => ['NC I', 'NC II']],
        ],
        'tvetLevels' => ['NC I', 'NC II'],
    ], $admin);

    $ref = new ReflectionMethod(PortalDataService::class, 'applyEnrollmentCatalogues');
    $ref->setAccessible(true);

    $kept = $ref->invoke($svc, ['strand' => 'ICT', 'track' => 'ABM-2'], Enrollment::class, []);
    check('listed strand kept', ($kept['strand'] ?? null) === 'ICT', json_encode($kept));

    $droppedStrand = $ref->invoke($svc, ['strand' => 'ZZZ'], Enrollment::class, ['id' => 'TMP-NO-ROW']);
    check('off-list strand dropped', ! array_key_exists('strand', $droppedStrand), json_encode($droppedStrand));

    $droppedTrack = $ref->invoke($svc, ['track' => 'NOPE'], Enrollment::class, ['id' => 'TMP-NO-ROW']);
    check('off-list track dropped', ! array_key_exists('track', $droppedTrack), json_encode($droppedTrack));

    DB::rollBack();
} catch (Exception $e) {
    try {
        DB::rollBack();
    } catch (Exception $ignored) {
    }
    check('sync gate transaction', false, $e->getMessage());
}

echo '== Sync-layer TVET gate uses qualifications =='.PHP_EOL;
try {
    DB::beginTransaction();
    $admin = scratchUser('admin');
    $svc->putCollection('settings', [
        'programs' => ['ICT', 'ABM-2'],
        'tvetQualifications' => [
            ['name' => 'Computer Systems Servicing NC II', 'description' => '', 'levels' => ['NC I', 'NC II']],
        ],
        'tvetLevels' => ['NC I', 'NC II'],
    ], $admin);

    $ref = new ReflectionMethod(PortalDataService::class, 'applyEnrollmentCatalogues');
    $ref->setAccessible(true);

    $tvetKept = $ref->invoke($svc, ['programType' => 'TVET', 'track' => 'Computer Systems Servicing NC II'], Enrollment::class, []);
    check('listed qualification kept for TVET', ($tvetKept['track'] ?? null) === 'Computer Systems Servicing NC II', json_encode($tvetKept));

    $offQual = $ref->invoke($svc, ['programType' => 'TVET', 'track' => 'NOT A QUALIFICATION'], Enrollment::class, ['id' => 'TMP-NO-ROW']);
    check('off-list qualification dropped for TVET', ! array_key_exists('track', $offQual), json_encode($offQual));

    $tvetLevels = $svc->tvetLevelsForQualification('Computer Systems Servicing NC II');
    check('tvetLevelsForQualification returns row levels', $tvetLevels === ['NC I', 'NC II'], implode('|', $tvetLevels));

    $fallbackLevels = $svc->tvetLevelsForQualification('Unknown Qualification');
    check('unknown qualification falls back to global levels', $fallbackLevels === ['NC I', 'NC II'], implode('|', $fallbackLevels));

    DB::rollBack();
} catch (Exception $e) {
    try {
        DB::rollBack();
    } catch (Exception $ignored) {
    }
    check('tvet sync gate transaction', false, $e->getMessage());
}

echo '== FormRequest closure reads the same source =='.PHP_EOL;
try {
    DB::beginTransaction();
    $student = scratchUser('student');
    Auth::login($student);
    $admin = scratchUser('admin');
    $svc->putCollection('settings', ['programs' => ['ICT', 'ABM-2']], $admin);

    $accepted = true;
    try {
        $req = StoreEnrollmentRequest::create('/api/student/enrollments', 'POST', catalogPayload('ICT', 'ABM-2'));
        $req->headers->set('Accept', 'application/json');
        $req->setContainer(app());
        $req->setRedirector(app('redirect'));
        $req->setUserResolver(fn () => $student);
        $req->validateResolved();
    } catch (ValidationException $e) {
        $accepted = false;
    }
    check('store accepts listed strand/track', $accepted === true);

    $rejected = false;
    try {
        $req = StoreEnrollmentRequest::create('/api/student/enrollments', 'POST', catalogPayload('ZZZ', 'ABM-2'));
        $req->headers->set('Accept', 'application/json');
        $req->setContainer(app());
        $req->setRedirector(app('redirect'));
        $req->setUserResolver(fn () => $student);
        $req->validateResolved();
    } catch (ValidationException $e) {
        $rejected = true;
    }
    check('store rejects off-list strand', $rejected === true);

    $svc->putCollection('settings', [
        'programs' => ['ICT', 'ABM-2'],
        'tvetQualifications' => [
            ['name' => 'Computer Systems Servicing NC II', 'description' => '', 'levels' => ['NC I', 'NC II']],
        ],
        'tvetLevels' => ['NC I', 'NC II'],
    ], $admin);

    $tvetAccepted = true;
    try {
        $req = StoreEnrollmentRequest::create('/api/student/enrollments', 'POST', tvetPayload('Computer Systems Servicing NC II', 'NC II'));
        $req->headers->set('Accept', 'application/json');
        $req->setContainer(app());
        $req->setRedirector(app('redirect'));
        $req->setUserResolver(fn () => $student);
        $req->validateResolved();
    } catch (ValidationException $e) {
        $tvetAccepted = false;
    }
    check('store accepts listed qualification for TVET', $tvetAccepted === true);

    $tvetBadLevel = false;
    try {
        $req = StoreEnrollmentRequest::create('/api/student/enrollments', 'POST', tvetPayload('Computer Systems Servicing NC II', 'NC XLI'));
        $req->headers->set('Accept', 'application/json');
        $req->setContainer(app());
        $req->setRedirector(app('redirect'));
        $req->setUserResolver(fn () => $student);
        $req->validateResolved();
    } catch (ValidationException $e) {
        $tvetBadLevel = true;
    }
    check('store rejects off-list training level for TVET', $tvetBadLevel === true);

    $tvetBadQual = false;
    try {
        $req = StoreEnrollmentRequest::create('/api/student/enrollments', 'POST', tvetPayload('Not an offered qualification'));
        $req->headers->set('Accept', 'application/json');
        $req->setContainer(app());
        $req->setRedirector(app('redirect'));
        $req->setUserResolver(fn () => $student);
        $req->validateResolved();
    } catch (ValidationException $e) {
        $tvetBadQual = true;
    }
    check('store rejects off-list qualification for TVET', $tvetBadQual === true);

    DB::rollBack();
} catch (Exception $e) {
    try {
        DB::rollBack();
    } catch (Exception $ignored) {
    }
    check('form request gate transaction', false, $e->getMessage());
}

echo '== PSGC codes resolve to names on display =='.PHP_EOL;
try {
    DB::beginTransaction();
    $ref = new ReflectionMethod(PortalDataService::class, 'resolveLocationName');
    $ref->setAccessible(true);

    check('province code resolves', $ref->invoke($svc, 'province', '0520') === 'CATANDUANES');
    check('city code resolves', $ref->invoke($svc, 'city', '052008') === 'SAN ANDRES (CALOLBON)');
    check('barangay code resolves', $ref->invoke($svc, 'barangay', '052008014') === 'Codon');
    check('plain location name passes through', $ref->invoke($svc, 'province', 'Quezon') === 'Quezon');
    check('null location passes through', $ref->invoke($svc, 'city', null) === null);
    check('empty location passes through', $ref->invoke($svc, 'address', '') === '');

    DB::rollBack();
} catch (Exception $e) {
    try {
        DB::rollBack();
    } catch (Exception $ignored) {
    }
    check('psgc resolution transaction', false, $e->getMessage());
}

echo PHP_EOL."Batch summary: {$passed} passed, {$failed} failed".PHP_EOL;
exit($failed > 0 ? 1 : 0);
