<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthenticateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(AuthenticateUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where(function ($query) use ($validated): void {
                $query->where('user_id', $validated['user_id'])
                    ->orWhere('email', $validated['user_id']);
            })
            ->where('role', $validated['role'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->withInput()->withErrors([
                'user_id' => 'The supplied credentials are invalid.',
            ]);
        }

        if ($user->status !== 'active') {
            return back()->withInput()->withErrors([
                'user_id' => 'This account is not active.',
            ]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $validated['password']])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->mustChangePassword) {
            return redirect()->route('auth.password.change');
        }

        $redirects = [
            'student' => '/student/dashboard',
            'teacher' => '/teacher/dashboard',
            'admin' => '/admin/dashboard',
            'parent' => '/parent/dashboard',
            'guest' => '/guest/announcements',
        ];

        return redirect($redirects[$user->role] ?? '/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
