<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SignupController extends Controller
{
   public function showSignup()
    {
        $regions = DB::table('philippine_regions')->get(['region_code', 'name']);
        return view('auth.signup', compact('regions'));
    }
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'role'           => 'required|in:student,teacher,parent,guest',
            'firstName'      => 'required|string|max:100',
            'lastName'       => 'required|string|max:100',
            'middleName'     => 'nullable|string|max:100',
            'contact'        => 'required|string|max:20',
            'birthDate'      => 'required|date|before:today',
            'birthPlace'     => 'required|string|max:150',
            'barangay'       => 'required|string|max:255',
            'city'           => 'required|string|max:255',
            'province'       => 'required|string|max:255',
            'region'         => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|min:8|confirmed',
            'rolePassword'   => 'nullable|string|min:8',
        ]);

        $rolePassword = env('ROLE_PASSWORD');
        if (in_array($validated['role'], ['admin', 'teacher'])) {
        if ($validated['rolePassword'] !== $rolePassword) {
            return back()
                ->withErrors([
                    'rolePassword' => 'Invalid role password.'
                ])
                ->withInput();
        }
    }
        $userId = $this->generateUniqueUserId($validated['role']);
        DB::table('users')->insert([
            'user_id'       => $userId,
            'role'          => $validated['role'],
            'first_name'    => $validated['firstName'],
            'last_name'     => $validated['lastName'],
            'middle_name'   => $validated['middleName'] ?? null,
            'contact'       => $validated['contact'],
            'birth_date'    => $validated['birthDate'],
            'birth_place'   => $validated['birthPlace'],
            'region'        => $validated['region'],
            'province'      => $validated['province'],
            'city'          => $validated['city'],
            'barangay'      => $validated['barangay'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'rolePassword' => in_array($validated['role'], ['admin', 'teacher'])
                ? Hash::make($validated['rolePassword'])
                : null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return redirect()->route('auth.login')
            ->with('success', "Account created! Your User ID is: {$userId}");
    }   
    private function generateUniqueUserId(string $role): string
    {
        $prefixes = [
            'student' => 'STU',
            'teacher' => 'TCH',
            'admin'   => 'ADM',
            'parent'  => 'PRT',
            'guest'   => 'GST',
        ];

        $prefix = $prefixes[$role] ?? 'USR';
        $year   = date('Y');
        do {
            $random = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $id = "{$prefix}-{$year}-{$random}";
        } while (DB::table('users')->where('user_id', $id)->exists());
        return $id;
    }
}   