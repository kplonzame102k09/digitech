<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Authentication\LoginController;
use App\Http\Controllers\Authentication\SignupController;
use App\Http\Controllers\AddressController;

Route::get('/index', function () {
    return view('index');
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

Route::middleware('auth')->prefix('student')->name('student.')->group(function (){
    Route::get('/dashboard', function () { return view('student.dashboard'); })->name('dashboard');
    Route::get('/enrollment', function () { return view('student.enrollment'); })->name('enrollment');
    Route::get('/attendance', function () { return view('student.attendane'); })->name('attendance');
    Route::get('/documents', function () { return view('student.documents'); })->name('documents');
    Route::get('competencies', function () { return view('student.competencies'); })->name('competencies');
    Route::get('/requirements', function () { return view('student.requirements'); })->name('requirements');
    Route::get('/grades', function () { return view('student.grades'); })->name('grades');
    Route::get('/announcements', function () { return view('student.announcements'); })->name('announcements');
    Route::get('/profile', function () { return view('student.profile'); })->name('profile');
});

Route::middleware('auth')->prefix('teacher')->name('teacher.')->group(function (){
    Route::get('/announcements', function () { return view('teacher.announcements'); })->name('announcements');
    Route::get('/attendance', function () { return view('teacher.attendance'); })->name('attendance');
    Route::get('/competencies', function () { return view('teacher.compentencies'); })->name('compentencies');
    Route::get('/dashboard', function () { return view('teacher.dashboard'); })->name('dashboard');
    Route::get('/grades', function () { return view('teacher.grades'); })->name('grades');
    Route::get('/profile', function () { return view('teacher.profile'); })->name('profile');
    Route::get('/students', function () { return view('teacher.students'); })->name('students');
});

Route::middleware('auth')->prefix('parent')->name('parent.')->group(function () {
    Route::get('/announcements', function () { return view('parent.announcements'); })->name('announcements');
    Route::get('/attendance', function () { return view('parent.attendance'); })->name('attendance');
    Route::get('/children', function () { return view('parent.children'); })->name('children');
    Route::get('/dashboard', function () { return view('parent.dashboard'); })->name('dashboard');
    Route::get('/documents', function () { return view('parent.documents'); })->name('documents');
    Route::get('/grades', function () { return view('parent.grades'); })->name('grades');
    Route::get('/profile', function () { return view('parent.profile'); })->name('profile');
});