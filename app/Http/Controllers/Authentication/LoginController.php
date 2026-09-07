<?php

namespace App\Http\Controllers\Authentication;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

public function login(Request $request) { 
    $validated = $request->validate(
        [ 
            'role' => 'required|in:student,teacher,admin,parent,guest', 
            'user_id' => 'required|string', 
            'password' => 'required|string', 
            'rolePassword' => 'nullable|string', 
        ]
    ); 
    // Find user by ID and role 
    $user = DB::table('users') ->where('user_id', $validated['user_id'])->where('role', $validated['role']) ->first(); 
    if (!$user) { return back()->withInput()->withErrors([ 
        'user_id' => 'No account found with that User ID and role.', 
        ]); 
    } 
    // Verify normal password 
    if (!Hash::check($validated['password'], $user->password)) { 
        return back()->withInput()->withErrors([ 
            'password' => 'Incorrect password.', 
        ]); 
    } 
    // Extra role password for Admin and Teacher 
    if (in_array($user->role, ['admin', 'teacher'])) { 
        if (empty($validated['rolePassword'])) { 
            return back()->withInput()->withErrors([ 
                'rolePassword' => 'Role password is required for Admin and Teacher.', 
            ]); 
        } 
        if ( !$user->rolePassword || !Hash::check($validated['rolePassword'], $user->rolePassword) ) { 
            return back()->withInput()->withErrors([ 
                'rolePassword' => 'Incorrect role password.', 
                ]); 
            } 
    } 
    // Authenticate the user with Laravel 
    Auth::loginUsingId($user->id); 
    // Prevent session fixation 
    $request->session()->regenerate(); // Redirect by role 
    $redirects = [ 
        'student' => '/student/dashboard', 
        'teacher' => '/teacher/dashboard', 
        'admin' => '/admin/dashboard', 
        'parent' => '/parent/dashboard', 
        'guest' => '/guest/dashboard', 
    ]; 
    return redirect($redirects[$user->role]); 
    }

    public function logout(Request $request)
    {
        $request->session()->flush();

        return redirect('/login');
    }
}
  