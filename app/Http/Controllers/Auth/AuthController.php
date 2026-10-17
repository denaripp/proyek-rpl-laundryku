<?php

namespace App\Http\Controllers\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\NotificationTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(\App\Http\Requests\Auth\LoginRequest $request)
    {
        // SECURITY (Tahap 19): rate limiting now applied via throttle:login
        // middleware on POST /login (AppServiceProvider::configureRateLimiters).
        // 5 attempts/minute per email+IP. Manual check removed here to
        // avoid double-counting.

        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan.',
            ]);
        }

        // Regenerate session to prevent fixation
        $request->session()->regenerate();

        // Check if 2FA is required
        if ($user->two_factor_enabled) {
            $request->session()->put('pending_2fa', true);
            return redirect()->route('2fa.verify');
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Ensure user has store_owner role if they own a store
        if ($user->ownedStores()->exists() && !$user->hasRole('store_owner')) {
            $storeOwnerRole = \App\Models\Role::where('name', 'store_owner')->first();
            if ($storeOwnerRole) {
                $user->roles()->attach($storeOwnerRole->id, ['store_id' => $user->ownedStores()->first()->id]);
            }
        }

        // SECURITY (Tahap 23+24): honour ?next= param for redirect-back
        // after login. Strict validation against open-redirect attacks:
        //  - Must start with EXACTLY one "/"
        //  - Second char must NOT be "/" (//evil.com — protocol-relative
        //    URL) or "\" (/\evil.com — some browsers normalise backslash
        //    to slash, treating it as protocol-relative)
        //  - Whitelist of allowed path characters only
        $next = $request->input('next');
        if (is_string($next)
            && isset($next[0]) && $next[0] === '/'
            && (!isset($next[1]) || ($next[1] !== '/' && $next[1] !== '\\'))
            && preg_match('/^[\/a-zA-Z0-9_\-\.\?\#\=\&\;\%\+\,\@]*$/', $next) === 1
        ) {
            return redirect($next);
        }

        return redirect()->intended('/dashboard');
    }

    public function showRegisterForm()
    {
        // SECURITY: manual register is admin/owner-only (PRD §7 — public
        // customers must use Google OAuth). If the visitor is not logged-in
        // (or lacks manage_internal_users permission), redirect to Google.
        $user = Auth::user();
        if (!$user || !$user->hasPermissionTo('manage_internal_users')) {
            return redirect()->route('google.redirect');
        }

        return view('auth.register');
    }

    public function register(\App\Http\Requests\Auth\RegisterRequest $request)
    {
        // SECURITY: manual register is admin/owner-only. Guest/public
        // customers cannot self-register — they MUST use Google OAuth.
        $user = Auth::user();
        abort_unless(
            $user && $user->hasPermissionTo('manage_internal_users'),
            403,
            'Manual registration is restricted to platform admin/owner. Public customers must sign up with Google.'
        );

        $validated = $request->validated();

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        // Tahap 15: welcome notification (queued side effect)
        NotificationTrigger::registered($newUser);

        // SECURITY: audit trail for internal user provisioning.
        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'internal_user_provisioned',
            'resource_type' => 'users',
            'resource_id' => $newUser->id,
            'after_value' => [
                'email' => $newUser->email,
                'name' => $newUser->name,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        // Do NOT auto-login the new internal account. The admin who
        // provisioned it is already logged in; the new user must log in
        // themselves (with their known credentials) for an audit trail.
        return redirect()->route('login')->with('status', 'User ' . $newUser->email . ' created. They can now log in.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    // ===== COMPLETE PROFILE =====

    public function showCompleteProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }
        return view('auth.complete-profile', compact('user'));
    }

    public function updateCompleteProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $request->validate([
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        $user->update([
            'phone' => $request->phone,
            // address is not in users table; if you have a separate addresses table, handle it there.
            // For now we skip address or you can store in a user meta.
        ]);

        return redirect()->route('home')->with('status', 'Profil berhasil diperbarui!');
    }
}