'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Card,
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
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  DollarSign,
  TrendingUp,
  TrendingDown,
  Wallet,
  ShoppingBag,
  Truck,
  Sparkles,
  ArrowDownLeft,
  ArrowUpRight,
  Filter,
  RotateCw,
  CreditCard,
  Building2,
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
  const [totalPages, setTotalPages] = useState<number>(1);

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
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat data kas & ledger toko.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadLedgerData();
  }, [selectedType, selectedDirection, page]);

  const handleLogout = async () => {
    await logout();
    router.push(LOGIN_PATH);
  };

  const getEntryBadge = (type: string) => {
    switch (type) {
      case 'PAYMENT_RECEIPT':
        return <Badge variant="success" className="text-[10px]">Pembayaran Digital</Badge>;
      case 'COD_DEPOSIT':
        return <Badge variant="teal" className="text-[10px]">Setoran COD</Badge>;
      case 'COURIER_WAGE':
        return <Badge variant="warning" className="text-[10px]">Upah Kurir</Badge>;
      case 'PLATFORM_COMMISSION':
        return <Badge variant="secondary" className="text-[10px]">Komisi Platform</Badge>;
      default:
        return <Badge variant="secondary" className="text-[10px]">{type}</Badge>;
    }
  };

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || 'admin@toko.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-8">
        {/* Header Title & Refresh */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="font-heading font-extrabold text-2xl text-navy-900 flex items-center gap-2.5">
              <Wallet className="w-7 h-7 text-teal-600" />
              <span>Buku Kas & Ledger Toko</span>
            </h1>
            <p className="text-xs text-slate-500 mt-1">
              Catatan transparansi keuangan pemasukan, setoran COD, upah kurir, dan komisi platform.
            </p>
          </div>

          <Button
            variant="outline"
            onClick={loadLedgerData}
            disabled={loading}
            className="rounded-xl text-xs font-bold min-h-[44px]"
          >
            <RotateCw className={cn('w-4 h-4 mr-2', loading && 'animate-spin')} />
            <span>Segarkan Data</span>
          </Button>
        </div>

        {/* Loading State */}
        {loading && !summary && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
              <Skeleton className="h-36 rounded-3xl" />
              <Skeleton className="h-36 rounded-3xl" />
              <Skeleton className="h-36 rounded-3xl" />
            </div>
            <Skeleton className="h-80 rounded-3xl" />
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <Card variant="default" className="p-8 text-center bg-white rounded-3xl border border-red-200">
            <ErrorState
              title="Gagal Memuat Ledger"
              message={error}
              retryText="Coba Lagi"
              onRetry={loadLedgerData}
            />
          </Card>
        )}

        {/* Content */}
        {summary && (
          <>
            {/* Bento Grid Summary Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
              {/* 1. Net Balance */}
              <Card variant="bento" className="p-6 bg-gradient-to-br from-navy-900 to-navy-950 text-white shadow-md relative overflow-hidden">
                <div className="absolute top-0 right-0 w-32 h-32 bg-teal-500/10 rounded-full blur-2xl" />
                <div className="relative z-10 space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-slate-300">Saldo Bersih Toko</span>
                    <div className="w-8 h-8 rounded-xl bg-teal-400/20 flex items-center justify-center text-teal-300">
                      <Wallet className="w-4 h-4" />
                    </div>
                  </div>
                  <div>
                    <span className="font-heading font-extrabold text-2xl text-teal-300 tabular-nums">
                      {formatRupiah(summary.net_balance)}
                    </span>
                    <span className="text-[11px] text-slate-400 block mt-1">
                      {summary.total_transactions} transaksi tercatat
                    </span>
                  </div>
                </div>
              </Card>

              {/* 2. Total Income (Credit) */}
              <Card variant="bento" className="p-6 bg-white border border-slate-200 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-slate-500">Total Pemasukan (Kredit)</span>
                  <div className="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-700">
                    <TrendingUp className="w-4 h-4" />
                  </div>
                </div>
                <div>
                  <span className="font-heading font-extrabold text-2xl text-emerald-600 tabular-nums">
                    {formatRupiah(summary.total_income)}
                  </span>
                  <div className="text-[11px] text-slate-500 space-y-0.5 mt-1">
                    <p>• Digital (Xendit): <b>{formatRupiah(summary.breakdown.digital_payments)}</b></p>
                    <p>• Setoran COD: <b>{formatRupiah(summary.breakdown.cod_deposits)}</b></p>
                  </div>
                </div>
              </Card>

              {/* 3. Total Expense (Debit) */}
              <Card variant="bento" className="p-6 bg-white border border-slate-200 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-slate-500">Total Pengeluaran (Debit)</span>
                  <div className="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center text-red-700">
                    <TrendingDown className="w-4 h-4" />
                  </div>
                </div>
                <div>
                  <span className="font-heading font-extrabold text-2xl text-red-600 tabular-nums">
                    {formatRupiah(summary.total_expense)}
                  </span>
                  <div className="text-[11px] text-slate-500 space-y-0.5 mt-1">
                    <p>• Upah Kurir Toko: <b>{formatRupiah(summary.breakdown.courier_wages)}</b></p>
                    <p>• Komisi Platform: <b>{formatRupiah(summary.breakdown.platform_commission)}</b></p>
                  </div>
                </div>
              </Card>
            </div>

            {/* Financial Business Rules Banner */}
            <div className="p-4 bg-sky-50 rounded-2xl border border-sky-200 flex items-start gap-3 text-xs text-sky-950">
              <Info className="w-5 h-5 text-teal-600 shrink-0 mt-0.5" />
              <div>
                <strong className="block font-bold">Aturan Akuntansi LaundryKu:</strong>
                <span>
                  Uang tunai COD disetorkan langsung ke kas toko. Upah kurir dibayarkan per order selesai dari dana toko. Komisi platform bernilai flat Rp1.000 per order yang selesai secara penuh.
                </span>
              </div>
            </div>

            {/* Ledger Transactions Card */}
            <Card variant="bento" className="p-6 sm:p-8 space-y-6">
              {/* Filter Tabs */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-xs font-bold text-slate-700 mr-2 flex items-center gap-1">
                    <Filter className="w-3.5 h-3.5 text-teal-600" />
                    <span>Filter Tipe:</span>
                  </span>
                  {[
                    { id: 'ALL', label: 'Semua Transaksi' },
                    { id: 'PAYMENT_RECEIPT', label: 'Digital Xendit' },
                    { id: 'COD_DEPOSIT', label: 'Setoran COD' },
                    { id: 'COURIER_WAGE', label: 'Upah Kurir' },
                    { id: 'PLATFORM_COMMISSION', label: 'Komisi Platform' },
                  ].map((tab) => (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => {
                        setSelectedType(tab.id);
                        setPage(1);
                      }}
                      className={cn(
                        'px-3 py-1.5 rounded-xl text-xs font-bold transition-all min-h-[36px]',
                        selectedType === tab.id
                          ? 'bg-navy-900 text-white shadow-sm'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                      )}
                    >
                      {tab.label}
                    </button>
                  ))}
                </div>

                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold text-slate-500">Arah:</span>
                  <select
                    value={selectedDirection}
                    onChange={(e) => {
                      setSelectedDirection(e.target.value);
                      setPage(1);
                    }}
                    className="py-1.5 px-3 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 bg-white"
                  >
                    <option value="ALL">Semua (Debit/Kredit)</option>
                    <option value="credit">Hanya Kredit (+)</option>
                    <option value="debit">Hanya Debit (-)</option>
                  </select>
                </div>
              </div>

              {/* Transactions List */}
              {entries.length === 0 ? (
                <EmptyState
                  title="Belum Ada Transaksi Ledger"
                  description="Setiap pesanan yang diselesaikan oleh kurir akan otomatis dicatat ke buku kas ini."
                />
              ) : (
                <div className="space-y-3">
                  {entries.map((item) => (
                    <div
                      key={item.id}
                      className="p-4 bg-sky-50/40 rounded-2xl border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                    >
                      <div className="flex items-start gap-3">
                        <div
                          className={cn(
                            'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
                            item.direction === 'credit'
                              ? 'bg-emerald-100 text-emerald-700'
                              : 'bg-red-100 text-red-700'
                          )}
                        >
                          {item.direction === 'credit' ? (
                            <ArrowDownLeft className="w-5 h-5" />
                          ) : (
                            <ArrowUpRight className="w-5 h-5" />
                          )}
                        </div>
                        <div>
                          <div className="flex items-center gap-2 mb-1 flex-wrap">
                            {getEntryBadge(item.entry_type)}
                            {item.order_number && (
                              <Link
                                href={`/store/orders/${item.order_id}`}
                                className="font-mono font-bold text-navy-900 hover:text-teal-600 transition-colors"
                              >
                                {item.order_number}
                              </Link>
                            )}
                          </div>
                          <p className="text-slate-700 font-medium">
                            {item.description || 'Pencatatan transaksi kas.'}
                          </p>
                          <span className="text-[11px] text-slate-400 block mt-1">
                            {formatDate(item.created_at, 'DD MMMM YYYY, HH:mm')}
                            {item.created_by_name && ` • Oleh: ${item.created_by_name}`}
                          </span>
                        </div>
                      </div>

                      <div className="text-left sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-200/60">
                        <span
                          className={cn(
                            'font-heading font-extrabold text-base tabular-nums',
                            item.direction === 'credit' ? 'text-emerald-600' : 'text-red-600'
                          )}
                        >
                          {item.direction === 'credit' ? '+' : '-'} {formatRupiah(item.amount)}
                        </span>
                        <span className="text-[10px] uppercase font-bold text-slate-400 block">
                          {item.direction === 'credit' ? 'Pemasukan' : 'Pengeluaran'}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </Card>
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
