'use client';

import React, { useState, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Shield, Lock, Mail, AlertCircle, ArrowRight, KeyRound } from 'lucide-react';
import { OTPInput } from '@/components/ui/OTPInput';
import { getSafeRedirectPath } from '@/lib/auth';

function AdminLoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const rawRedirect = searchParams.get('redirect');
  const safeRedirect = getSafeRedirectPath(rawRedirect, '/admin');
  const redirectTarget = safeRedirect && safeRedirect !== '/admin/login' ? safeRedirect : '/admin';

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [is2faStep, setIs2faStep] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleStep1Submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password) {
      setErrorMessage('Silakan isi email/username dan kata sandi.');
      return;
    }

    setLoading(true);
    setErrorMessage(null);

    try {
      const res = await fetch('/api/auth/admin/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email: email.trim(), password }),
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.success) {
        const msg = json.message || json.error?.message || (res.status === 422 ? 'Kontak atau password salah.' : 'Login admin gagal.');
        setErrorMessage(msg);
        return;
      }

      if (json.requires_2fa) {
        setIs2faStep(true);
        setPassword('');
        return;
      }

      // If no 2FA required (fallback)
      if (typeof window !== 'undefined' && json.data?.user) {
        localStorage.setItem('laundryku_admin_user', JSON.stringify(json.data.user));
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: json.data.user }));
      }
      router.push(redirectTarget);
    } catch {
      setErrorMessage('Tidak dapat terhubung ke server backend.');
    } finally {
      setLoading(false);
    }
  };

  const handleStep2Submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!twoFactorCode || twoFactorCode.length !== 6) {
      setErrorMessage('Masukkan 6 digit kode autentikasi 2FA Google Authenticator.');
      return;
    }

    setLoading(true);
    setErrorMessage(null);

    try {
      const res = await fetch('/api/auth/admin/2fa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ code: twoFactorCode }),
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.success) {
        const msg = json.message || 'Kode 2FA tidak valid atau telah kedaluwarsa.';
        setErrorMessage(msg);
        return;
      }

      if (typeof window !== 'undefined' && json.data?.user) {
        localStorage.setItem('laundryku_admin_user', JSON.stringify(json.data.user));
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: json.data.user }));
      }

      router.push(redirectTarget);
    } catch {
      setErrorMessage('Gagal memverifikasi kode 2FA. Silakan coba lagi.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="flex justify-center">
          <div className="w-12 h-12 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-100 shadow-xs">
            <Shield className="w-6 h-6 text-slate-300" strokeWidth={1.5} />
          </div>
        </div>
        <h1 className="mt-4 text-center text-xl font-semibold tracking-tight text-white">
          LaundryKu Portal
        </h1>
        <p className="mt-1 text-center text-xs text-slate-400">
          Autentikasi Khusus Super Administrator
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-slate-900/90 py-8 px-6 border border-slate-800 sm:rounded-lg sm:px-10">
          {errorMessage && (
            <div className="mb-5 p-3 rounded-md bg-rose-950/40 border border-rose-800/50 flex items-start gap-2.5 text-rose-300 text-xs leading-relaxed">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-400 mt-0.5" strokeWidth={1.5} />
              <span>{errorMessage}</span>
            </div>
          )}

          {!is2faStep ? (
            <form className="space-y-4" onSubmit={handleStep1Submit}>
              <div>
                <label htmlFor="email" className="block text-xs font-medium text-slate-300">
                  Email / Username Admin
                </label>
                <div className="mt-1.5 relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <Mail className="w-4 h-4" strokeWidth={1.5} />
                  </div>
                  <input
                    id="email"
                    name="email"
                    type="text"
                    autoComplete="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="admin@laundryku.test"
                    className="block w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-md text-slate-200 placeholder-slate-500 text-sm focus:outline-hidden focus:ring-1 focus:ring-slate-400 focus:border-slate-400"
                  />
                </div>
              </div>

              <div>
                <label htmlFor="password" className="block text-xs font-medium text-slate-300">
                  Kata Sandi
                </label>
                <div className="mt-1.5 relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <Lock className="w-4 h-4" strokeWidth={1.5} />
                  </div>
                  <input
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="••••••••"
                    className="block w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-md text-slate-200 placeholder-slate-500 text-sm focus:outline-hidden focus:ring-1 focus:ring-slate-400 focus:border-slate-400"
                  />
                </div>
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={loading}
                  className="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-transparent rounded-md text-xs font-medium text-white bg-slate-800 hover:bg-slate-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                >
                  {loading ? (
                    <span>Memverifikasi Kredensial...</span>
                  ) : (
                    <>
                      <span>Lanjut ke Verifikasi 2FA</span>
                      <ArrowRight className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.5} />
                    </>
                  )}
                </button>
              </div>
            </form>
          ) : (
            <form className="space-y-5" onSubmit={handleStep2Submit}>
              <div className="text-center">
                <div className="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 mx-auto mb-2">
                  <KeyRound className="w-5 h-5 text-slate-300" strokeWidth={1.5} />
                </div>
                <h3 className="text-sm font-semibold text-white">Verifikasi Dua Langkah (2FA)</h3>
                <p className="text-xs text-slate-400 mt-1">
                  Masukkan 6 digit kode dari aplikasi Google Authenticator Anda
                </p>
              </div>

              <div className="flex justify-center py-2">
                <OTPInput
                  value={twoFactorCode}
                  onChange={setTwoFactorCode}
                  onComplete={(val) => setTwoFactorCode(val)}
                  autoFocus
                />
              </div>


              <button
                type="submit"
                disabled={loading || twoFactorCode.length !== 6}
                className="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-transparent rounded-md text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
              >
                {loading ? <span>Memverifikasi 2FA...</span> : <span>Konfirmasi & Masuk Konsol</span>}
              </button>

              <div className="text-center">
                <button
                  type="button"
                  onClick={() => {
                    setIs2faStep(false);
                    setTwoFactorCode('');
                    setErrorMessage(null);
                  }}
                  className="text-xs text-slate-400 hover:text-slate-300 underline underline-offset-2 cursor-pointer"
                >
                  Kembali ke formulir login
                </button>
              </div>
            </form>
          )}

          <div className="mt-6 pt-4 border-t border-slate-800/80 text-center">
            <span className="text-[11px] text-slate-500">
              Sistem terisolasi dengan rate limiting, 2FA wajib, dan httpOnly session.
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function AdminLoginPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-slate-950 flex items-center justify-center">
          <div className="w-6 h-6 border-2 border-slate-500 border-t-transparent rounded-full animate-spin" />
        </div>
      }
    >
      <AdminLoginForm />
    </Suspense>
  );
}
