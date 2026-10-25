'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Clock,
  Store,
  CheckCircle2,
  XCircle,
  FileEdit,
  LogOut,
  ExternalLink,
  ShieldAlert,
  ArrowRight,
  MapPin,
} from 'lucide-react';

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
          const fallback =
            typeof window !== 'undefined'
              ? JSON.parse(localStorage.getItem('laundryku_mitra_user') || 'null')
              : null;
          setUser(fallback);
        }
      } catch {
        const fallback =
          typeof window !== 'undefined'
            ? JSON.parse(localStorage.getItem('laundryku_mitra_user') || 'null')
            : null;
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
      localStorage.removeItem('laundryku_mitra_user');
    }
    router.push('/mitra/login');
  };

  const store = user?.stores?.[0];
  const rawStatus = (store?.status || 'submitted').toLowerCase();

  // Status configuration
  const statusConfig = {
    approved: {
      badgeBg: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
      pingBg: 'bg-emerald-400',
      label: 'Disetujui & Aktif',
      icon: CheckCircle2,
      iconColor: 'text-emerald-400',
      cardBorder: 'border-emerald-800/40 bg-emerald-950/20',
      title: 'Selamat! Gerai Anda Telah Aktif',
      description:
        'Pengajuan gerai Anda telah diverifikasi dan disetujui. Gerai Anda sekarang aktif dan dapat mulai menerima pesanan.',
    },
    rejected: {
      badgeBg: 'bg-rose-500/10 text-rose-400 border-rose-500/30',
      pingBg: 'bg-rose-400',
      label: 'Pendaftaran Ditolak',
      icon: XCircle,
      iconColor: 'text-rose-400',
      cardBorder: 'border-rose-800/40 bg-rose-950/20',
      title: 'Pengajuan Gerai Belum Memenuhi Syarat',
      description:
        'Mohon maaf, pengajuan gerai Anda belum dapat disetujui oleh Administrator Platform LaundryKu.',
    },
    under_review: {
      badgeBg: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
      pingBg: 'bg-blue-400',
      label: 'Sedang Ditinjau (Under Review)',
      icon: Clock,
      iconColor: 'text-blue-400',
      cardBorder: 'border-blue-800/40 bg-blue-950/20',
      title: 'Gerai Sedang Dalam Tinjauan',
      description:
        'Administrator platform sedang memeriksa kelengkapan data gerai Anda. Proses ini umumnya selesai dalam 1x24 jam kerja.',
    },
    draft: {
      badgeBg: 'bg-slate-500/10 text-slate-300 border-slate-500/30',
      pingBg: 'bg-slate-400',
      label: 'Draf Belum Disubmit',
      icon: FileEdit,
      iconColor: 'text-slate-400',
      cardBorder: 'border-slate-700/60 bg-slate-900/40',
      title: 'Draf Pengajuan Tersimpan',
      description:
        'Data gerai Anda saat ini tersimpan sebagai draf dan belum dikirim untuk ditinjau.',
    },
    submitted: {
      badgeBg: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
      pingBg: 'bg-amber-400',
      label: 'Menunggu Verifikasi (Submitted)',
      icon: Clock,
      iconColor: 'text-amber-400',
      cardBorder: 'border-amber-800/40 bg-amber-950/20',
      title: 'Gerai dalam Antrean Verifikasi',
      description:
        'Data gerai Anda telah kami terima dan saat ini sedang berada dalam antrean peninjauan oleh Administrator Platform LaundryKu.',
    },
  };

  const currentStatus =
    statusConfig[rawStatus as keyof typeof statusConfig] || statusConfig.submitted;
  const StatusIcon = currentStatus.icon;

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full mx-auto">
        <div className="text-center mb-6">
          <div
            className={`w-14 h-14 rounded-2xl border flex items-center justify-center mx-auto mb-3 shadow-lg ${currentStatus.badgeBg}`}
          >
            <StatusIcon className="w-7 h-7" strokeWidth={1.5} />
          </div>
          <h1 className="text-xl font-bold text-white tracking-tight">
            Status Pengajuan Gerai Mitra
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Pantau status verifikasi dan aktivasi operasional gerai Anda
          </p>
        </div>

        <div className="bg-slate-800/95 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-5">
          <div className="p-4 rounded-xl bg-slate-900/80 border border-slate-700 space-y-2.5">
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Nama Gerai:</span>
              <span className="text-xs font-bold text-white">
                {store?.name || 'Gerai Baru Mitra'}
              </span>
            </div>
            {store?.address && (
              <div className="flex items-start justify-between gap-2">
                <span className="text-xs text-slate-400 shrink-0">Alamat:</span>
                <span className="text-xs text-slate-300 text-right line-clamp-2">
                  {store.address}
                </span>
              </div>
            )}
            <div className="flex items-center justify-between">
              <span className="text-xs text-slate-400">Penanggung Jawab:</span>
              <span className="text-xs font-medium text-slate-300">
                {user?.name || 'Mitra Laundry'}
              </span>
            </div>
            <div className="flex items-center justify-between pt-1 border-t border-slate-800">
              <span className="text-xs text-slate-400">Status Persetujuan:</span>
              <span
                className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ${currentStatus.badgeBg}`}
              >
                <span className={`w-1.5 h-1.5 rounded-full ${currentStatus.pingBg} animate-ping`} />
                <span>{currentStatus.label}</span>
              </span>
            </div>
          </div>

          {/* Status Context Info Card */}
          <div className={`p-4 rounded-xl border flex items-start gap-3 ${currentStatus.cardBorder}`}>
            <ShieldAlert className={`w-5 h-5 shrink-0 mt-0.5 ${currentStatus.iconColor}`} strokeWidth={1.5} />
            <div className="text-xs leading-relaxed space-y-1">
              <p className="font-semibold text-white">{currentStatus.title}</p>
              <p className="text-slate-300">{currentStatus.description}</p>
            </div>
          </div>

          {/* Rejection Reason Alert (If Rejected) */}
          {rawStatus === 'rejected' && store?.rejection_reason && (
            <div className="p-3.5 rounded-xl bg-rose-950/40 border border-rose-800/60 text-xs space-y-1">
              <span className="font-semibold text-rose-300 flex items-center gap-1.5">
                <XCircle className="w-4 h-4 text-rose-400" />
                Alasan Penolakan:
              </span>
              <p className="text-rose-200 pl-5 italic">
                &ldquo;{store.rejection_reason}&rdquo;
              </p>
            </div>
          )}

          {/* Actions */}
          <div className="space-y-2 pt-2">
            {rawStatus === 'approved' ? (
              <Link
                href="/store"
                className="w-full flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors shadow-lg shadow-emerald-950/40 cursor-pointer"
              >
                <Store className="w-4 h-4" strokeWidth={1.5} />
                <span>Masuk ke Dashboard Toko</span>
                <ArrowRight className="w-4 h-4" />
              </Link>
            ) : (
              <Link
                href="/store"
                className="w-full flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg text-xs font-medium text-slate-300 bg-slate-700/60 hover:bg-slate-700 transition-colors cursor-pointer"
              >
                <Store className="w-4 h-4 text-slate-400" strokeWidth={1.5} />
                <span>Lihat Pratinjau Dashboard Toko</span>
              </Link>
            )}

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
