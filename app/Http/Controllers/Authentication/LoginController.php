<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthenticateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\LoginAttemptGuard;
use App\Support\UrlCipher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Seconds a verified-but-not-yet-authenticated login stays valid. Long
     * enough to survive a slow page load, short enough that a shared machine
     * cannot be signed in by a link left in history.
     */
    private const PENDING_TTL = 30;

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(AuthenticateUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Determine role from user_id prefix
        $role = $this->determineRoleFromUserId($validated['user_id']);

        $user = User::query()
            ->where(function ($query) use ($validated): void {
                $query->where('user_id', $validated['user_id'])
                    ->orWhere('email', $validated['user_id']);
            })
            ->where('role', $role)
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

        // The password is proven here, but the session is not authenticated yet.
        // The ref travels through the enciphered loading route, and the
        // completion step below is what actually signs the user in.
        $request->session()->regenerate();
        $request->session()->put('login.pending', [
            'user_id' => $user->user_id,
            'ref' => UrlCipher::ref($user->user_id),
            'expires_at' => now()->addSeconds(self::PENDING_TTL)->timestamp,
        ]);

        return redirect()->route('login.logging-in', [
            'logging_in_user_id' => UrlCipher::ref($user->user_id),
        ]);
    }

    /**
     * Render the interstitial shown between submitting credentials and being
     * signed in.
     *
     * This route deliberately performs no authentication: a GET can be
     * prefetched by a browser or requested by a crawler, so a page load must
     * never log anybody in. It only confirms the pending login still exists so
     * a stale or forged link shows the expiry message instead of a spinner
     * that can never complete.
     */
    public function loggingIn(Request $request, string $logging_in_user_id): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectToDashboard($request->user());
        }

        if (! $this->pendingLoginIsValid($request, $logging_in_user_id)) {
            return redirect()->route('login')->withErrors([
                'user_id' => 'Your login attempt expired. Please sign in again.',
            ]);
        }

        return view('auth.logging-in', [
            'reference' => $logging_in_user_id,
        ]);
    }

    /**
     * Consume the pending login and perform the actual sign-in.
     *
     * Everything that must happen only on a genuine sign-in lives here:
     * Auth::login, session regeneration, clearing the throttle counter, and
     * the audit entry. The pending payload is single-use and forgotten on every
     * outcome, successful or not, so a replayed POST cannot sign anyone in.
     */
    public function completeLogin(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.pending');
        $request->session()->forget('login.pending');

        if (! is_array($pending) || ! isset($pending['user_id'], $pending['expires_at'])) {
            return redirect()->route('login')->withErrors([
                'user_id' => 'Your login attempt expired. Please sign in again.',
            ]);
        }

        if ((int) $pending['expires_at'] < now()->timestamp) {
            return redirect()->route('login')->withErrors([
                'user_id' => 'Your login attempt expired. Please sign in again.',
            ]);
        }

        $user = User::query()->where('user_id', $pending['user_id'])->first();

        if (! $user || $user->status !== 'active') {
            return redirect()->route('login')->withErrors([
                'user_id' => 'The supplied credentials are invalid.',
            ]);
        }

        // Authenticated only now, at the end of the chain.
        LoginAttemptGuard::clearAttempts($request);
        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::record([
            'entity' => AuditLog::ENTITY_USER,
            'recordId' => $user->user_id,
            'action' => 'login',
            'notes' => trim($user->firstName.' '.$user->lastName).' signed in to the portal.',
            'actorId' => $user->user_id,
        ]);

        if ($user->mustChangePassword) {
            return redirect()->route('auth.password.change');
        }

        return $this->redirectToDashboard($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::record([
                'entity' => AuditLog::ENTITY_USER,
                'recordId' => $user->user_id,
                'action' => 'logout',
                'notes' => trim($user->firstName.' '.$user->lastName).' signed out of the portal.',
                'actorId' => $user->user_id,
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Determine role from user_id prefix (shared with the login throttle so
     * throttling keys match the account being signed in to).
     */
    private function determineRoleFromUserId(string $userId): string
    {
        return LoginAttemptGuard::roleForUserId($userId);
    }

    /**
     * Confirm the loading page is being opened for the login that is genuinely
     * in progress: same account, same ref, not yet expired.
     */
    private function pendingLoginIsValid(Request $request, string $reference): bool
    {
        $pending = $request->session()->get('login.pending');

        if (! is_array($pending) || ! isset($pending['user_id'], $pending['ref'], $pending['expires_at'])) {
            return false;
        }

        if ((int) $pending['expires_at'] < now()->timestamp) {
            $request->session()->forget('login.pending');

            return false;
        }

        return UrlCipher::resolve($reference) === (string) $pending['user_id'];
    }

    private function redirectToDashboard(?User $user): RedirectResponse
    {
        $redirects = [
            'student' => '/student/dashboard',
            'teacher' => '/teacher/dashboard',
            'admin' => '/admin/dashboard',
            'parent' => '/parent/dashboard',
            'guest' => '/guest/announcements',
        ];

        return redirect($redirects[$user?->role] ?? '/');
    }
}
