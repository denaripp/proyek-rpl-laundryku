'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Clock, Store, CheckCircle2, AlertCircle, LogOut, ExternalLink, ShieldAlert } from 'lucide-react';
import { getStoredUser } from '@/lib/auth';

export default function MitraStatusPage() {
  const router = useRouter();
  const [user, setUser] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadSession() {
      try {
        const res = await fetch('/api/auth/mitra/session');
        if (res.ok) {
          const json = await res.json();
          setUser(json.user);
        } else {
          const fallback = getStoredUser();
          setUser(fallback);
        }
      } catch {
        const fallback = getStoredUser();
        setUser(fallback);
      } finally {
        setLoading(false);
      }
    }
    loadSession();
  }, []);

  const handleLogout = async () => {
    try {
      await fetch('/api/auth/mitra/logout', { method: 'POST' });
    } catch {
      // Ignore
    }
    if (typeof window !== 'undefined') {
      localStorage.removeItem('laundryku_user');
    }
    router.push('/mitra/login');
  };

  const store = user?.stores?.[0];

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full mx-auto">
        <div className="text-center mb-6">
          <div className="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center mx-auto mb-3 shadow-lg">
            <Clock className="w-7 h-7 animate-pulse" strokeWidth={1.5} />
          </div>
          <h1 className="text-xl font-bold text-white tracking-tight">
            Status Pengajuan Gerai Mitra
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Pantau status verifikasi dan aktivasi operasional gerai Anda
          </p>
        </div>

        <div className="bg-slate-800/95 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-5">
          <div className="p-4 rounded-xl bg-slate-900/80 border border-slate-700 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Nama Gerai:</span>
              <span className="text-xs font-bold text-white">{store?.name || 'Gerai Baru Mitra'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Penanggung Jawab:</span>
              <span className="text-xs font-medium text-slate-300">{user?.name || 'Mitra Laundry'}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Status Persetujuan:</span>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                <span className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping" />
                <span>Menunggu Verifikasi (Pending)</span>
              </span>
            </div>
          </div>

          <div className="p-3.5 rounded-xl bg-blue-950/40 border border-blue-800/50 flex items-start gap-3">
            <ShieldAlert className="w-5 h-5 text-blue-400 shrink-0 mt-0.5" strokeWidth={1.5} />
            <div className="text-xs text-blue-200 leading-relaxed space-y-1">
              <p className="font-semibold text-white">Gerai dalam Proses Tinjauan</p>
              <p className="text-slate-300">
                Data gerai Anda telah kami terima dan saat ini sedang ditinjau oleh Administrator Platform LaundryKu.
                Proses verifikasi membutuhkan waktu maksimal 1x24 jam kerja.
              </p>
            </div>
          </div>

          <div className="space-y-2 pt-2">
            <Link
              href="/store"
              className="w-full flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg text-xs font-medium text-white bg-slate-700 hover:bg-slate-600 transition-colors"
            >
              <Store className="w-4 h-4 text-slate-300" strokeWidth={1.5} />
              <span>Lihat Pratinjau Dashboard Toko</span>
            </Link>

            <button
              onClick={handleLogout}
              className="w-full flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg text-xs font-medium text-rose-300 hover:bg-rose-950/30 border border-rose-800/40 transition-colors cursor-pointer"
            >
              <LogOut className="w-4 h-4 text-rose-400" strokeWidth={1.5} />
              <span>Keluar dari Akun Mitra</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
