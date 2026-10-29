'use client';

import React, { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import { adminApi, DashboardMetrics } from '@/lib/admin';
import { StatCard } from '@/components/admin/StatCard';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { AlertCircle, ArrowUpRight } from 'lucide-react';
import { usePolling } from '@/hooks/usePolling';

export default function AdminDashboardPage() {
  const [period, setPeriod] = useState<string>('all_time');
  const [metricsData, setMetricsData] = useState<DashboardMetrics | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const fetchMetrics = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);
    setError(null);

    try {
      const data = await adminApi.getMetrics(period);
      setMetricsData(data);
    } catch (err: any) {
      setError(err.message || 'Gagal memuat metrik dashboard.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [period]);

  useEffect(() => {
    fetchMetrics();
  }, [fetchMetrics]);

  // 60s auto-refresh polling with auto-pause on background tab & refetch on refocus
  usePolling(() => fetchMetrics(true), 60000);

  const formatRupiah = (val: number | null | undefined) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(val || 0);
  };

  const auditColumns: Column<DashboardMetrics['recent_logs'][0]>[] = [
    {
      header: 'Waktu (WIB)',
      accessor: (item) => (
        <span className="font-mono text-slate-500">
          {new Date(item.created_at).toLocaleString('id-ID', {
            timeZone: 'Asia/Jakarta',
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
          })}
        </span>
      ),
    },
    {
      header: 'Aksi',
      accessor: (item) => <span className="font-mono text-slate-800 font-semibold">{item.action}</span>,
    },
    {
      header: 'Entitas',
      accessor: (item) => (
        <span className="text-slate-600">
          {item.entity_type} {item.entity_id ? `(${item.entity_id.slice(0, 8)}...)` : ''}
        </span>
      ),
    },
    {
      header: 'Aktor',
      accessor: (item) => <span className="text-slate-700 font-medium">{item.actor_name}</span>,
    },
  ];

  if (error && !metricsData) {
    return (
      <div className="space-y-6">
        <AdminHeader title="Ringkasan platform" />
        <div className="bg-white border border-rose-200/80 rounded-2xl p-8 text-center shadow-2xs">
          <AlertCircle className="w-8 h-8 text-rose-500 mx-auto mb-2" strokeWidth={1.75} />
          <h2 className="text-sm font-semibold text-slate-900 mb-1">Gagal memuat data dashboard</h2>
          <p className="text-xs text-slate-500 mb-4">{error}</p>
          <button
            onClick={() => fetchMetrics()}
            className="px-4 py-2 bg-navy-900 text-white rounded-xl text-xs font-semibold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs min-h-[38px]"
          >
            Coba lagi
          </button>
        </div>
      </div>
    );
  }

  const m = metricsData?.metrics;
  const statusCounts = metricsData?.orders_by_status || {};
  const roleCounts = metricsData?.users_by_role;

  return (
    <div className="space-y-6">
      {/* Header & Filter */}
      <AdminHeader
        title="Ringkasan platform"
        subtitle="Agregasi metrik transaksi real-time dari basis data LaundryKu"
        onRefresh={() => fetchMetrics(true)}
        refreshing={refreshing}
        actions={
          <div className="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1 shadow-2xs">
            {[
              { id: 'all_time', label: 'Semua' },
              { id: 'today', label: 'Hari ini' },
              { id: 'this_week', label: 'Minggu ini' },
              { id: 'this_month', label: 'Bulan ini' },
              { id: 'this_year', label: 'Tahun ini' },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => setPeriod(tab.id)}
                className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer ${
                  period === tab.id
                    ? 'bg-navy-900 text-white shadow-2xs'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        }
      />

      {/* Main KPI Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Total GMV"
          value={loading ? '...' : formatRupiah(m?.total_gmv)}
          subtext="Pesanan selesai (final_amount)"
          tooltip="Definisi: Total nilai transaksi bersih pada pesanan yang berstatus COMPLETED dalam periode terpilih."
        />
        <StatCard
          label="Pendapatan komisi"
          value={loading ? '...' : formatRupiah(m?.total_platform_revenue)}
          subtext="Snapshot komisi platform"
          tooltip="Definisi: Total komisi platform yang tercatat saat pesanan diselesaikan."
        />
        <StatCard
          label="Total pesanan"
          value={loading ? '...' : (m?.total_orders ?? 0).toLocaleString('id-ID')}
          subtext={`${m?.completed_orders ?? 0} pesanan selesai`}
          tooltip="Total semua pesanan masuk pada periode terpilih."
        />
        <StatCard
          label="Toko aktif"
          value={loading ? '...' : (m?.active_stores ?? 0).toLocaleString('id-ID')}
          subtext={`${m?.pending_stores ?? 0} menunggu persetujuan`}
          tooltip="Toko berstatus operasional aktif dan disetujui."
        />
      </div>

      {/* User & Store Breakdown */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Order Status Summary */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <h2 className="text-xs font-semibold text-slate-800">Distribusi status pesanan</h2>
            <Link href="/admin/orders" className="text-xs text-slate-500 hover:text-navy-950 font-semibold flex items-center gap-1 transition-colors">
              <span>Lihat semua</span>
              <ArrowUpRight className="w-3.5 h-3.5" strokeWidth={1.75} />
            </Link>
          </div>
          <div className="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Menunggu jemput</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading ? '-' : (statusCounts['PENDING_PICKUP'] || 0) + (statusCounts['PICKUP_ASSIGNED'] || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Diproses toko</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading
                  ? '-'
                  : (statusCounts['PROCESSING'] || 0) +
                    (statusCounts['PICKED_UP'] || 0) +
                    (statusCounts['AT_STORE'] || 0) +
                    (statusCounts['WEIGHED'] || 0) +
                    (statusCounts['AWAITING_PRICE_APPROVAL'] || 0) +
                    (statusCounts['PRICE_APPROVED'] || 0) +
                    (statusCounts['AWAITING_PAYMENT'] || 0) +
                    (statusCounts['PAID'] || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Selesai</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading ? '-' : (statusCounts['COMPLETED'] || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Dibatalkan / exp</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading
                  ? '-'
                  : (statusCounts['CANCELLED'] || 0) +
                    (statusCounts['PAYMENT_EXPIRED'] || 0) +
                    (statusCounts['PAYMENT_FAILED'] || 0)}
              </div>
            </div>
          </div>
        </div>

        {/* User Composition */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <h2 className="text-xs font-semibold text-slate-800">Komposisi pengguna terdaftar</h2>
            <Link href="/admin/users" className="text-xs text-slate-500 hover:text-navy-950 font-semibold flex items-center gap-1 transition-colors">
              <span>Kelola pengguna</span>
              <ArrowUpRight className="w-3.5 h-3.5" strokeWidth={1.75} />
            </Link>
          </div>
          <div className="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Pelanggan</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading ? '-' : (roleCounts?.customer || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Admin toko</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading ? '-' : (roleCounts?.store_admin || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Kurir</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading ? '-' : (roleCounts?.courier || 0)}
              </div>
            </div>
            <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
              <div className="text-[11px] text-slate-500 font-medium">Ditangguhkan</div>
              <div className="text-base font-bold font-mono text-slate-900 tabular-nums mt-0.5">
                {loading ? '-' : (m?.suspended_users || 0)}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Audit Logs Section */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-xs font-semibold text-slate-800">Aktivitas audit log terbaru</h2>
          <Link
            href="/admin/audit-logs"
            className="text-xs text-slate-500 hover:text-navy-950 font-semibold flex items-center gap-1 transition-colors"
          >
            <span>Buka seluruh log</span>
            <ArrowUpRight className="w-3.5 h-3.5" strokeWidth={1.75} />
          </Link>
        </div>

        <DataTable
          columns={auditColumns}
          data={metricsData?.recent_logs || []}
          keyExtractor={(item) => item.id}
          loading={loading}
          emptyText="Belum ada rekaman aktivitas sistem."
        />
      </div>
    </div>
  );
}
