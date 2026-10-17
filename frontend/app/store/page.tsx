'use client';

import React, { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Card, Button, Skeleton } from '@/components/ui';
import { getStoredUser, logout, AuthUser, getActiveStoreId } from '@/lib/auth';
import { getStoreMetrics, StoreMetricsData } from '@/lib/store';
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

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);
      const activeStoreId = getActiveStoreId(user);
      const data = await getStoreMetrics(activeStoreId || undefined);
      setDashboardData(data);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal memuat ringkasan dashboard toko.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();

    const handleStoreChange = () => {
      loadData();
    };

    window.addEventListener('laundryku_active_store_change', handleStoreChange);
    return () => {
      window.removeEventListener('laundryku_active_store_change', handleStoreChange);
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
      onLogout={logout}
    >
      <div className="space-y-6">
        {/* Header Title & Actions */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold text-slate-900 tracking-tight">
              Ringkasan Operasional
            </h1>
            <p className="text-xs text-slate-500 mt-0.5">
              {store?.name ? `Pantau performa harian dan pesanan aktif gerai ${store.name}.` : 'Pantau performa harian dan pesanan aktif gerai Anda.'}
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Link href="/store/services">
              <Button
                variant="primary"
                size="sm"
                className="bg-[#1A3A5C] hover:bg-[#132d47] text-xs flex items-center gap-1.5"
              >
                <Sparkles className="w-3.5 h-3.5" />
                <span>Katalog Layanan</span>
              </Button>
            </Link>
            <Link href="/store/orders">
              <Button
                variant="secondary"
                size="sm"
                className="text-xs flex items-center gap-1.5"
              >
                <ShoppingBag className="w-3.5 h-3.5" />
                <span>Semua Pesanan</span>
              </Button>
            </Link>
          </div>
        </div>

        {/* Loading Skeleton */}
        {loading && (
          <div className="space-y-6 animate-pulse">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
              {[...Array(5)].map((_, i) => (
                <div key={i} className="p-4 border border-slate-200 rounded-lg bg-white space-y-2">
                  <Skeleton className="h-3 w-20" />
                  <Skeleton className="h-7 w-16" />
                </div>
              ))}
            </div>
            <div className="p-5 border border-slate-200 rounded-lg bg-white space-y-3">
              <Skeleton className="h-4 w-32" />
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-12 w-full" />
            </div>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <Card className="p-8 text-center border-slate-200 bg-white">
            <div className="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3">
              <AlertTriangle className="w-5 h-5" />
            </div>
            <h3 className="text-sm font-bold text-slate-900 mb-1">Gagal Memuat Ringkasan</h3>
            <p className="text-xs text-slate-500 max-w-md mx-auto mb-4">{error}</p>
            <Button
              variant="secondary"
              size="sm"
              onClick={loadData}
              className="inline-flex items-center gap-1.5 text-xs cursor-pointer"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              <span>Coba lagi</span>
            </Button>
          </Card>
        )}

        {/* Loaded Dashboard Content */}
        {!loading && !error && metrics && (
          <>
            {/* 5 KPI Stat Cards Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
              {/* Card 1: Pesanan Baru */}
              <div className="p-4 rounded-lg bg-white border border-slate-200 flex flex-col justify-between">
                <div className="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                  <span>Pesanan Baru</span>
                  <Package className="w-4 h-4 text-sky-600" />
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-slate-900 mt-1">
                  {metrics.new_orders}
                </div>
                <div className="text-[11px] text-slate-400 mt-2">Menunggu konfirmasi</div>
              </div>

              {/* Card 2: Sedang Diproses */}
              <div className="p-4 rounded-lg bg-white border border-slate-200 flex flex-col justify-between">
                <div className="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                  <span>Sedang Diproses</span>
                  <Clock className="w-4 h-4 text-amber-600" />
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-slate-900 mt-1">
                  {metrics.processing_orders}
                </div>
                <div className="text-[11px] text-slate-400 mt-2">Pencucian & penyetrikaan</div>
              </div>

              {/* Card 3: Siap Diantar */}
              <div className="p-4 rounded-lg bg-white border border-slate-200 flex flex-col justify-between">
                <div className="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                  <span>Siap Diantar</span>
                  <Truck className="w-4 h-4 text-indigo-600" />
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-slate-900 mt-1">
                  {metrics.ready_for_delivery}
                </div>
                <div className="text-[11px] text-slate-400 mt-2">Menunggu kurir / siap ambil</div>
              </div>

              {/* Card 4: Selesai Hari Ini */}
              <div className="p-4 rounded-lg bg-white border border-slate-200 flex flex-col justify-between">
                <div className="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                  <span>Selesai Hari Ini</span>
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                </div>
                <div className="text-2xl font-bold font-mono tabular-nums text-slate-900 mt-1">
                  {metrics.completed_today}
                </div>
                <div className="text-[11px] text-slate-400 mt-2">Pesanan berhasil ditutup</div>
              </div>

              {/* Card 5: Omset Hari Ini */}
              <div className="p-4 rounded-lg bg-white border border-slate-200 flex flex-col justify-between">
                <div className="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                  <span>Omset Hari Ini</span>
                  <TrendingUp className="w-4 h-4 text-[#1A3A5C]" />
                </div>
                <div className="text-lg font-bold font-mono tabular-nums text-slate-900 mt-1">
                  {formatRupiah(metrics.today_revenue)}
                </div>
                <div className="text-[11px] text-slate-400 mt-2">Total transaksi terbayar</div>
              </div>
            </div>

            {/* Recent Orders List */}
            <div className="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                  <h2 className="text-sm font-bold text-slate-900">Pesanan Terbaru</h2>
                  <p className="text-xs text-slate-500 mt-0.5">Daftar transaksi masuk dan aktif</p>
                </div>
                <Link
                  href="/store/orders"
                  className="text-xs font-semibold text-[#1A3A5C] hover:underline inline-flex items-center gap-1"
                >
                  <span>Lihat semua pesanan</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </Link>
              </div>

              {recentOrders.length === 0 ? (
                <div className="py-12 text-center text-slate-500 text-xs space-y-1">
                  <p className="font-medium text-slate-700">Belum ada pesanan terbaru hari ini.</p>
                  <p className="text-slate-400">
                    Pesanan yang masuk dari pelanggan akan langsung tampil di sini.
                  </p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr className="border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[10px]">
                        <th className="py-2.5 px-3">No. Order</th>
                        <th className="py-2.5 px-3">Pelanggan</th>
                        <th className="py-2.5 px-3">Waktu</th>
                        <th className="py-2.5 px-3">Total</th>
                        <th className="py-2.5 px-3">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 text-slate-700">
                      {recentOrders.map((ord) => (
                        <tr key={ord.id} className="hover:bg-slate-50 transition-colors">
                          <td className="py-3 px-3 font-mono font-semibold text-slate-900">
                            {ord.order_number}
                          </td>
                          <td className="py-3 px-3 font-medium text-slate-900">
                            {ord.customer_name}
                          </td>
                          <td className="py-3 px-3 text-slate-500 font-mono text-[11px]">
                            {ord.created_at ? formatTanggalWaktu(ord.created_at) : '-'}
                          </td>
                          <td className="py-3 px-3 font-mono tabular-nums text-slate-900">
                            {formatRupiah(ord.total_amount)}
                          </td>
                          <td className="py-3 px-3">
                            <span className="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-800 border border-slate-200">
                              {ord.status_label || ord.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
