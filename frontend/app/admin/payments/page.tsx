'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AdminPayment } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search } from 'lucide-react';

export default function AdminPaymentsPage() {
  const [payments, setPayments] = useState<AdminPayment[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [methodFilter, setMethodFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);

  const fetchPayments = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const res = await adminApi.getPayments({
        status: statusFilter || undefined,
        method: methodFilter || undefined,
        q: searchQuery || undefined,
        page,
      });
      setPayments(res.data || []);
      if (res.meta) {
        setTotal(res.meta.total || 0);
        setLastPage(res.meta.last_page || 1);
      }
    } catch {
      setPayments([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [statusFilter, methodFilter, searchQuery, page]);

  useEffect(() => {
    fetchPayments();
  }, [fetchPayments]);

  const formatRupiah = (val: number | null | undefined) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(val || 0);
  };

  const columns: Column<AdminPayment>[] = [
    {
      header: 'No. pembayaran',
      accessor: (item) => (
        <div>
          <span className="font-mono font-semibold text-slate-900">{item.payment_number}</span>
          <div className="text-[11px] text-slate-500 font-mono mt-0.5">
            {new Date(item.created_at).toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
            })}
          </div>
        </div>
      ),
    },
    {
      header: 'No. pesanan',
      accessor: (item) => (
        <span className="font-mono font-medium text-slate-700">{item.order_number || '-'}</span>
      ),
    },
    {
      header: 'Pelanggan & toko',
      accessor: (item) => (
        <div>
          <div className="font-medium text-slate-800">{item.customer_name || '-'}</div>
          <div className="text-[11px] text-slate-500">{item.store_name || '-'}</div>
        </div>
      ),
    },
    {
      header: 'Metode',
      accessor: (item) => (
        <span className="font-mono uppercase text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
          {item.method} ({item.provider})
        </span>
      ),
    },
    {
      header: 'Status',
      accessor: (item) => <StatusText status={item.status} />,
    },
    {
      header: 'Nominal',
      accessor: (item) => (
        <span className="font-mono tabular-nums font-semibold text-slate-900">
          {formatRupiah(item.amount)}
        </span>
      ),
    },
    {
      header: 'Ref provider',
      accessor: (item) => (
        <span className="font-mono text-[11px] text-slate-500" title={item.provider_reference || ''}>
          {item.provider_reference ? item.provider_reference.slice(0, 16) + '...' : '-'}
        </span>
      ),
    },
  ];

  return (
    <div className="space-y-5">
      <AdminHeader
        title="Transaksi dan pembayaran"
        subtitle="Daftar transaksi pembayaran masuk via Xendit VA, QRIS, dan COD (mode baca)"
        onRefresh={() => fetchPayments(true)}
        refreshing={refreshing}
      />

      {/* Filters Toolbar */}
      <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white border border-slate-200/80 rounded-2xl p-3.5 shadow-2xs">
        <div className="flex flex-col sm:flex-row items-center gap-2.5 w-full sm:w-auto">
          <div className="relative w-full sm:w-72">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Cari no. bayar, referensi..."
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value);
                setPage(1);
              }}
              className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 transition-all font-medium"
            />
          </div>

          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            className="w-full sm:w-auto py-2 px-3 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-700 font-medium focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 transition-all cursor-pointer"
          >
            <option value="">Semua status pembayaran</option>
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="failed">Failed</option>
            <option value="expired">Expired</option>
          </select>

          <select
            value={methodFilter}
            onChange={(e) => {
              setMethodFilter(e.target.value);
              setPage(1);
            }}
            className="w-full sm:w-auto py-2 px-3 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-700 font-medium focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 transition-all cursor-pointer"
          >
            <option value="">Semua metode</option>
            <option value="va">Virtual Account</option>
            <option value="qris">QRIS</option>
            <option value="cod">COD</option>
          </select>
        </div>
      </div>

      {/* Payments Table */}
      <DataTable
        columns={columns}
        data={payments}
        keyExtractor={(item) => item.id}
        loading={loading}
        emptyText="Tidak ada rekaman transaksi pembayaran yang cocok."
        pagination={{
          currentPage: page,
          lastPage: lastPage,
          total: total,
          onPageChange: (p) => setPage(p),
        }}
      />
    </div>
  );
}
