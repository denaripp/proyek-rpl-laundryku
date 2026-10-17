'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AdminOrder } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search, Eye, X } from 'lucide-react';

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
      header: 'No. Pesanan',
      accessor: (item) => (
        <div>
          <span className="font-mono font-medium text-slate-900">{item.order_number}</span>
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
      header: 'Pelanggan',
      accessor: (item) => (
        <div>
          <div className="font-medium text-slate-800">{item.customer?.name || '-'}</div>
          <div className="text-[11px] text-slate-500">{item.customer?.phone || ''}</div>
        </div>
      ),
    },
    {
      header: 'Toko Outlet',
      accessor: (item) => <span className="text-slate-700">{item.store?.name || '-'}</span>,
    },
    {
      header: 'Status Pesanan',
      accessor: (item) => <StatusText status={item.status} />,
    },
    {
      header: 'Pembayaran',
      accessor: (item) => <StatusText status={item.payment_status} />,
    },
    {
      header: 'Nominal Akhir',
      accessor: (item) => (
        <span className="font-mono tabular-nums font-medium text-slate-900">
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
          className="p-1 rounded text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors cursor-pointer"
          title="Lihat Detail Pesanan"
          aria-label="Lihat detail pesanan"
        >
          <Eye className="w-4 h-4" strokeWidth={1.5} />
        </button>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <AdminHeader
        title="Daftar Pesanan"
        subtitle="Monitoring pesanan laundry seluruh outlet toko secara terpusat (Read-Only)"
        onRefresh={() => fetchOrders(true)}
        refreshing={refreshing}
      />

      {/* Filters Toolbar */}
      <div className="flex flex-col sm:flex-row gap-2.5 items-center justify-between bg-white border border-slate-200 rounded-lg p-3">
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" strokeWidth={1.5} />
            <input
              type="text"
              placeholder="Cari no. pesanan, pelanggan..."
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
        <div className="fixed inset-0 bg-slate-950/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg border border-slate-200 max-w-lg w-full p-5 space-y-4 shadow-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">Detail Pesanan</h3>
                <p className="font-mono text-xs text-slate-500">{selectedOrder.order_number}</p>
              </div>
              <button
                onClick={() => setSelectedOrder(null)}
                className="p-1 rounded text-slate-400 hover:text-slate-700"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
              <div>
                <span className="text-slate-500">Status Pesanan:</span>
                <div className="mt-0.5"><StatusText status={selectedOrder.status} /></div>
              </div>
              <div>
                <span className="text-slate-500">Status Pembayaran:</span>
                <div className="mt-0.5"><StatusText status={selectedOrder.payment_status} /></div>
              </div>
              <div>
                <span className="text-slate-500">Toko:</span>
                <div className="font-medium text-slate-800">{selectedOrder.store?.name || '-'}</div>
              </div>
              <div>
                <span className="text-slate-500">Pelanggan:</span>
                <div className="font-medium text-slate-800">{selectedOrder.customer?.name || '-'}</div>
              </div>
              <div>
                <span className="text-slate-500">Kurir:</span>
                <div className="font-medium text-slate-800">{selectedOrder.courier?.name || 'Belum ditugaskan'}</div>
              </div>
              <div>
                <span className="text-slate-500">Komisi Platform:</span>
                <div className="font-mono text-slate-800 font-medium">{formatRupiah(selectedOrder.commission_amount)}</div>
              </div>
            </div>

            {selectedOrder.items && selectedOrder.items.length > 0 && (
              <div className="pt-2 border-t border-slate-100">
                <span className="text-xs font-semibold text-slate-700 block mb-2">Item Layanan:</span>
                <div className="divide-y divide-slate-100 border border-slate-100 rounded">
                  {selectedOrder.items.map((it) => (
                    <div key={it.id} className="p-2 flex justify-between text-xs">
                      <div>
                        <div className="font-medium text-slate-800">{it.service_name_snapshot}</div>
                        <div className="text-[10px] text-slate-500">
                          {it.quantity_actual ?? it.quantity_estimated} unit @ {formatRupiah(it.unit_price_snapshot)}
                        </div>
                      </div>
                      <div className="font-mono tabular-nums text-slate-900">
                        {formatRupiah(it.line_final_amount ?? (it.quantity_estimated * it.unit_price_snapshot))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="pt-3 border-t border-slate-200 flex justify-end">
              <button
                onClick={() => setSelectedOrder(null)}
                className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-md transition-colors cursor-pointer"
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
