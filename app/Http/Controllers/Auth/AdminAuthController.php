<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        if (Auth::guard('web')->check()) {
            return redirect()->route('customer.dashboard');
        }
        if (Auth::guard('mitra')->check()) {
            return redirect()->route('mitra.dashboard');
        }
        if (Auth::guard('kurir')->check()) {
            return redirect()->route('kurir.dashboard');
        }

        return view('auth.admin-login');
    }

    public function login(Request $request)
    {
        // Optional IP allowlist via settings
        $ipAllowlist = Setting::get('ADMIN_IP_ALLOWLIST', null);
        if ($ipAllowlist) {
            $allowed = array_filter(array_map('trim', explode(',', $ipAllowlist)));
            if (!empty($allowed) && !in_array($request->ip(), $allowed)) {
                AuditLog::create([
                    'actor_id' => null,
                    'actor_role' => 'super_admin',
                    'action' => 'ADMIN_LOGIN_IP_BLOCKED',
                    'resource_type' => 'auth',
                    'resource_id' => null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                abort(403, 'Akses dari IP Anda tidak diizinkan.');
            }
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = AdminUser::where('email', $request->input('email'))->first();

        if (!$admin || !Hash::check($request->input('password'), $admin->password)) {
            AuditLog::create([
                'actor_id' => null,
                'actor_role' => 'super_admin',
                'action' => 'ADMIN_LOGIN_FAILED',
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

        if (!$admin->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Akun Administrator ini dinonaktifkan.',
            ]);
        }

        // Store pre-authenticated state for mandatory 2FA
        $request->session()->put('admin_auth_user_id', $admin->id);
        $request->session()->put('admin_remember', $request->boolean('remember'));

        return redirect()->route('admin.2fa');
    }

    public function show2faForm(Request $request)
    {
        if (!$request->session()->has('admin_auth_user_id')) {
            return redirect()->route('admin.login');
        }

        return view('auth.admin-2fa');
    }

    public function verify2fa(Request $request)
    {
        $adminId = $request->session()->get('admin_auth_user_id');
        if (!$adminId) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'one_time_password' => 'required|string|size:6',
        ]);

        $admin = AdminUser::find($adminId);
        if (!$admin) {
            return redirect()->route('admin.login');
        }

        $google2fa = new Google2FA();
        $secret = $admin->google2fa_secret;
        $otp = $request->input('one_time_password');

        $isValid = false;
        if (!empty($secret)) {
            $isValid = $google2fa->verifyKey($secret, $otp, 1);
        }

        // Also allow local test bypass if in local testing mode
        if (app()->environment('testing') && $otp === '123456') {
            $isValid = true;
        }

        if (!$isValid) {
            AuditLog::create([
                'actor_id' => $admin->id,
                'actor_role' => 'super_admin',
                'action' => 'ADMIN_2FA_FAILED',
                'resource_type' => 'auth',
                'resource_id' => $admin->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'one_time_password' => 'Kode autentikasi 2FA tidak valid atau sudah kadaluwarsa.',
            ]);
        }

        $remember = $request->session()->get('admin_remember', false);
        $request->session()->forget(['admin_auth_user_id', 'admin_remember']);

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();
        $admin->update(['last_login_at' => now()]);

        AuditLog::create([
            'actor_id' => $admin->id,
            'actor_role' => 'super_admin',
            'action' => 'ADMIN_LOGIN_SUCCESS',
            'resource_type' => 'user',
            'resource_id' => $admin->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('admin')->user();
        if ($user) {
            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => 'super_admin',
                'action' => 'ADMIN_LOGOUT',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
