<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function show(): View
    {
        abort_unless(Auth::check(), 403);

        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), 'different:current_password'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'The current password you entered does not match our records.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'mustChangePassword' => false,
        ])->save();

        $request->session()->regenerate();

        $redirects = [
            'student' => '/student/dashboard',
            'teacher' => '/teacher/dashboard',
            'admin' => '/admin/dashboard',
            'parent' => '/parent/dashboard',
        ];

        return redirect($redirects[$user->role] ?? '/')->with('success', 'Your password has been updated.');
    }
}
