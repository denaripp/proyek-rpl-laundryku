'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Skeleton } from '@/components/ui';
import { getStoredUser, AuthUser, getActiveStoreId, logoutAndRedirectHome } from '@/lib/auth';
import { getStoreMetrics, StoreMetricsData } from '@/lib/store';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { formatRupiah, formatTanggalWaktu } from '@/lib/format';
import {
  Sparkles,
  ShoppingBag,
  Clock,
  CheckCircle2,
  TrendingUp,
  Truck,
  ArrowRight,
  AlertTriangle,
  RotateCcw,
  Package,
} from 'lucide-react';

export default function StoreDashboardOverviewPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [dashboardData, setDashboardData] = useState<StoreMetricsData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const isFetchingRef = useRef(false);

  const loadData = useCallback(async (isBackground: boolean | unknown = false) => {
    const isBg = typeof isBackground === 'boolean' && isBackground;
    if (isFetchingRef.current) return;
    isFetchingRef.current = true;
    if (!isBg) {
      setLoading(true);
      setError(null);
    }
    try {
      let user = getStoredUser('mitra');
      if (!user) {
        try {
          const res = await fetch('/api/auth/mitra/session');
          if (res.ok) {
            const json = await res.json();
            if (json.user) {
              user = json.user;
              if (typeof window !== 'undefined') {
                localStorage.setItem('laundryku_mitra_user', JSON.stringify(json.user));
              }
            }
          }
        } catch {
          // ignore
        }
      }
      setCurrentUser(user);
      const activeStoreId = getActiveStoreId(user);
      const data = await getStoreMetrics(activeStoreId || undefined);
      setDashboardData(data);
      if (isBg) {
        setError(null);
      }
    } catch (err: unknown) {
      if (!isBg) {
        setError(formatFriendlyErrorMessage(err, 'Gagal memuat ringkasan dashboard toko.'));
      }
    } finally {
      if (!isBg) {
        setLoading(false);
      }
      isFetchingRef.current = false;
    }
  }, []);

  useEffect(() => {
    loadData();

    const handleStoreChange = () => {
      loadData();
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        loadData(true);
      }
    };

    const handleFocus = () => {
      loadData(true);
    };

    window.addEventListener('laundryku_active_store_change', handleStoreChange);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', handleFocus);

    return () => {
      window.removeEventListener('laundryku_active_store_change', handleStoreChange);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('focus', handleFocus);
    };
  }, [loadData]);

  const metrics = dashboardData?.metrics;
  const recentOrders = dashboardData?.recent_orders || [];
  const store = dashboardData?.store;

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
      onLogout={() => logoutAndRedirectHome('mitra')}
    >
      <div className="space-y-6">
        {/* Compact Workspace Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
              Ringkasan operasional
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              {store?.name
                ? `Pantau performa harian dan pesanan aktif gerai ${store.name}.`
                : 'Pantau performa harian dan pesanan aktif gerai Anda.'}
            </p>
          </div>

          <div className="flex items-center gap-2.5">
            <Link href="/store/services">
              <Button
                variant="primary"
                size="sm"
                className="bg-navy-900 hover:bg-navy-800 text-white text-xs flex items-center gap-1.5 rounded-xl shadow-2xs min-h-[38px] px-3.5"
              >
                <Sparkles className="w-3.5 h-3.5 text-sky-300" strokeWidth={1.75} />
                <span>Katalog layanan</span>
              </Button>
            </Link>
            <Link href="/store/orders">
              <Button
                variant="secondary"
                size="sm"
                className="text-xs flex items-center gap-1.5 rounded-xl border-slate-200 bg-white hover:bg-slate-50 shadow-2xs min-h-[38px] px-3.5"
              >
                <ShoppingBag className="w-3.5 h-3.5 text-slate-600" strokeWidth={1.75} />
                <span>Semua pesanan</span>
              </Button>
            </Link>
          </div>
        </div>

        {/* Loading Skeleton */}
        {loading && (
          <div className="space-y-6 animate-pulse">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
              {[...Array(5)].map((_, i) => (
                <div key={i} className="p-4 border border-slate-200/80 rounded-2xl bg-white space-y-2.5 shadow-2xs">
                  <Skeleton className="h-3 w-20" />
                  <Skeleton className="h-7 w-16" />
                  <Skeleton className="h-2.5 w-24" />
                </div>
              ))}
            </div>
            <div className="p-5 border border-slate-200/80 rounded-2xl bg-white space-y-3 shadow-2xs">
              <Skeleton className="h-4 w-32" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="p-8 text-center border border-slate-200/80 bg-white rounded-2xl shadow-2xs">
            <div className="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3 border border-rose-100">
              <AlertTriangle className="w-5 h-5" strokeWidth={2} />
            </div>
            <h3 className="text-sm font-bold text-slate-900 mb-1">Gagal memuat ringkasan</h3>
            <p className="text-xs text-slate-500 max-w-md mx-auto mb-4">{error}</p>
            <div className="flex items-center justify-center gap-2">
              <Button
                variant="secondary"
                size="sm"
                onClick={loadData}
                className="inline-flex items-center gap-1.5 text-xs cursor-pointer rounded-xl"
              >
                <RotateCcw className="w-3.5 h-3.5" strokeWidth={1.75} />
                <span>Coba lagi</span>
              </Button>
              {error.toLowerCase().includes('unauthenticated') && (
                <Link href="/mitra/login">
                  <Button
                    variant="primary"
                    size="sm"
                    className="inline-flex items-center gap-1.5 text-xs cursor-pointer rounded-xl bg-navy-900 hover:bg-navy-800 text-white"
                  >
                    <span>Login Akun Mitra</span>
                  </Button>
                </Link>
              )}
            </div>
          </div>
        )}

        {/* Loaded Dashboard Content */}
        {!loading && !error && metrics && (
          <>
            {/* 5 KPI Bento Stat Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
              {/* Card 1: Pesanan Baru */}
              <div className="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div className="flex items-center justify-between text-slate-600 text-xs font-semibold mb-2">
                  <span>Pesanan baru</span>
                  <div className="w-7 h-7 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center border border-sky-100">
                    <Package className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-navy-950">
                  {metrics.new_orders}
                </div>
                <div className="text-[11px] text-slate-500 mt-2 font-medium">Menunggu konfirmasi</div>
              </div>

              {/* Card 2: Sedang Diproses */}
              <div className="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div className="flex items-center justify-between text-slate-600 text-xs font-semibold mb-2">
                  <span>Sedang diproses</span>
                  <div className="w-7 h-7 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-100">
                    <Clock className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-navy-950">
                  {metrics.processing_orders}
                </div>
                <div className="text-[11px] text-slate-500 mt-2 font-medium">Pencucian & penyetrikaan</div>
              </div>

              {/* Card 3: Siap Diantar */}
              <div className="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div className="flex items-center justify-between text-slate-600 text-xs font-semibold mb-2">
                  <span>Siap diantar</span>
                  <div className="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center border border-indigo-100">
                    <Truck className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-navy-950">
                  {metrics.ready_for_delivery}
                </div>
                <div className="text-[11px] text-slate-500 mt-2 font-medium">Menunggu kurir / siap ambil</div>
              </div>

              {/* Card 4: Selesai Hari Ini */}
              <div className="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div className="flex items-center justify-between text-slate-600 text-xs font-semibold mb-2">
                  <span>Selesai hari ini</span>
                  <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100">
                    <CheckCircle2 className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-navy-950">
                  {metrics.completed_today}
                </div>
                <div className="text-[11px] text-slate-500 mt-2 font-medium">Pesanan berhasil ditutup</div>
              </div>

              {/* Card 5: Omset Hari Ini */}
              <div className="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div className="flex items-center justify-between text-slate-600 text-xs font-semibold mb-2">
                  <span>Omset hari ini</span>
                  <div className="w-7 h-7 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center border border-navy-100">
                    <TrendingUp className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div className="text-lg font-bold font-mono tabular-nums text-navy-950 truncate">
                  {formatRupiah(metrics.today_revenue)}
                </div>
                <div className="text-[11px] text-slate-500 mt-2 font-medium">Total transaksi terbayar</div>
              </div>
            </div>

            {/* Recent Orders Table / Cards (Responsive) */}
            <div className="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
              <div className="p-4 sm:p-5 flex items-center justify-between border-b border-slate-100">
                <div>
                  <h2 className="text-sm font-bold text-slate-900">Pesanan terbaru</h2>
                  <p className="text-xs text-slate-500 mt-0.5">Daftar transaksi masuk dan aktif</p>
                </div>
                <Link
                  href="/store/orders"
                  className="text-xs font-semibold text-navy-900 hover:text-navy-700 inline-flex items-center gap-1.5 transition-colors"
                >
                  <span>Lihat semua pesanan</span>
                  <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
                </Link>
              </div>

              {recentOrders.length === 0 ? (
                <div className="py-12 px-4 text-center text-slate-500 text-xs space-y-1">
                  <p className="font-semibold text-slate-700">Belum ada pesanan terbaru hari ini.</p>
                  <p className="text-slate-400">
                    Pesanan yang masuk dari pelanggan akan langsung tampil di sini.
                  </p>
                </div>
              ) : (
                <>
                  {/* Desktop Table View */}
                  <div className="hidden md:block overflow-x-auto">
                    <table className="w-full text-left text-xs border-collapse">
                      <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/60 text-slate-500 font-semibold text-[11px]">
                          <th className="py-3 px-4">Nomor pesanan</th>
                          <th className="py-3 px-4">Pelanggan</th>
                          <th className="py-3 px-4">Waktu</th>
                          <th className="py-3 px-4">Total</th>
                          <th className="py-3 px-4">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 text-slate-700">
                        {recentOrders.map((ord) => (
                          <tr key={ord.id} className="hover:bg-slate-50/80 transition-colors">
                            <td className="py-3 px-4 font-mono font-bold text-navy-900">
                              {ord.order_number}
                            </td>
                            <td className="py-3 px-4 font-medium text-slate-900">
                              {ord.customer_name}
                            </td>
                            <td className="py-3 px-4 text-slate-500 font-mono text-[11px]">
                              {ord.created_at ? formatTanggalWaktu(ord.created_at) : '-'}
                            </td>
                            <td className="py-3 px-4 font-mono tabular-nums font-semibold text-slate-900">
                              {formatRupiah(ord.total_amount)}
                            </td>
                            <td className="py-3 px-4">
                              <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                                {ord.status_label || ord.status}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Mobile Card List View */}
                  <div className="md:hidden divide-y divide-slate-100">
                    {recentOrders.map((ord) => (
                      <div key={ord.id} className="p-4 space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="font-mono font-bold text-xs text-navy-900">
                            {ord.order_number}
                          </span>
                          <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                            {ord.status_label || ord.status}
                          </span>
                        </div>
                        <div className="flex items-center justify-between text-xs">
                          <span className="text-slate-800 font-medium">{ord.customer_name}</span>
                          <span className="font-mono tabular-nums font-bold text-navy-950">
                            {formatRupiah(ord.total_amount)}
                          </span>
                        </div>
                        <div className="text-[11px] text-slate-400 font-mono">
                          {ord.created_at ? formatTanggalWaktu(ord.created_at) : '-'}
                        </div>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </div>
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
