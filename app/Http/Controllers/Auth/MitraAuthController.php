<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MitraUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MitraAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('mitra')->check()) {
            return redirect()->route('mitra.dashboard');
        }
        if (Auth::guard('web')->check()) {
            return redirect()->route('customer.dashboard');
        }
        if (Auth::guard('kurir')->check()) {
            return redirect()->route('kurir.dashboard');
        }
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.mitra-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::guard('mitra')->attempt($credentials, $request->boolean('remember'))) {
            AuditLog::create([
                'actor_id' => null,
                'actor_role' => 'mitra',
                'action' => 'MITRA_LOGIN_FAILED',
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

        $user = Auth::guard('mitra')->user();

        if (!$user->is_active) {
            Auth::guard('mitra')->logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Mitra Anda sedang dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();

        if ($user->two_factor_enabled) {
            $request->session()->put('pending_2fa', true);
            return redirect()->route('2fa.verify');
        }

        $user->update(['last_login_at' => now()]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => 'store_admin',
            'action' => 'MITRA_LOGIN_SUCCESS',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('mitra.dashboard'));
    }

    public function showRegisterForm()
    {
        if (Auth::guard('mitra')->check()) {
            return redirect()->route('mitra.dashboard');
        }

        return view('auth.mitra-register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                // Unique per (email, role)
                function ($attribute, $value, $fail) {
                    if (User::where('email', $value)->where('role', User::ROLE_STORE_ADMIN)->exists()) {
                        $fail('Email sudah terdaftar sebagai mitra toko.');
                    }
                },
            ],
            'phone' => 'required|string|max:32',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = MitraUser::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => User::ROLE_STORE_ADMIN,
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        $storeRole = Role::whereIn('name', ['store_owner', 'store_admin'])->first();
        if ($storeRole) {
            $user->roles()->attach($storeRole->id, ['assigned_at' => now()]);
        }

        Auth::guard('mitra')->login($user);
        $request->session()->regenerate();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => 'store_admin',
            'action' => 'MITRA_REGISTER_SUCCESS',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('mitra.dashboard');
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('mitra')->user();
        if ($user) {
            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => 'store_admin',
                'action' => 'MITRA_LOGOUT',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('mitra')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('mitra.login');
    }
}
