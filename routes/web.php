<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Authentication\LoginController;
use App\Http\Controllers\Authentication\SignupController;
use App\Http\Controllers\AddressController;

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/auth/login', [LoginController::class, 'showLogin'])->name('auth.login');
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/auth/signup', [SignupController::class, 'showSignup'])->name('auth.signup');
Route::post('/signup', [SignupController::class, 'signup'])->name('signup.submit');

Route::get('/api/provinces/{regionCode}', [AddressController::class, 'provinces']);
Route::get('/api/cities/{provinceCode}', [AddressController::class, 'cities']);
Route::get('/api/barangays/{cityCode}', [AddressController::class, 'barangays']);

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () { return view('admin.dashboard'); })->name('dashboard'); 
    Route::get('/users', function () { return view('admin.users'); })->name('users'); 
    Route::get('/enrollment', function () { return view('admin.enrollment'); })->name('enrollment');
    Route::get('/documents', function () { return view('admin.documents'); })->name('documents');
    Route::get('/requirements', function () { return view('admin.requirements'); })->name('requirements');
    Route::get('/grades', function () { return view('admin.grades'); })->name('grades');
    Route::get('/settings', function () { return view('admin.settings'); })->name('settings');
    Route::get('/competencies', function () { return view('admin.competencies'); })->name('competencies');
    Route::get('/attendance', function () { return view('admin.attendance'); })->name('attendance');
    Route::get('/announcements', function () { return view('admin.announcements'); })->name('announcements');
    Route::get('/parent-links', function () { return view('admin.parent-links'); })->name('parent-links');
});