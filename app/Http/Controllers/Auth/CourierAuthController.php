<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CourierAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('kurir')->check()) {
            return redirect()->route('kurir.dashboard');
        }
        if (Auth::guard('web')->check()) {
            return redirect()->route('customer.dashboard');
        }
        if (Auth::guard('mitra')->check()) {
            return redirect()->route('mitra.dashboard');
        }
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.kurir-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::guard('kurir')->attempt($credentials, $request->boolean('remember'))) {
            AuditLog::create([
                'actor_id' => null,
                'actor_role' => 'courier',
                'action' => 'COURIER_LOGIN_FAILED',
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

        $user = Auth::guard('kurir')->user();

        if (!$user->is_active) {
            Auth::guard('kurir')->logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Kurir Anda sedang dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => 'courier',
            'action' => 'COURIER_LOGIN_SUCCESS',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('kurir.dashboard'));
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('kurir')->user();
        if ($user) {
            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => 'courier',
                'action' => 'COURIER_LOGOUT',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('kurir')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('kurir.login');
    }
}
