<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Http\Controllers\Controller;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function showVerifyForm(Request $request)
    {
        if (!$request->session()->has('pending_2fa')) {
            return redirect('/dashboard');
        }
        return view('auth.2fa-verify');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        if (!$user || !$user->two_factor_enabled) {
            return redirect('/login');
        }

        $valid = $this->google2fa->verifyKey(
            $user->google2fa_secret,
            $request->code
        );

        if (!$valid) {
            return back()->withErrors(['code' => 'Kode verifikasi tidak valid.']);
        }

        $request->session()->forget('pending_2fa');
        $user->update(['last_login_at' => now()]);

        return redirect()->intended('/dashboard');
    }

    public function showEnableForm()
    {
        $user = Auth::user();
        if ($user->two_factor_enabled) {
            return redirect('/dashboard')->with('error', '2FA sudah aktif.');
        }

        $secret = $this->google2fa->generateSecretKey();
        $user->update(['google2fa_secret' => $secret]);

        $qrCode = $this->generateQrCode($user->email, $secret);

        return view('auth.2fa-enable', [
            'secret' => $secret,
            'qrCode' => $qrCode,
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        if (!$user->google2fa_secret) {
            return back()->withErrors(['code' => 'Secret tidak ditemukan.']);
        }

        $valid = $this->google2fa->verifyKey(
            $user->google2fa_secret,
            $request->code
        );

        if (!$valid) {
            return back()->withErrors(['code' => 'Kode verifikasi tidak valid.']);
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        return redirect('/dashboard')->with('success', '2FA berhasil diaktifkan.');
    }

    public function disable(Request $request)
    {
        $user = Auth::user();
        if (!$user->two_factor_enabled) {
            return redirect('/dashboard')->with('error', '2FA belum aktif.');
        }

        // For security, require password confirmation or 2FA code before disabling
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user->update([
            'google2fa_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect('/dashboard')->with('success', '2FA berhasil dinonaktifkan.');
    }

    protected function generateQrCode($email, $secret)
    {
        $company = config('app.name');
        $qrContent = $this->google2fa->getQRCodeUrl($company, $email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new ImagickImageBackEnd()
        );

        $writer = new Writer($renderer);
        return 'data:image/png;base64,' . base64_encode($writer->writeString($qrContent));
    }
}