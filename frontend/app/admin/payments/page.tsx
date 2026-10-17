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
      header: 'No. Pembayaran',
      accessor: (item) => (
        <div>
          <span className="font-mono font-medium text-slate-900">{item.payment_number}</span>
          <div className="text-[10px] text-slate-400 font-mono">
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
      header: 'No. Pesanan',
      accessor: (item) => (
        <span className="font-mono text-slate-700">{item.order_number || '-'}</span>
      ),
    },
    {
      header: 'Pelanggan / Toko',
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
        <span className="font-mono uppercase text-xs font-medium text-slate-700">
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
      header: 'Ref Provider',
      accessor: (item) => (
        <span className="font-mono text-[11px] text-slate-500" title={item.provider_reference || ''}>
          {item.provider_reference ? item.provider_reference.slice(0, 16) + '...' : '-'}
        </span>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <AdminHeader
        title="Transaksi & Pembayaran"
        subtitle="Daftar transaksi pembayaran masuk via Xendit VA, QRIS, dan COD (Read-Only)"
        onRefresh={() => fetchPayments(true)}
        refreshing={refreshing}
      />

      {/* Filters Toolbar */}
      <div className="flex flex-col sm:flex-row gap-2.5 items-center justify-between bg-white border border-slate-200 rounded-lg p-3">
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" strokeWidth={1.5} />
            <input
              type="text"
              placeholder="Cari no. bayar, referensi..."
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value);
                setPage(1);
              }}
              className="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400"
            />
          </div>

          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            className="py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-200 rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer"
          >
            <option value="">Semua Status</option>
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
            className="py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-200 rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer"
          >
            <option value="">Semua Metode</option>
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
