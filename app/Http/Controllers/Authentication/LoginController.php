<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'role' => 'required|in:student,teacher,admin,parent',
            'user_id' => 'required|string',
            'password' => 'required|string',
            'rolePassword' => 'nullable|string',
        ]);

        $user = DB::table('users')
            ->where(function ($query) use ($validated): void {
                $query->where('user_id', $validated['user_id'])
                    ->orWhere('email', $validated['user_id']);
            })
            ->where('role', $validated['role'])
            ->first();

        if (! $user) {
            return back()->withInput()->withErrors([
                'user_id' => 'No account found with that User ID and role.',
            ]);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return back()->withInput()->withErrors([
                'password' => 'Incorrect password.',
            ]);
        }

        if (in_array($user->role, ['admin', 'teacher'], true)) {
            if (empty($validated['rolePassword'])) {
                return back()->withInput()->withErrors([
                    'rolePassword' => 'Role password is required for Admin and Teacher.',
                ]);
            }

            if (! $user->rolePassword || ! Hash::check($validated['rolePassword'], $user->rolePassword)) {
                return back()->withInput()->withErrors([
                    'rolePassword' => 'Incorrect role password.',
                ]);
            }
        }

        Auth::loginUsingId($user->id);
        $request->session()->regenerate();

        $redirects = [
            'student' => '/student/dashboard',
            'teacher' => '/teacher/dashboard',
            'admin' => '/admin/dashboard',
            'parent' => '/parent/dashboard',
            'guest' => '/',
        ];

        return redirect($redirects[$user->role] ?? '/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
