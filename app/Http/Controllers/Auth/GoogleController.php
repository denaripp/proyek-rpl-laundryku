<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;

class GoogleController extends Controller
{
    public function redirect(Request $request)
    {
        // SECURITY (Tahap 23+24): store ?next= in SESSION (not in OAuth
        // `state` param — that's reserved for Socialite's CSRF token and
        // overriding it breaks the callback validation silently).
        // Strict validation: relative path only, no protocol-relative,
        // no backslash trick.
        $next = $request->input('next');
        if (is_string($next)
            && isset($next[0]) && $next[0] === '/'
            && (!isset($next[1]) || ($next[1] !== '/' && $next[1] !== '\\'))
            && preg_match('/^[\/a-zA-Z0-9_\-\.\?\#\=\&\;\%\+\,\@]*$/', $next) === 1
        ) {
            $request->session()->put('oauth_next', $next);
        } else {
            // Clear any stale value to avoid leaking between logins.
            $request->session()->forget('oauth_next');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['email' => 'Gagal login dengan Google.']);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Existing user — update google_id if not set
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        } else {
            // Create new user
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Hash::make(Str::random(32)),
                'is_active' => true,
            ]);

            // Assign default customer role for all new users
            $customerRole = \App\Models\Role::where('name', 'customer')->first();
            if ($customerRole) {
                $user->roles()->attach($customerRole->id);
            }

            // Welcome notification
            \App\Notifications\NotificationTrigger::registered($user);
        }

        // Ensure user has store_owner role if they already own a store
        if ($user->ownedStores()->exists() && !$user->hasRole('store_owner')) {
            $storeOwnerRole = \App\Models\Role::where('name', 'store_owner')->first();
            if ($storeOwnerRole) {
                $user->roles()->attach($storeOwnerRole->id, ['store_id' => $user->ownedStores()->first()->id]);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Check 2FA
        if ($user->two_factor_enabled) {
            $request->session()->put('pending_2fa', true);
            return redirect()->route('2fa.verify');
        }

        $user->update(['last_login_at' => now()]);

        // SECURITY (Tahap 23+24): read ?next= from session
        $next = $request->session()->pull('oauth_next', '/dashboard');

        return redirect($next);
    }
}