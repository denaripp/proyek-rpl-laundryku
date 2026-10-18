'use client';

import React, { useState, Suspense } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { Store, Lock, Mail, AlertCircle, ArrowRight, ShieldCheck, KeyRound } from 'lucide-react';
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
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <div className="flex justify-center">
          <div className="w-12 h-12 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400 shadow-md">
            <Store className="w-6 h-6" strokeWidth={1.5} />
          </div>
        </div>
        <h1 className="mt-4 text-center text-2xl font-bold tracking-tight text-white">
          Portal Mitra LaundryKu
        </h1>
        <p className="mt-1.5 text-center text-xs text-slate-400">
          Kelola gerai, staf operasional, dan pesanan laundry Anda
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-slate-800/90 py-8 px-6 border border-slate-700/80 rounded-2xl shadow-xl sm:px-10">
          {errorMessage && (
            <div className="mb-5 p-3.5 rounded-lg bg-rose-950/60 border border-rose-800/60 flex items-start gap-2.5 text-rose-300 text-xs leading-relaxed">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-400 mt-0.5" strokeWidth={1.5} />
              <span>{errorMessage}</span>
            </div>
          )}

          {!is2faStep ? (
            <form className="space-y-4" onSubmit={handleLoginSubmit}>
              <div>
                <label htmlFor="email" className="block text-xs font-medium text-slate-300">
                  Email Akun Mitra
                </label>
                <div className="mt-1.5 relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <Mail className="w-4 h-4" strokeWidth={1.5} />
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
                    className="block w-full pl-9 pr-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  />
                </div>
              </div>

              <div>
                <div className="flex items-center justify-between">
                  <label htmlFor="password" className="block text-xs font-medium text-slate-300">
                    Kata Sandi
                  </label>
                </div>
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
                    className="block w-full pl-9 pr-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  />
                </div>
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={loading}
                  className="w-full flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer shadow-md"
                >
                  {loading ? (
                    <span>Memverifikasi...</span>
                  ) : (
                    <>
                      <span>Masuk ke Dashboard Toko</span>
                      <ArrowRight className="w-4 h-4 text-blue-200" strokeWidth={1.5} />
                    </>
                  )}
                </button>
              </div>

              <div className="pt-3 text-center">
                <p className="text-xs text-slate-400">
                  Belum memiliki akun toko?{' '}
                  <Link href="/mitra/register" className="font-semibold text-blue-400 hover:text-blue-300 underline underline-offset-2">
                    Daftar sebagai Mitra Baru
                  </Link>
                </p>
              </div>
            </form>
          ) : (
            <form className="space-y-5" onSubmit={handle2faSubmit}>
              <div className="text-center">
                <div className="w-10 h-10 rounded-full bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-blue-400 mx-auto mb-2">
                  <KeyRound className="w-5 h-5" strokeWidth={1.5} />
                </div>
                <h3 className="text-sm font-bold text-white">Verifikasi Dua Langkah (2FA)</h3>
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
                className="w-full flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer shadow-md"
              >
                {loading ? <span>Memverifikasi 2FA...</span> : <span>Konfirmasi & Masuk</span>}
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

          <div className="mt-6 pt-4 border-t border-slate-700/60 flex items-center justify-center gap-1.5 text-center">
            <ShieldCheck className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.5} />
            <span className="text-[11px] text-slate-500">
              Autentikasi terisolasi multi-tenant dengan proteksi httpOnly session.
            </span>
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
        <div className="min-h-screen bg-slate-900 flex items-center justify-center">
          <div className="w-7 h-7 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
        </div>
      }
    >
      <MitraLoginForm />
    </Suspense>
  );
}
