'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AdminOrder } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search, Eye, X, ReceiptText, Store, User, Bike, Coins } from 'lucide-react';

export default function AdminOrdersPage() {
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [selectedOrder, setSelectedOrder] = useState<AdminOrder | null>(null);

  const fetchOrders = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const res = await adminApi.getOrders({
        status: statusFilter || undefined,
        q: searchQuery || undefined,
        page,
      });
      setOrders(res.data || []);
      if (res.meta) {
        setTotal(res.meta.total || 0);
        setLastPage(res.meta.last_page || 1);
      }
    } catch {
      setOrders([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [statusFilter, searchQuery, page]);

  useEffect(() => {
    fetchOrders();
  }, [fetchOrders]);

  const formatRupiah = (val: number | null | undefined) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(val || 0);
  };

  const columns: Column<AdminOrder>[] = [
    {
      header: 'No. pesanan',
      accessor: (item) => (
        <div>
          <span className="font-mono font-semibold text-slate-900">{item.order_number}</span>
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
      header: 'Pelanggan',
      accessor: (item) => (
        <div>
          <div className="font-medium text-slate-800">{item.customer?.name || '-'}</div>
          <div className="text-[11px] text-slate-500 font-mono">{item.customer?.phone || ''}</div>
        </div>
      ),
    },
    {
      header: 'Toko outlet',
      accessor: (item) => <span className="font-medium text-slate-700">{item.store?.name || '-'}</span>,
    },
    {
      header: 'Status pesanan',
      accessor: (item) => <StatusText status={item.status} />,
    },
    {
      header: 'Pembayaran',
      accessor: (item) => <StatusText status={item.payment_status} />,
    },
    {
      header: 'Nominal akhir',
      accessor: (item) => (
        <span className="font-mono tabular-nums font-semibold text-slate-900">
          {formatRupiah(item.final_amount ?? item.estimated_amount)}
        </span>
      ),
    },
    {
      header: 'Aksi',
      className: 'text-right',
      accessor: (item) => (
        <button
          onClick={() => setSelectedOrder(item)}
          className="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-700 bg-slate-100 hover:bg-navy-50 hover:text-navy-700 transition-colors cursor-pointer"
          title="Lihat detail pesanan"
          aria-label="Lihat detail pesanan"
        >
          <Eye className="w-3.5 h-3.5" />
          <span>Detail</span>
        </button>
      ),
    },
  ];

  return (
    <div className="space-y-5">
      <AdminHeader
        title="Daftar pesanan"
        subtitle="Monitoring pesanan laundry seluruh outlet toko secara terpusat (mode baca)"
        onRefresh={() => fetchOrders(true)}
        refreshing={refreshing}
      />

      {/* Filters Toolbar */}
      <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white border border-slate-200/80 rounded-2xl p-3.5 shadow-2xs">
        <div className="flex flex-col sm:flex-row items-center gap-2.5 w-full sm:w-auto">
          <div className="relative w-full sm:w-72">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Cari no. pesanan, pelanggan..."
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
            <option value="">Semua status pesanan</option>
            <option value="PENDING_PICKUP">PENDING PICKUP</option>
            <option value="PICKUP_ASSIGNED">PICKUP ASSIGNED</option>
            <option value="PICKED_UP">PICKED UP</option>
            <option value="AT_STORE">AT STORE</option>
            <option value="PROCESSING">PROCESSING</option>
            <option value="READY_FOR_DELIVERY">READY FOR DELIVERY</option>
            <option value="OUT_FOR_DELIVERY">OUT FOR DELIVERY</option>
            <option value="COMPLETED">COMPLETED</option>
            <option value="CANCELLED">CANCELLED</option>
            <option value="PAYMENT_EXPIRED">PAYMENT EXPIRED</option>
          </select>
        </div>
      </div>

      {/* Orders Table */}
      <DataTable
        columns={columns}
        data={orders}
        keyExtractor={(item) => item.id}
        loading={loading}
        emptyText="Tidak ada data pesanan yang sesuai filter."
        pagination={{
          currentPage: page,
          lastPage: lastPage,
          total: total,
          onPageChange: (p) => setPage(p),
        }}
      />

      {/* Order Detail Modal */}
      {selectedOrder && (
        <div className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200/90 max-w-lg w-full p-6 space-y-5 shadow-xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-start justify-between pb-4 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-navy-50 text-navy-700 flex items-center justify-center">
                  <ReceiptText className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-base font-semibold text-slate-900">Detail pesanan</h3>
                  <p className="font-mono text-xs font-medium text-slate-500">{selectedOrder.order_number}</p>
                </div>
              </div>
              <button
                onClick={() => setSelectedOrder(null)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <span className="text-slate-500 block mb-1">Status pesanan</span>
                <div><StatusText status={selectedOrder.status} /></div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <span className="text-slate-500 block mb-1">Status pembayaran</span>
                <div><StatusText status={selectedOrder.payment_status} /></div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <div className="flex items-center gap-1.5 text-slate-500 mb-1">
                  <Store className="w-3.5 h-3.5" />
                  <span>Toko outlet</span>
                </div>
                <div className="font-semibold text-slate-800">{selectedOrder.store?.name || '-'}</div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <div className="flex items-center gap-1.5 text-slate-500 mb-1">
                  <User className="w-3.5 h-3.5" />
                  <span>Pelanggan</span>
                </div>
                <div className="font-semibold text-slate-800">{selectedOrder.customer?.name || '-'}</div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <div className="flex items-center gap-1.5 text-slate-500 mb-1">
                  <Bike className="w-3.5 h-3.5" />
                  <span>Kurir</span>
                </div>
                <div className="font-semibold text-slate-800">{selectedOrder.courier?.name || 'Belum ditugaskan'}</div>
              </div>
              <div className="p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                <div className="flex items-center gap-1.5 text-slate-500 mb-1">
                  <Coins className="w-3.5 h-3.5" />
                  <span>Komisi platform</span>
                </div>
                <div className="font-mono text-slate-800 font-semibold">{formatRupiah(selectedOrder.commission_amount)}</div>
              </div>
            </div>

            {selectedOrder.items && selectedOrder.items.length > 0 && (
              <div className="pt-2 border-t border-slate-100">
                <span className="text-xs font-semibold text-slate-700 block mb-2.5">Item layanan</span>
                <div className="divide-y divide-slate-100 border border-slate-200/80 rounded-xl overflow-hidden bg-slate-50/40">
                  {selectedOrder.items.map((it) => (
                    <div key={it.id} className="p-3 flex justify-between items-center text-xs">
                      <div>
                        <div className="font-medium text-slate-800">{it.service_name_snapshot}</div>
                        <div className="text-[11px] text-slate-500 font-mono mt-0.5">
                          {it.quantity_actual ?? it.quantity_estimated} unit @ {formatRupiah(it.unit_price_snapshot)}
                        </div>
                      </div>
                      <div className="font-mono tabular-nums font-semibold text-slate-900">
                        {formatRupiah(it.line_final_amount ?? (it.quantity_estimated * it.unit_price_snapshot))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="pt-3 border-t border-slate-100 flex justify-end">
              <button
                onClick={() => setSelectedOrder(null)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-colors cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
