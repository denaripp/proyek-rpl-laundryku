<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\CustomerUser;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NotificationTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('customer.dashboard');
        }
        if (Auth::guard('mitra')->check()) {
            return redirect()->route('mitra.dashboard');
        }
        if (Auth::guard('kurir')->check()) {
            return redirect()->route('kurir.dashboard');
        }
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            AuditLog::create([
                'actor_id' => null,
                'actor_role' => 'customer',
                'action' => 'CUSTOMER_LOGIN_FAILED',
                'resource_type' => 'auth',
                'resource_id' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'after_value' => json_encode(['email' => $request->input('email')]),
            ]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::guard('web')->user();

        if (!$user->is_active) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();

        // 2FA requirement check
        if ($user->two_factor_enabled) {
            $request->session()->put('pending_2fa', true);
            return redirect()->route('2fa.verify');
        }

        $user->update(['last_login_at' => now()]);

        // Auto-sync store owner role if user owns a store
        if ($user->ownedStores()->exists() && !$user->hasRole('store_owner')) {
            $storeOwnerRole = Role::where('name', 'store_owner')->first();
            if ($storeOwnerRole) {
                $user->roles()->attach($storeOwnerRole->id, ['store_id' => $user->ownedStores()->first()->id]);
            }
        }

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role ?? 'customer',
            'action' => 'LOGIN_SUCCESS',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Validate safe ?next= redirect parameter
        $next = $request->input('next') ?? $request->query('next');
        if (is_string($next)
            && isset($next[0]) && $next[0] === '/'
            && (!isset($next[1]) || ($next[1] !== '/' && $next[1] !== '\\'))
            && preg_match('/^[\/a-zA-Z0-9_\-\.\?\#\=\&\;\%\+\,\@]*$/', $next) === 1
        ) {
            return redirect($next);
        }

        // Role-based destination for /login fallback
        if ($user->hasRole('platform_admin') || $user->hasRole('platform_owner') || $user->hasPermissionTo('manage_platform')) {
            return redirect()->intended('/dashboard');
        }
        if ($user->hasRole('store_owner') || $user->ownedStores()->exists()) {
            $store = $user->ownedStores()->first();
            if ($store && $store->isApproved()) {
                return redirect()->intended(route('store.dashboard'));
            }
            return redirect()->intended(route('store.onboard'));
        }
        if ($user->hasRole('courier')) {
            return redirect()->intended(route('courier.dashboard'));
        }

        return redirect()->intended(route('customer.dashboard'));
    }

    public function showRegisterForm()
    {
        // If logged in as admin with manage_internal_users: show admin provisioning form
        if (Auth::check() && Auth::user()->hasPermissionTo('manage_internal_users')) {
            return view('auth.register');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route('customer.dashboard');
        }
        if (Auth::guard('mitra')->check()) {
            return redirect()->route('mitra.dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        // 1. If authenticated user is attempting manual internal user provisioning
        if (Auth::check()) {
            abort_unless(
                Auth::user()->hasPermissionTo('manage_internal_users'),
                403,
                'Manual registration is restricted to platform admin/owner. Public customers must sign up with Google or customer portal.'
            );

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'nullable|string|max:32',
            ]);

            $newUser = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'phone' => $request->input('phone'),
                'is_active' => true,
            ]);

            NotificationTrigger::registered($newUser);

            AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => 'internal_user_provisioned',
                'resource_type' => 'users',
                'resource_id' => $newUser->id,
                'after_value' => json_encode(['email' => $newUser->email, 'name' => $newUser->name]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('login')->with('status', 'User created. They can now log in.');
        }

        // 2. Security check: Platform Owner / Admin / privileged roles cannot be created publicly
        if (stripos($request->input('name', ''), 'Platform Owner') !== false ||
            stripos($request->input('name', ''), 'Admin') !== false ||
            stripos($request->input('email', ''), 'owner@') !== false) {
            return redirect()->route('login')->withErrors(['email' => 'Role internal ini tidak dapat didaftarkan secara publik.']);
        }

        // 3. Public Customer Registration
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (User::where('email', $value)->where('role', User::ROLE_CUSTOMER)->exists()) {
                        $fail('Email sudah terdaftar sebagai customer.');
                    }
                },
            ],
            'phone' => 'nullable|string|max:32',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = CustomerUser::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => User::ROLE_CUSTOMER,
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole) {
            $user->roles()->attach($customerRole->id, ['assigned_at' => now()]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => 'customer',
            'action' => 'CUSTOMER_REGISTER_SUCCESS',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('customer.dashboard');
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('web')->user() ?? Auth::user();
        if ($user) {
            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => 'customer',
                'action' => 'CUSTOMER_LOGOUT',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('web')->logout();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
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
        ]);

        return redirect()->route('home')->with('status', 'Profil berhasil diperbarui!');
    }
}