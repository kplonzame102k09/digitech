<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterPortalUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SignupController extends Controller
{
    public function showSignup(): View
    {
        $regions = DB::table('philippine_regions')->get(['region_code', 'name']);

        return view('auth.signup', compact('regions'));
    }

    public function signup(RegisterPortalUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $userId = $this->generateUniqueUserId($validated['role']);

        DB::table('users')->insert([
            'user_id' => $userId,
            'role' => $validated['role'],
            'status' => 'active',
            'firstName' => $validated['firstName'],
            'lastName' => $validated['lastName'],
            'middleName' => $validated['middleName'] ?? null,
            'contact' => $validated['contact'],
            'birthDate' => $validated['birthDate'],
            'birthPlace' => $validated['birthPlace'],
            'region' => $validated['region'],
            'province' => $validated['province'],
            'city' => $validated['city'],
            'barangay' => $validated['barangay'],
            'email' => $validated['email'],
            'username' => $validated['username'] ?? null,
            'password' => Hash::make($validated['password']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('auth.login')
            ->with('success', "Account created! Your User ID is: {$userId}");
    }

    private function generateUniqueUserId(string $role): string
    {
        $prefixes = [
            'student' => 'STU',
            'teacher' => 'TCH',
            'admin' => 'ADM',
            'parent' => 'PRT',
            'guest' => 'GST',
        ];

        $prefix = $prefixes[$role] ?? 'USR';
        $year = date('Y');

        do {
            $random = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $id = "{$prefix}-{$year}-{$random}";
        } while (DB::table('users')->where('user_id', $id)->exists());

        return $id;
    }
}
