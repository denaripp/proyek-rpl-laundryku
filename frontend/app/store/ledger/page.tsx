'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Badge,
  Button,
  Skeleton,
  ErrorState,
  EmptyState,
} from '@/components/ui';
import {
  getStoreLedger,
  StoreLedgerSummary,
  StoreLedgerEntryItem,
} from '@/lib/order';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, AuthUser, logoutAndRedirectHome } from '@/lib/auth';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  TrendingUp,
  TrendingDown,
  Wallet,
  ArrowDownLeft,
  ArrowUpRight,
  Filter,
  RotateCw,
  Info,
} from 'lucide-react';

export default function StoreLedgerPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [summary, setSummary] = useState<StoreLedgerSummary | null>(null);
  const [entries, setEntries] = useState<StoreLedgerEntryItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [selectedType, setSelectedType] = useState<string>('ALL');
  const [selectedDirection, setSelectedDirection] = useState<string>('ALL');
  const [page, setPage] = useState<number>(1);

  const loadLedgerData = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);

      const res = await getStoreLedger({
        entry_type: selectedType !== 'ALL' ? selectedType : undefined,
        direction: selectedDirection !== 'ALL' ? selectedDirection : undefined,
        page,
      });

      setSummary(res.summary);
      setEntries(res.entries);
    } catch (err: unknown) {
      setError(formatFriendlyErrorMessage(err, 'Gagal memuat data kas & ledger toko.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadLedgerData();
  }, [selectedType, selectedDirection, page]);

  const handleLogout = async () => {
    await logoutAndRedirectHome('mitra');
  };

  const getEntryBadge = (type: string) => {
    switch (type) {
      case 'PAYMENT_RECEIPT':
        return <Badge variant="success" className="text-[10px] rounded-md font-semibold">Digital Xendit</Badge>;
      case 'COD_DEPOSIT':
        return <Badge variant="teal" className="text-[10px] rounded-md font-semibold">Setoran COD</Badge>;
      case 'COURIER_WAGE':
        return <Badge variant="warning" className="text-[10px] rounded-md font-semibold">Upah kurir</Badge>;
      case 'PLATFORM_COMMISSION':
        return <Badge variant="secondary" className="text-[10px] rounded-md font-semibold">Komisi platform</Badge>;
      default:
        return <Badge variant="secondary" className="text-[10px] rounded-md font-semibold">{type}</Badge>;
    }
  };

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || 'admin@toko.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-6">
        {/* Header Title & Refresh */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
              Buku kas & ledger toko
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Catatan transparansi keuangan pemasukan, setoran COD, upah kurir, dan komisi platform.
            </p>
          </div>

          <Button
            variant="outline"
            onClick={loadLedgerData}
            disabled={loading}
            className="rounded-xl border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-2xs flex items-center gap-1.5 self-start sm:self-auto min-h-[38px] px-3.5 text-xs font-semibold"
          >
            <RotateCw className={cn('w-3.5 h-3.5', loading && 'animate-spin')} strokeWidth={1.75} />
            <span>Segarkan data</span>
          </Button>
        </div>

        {/* Loading State */}
        {loading && !summary && (
          <div className="space-y-6 animate-pulse">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Skeleton className="h-32 rounded-2xl" />
              <Skeleton className="h-32 rounded-2xl" />
              <Skeleton className="h-32 rounded-2xl" />
            </div>
            <Skeleton className="h-72 rounded-2xl" />
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="p-8 text-center bg-white rounded-2xl border border-rose-200/80 shadow-2xs">
            <ErrorState
              title="Gagal memuat ledger"
              message={error}
              retryText="Coba lagi"
              onRetry={loadLedgerData}
            />
          </div>
        )}

        {/* Content */}
        {summary && (
          <>
            {/* Bento Grid Summary Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              {/* 1. Net Balance */}
              <div className="p-5 bg-navy-950 text-white rounded-2xl border border-navy-900 shadow-2xs space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-medium text-slate-300">Saldo bersih toko</span>
                  <div className="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-sky-300 border border-white/10">
                    <Wallet className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div>
                  <div className="font-mono font-bold text-2xl text-white tabular-nums">
                    {formatRupiah(summary.net_balance)}
                  </div>
                  <span className="text-[11px] text-slate-400 block mt-1 font-mono">
                    {summary.total_transactions} transaksi tercatat
                  </span>
                </div>
              </div>

              {/* 2. Total Income (Credit) */}
              <div className="p-5 bg-white border border-slate-200/80 rounded-2xl shadow-2xs space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-medium text-slate-500">Total pemasukan (kredit)</span>
                  <div className="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100">
                    <TrendingUp className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div>
                  <div className="font-mono font-bold text-2xl text-emerald-600 tabular-nums">
                    {formatRupiah(summary.total_income)}
                  </div>
                  <div className="text-[11px] text-slate-500 space-y-0.5 mt-1 font-mono">
                    <p>• Digital (Xendit): <b>{formatRupiah(summary.breakdown.digital_payments)}</b></p>
                    <p>• Setoran COD: <b>{formatRupiah(summary.breakdown.cod_deposits)}</b></p>
                  </div>
                </div>
              </div>

              {/* 3. Total Expense (Debit) */}
              <div className="p-5 bg-white border border-slate-200/80 rounded-2xl shadow-2xs space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-medium text-slate-500">Total pengeluaran (debit)</span>
                  <div className="w-8 h-8 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center border border-rose-100">
                    <TrendingDown className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                </div>
                <div>
                  <div className="font-mono font-bold text-2xl text-rose-600 tabular-nums">
                    {formatRupiah(summary.total_expense)}
                  </div>
                  <div className="text-[11px] text-slate-500 space-y-0.5 mt-1 font-mono">
                    <p>• Upah kurir toko: <b>{formatRupiah(summary.breakdown.courier_wages)}</b></p>
                    <p>• Komisi platform: <b>{formatRupiah(summary.breakdown.platform_commission)}</b></p>
                  </div>
                </div>
              </div>
            </div>

            {/* Financial Business Rules Banner */}
            <div className="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-start gap-3 text-xs text-slate-700 shadow-2xs">
              <Info className="w-4 h-4 text-navy-800 shrink-0 mt-0.5" strokeWidth={1.75} />
              <div>
                <strong className="block font-semibold text-navy-950">Aturan akuntansi LaundryKu:</strong>
                <span className="text-slate-600 mt-0.5 block leading-relaxed">
                  Uang tunai COD disetorkan langsung ke kas toko. Upah kurir dibayarkan per order selesai dari dana toko. Komisi platform bernilai flat Rp1.000 per order yang selesai secara penuh.
                </span>
              </div>
            </div>

            {/* Ledger Transactions Card */}
            <div className="p-5 sm:p-6 bg-white border border-slate-200/80 rounded-2xl shadow-2xs space-y-5">
              {/* Filter Tabs */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div className="flex items-center gap-1.5 flex-wrap">
                  <span className="text-xs font-semibold text-slate-500 mr-1 flex items-center gap-1">
                    <Filter className="w-3.5 h-3.5" strokeWidth={1.75} />
                    <span>Tipe:</span>
                  </span>
                  {[
                    { id: 'ALL', label: 'Semua transaksi' },
                    { id: 'PAYMENT_RECEIPT', label: 'Digital Xendit' },
                    { id: 'COD_DEPOSIT', label: 'Setoran COD' },
                    { id: 'COURIER_WAGE', label: 'Upah kurir' },
                    { id: 'PLATFORM_COMMISSION', label: 'Komisi platform' },
                  ].map((tab) => (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => {
                        setSelectedType(tab.id);
                        setPage(1);
                      }}
                      className={cn(
                        'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all min-h-[34px] cursor-pointer',
                        selectedType === tab.id
                          ? 'bg-navy-900 text-white shadow-2xs'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70'
                      )}
                    >
                      {tab.label}
                    </button>
                  ))}
                </div>

                <div className="flex items-center gap-2">
                  <span className="text-xs font-medium text-slate-500">Arah:</span>
                  <select
                    value={selectedDirection}
                    onChange={(e) => {
                      setSelectedDirection(e.target.value);
                      setPage(1);
                    }}
                    className="py-1.5 px-3 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-navy-900 shadow-2xs"
                  >
                    <option value="ALL">Semua (debit/kredit)</option>
                    <option value="credit">Hanya kredit (+)</option>
                    <option value="debit">Hanya debit (-)</option>
                  </select>
                </div>
              </div>

              {/* Transactions List */}
              {entries.length === 0 ? (
                <EmptyState
                  title="Belum ada transaksi ledger"
                  description="Setiap pesanan yang diselesaikan oleh kurir akan otomatis dicatat ke buku kas ini."
                />
              ) : (
                <div className="space-y-2.5">
                  {entries.map((item) => (
                    <div
                      key={item.id}
                      className="p-4 bg-slate-50/50 rounded-xl border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs hover:border-slate-300 transition-colors"
                    >
                      <div className="flex items-start gap-3">
                        <div
                          className={cn(
                            'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 border',
                            item.direction === 'credit'
                              ? 'bg-emerald-50 text-emerald-700 border-emerald-100'
                              : 'bg-rose-50 text-rose-700 border-rose-100'
                          )}
                        >
                          {item.direction === 'credit' ? (
                            <ArrowDownLeft className="w-4 h-4" strokeWidth={2} />
                          ) : (
                            <ArrowUpRight className="w-4 h-4" strokeWidth={2} />
                          )}
                        </div>
                        <div>
                          <div className="flex items-center gap-2 mb-1 flex-wrap">
                            {getEntryBadge(item.entry_type)}
                            {item.order_number && (
                              <Link
                                href={`/store/orders/${item.order_id}`}
                                className="font-mono font-bold text-navy-900 hover:text-navy-700 transition-colors"
                              >
                                {item.order_number}
                              </Link>
                            )}
                          </div>
                          <p className="text-slate-800 font-medium">
                            {item.description || 'Pencatatan transaksi kas.'}
                          </p>
                          <span className="text-[11px] text-slate-400 block mt-0.5 font-mono">
                            {formatDate(item.created_at, 'DD MMMM YYYY, HH:mm')}
                            {item.created_by_name && ` • Oleh: ${item.created_by_name}`}
                          </span>
                        </div>
                      </div>

                      <div className="text-left sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-200">
                        <span
                          className={cn(
                            'font-mono font-bold text-sm tabular-nums block',
                            item.direction === 'credit' ? 'text-emerald-600' : 'text-rose-600'
                          )}
                        >
                          {item.direction === 'credit' ? '+' : '-'} {formatRupiah(item.amount)}
                        </span>
                        <span className="text-[10px] uppercase font-semibold text-slate-400 block">
                          {item.direction === 'credit' ? 'Pemasukan' : 'Pengeluaran'}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
