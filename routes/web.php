<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AttendanceFinalizeController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Analytics\AttendanceAnalyticsController;
use App\Http\Controllers\Analytics\GradeAnalyticsController;
use App\Http\Controllers\Authentication\LoginController;
use App\Http\Controllers\Authentication\PasswordController;
use App\Http\Controllers\AccountRequestController;
use App\Http\Controllers\PortalDataController;
use App\Http\Controllers\Student\DocumentRequestController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\GradeController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\ClassroomDetailController;
use App\Http\Controllers\Student\ClassroomJoinController;
use App\Http\Controllers\Student\ClassroomMeetingController as StudentClassroomMeetingController;
use App\Http\Controllers\Student\ClassroomWorkController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Teacher\AttendanceReviewController;
use App\Http\Controllers\Teacher\ClassroomActivityController;
use App\Http\Controllers\Teacher\ClassroomMeetingController as TeacherClassroomMeetingController;
use App\Http\Controllers\Teacher\ClassroomController as TeacherClassroomController;
use App\Http\Controllers\Teacher\ClassroomSubmissionController;
use App\Http\Controllers\ClassroomFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
})->name('index');

Route::get('/auth/login', [LoginController::class, 'showLogin'])->name('auth.login');
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/auth/password/change', [PasswordController::class, 'show'])->middleware('auth')->name('auth.password.change');
Route::post('/auth/password/change', [PasswordController::class, 'update'])->middleware('auth')->name('auth.password.update');

Route::get('/request-account', fn () => view('request-account'))->name('account.request');
Route::post('/account-requests', [AccountRequestController::class, 'store'])->middleware('throttle:account-requests')->name('account-requests.store');

Route::get('/api/provinces/{regionCode}', [AddressController::class, 'provinces']);
Route::get('/api/regions', [AddressController::class, 'regions']);
Route::get('/api/cities/{provinceCode}', [AddressController::class, 'cities']);
Route::get('/api/barangays/{cityCode}', [AddressController::class, 'barangays']);

Route::middleware(['auth', 'password.updated'])->prefix('api/portal')->group(function () {
    Route::get('/', [PortalDataController::class, 'index']);
    Route::get('/boot', [PortalDataController::class, 'boot']);
    Route::post('/users/import', [PortalDataController::class, 'importUsers']);
    Route::get('/users/import/{batchId}', [PortalDataController::class, 'importStatus']);
    Route::post('/photo', [PortalDataController::class, 'uploadPhoto'])->middleware('throttle:uploads');
    Route::post('/uploads', [PortalDataController::class, 'uploadImage'])->middleware('throttle:uploads');
    Route::post('/requirements/upload', [PortalDataController::class, 'uploadRequirementFile'])->middleware('throttle:uploads');
    Route::get('/requirements/files/{file}', [PortalDataController::class, 'downloadRequirementFile']);
    Route::get('/account/activity', [AccountController::class, 'activity']);
    Route::post('/account/password', [AccountController::class, 'changePassword'])->name('account.password');
    // Phase 1: server-side analytics (additive; legacy bulk PUT below untouched).
    Route::get('/analytics/attendance', [AttendanceAnalyticsController::class, 'show']);
    Route::get('/analytics/attendance/by-recorder/{recorderId}', [AttendanceAnalyticsController::class, 'byRecorder']);
    Route::get('/analytics/grades/summary', [GradeAnalyticsController::class, 'summary']);
    Route::post('/analytics/grades/preview', [GradeAnalyticsController::class, 'preview']);
    Route::get('/{key}', [PortalDataController::class, 'show']);
    Route::put('/{key}', [PortalDataController::class, 'update']);
});

Route::middleware(['auth', 'password.updated', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');
    Route::get('/users', fn () => view('admin.users'))->name('users');
    Route::get('/enrollment', fn () => view('admin.enrollment'))->name('enrollment');
    Route::get('/programs', fn () => view('admin.programs'))->name('programs');
    Route::get('/documents', fn () => view('admin.documents'))->name('documents');
    Route::get('/requirements', fn () => view('admin.requirements'))->name('requirements');
    Route::get('/grades', fn () => view('admin.grades'))->name('grades');
    Route::get('/settings', fn () => view('admin.settings'))->name('settings');
    Route::get('/competencies', fn () => view('admin.competencies'))->name('competencies');
    Route::get('/attendance', fn () => view('admin.attendance'))->name('attendance');
    Route::get('/attendance/finalize', fn () => view('admin.attendance-finalization'))->name('attendance.finalize');
    Route::get('/announcements', fn () => view('admin.announcements'))->name('announcements');
    Route::get('/parent-links', fn () => view('admin.parent-links'))->name('parent-links');
    Route::get('/profile', fn () => view('admin.profile'))->name('profile');
    Route::get('/api/profile', [AdminProfileController::class, 'show'])->name('api.profile.show');
    Route::put('/api/profile', [AdminProfileController::class, 'update'])->name('api.profile.update');
    Route::post('/api/profile/photo', [AdminProfileController::class, 'uploadPhoto'])->name('api.profile.photo');
    // Advisory attendance: adviser submissions awaiting (or finished)
    // finalization, plus the RETURN route that sends a package back to its
    // adviser for correction.
    Route::get('/api/attendance/finalize', [AttendanceFinalizeController::class, 'index'])->name('api.attendance.finalize.index');
    Route::get('/api/attendance/finalize/{id}', [AttendanceFinalizeController::class, 'show'])->name('api.attendance.finalize.show');
    Route::post('/api/attendance/finalize/{id}', [AttendanceFinalizeController::class, 'finalize'])->name('api.attendance.finalize.finalize');
    Route::post('/api/attendance/finalize/{id}/return', [AttendanceFinalizeController::class, 'returnPackage'])->name('api.attendance.finalize.return');
});

Route::middleware(['auth', 'password.updated', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', fn () => view('student.dashboard'))->name('dashboard');
    Route::get('/enrollment', fn () => view('student.enrollment'))->name('enrollment');
    Route::get('/attendance', fn () => view('student.attendance'))->name('attendance');
    Route::get('/documents', fn () => view('student.documents'))->name('documents');
    Route::get('/competencies', fn () => view('student.competencies'))->name('competencies');
    Route::get('/requirements', fn () => view('student.requirements'))->name('requirements');
    Route::get('/grades', fn () => view('student.grades'))->name('grades');
    Route::get('/announcements', fn () => view('student.announcements'))->name('announcements');
    Route::get('/profile', fn () => view('student.profile'))->name('profile');
    Route::get('/classrooms', fn () => view('student.classrooms'))->name('classrooms');
    Route::get('/classrooms/{id}', fn (string $id) => view('student.classroom-detail', ['classroomId' => $id]))->name('classrooms.show');

    // API routes for student operations
    Route::apiResource('api/enrollments', EnrollmentController::class);
    Route::get('api/grades/summary', [GradeController::class, 'summary'])->name('grades.summary');
    Route::apiResource('api/grades', GradeController::class)->only(['index', 'show']);
    Route::apiResource('api/document-requests', DocumentRequestController::class);
    Route::get('/api/profile', [ProfileController::class, 'show'])->name('api.profile.show');
    Route::put('/api/profile', [ProfileController::class, 'update'])->name('api.profile.update');
    Route::post('/api/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('api.profile.photo');
    // Classrooms: roster + join (code or invite token).
    Route::get('/api/classrooms', [ClassroomJoinController::class, 'mine'])->name('api.classrooms.mine');
    Route::post('/api/classrooms/join', [ClassroomJoinController::class, 'join'])->middleware('throttle:classroom-joins')->name('api.classrooms.join');
    Route::get('/api/classrooms/{id}', [ClassroomDetailController::class, 'show'])->name('api.classrooms.show');
    // Classroom activities + submissions (fixed classroom subject; files on private disk).
    Route::get('/api/classrooms/{classroomId}/activities', [ClassroomWorkController::class, 'activities'])->name('api.classrooms.activities');
    Route::post('/api/activities/{activityId}/submit', [ClassroomWorkController::class, 'submit'])->middleware('throttle:uploads')->name('api.activities.submit');
    // Classroom video (self-hosted LiveKit; server-minted tokens only).
    Route::get('/api/classrooms/{classroomId}/meetings', [StudentClassroomMeetingController::class, 'index'])->name('api.classrooms.meetings');
    Route::post('/api/classrooms/{classroomId}/video/token', [StudentClassroomMeetingController::class, 'token'])->middleware('throttle:classroom-joins')->name('api.classrooms.video.token');
});

Route::middleware(['auth', 'password.updated', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/announcements', fn () => view('teacher.announcements'))->name('announcements');
    Route::get('/attendance', fn () => view('teacher.attendance'))->name('attendance');
    Route::get('/attendance/review', fn () => view('teacher.attendance-review'))->name('attendance.review');
    Route::get('/competencies', fn () => view('teacher.competencies'))->name('competencies');
    Route::get('/dashboard', fn () => view('teacher.dashboard'))->name('dashboard');
    Route::get('/grades', fn () => view('teacher.grades'))->name('grades');
    Route::get('/profile', fn () => view('teacher.profile'))->name('profile');
    Route::get('/students', fn () => view('teacher.students'))->name('students');
    Route::get('/classrooms', fn () => view('teacher.classrooms'))->name('classrooms');
    // Classroom management API (REST; never mirrored via bulk PUT sync).
    Route::get('/api/classrooms', [TeacherClassroomController::class, 'index'])->name('api.classrooms.index');
    Route::post('/api/classrooms', [TeacherClassroomController::class, 'store'])->name('api.classrooms.store');
    Route::get('/api/classrooms/{id}', [TeacherClassroomController::class, 'show'])->name('api.classrooms.show');
    Route::put('/api/classrooms/{id}', [TeacherClassroomController::class, 'update'])->name('api.classrooms.update');
    Route::post('/api/classrooms/{id}/regenerate', [TeacherClassroomController::class, 'regenerate'])->name('api.classrooms.regenerate');
    Route::post('/api/classrooms/{id}/archive', [TeacherClassroomController::class, 'archive'])->name('api.classrooms.archive');
    Route::delete('/api/classrooms/{id}/students/{studentId}', [TeacherClassroomController::class, 'removeStudent'])->name('api.classrooms.removeStudent');
    Route::delete('/api/classrooms/{id}', [TeacherClassroomController::class, 'destroy'])->name('api.classrooms.destroy');
    // Classroom detail page.
    Route::get('/classrooms/{id}', fn (string $id) => view('teacher.classroom-detail', ['classroomId' => $id]))->name('classrooms.show');
    // Activities (no per-activity subject; fixed classroom subject applies).
    Route::get('/api/classrooms/{id}/activities', [ClassroomActivityController::class, 'index'])->name('api.classroom-activities.index');
    Route::post('/api/classrooms/{id}/activities', [ClassroomActivityController::class, 'store'])->name('api.classroom-activities.store');
    Route::delete('/api/classrooms/{id}/activities/{activityId}', [ClassroomActivityController::class, 'destroy'])->name('api.classroom-activities.destroy');
    // Submissions: view/score/return/export + gradebook (fixed subject).
    Route::get('/api/classrooms/{id}/submissions', [ClassroomSubmissionController::class, 'index'])->name('api.classroom-submissions.index');
    Route::get('/api/classrooms/{id}/submissions/export', [ClassroomSubmissionController::class, 'export'])->name('api.classroom-submissions.export');
    Route::get('/api/classrooms/{id}/submissions/{submissionId}', [ClassroomSubmissionController::class, 'show'])->name('api.classroom-submissions.show');
    Route::post('/api/classrooms/{id}/submissions/{submissionId}/score', [ClassroomSubmissionController::class, 'score'])->name('api.classroom-submissions.score');
    Route::post('/api/classrooms/{id}/submissions/{submissionId}/return', [ClassroomSubmissionController::class, 'return'])->name('api.classroom-submissions.return');
    Route::get('/api/classrooms/{id}/gradebook', [ClassroomSubmissionController::class, 'gradebook'])->name('api.classroom-gradebook.index');
    Route::put('/api/classrooms/{id}/gradebook/{studentId}', [ClassroomSubmissionController::class, 'gradeStudent'])->name('api.classroom-gradebook.grade');
    // Classroom video meetings (self-hosted LiveKit; no recording).
    Route::get('/api/classrooms/{id}/meetings', [TeacherClassroomMeetingController::class, 'index'])->name('api.classroom-meetings.index');
    Route::post('/api/classrooms/{id}/meetings', [TeacherClassroomMeetingController::class, 'store'])->name('api.classroom-meetings.store');
    Route::post('/api/classrooms/{id}/meetings/{meetingId}/start', [TeacherClassroomMeetingController::class, 'start'])->name('api.classroom-meetings.start');
    Route::post('/api/classrooms/{id}/meetings/{meetingId}/end', [TeacherClassroomMeetingController::class, 'end'])->name('api.classroom-meetings.end');
    Route::post('/api/classrooms/{id}/meetings/{meetingId}/cancel', [TeacherClassroomMeetingController::class, 'cancel'])->name('api.classroom-meetings.cancel');
    Route::delete('/api/classrooms/{id}/meetings/{meetingId}', [TeacherClassroomMeetingController::class, 'destroy'])->name('api.classroom-meetings.destroy');
    Route::post('/api/classrooms/{id}/video/token', [TeacherClassroomMeetingController::class, 'token'])->middleware('throttle:classroom-joins')->name('api.classrooms.video.token');
    // Attendance sessions: the per-classroom funnel park (start/mark/verify/
    // submit/cancel/export) and the advisory review flow (filters, day review,
    // status edits, submit-to-admin). Session marks are checks; the adviser
    // combines them into the official finals reviewed by the admin.
    Route::get('/api/attendance/filters', [TeacherAttendanceController::class, 'filters'])->name('api.attendance.filters');
    Route::get('/api/attendance/classrooms', [TeacherAttendanceController::class, 'classrooms'])->name('api.attendance.classrooms');
    Route::get('/api/attendance/sessions', [TeacherAttendanceController::class, 'index'])->name('api.attendance.sessions.index');
    Route::post('/api/attendance/sessions', [TeacherAttendanceController::class, 'start'])->name('api.attendance.sessions.start');
    Route::get('/api/attendance/sessions/{id}', [TeacherAttendanceController::class, 'show'])->name('api.attendance.sessions.show');
    Route::post('/api/attendance/sessions/{id}/mark', [TeacherAttendanceController::class, 'mark'])->name('api.attendance.sessions.mark');
    Route::post('/api/attendance/sessions/{id}/verify', [TeacherAttendanceController::class, 'verify'])->name('api.attendance.sessions.verify');
    Route::post('/api/attendance/sessions/{id}/submit', [TeacherAttendanceController::class, 'submit'])->name('api.attendance.sessions.submit');
    Route::post('/api/attendance/sessions/{id}/cancel', [TeacherAttendanceController::class, 'cancel'])->name('api.attendance.sessions.cancel');
    Route::get('/api/attendance/sessions/{id}/export', [TeacherAttendanceController::class, 'export'])->name('api.attendance.sessions.export');
    Route::get('/api/attendance/review', [AttendanceReviewController::class, 'show'])->name('api.attendance.review.show');
    Route::post('/api/attendance/review', [AttendanceReviewController::class, 'edit'])->name('api.attendance.review.edit');
    Route::post('/api/attendance/review/submit', [AttendanceReviewController::class, 'submit'])->name('api.attendance.review.submit');
});

// Classroom invite-link landing (any authenticated user; students can join).
Route::middleware(['auth', 'password.updated'])->group(function () {
    Route::get('/classroom/join/{token}', fn (string $token) => view('classroom.join', ['token' => $token]))->name('classroom.join');
    Route::get('/api/classrooms/preview/{token}', [ClassroomJoinController::class, 'preview'])->name('api.classrooms.preview');
    // Classroom files live on the private disk behind ownership checks.
    Route::post('/api/classroom-files', [ClassroomFileController::class, 'upload'])->middleware('throttle:uploads')->name('api.classroom-files.upload');
    Route::get('/api/classroom-files/{file}', [ClassroomFileController::class, 'download'])->name('api.classroom-files.download');
});

Route::middleware(['auth', 'password.updated', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/announcements', fn () => view('parent.announcements'))->name('announcements');
    Route::get('/attendance', fn () => view('parent.attendance'))->name('attendance');
    Route::get('/children', fn () => view('parent.children'))->name('children');
    Route::get('/dashboard', fn () => view('parent.dashboard'))->name('dashboard');
    Route::get('/documents', fn () => view('parent.documents'))->name('documents');
    Route::get('/grades', fn () => view('parent.grades'))->name('grades');
    Route::get('/profile', fn () => view('parent.profile'))->name('profile');
});

Route::middleware(['auth', 'password.updated', 'role:guest'])->prefix('guest')->name('guest.')->group(function () {
    Route::get('/announcements', fn () => view('guest.announcements'))->name('announcements');
    Route::get('/documents', fn () => view('guest.documents'))->name('documents');
    Route::get('/profile', fn () => view('guest.profile'))->name('profile');
});
