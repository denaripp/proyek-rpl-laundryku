'use client';

import React, { useState, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { adminApi, ADMIN_LOGIN_PATH } from '@/lib/admin';
import { getSafeRedirectPath } from '@/lib/auth';
import { Shield, Lock, User, AlertCircle, ArrowRight } from 'lucide-react';

function AdminLoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const rawRedirect = searchParams.get('redirect');
  const redirectTarget = getSafeRedirectPath(rawRedirect, '/admin');

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!username.trim() || !password) {
      setErrorMessage('Silakan isi username dan kata sandi.');
      return;
    }

    setLoading(true);
    setErrorMessage(null);

    try {
      await adminApi.login({
        username: username.trim(),
        password,
      });

      // Avoid redirect loop if redirect parameter points back to /admin/login
      const destination = redirectTarget === ADMIN_LOGIN_PATH ? '/admin' : redirectTarget;
      router.push(destination);
    } catch (err: any) {
      setErrorMessage(err.message || 'Kredensial tidak valid atau akun tidak memiliki hak akses Super Administrator.');
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

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div>
              <label htmlFor="username" className="block text-xs font-medium text-slate-300">
                Username
              </label>
              <div className="mt-1.5 relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                  <User className="w-4 h-4" strokeWidth={1.5} />
                </div>
                <input
                  id="username"
                  name="username"
                  type="text"
                  autoComplete="username"
                  required
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="Masukkan username"
                  className="block w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-md text-slate-200 placeholder-slate-500 text-sm focus:outline-hidden focus:ring-1 focus:ring-slate-400 focus:border-slate-400"
                />
              </div>
            </div>

            <div>
              <label htmlFor="password" className="block text-xs font-medium text-slate-300">
                Password
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
                  <span>Memverifikasi...</span>
                ) : (
                  <>
                    <span>Masuk ke Konsol</span>
                    <ArrowRight className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.5} />
                  </>
                )}
              </button>
            </div>
          </form>

          <div className="mt-6 pt-4 border-t border-slate-800/80 text-center">
            <span className="text-[11px] text-slate-500">
              Sistem terisolasi dengan rate limiting dan pencatatan audit.
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
