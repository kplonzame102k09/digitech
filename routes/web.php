<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Authentication\LoginController;
use App\Http\Controllers\Authentication\SignupController;
use App\Http\Controllers\PortalDataController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
})->name('home');

Route::get('/auth/login', [LoginController::class, 'showLogin'])->name('auth.login');
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/auth/signup', [SignupController::class, 'showSignup'])->name('auth.signup');
Route::post('/signup', [SignupController::class, 'signup'])->name('signup.submit');

Route::get('/api/provinces/{regionCode}', [AddressController::class, 'provinces']);
Route::get('/api/regions', [AddressController::class, 'regions']);
Route::get('/api/cities/{provinceCode}', [AddressController::class, 'cities']);
Route::get('/api/barangays/{cityCode}', [AddressController::class, 'barangays']);

Route::middleware('auth')->prefix('api/portal')->group(function () {
    Route::get('/', [PortalDataController::class, 'index']);
    Route::get('/boot', [PortalDataController::class, 'boot']);
    Route::post('/users/import', [PortalDataController::class, 'importUsers']);
    Route::get('/users/import/{batchId}', [PortalDataController::class, 'importStatus']);
    Route::get('/{key}', [PortalDataController::class, 'show']);
    Route::put('/{key}', [PortalDataController::class, 'update']);
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
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
});

Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', fn () => view('student.dashboard'))->name('dashboard');
    Route::get('/enrollment', fn () => view('student.enrollment'))->name('enrollment');
    Route::get('/attendance', fn () => view('student.attendance'))->name('attendance');
    Route::get('/documents', fn () => view('student.documents'))->name('documents');
    Route::get('/competencies', fn () => view('student.competencies'))->name('competencies');
    Route::get('/requirements', fn () => view('student.requirements'))->name('requirements');
    Route::get('/grades', fn () => view('student.grades'))->name('grades');
    Route::get('/announcements', fn () => view('student.announcements'))->name('announcements');
    Route::get('/profile', fn () => view('student.profile'))->name('profile');
});

Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/announcements', fn () => view('teacher.announcements'))->name('announcements');
    Route::get('/attendance', fn () => view('teacher.attendance'))->name('attendance');
    Route::get('/competencies', fn () => view('teacher.competencies'))->name('competencies');
    Route::get('/dashboard', fn () => view('teacher.dashboard'))->name('dashboard');
    Route::get('/grades', fn () => view('teacher.grades'))->name('grades');
    Route::get('/profile', fn () => view('teacher.profile'))->name('profile');
    Route::get('/students', fn () => view('teacher.students'))->name('students');
});

Route::middleware(['auth', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/announcements', fn () => view('parent.announcements'))->name('announcements');
    Route::get('/attendance', fn () => view('parent.attendance'))->name('attendance');
    Route::get('/children', fn () => view('parent.children'))->name('children');
    Route::get('/dashboard', fn () => view('parent.dashboard'))->name('dashboard');
    Route::get('/documents', fn () => view('parent.documents'))->name('documents');
    Route::get('/grades', fn () => view('parent.grades'))->name('grades');
    Route::get('/profile', fn () => view('parent.profile'))->name('profile');
});
