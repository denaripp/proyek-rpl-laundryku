'use client';

import React, { useState, Suspense } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { Store, Lock, Mail, AlertCircle, ArrowRight, ArrowLeft, KeyRound } from 'lucide-react';
import { OTPInput } from '@/components/ui/OTPInput';

function MitraLoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const redirectTarget = searchParams.get('redirect') || '/store';

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [is2faStep, setIs2faStep] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleLoginSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password) {
      setErrorMessage('Silakan isi alamat email dan kata sandi.');
      return;
    }

    setLoading(true);
    setErrorMessage(null);

    try {
      const res = await fetch('/api/auth/mitra/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email: email.trim(), password }),
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.success) {
        const msg = json.message || json.error?.message || (res.status === 422 ? 'Kontak atau password salah.' : 'Login mitra gagal.');
        setErrorMessage(msg);
        return;
      }

      if (json.requires_2fa) {
        setIs2faStep(true);
        setPassword('');
        return;
      }

      // Login success without 2FA
      if (typeof window !== 'undefined' && json.data?.user) {
        localStorage.setItem('laundryku_mitra_user', JSON.stringify(json.data.user));
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: json.data.user }));
      }

      // Check if store is pending approval
      const storeStatus = json.data?.store?.status;
      if (storeStatus === 'submitted' || storeStatus === 'pending') {
        router.push('/mitra/status');
      } else {
        router.push(redirectTarget);
      }
    } catch {
      setErrorMessage('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
    } finally {
      setLoading(false);
    }
  };

  const handle2faSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!twoFactorCode || twoFactorCode.length !== 6) {
      setErrorMessage('Masukkan 6 digit kode autentikasi 2FA Google Authenticator.');
      return;
    }

    setLoading(true);
    setErrorMessage(null);

    try {
      const res = await fetch('/api/auth/mitra/2fa', {
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
        localStorage.setItem('laundryku_mitra_user', JSON.stringify(json.data.user));
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: json.data.user }));
      }

      router.push(redirectTarget);
    } catch {
      setErrorMessage('Tidak dapat memverifikasi kode 2FA. Silakan coba lagi.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-navy-50/60 py-10 px-4 sm:px-6 lg:px-8 relative overflow-hidden flex flex-col justify-between font-sans">
      {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
      <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
        {/* Bubble 1 (Top Left) */}
        <div className="absolute top-12 left-10 w-24 h-24 rounded-full border border-sky-300/40 bg-sky-200/20 backdrop-blur-[1px] shadow-inner animate-gentle-float">
          <div className="w-2.5 h-2.5 rounded-full bg-white/70 ml-3.5 mt-3.5" />
        </div>

        {/* Bubble 2 (Top Right) */}
        <div className="absolute top-24 right-12 w-32 h-32 rounded-full border border-sky-300/30 bg-sky-300/15 animate-gentle-float-slow">
          <div className="w-3.5 h-3.5 rounded-full bg-white/60 ml-4 mt-4" />
        </div>

        {/* Bubble 3 (Center Right - Hidden on mobile) */}
        <div className="hidden sm:block absolute top-1/2 right-16 w-20 h-20 rounded-full border border-sky-200/40 bg-sky-200/20 animate-gentle-float">
          <div className="w-2 h-2 rounded-full bg-white/60 ml-3 mt-3" />
        </div>

        {/* Bubble 4 (Bottom Left - Hidden on mobile) */}
        <div className="hidden sm:block absolute bottom-28 left-16 w-16 h-16 rounded-full border border-sky-300/25 bg-sky-200/15 animate-gentle-float-slow">
          <div className="w-1.5 h-1.5 rounded-full bg-white/50 ml-2 mt-2" />
        </div>
      </div>

      {/* Bottom Decorative Wave Accent (aria-hidden="true") */}
      <div className="absolute bottom-0 left-0 right-0 pointer-events-none overflow-hidden leading-none z-0" aria-hidden="true">
        <svg
          viewBox="0 0 1440 80"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          className="w-full h-12 sm:h-16 text-navy-100/60 preserve-3d"
        >
          <path
            d="M0 35C240 70 480 15 720 40C960 65 1200 20 1440 35V80H0V35Z"
            fill="currentColor"
          />
        </svg>
      </div>

      {/* Top Back Navigation Link */}
      <div className="max-w-md mx-auto w-full mb-4 z-10 relative">
        <Link
          href="/"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-navy-700 transition-colors py-1"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>Kembali ke beranda</span>
        </Link>
      </div>

      <div className="max-w-md mx-auto w-full z-10 relative space-y-6 my-auto">
        {/* Page Title & Subtitle */}
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-navy-700 text-white shadow-xs border border-navy-600/40 mb-1">
            <Store className="w-6 h-6" strokeWidth={1.75} />
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-navy-900">
            Portal Mitra LaundryKu
          </h1>
          <p className="text-xs sm:text-sm text-slate-600 max-w-xs mx-auto leading-relaxed">
            Kelola pesanan, kurir, dan usaha laundry Anda.
          </p>
        </div>

        {/* Main Card Container */}
        <div className="bg-white rounded-3xl border border-navy-200/90 shadow-sm p-6 sm:p-10 space-y-6">
          {errorMessage && (
            <div className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-start gap-2.5 leading-relaxed">
              <AlertCircle className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" strokeWidth={1.75} />
              <span>{errorMessage}</span>
            </div>
          )}

          {!is2faStep ? (
            <form className="space-y-4" onSubmit={handleLoginSubmit}>
              <div>
                <label htmlFor="email" className="block text-xs font-bold text-navy-900 mb-1.5">
                  Email Akun Mitra
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <Mail className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="nama@tokolaundry.com"
                    className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                  />
                </div>
              </div>

              <div>
                <label htmlFor="password" className="block text-xs font-bold text-navy-900 mb-1.5">
                  Kata Sandi
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <Lock className="w-4 h-4" strokeWidth={1.75} />
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
                    className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                  />
                </div>
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={loading}
                  className="w-full flex justify-center items-center gap-2 py-3 px-5 rounded-xl text-xs font-bold text-white bg-navy-700 hover:bg-navy-800 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-navy-700 disabled:opacity-50 transition-colors shadow-xs cursor-pointer min-h-[46px]"
                >
                  {loading ? (
                    <span>Memverifikasi...</span>
                  ) : (
                    <>
                      <span>Masuk</span>
                      <ArrowRight className="w-4 h-4 ml-0.5" />
                    </>
                  )}
                </button>
              </div>

              <div className="pt-2 text-center">
                <p className="text-xs text-slate-600">
                  Belum punya akun mitra?{' '}
                  <Link
                    href="/mitra/register"
                    className="font-semibold text-navy-700 hover:text-navy-800 underline transition-colors"
                  >
                    Daftar di sini
                  </Link>
                </p>
              </div>
            </form>
          ) : (
            <form className="space-y-5" onSubmit={handle2faSubmit}>
              <div className="text-center space-y-1">
                <div className="w-12 h-12 rounded-2xl bg-navy-50 text-navy-700 border border-navy-200/60 flex items-center justify-center mx-auto mb-2 shadow-2xs">
                  <KeyRound className="w-5 h-5" strokeWidth={1.75} />
                </div>
                <h3 className="text-sm font-bold text-navy-900">Verifikasi Dua Langkah (2FA)</h3>
                <p className="text-xs text-slate-600 max-w-xs mx-auto leading-relaxed">
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
                className="w-full flex justify-center items-center gap-2 py-3 px-5 rounded-xl text-xs font-bold text-white bg-navy-700 hover:bg-navy-800 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-navy-700 disabled:opacity-50 transition-colors shadow-xs cursor-pointer min-h-[46px]"
              >
                {loading ? <span>Memverifikasi 2FA...</span> : <span>Konfirmasi &amp; Masuk</span>}
              </button>

              <div className="text-center">
                <button
                  type="button"
                  onClick={() => {
                    setIs2faStep(false);
                    setTwoFactorCode('');
                    setErrorMessage(null);
                  }}
                  className="text-xs text-slate-500 hover:text-navy-900 font-semibold p-1 transition-colors cursor-pointer"
                >
                  ← Kembali ke formulir login
                </button>
              </div>
            </form>
          )}

          <div className="pt-2 text-center border-t border-slate-100">
            <Link
              href="/auth"
              className="inline-flex items-center gap-1.5 text-xs text-slate-600 hover:text-navy-700 transition-colors"
            >
              <span>Bukan mitra?</span>
              <span className="text-navy-700 font-semibold underline">Masuk sebagai pelanggan</span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function MitraLoginPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-navy-50 flex items-center justify-center p-4">
          <div className="w-8 h-8 border-3 border-navy-700 border-t-transparent rounded-full animate-spin" />
        </div>
      }
    >
      <MitraLoginForm />
    </Suspense>
  );
}
