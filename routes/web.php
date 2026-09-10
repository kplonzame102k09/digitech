<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Authentication\LoginController;
use App\Http\Controllers\Authentication\PasswordController;
use App\Http\Controllers\PortalDataController;
use App\Http\Controllers\Student\DocumentRequestController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\GradeController;
use App\Http\Controllers\Student\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
})->name('home');

Route::get('/auth/login', [LoginController::class, 'showLogin'])->name('auth.login');
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/auth/password/change', [PasswordController::class, 'show'])->middleware('auth')->name('auth.password.change');
Route::post('/auth/password/change', [PasswordController::class, 'update'])->middleware('auth')->name('auth.password.update');

Route::get('/api/provinces/{regionCode}', [AddressController::class, 'provinces']);
Route::get('/api/regions', [AddressController::class, 'regions']);
Route::get('/api/cities/{provinceCode}', [AddressController::class, 'cities']);
Route::get('/api/barangays/{cityCode}', [AddressController::class, 'barangays']);

Route::middleware('auth')->prefix('api/portal')->group(function () {
    Route::get('/', [PortalDataController::class, 'index']);
    Route::get('/boot', [PortalDataController::class, 'boot']);
    Route::post('/users/import', [PortalDataController::class, 'importUsers']);
    Route::get('/users/import/{batchId}', [PortalDataController::class, 'importStatus']);
    Route::post('/photo', [PortalDataController::class, 'uploadPhoto']);
    Route::post('/uploads', [PortalDataController::class, 'uploadImage']);
    Route::post('/requirements/upload', [PortalDataController::class, 'uploadRequirementFile']);
    Route::get('/account/activity', [AccountController::class, 'activity']);
    Route::post('/account/password', [AccountController::class, 'changePassword']);
    Route::get('/{key}', [PortalDataController::class, 'show']);
    Route::put('/{key}', [PortalDataController::class, 'update']);
});

Route::middleware(['auth', 'password.updated', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');
    Route::get('/users', fn () => view('admin.users'))->name('users');
    Route::get('/enrollment', fn () => view('admin.enrollment'))->name('enrollment');
    Route::get('/documents', fn () => view('admin.documents'))->name('documents');
    Route::get('/requirements', fn () => view('admin.requirements'))->name('requirements');
    Route::get('/grades', fn () => view('admin.grades'))->name('grades');
    Route::get('/settings', fn () => view('admin.settings'))->name('settings');
    Route::get('/competencies', fn () => view('admin.competencies'))->name('competencies');
    Route::get('/attendance', fn () => view('admin.attendance'))->name('attendance');
    Route::get('/announcements', fn () => view('admin.announcements'))->name('announcements');
    Route::get('/parent-links', fn () => view('admin.parent-links'))->name('parent-links');
    Route::get('/profile', fn () => view('admin.profile'))->name('profile');
    Route::get('/api/profile', [AdminProfileController::class, 'show'])->name('api.profile.show');
    Route::put('/api/profile', [AdminProfileController::class, 'update'])->name('api.profile.update');
    Route::post('/api/profile/photo', [AdminProfileController::class, 'uploadPhoto'])->name('api.profile.photo');
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

    // API routes for student operations
    Route::apiResource('api/enrollments', EnrollmentController::class);
    Route::get('api/grades/summary', [GradeController::class, 'summary'])->name('grades.summary');
    Route::apiResource('api/grades', GradeController::class)->only(['index', 'show']);
    Route::apiResource('api/document-requests', DocumentRequestController::class);
    Route::get('/api/profile', [ProfileController::class, 'show'])->name('api.profile.show');
    Route::put('/api/profile', [ProfileController::class, 'update'])->name('api.profile.update');
    Route::post('/api/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('api.profile.photo');
});

Route::middleware(['auth', 'password.updated', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/announcements', fn () => view('teacher.announcements'))->name('announcements');
    Route::get('/attendance', fn () => view('teacher.attendance'))->name('attendance');
    Route::get('/competencies', fn () => view('teacher.competencies'))->name('competencies');
    Route::get('/dashboard', fn () => view('teacher.dashboard'))->name('dashboard');
    Route::get('/grades', fn () => view('teacher.grades'))->name('grades');
    Route::get('/profile', fn () => view('teacher.profile'))->name('profile');
    Route::get('/students', fn () => view('teacher.students'))->name('students');
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
