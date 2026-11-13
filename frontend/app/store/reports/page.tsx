'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Input, Badge, Skeleton } from '@/components/ui';
import { getStoredUser, AuthUser } from '@/lib/auth';
import { getStoreReports, StoreReportsData } from '@/lib/store';
import { formatRupiah, cn } from '@/lib/format';
import {
  BarChart3,
  TrendingUp,
  Download,
  RefreshCw,
  Calendar,
  CheckCircle2,
  AlertCircle,
  Star,
  CreditCard,
  Banknote,
  RotateCcw,
  ShoppingBag,
  ArrowUpRight,
} from 'lucide-react';

export default function StoreReportsPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);

  // Filter state
  const [filterPreset, setFilterPreset] = useState<'7d' | '30d' | 'this_month' | 'custom'>('30d');
  const [customFrom, setCustomFrom] = useState<string>('');
  const [customTo, setCustomTo] = useState<string>('');

  // Report Data State
  const [data, setData] = useState<StoreReportsData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setCurrentUser(getStoredUser('mitra'));
  }, []);

  // Compute dates based on preset
  const getDateRange = useCallback((): { from: string; to: string } => {
    const today = new Date();
    const toStr = today.toISOString().split('T')[0];

    if (filterPreset === '7d') {
      const past = new Date();
      past.setDate(today.getDate() - 7);
      return { from: past.toISOString().split('T')[0], to: toStr };
    }

    if (filterPreset === 'this_month') {
      const past = new Date(today.getFullYear(), today.getMonth(), 1);
      return { from: past.toISOString().split('T')[0], to: toStr };
    }

    if (filterPreset === 'custom' && customFrom && customTo) {
      return { from: customFrom, to: customTo };
    }

    // Default 30d
    const past = new Date();
    past.setDate(today.getDate() - 30);
    return { from: past.toISOString().split('T')[0], to: toStr };
  }, [filterPreset, customFrom, customTo]);

  const fetchReports = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const range = getDateRange();
      const res = await getStoreReports(range.from, range.to);
      setData(res);
    } catch {
      setError('Gagal memuat data laporan operasional gerai.');
    } finally {
      setLoading(false);
    }
  }, [getDateRange]);

  useEffect(() => {
    fetchReports();
  }, [fetchReports]);

  // Client-side CSV export with anti-formula injection & quote escaping
  const handleExportCsv = () => {
    if (!data || !data.recent_orders || data.recent_orders.length === 0) {
      alert('Tidak ada data pesanan pada periode ini untuk diekspor.');
      return;
    }

    const sanitizeCsvCell = (val: unknown): string => {
      if (val === null || val === undefined) return '""';
      let str = String(val);
      if (/^[=+\-@\t\r]/.test(str)) {
        str = `'${str}`;
      }
      const escaped = str.replace(/"/g, '""');
      return `"${escaped}"`;
    };

    const headers = ['Nomor Pesanan', 'Tanggal', 'Pelanggan', 'Metode Pembayaran', 'Total Nilai (IDR)', 'Status'];
    const rows = data.recent_orders.map((o) => [
      sanitizeCsvCell(o.order_number),
      sanitizeCsvCell(o.created_at),
      sanitizeCsvCell(o.customer_name),
      sanitizeCsvCell(o.payment_method),
      typeof o.final_price === 'number' ? o.final_price : sanitizeCsvCell(o.final_price),
      sanitizeCsvCell(o.status),
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `laporan-transaksi-${data.window.from}-sd-${data.window.to}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  const onlineRatio =
    data && data.revenue_total > 0
      ? Math.round((data.revenue_online / data.revenue_total) * 100)
      : 0;
  const codRatio = 100 - onlineRatio;

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
    >
      <div className="space-y-6 max-w-5xl">
        {/* Header Section */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
          <div className="space-y-1">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-navy-50 text-navy-800 border border-navy-100 flex items-center justify-center">
                <BarChart3 className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
              </div>
              <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Laporan & rekap operasional
              </h1>
            </div>
            <p className="text-xs sm:text-sm text-slate-500">
              Pantau performa penjualan, rincian pembayaran, volume pesanan, dan unduh rekapitulasi transaksi gerai.
            </p>
          </div>

          <div className="flex items-center gap-2 self-start sm:self-auto">
            <Button
              variant="outline"
              size="sm"
              onClick={handleExportCsv}
              disabled={loading || !data?.recent_orders?.length}
              className="text-xs rounded-xl border-slate-200 text-slate-700 inline-flex items-center gap-1.5 min-h-[38px] px-3.5 hover:bg-slate-50 font-semibold"
            >
              <Download className="w-3.5 h-3.5 text-slate-500" />
              <span>Ekspor CSV</span>
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={fetchReports}
              className="text-xs rounded-xl border-slate-200 text-slate-700 inline-flex items-center gap-1.5 min-h-[38px] px-3.5 hover:bg-slate-50"
            >
              <RefreshCw className={cn('w-3.5 h-3.5 text-slate-500', loading && 'animate-spin')} />
              <span>Segarkan</span>
            </Button>
          </div>
        </div>

        {/* Filter Bar */}
        <div className="bg-white border border-slate-200/80 rounded-2xl p-3.5 shadow-2xs flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-1.5 flex-wrap">
            <button
              type="button"
              onClick={() => setFilterPreset('7d')}
              className={cn(
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
                filterPreset === '7d'
                  ? 'bg-navy-700 text-white shadow-2xs'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              )}
            >
              7 Hari Terakhir
            </button>
            <button
              type="button"
              onClick={() => setFilterPreset('30d')}
              className={cn(
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
                filterPreset === '30d'
                  ? 'bg-navy-700 text-white shadow-2xs'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              )}
            >
              30 Hari Terakhir
            </button>
            <button
              type="button"
              onClick={() => setFilterPreset('this_month')}
              className={cn(
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
                filterPreset === 'this_month'
                  ? 'bg-navy-700 text-white shadow-2xs'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              )}
            >
              Bulan Ini
            </button>
            <button
              type="button"
              onClick={() => setFilterPreset('custom')}
              className={cn(
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
                filterPreset === 'custom'
                  ? 'bg-navy-700 text-white shadow-2xs'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              )}
            >
              Kustom
            </button>
          </div>

          {filterPreset === 'custom' && (
            <div className="flex items-center gap-2 flex-wrap">
              <Input
                type="date"
                value={customFrom}
                onChange={(e) => setCustomFrom(e.target.value)}
                className="h-8 text-xs rounded-lg border-slate-200"
              />
              <span className="text-xs text-slate-400">s/d</span>
              <Input
                type="date"
                value={customTo}
                onChange={(e) => setCustomTo(e.target.value)}
                className="h-8 text-xs rounded-lg border-slate-200"
              />
              <Button
                variant="primary"
                size="sm"
                onClick={fetchReports}
                disabled={!customFrom || !customTo}
                className="h-8 text-xs rounded-lg bg-navy-700 text-white"
              >
                Terapkan
              </Button>
            </div>
          )}

          {data && (
            <div className="text-xs text-slate-400 flex items-center gap-1.5">
              <Calendar className="w-3.5 h-3.5 text-slate-400" />
              <span>
                Periode: <strong>{data.window.from}</strong> s/d <strong>{data.window.to}</strong>
              </span>
            </div>
          )}
        </div>

        {error && (
          <div className="p-3.5 bg-rose-50 border border-rose-200/90 rounded-2xl text-rose-800 text-xs flex items-center gap-2.5 shadow-2xs">
            <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" strokeWidth={2} />
            <span>{error}</span>
          </div>
        )}

        {/* KPI Cards Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Card 1: Omset Total */}
          <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs space-y-2">
            <div className="flex items-center justify-between text-slate-500">
              <span className="text-xs font-semibold">Total Omset Pendapatan</span>
              <div className="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <TrendingUp className="w-4 h-4" />
              </div>
            </div>
            {loading ? (
              <Skeleton className="h-7 w-32 rounded-lg" />
            ) : (
              <div className="text-xl font-extrabold text-slate-900 tracking-tight">
                {formatRupiah(data?.revenue_total || 0)}
              </div>
            )}
            <div className="text-[11px] text-slate-400 flex items-center gap-1">
              <span>Lunas pada periode terpilih</span>
            </div>
          </div>

          {/* Card 2: Split Online vs COD */}
          <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs space-y-2">
            <div className="flex items-center justify-between text-slate-500">
              <span className="text-xs font-semibold">Kanal Pembayaran</span>
              <div className="w-7 h-7 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center">
                <CreditCard className="w-4 h-4" />
              </div>
            </div>
            {loading ? (
              <Skeleton className="h-7 w-32 rounded-lg" />
            ) : (
              <div className="flex items-center gap-2 text-xs font-bold">
                <span className="text-navy-700">Online {onlineRatio}%</span>
                <span className="text-slate-300">|</span>
                <span className="text-emerald-700">COD {codRatio}%</span>
              </div>
            )}
            <div className="text-[11px] text-slate-400 flex items-center justify-between">
              <span>Online: {formatRupiah(data?.revenue_online || 0)}</span>
              <span>COD: {formatRupiah(data?.revenue_cod || 0)}</span>
            </div>
          </div>

          {/* Card 3: Volume Pesanan & Sukses */}
          <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs space-y-2">
            <div className="flex items-center justify-between text-slate-500">
              <span className="text-xs font-semibold">Pesanan Selesai</span>
              <div className="w-7 h-7 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center">
                <ShoppingBag className="w-4 h-4" />
              </div>
            </div>
            {loading ? (
              <Skeleton className="h-7 w-32 rounded-lg" />
            ) : (
              <div className="text-xl font-extrabold text-slate-900 tracking-tight">
                {data?.completed_count || 0}{' '}
                <span className="text-xs font-semibold text-slate-400">
                  / {data?.order_count || 0} total
                </span>
              </div>
            )}
            <div className="text-[11px] text-emerald-600 font-semibold flex items-center gap-1">
              <span>{data?.completion_rate || 0}% tingkat penyelesaian</span>
            </div>
          </div>

          {/* Card 4: Rating & Refund */}
          <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs space-y-2">
            <div className="flex items-center justify-between text-slate-500">
              <span className="text-xs font-semibold">Kepuasan Pelanggan</span>
              <div className="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                <Star className="w-4 h-4 fill-amber-400 text-amber-400" />
              </div>
            </div>
            {loading ? (
              <Skeleton className="h-7 w-32 rounded-lg" />
            ) : (
              <div className="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-1.5">
                <span>{data?.avg_rating || 0}</span>
                <span className="text-xs font-semibold text-slate-400">/ 5.0</span>
              </div>
            )}
            <div className="text-[11px] text-slate-400 flex items-center justify-between">
              <span>Total Refund:</span>
              <strong className="text-rose-600">{formatRupiah(data?.refund_total || 0)}</strong>
            </div>
          </div>
        </div>

        {/* Tabel Rekapitulasi Transaksi */}
        <div className="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
          <div className="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 className="text-sm font-bold text-slate-900">Rekapitulasi Transaksi Masuk</h2>
              <p className="text-xs text-slate-500 mt-0.5">
                Daftar pesanan yang tercatat pada rentang waktu terpilih.
              </p>
            </div>
          </div>

          {loading ? (
            <div className="p-6 space-y-3">
              <Skeleton className="h-10 w-full rounded-xl" />
              <Skeleton className="h-10 w-full rounded-xl" />
              <Skeleton className="h-10 w-full rounded-xl" />
            </div>
          ) : !data?.recent_orders || data.recent_orders.length === 0 ? (
            <div className="p-10 text-center space-y-2">
              <p className="text-xs text-slate-500">Tidak ada riwayat transaksi pada periode yang dipilih.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="bg-slate-50/80 text-slate-500 font-bold border-b border-slate-100">
                    <th className="py-3 px-4 sm:px-6">No. Pesanan</th>
                    <th className="py-3 px-3">Tanggal</th>
                    <th className="py-3 px-3">Pelanggan</th>
                    <th className="py-3 px-3">Metode Bayar</th>
                    <th className="py-3 px-3">Total Nilai</th>
                    <th className="py-3 px-4 sm:px-6 text-right">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-medium">
                  {data.recent_orders.map((o) => (
                    <tr key={o.id} className="hover:bg-slate-50/50 transition-colors">
                      <td className="py-3.5 px-4 sm:px-6 font-bold text-slate-900">
                        {o.order_number}
                      </td>
                      <td className="py-3.5 px-3 text-slate-500">{o.created_at}</td>
                      <td className="py-3.5 px-3 text-slate-800 font-semibold">{o.customer_name}</td>
                      <td className="py-3.5 px-3">
                        <span
                          className={cn(
                            'inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold uppercase',
                            o.payment_method === 'COD'
                              ? 'bg-amber-50 text-amber-800 border border-amber-200'
                              : 'bg-navy-50 text-navy-800 border border-navy-100'
                          )}
                        >
                          {o.payment_method}
                        </span>
                      </td>
                      <td className="py-3.5 px-3 font-bold text-slate-900">
                        {formatRupiah(o.final_price)}
                      </td>
                      <td className="py-3.5 px-4 sm:px-6 text-right">
                        <Badge
                          variant={
                            o.status === 'completed'
                              ? 'success'
                              : o.status === 'cancelled'
                              ? 'danger'
                              : 'info'
                          }
                          size="sm"
                        >
                          {o.status}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </DashboardLayout>
  );
}
