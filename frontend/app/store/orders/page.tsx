'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Badge,
  Button,
  Tabs,
  Skeleton,
  EmptyState,
  ErrorState,
} from '@/components/ui';
import { getOrders, OrderDetail, ORDER_STATUS_META } from '@/lib/order';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, AuthUser, logoutAndRedirectHome } from '@/lib/auth';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  Calendar,
  User,
  ChevronRight,
  RefreshCw,
} from 'lucide-react';

export default function StoreAdminOrdersPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [orders, setOrders] = useState<OrderDetail[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<string>('all');

  const loadStoreOrders = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);
      const res = await getOrders({ role: 'store_admin', per_page: 50 });
      setOrders(res || []);
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('laundryku_sidebar_count_refresh'));
      }
    } catch (err: unknown) {
      setError(formatFriendlyErrorMessage(err, 'Gagal memuat pesanan toko.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadStoreOrders();
  }, []);

  const handleLogout = async () => {
    await logoutAndRedirectHome('mitra');
  };

  // Filter orders by active tab
  const filteredOrders = React.useMemo(() => {
    if (activeTab === 'all') return orders;
    if (activeTab === 'pending_pickup') {
      return orders.filter((o) => o.status === 'PENDING_PICKUP');
    }
    if (activeTab === 'need_weigh') {
      return orders.filter((o) => o.status === 'AT_STORE' || o.status === 'PICKED_UP' || o.status === 'PICKUP_ASSIGNED');
    }
    if (activeTab === 'processing') {
      return orders.filter((o) => o.status === 'PAID' || o.status === 'PROCESSING');
    }
    if (activeTab === 'delivery') {
      return orders.filter((o) => o.status === 'READY_FOR_DELIVERY' || o.status === 'DELIVERY_ASSIGNED' || o.status === 'OUT_FOR_DELIVERY');
    }
    if (activeTab === 'completed') {
      return orders.filter((o) => o.status === 'COMPLETED');
    }
    return orders;
  }, [orders, activeTab]);

  const tabs = [
    { id: 'all', label: 'Semua', count: orders.length },
    {
      id: 'pending_pickup',
      label: 'Perlu kurir jemput',
      count: orders.filter((o) => o.status === 'PENDING_PICKUP').length,
    },
    {
      id: 'need_weigh',
      label: 'Penjemputan & timbang',
      count: orders.filter((o) => o.status === 'AT_STORE' || o.status === 'PICKED_UP' || o.status === 'PICKUP_ASSIGNED').length,
    },
    {
      id: 'processing',
      label: 'Sedang dicuci',
      count: orders.filter((o) => o.status === 'PAID' || o.status === 'PROCESSING').length,
    },
    {
      id: 'delivery',
      label: 'Pengantaran',
      count: orders.filter((o) => o.status === 'READY_FOR_DELIVERY' || o.status === 'DELIVERY_ASSIGNED' || o.status === 'OUT_FOR_DELIVERY').length,
    },
    {
      id: 'completed',
      label: 'Selesai',
      count: orders.filter((o) => o.status === 'COMPLETED').length,
    },
  ];

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || 'admin@toko.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-6">
        {/* Compact Workspace Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-1">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
              Daftar pesanan masuk
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Kelola status pesanan dari penugasan kurir jemput, penimbangan, proses cuci, hingga serah terima.
            </p>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={loadStoreOrders}
            disabled={loading}
            className="rounded-xl border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-2xs flex items-center gap-1.5 self-start sm:self-auto min-h-[38px] px-3.5 text-xs font-semibold"
          >
            <RefreshCw className={cn('w-3.5 h-3.5', loading && 'animate-spin')} strokeWidth={1.75} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* Filter Tabs */}
        <div className="overflow-x-auto pb-1 [scrollbar-width:none]">
          <Tabs
            tabs={tabs}
            activeTab={activeTab}
            onChange={setActiveTab}
            variant="boxed"
            className="min-w-max"
          />
        </div>

        {/* Loading Skeleton */}
        {loading && (
          <div className="space-y-3.5">
            {[1, 2, 3].map((i) => (
              <div key={i} className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-3">
                <div className="flex justify-between items-center">
                  <Skeleton className="h-4 w-40" />
                  <Skeleton className="h-6 w-24 rounded-lg" />
                </div>
                <Skeleton className="h-14 w-full rounded-xl" />
              </div>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="p-8 text-center bg-white rounded-2xl border border-rose-200/80 shadow-2xs">
            <ErrorState
              title="Gagal memuat pesanan toko"
              message={error}
              retryText="Coba lagi"
              onRetry={loadStoreOrders}
            />
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && filteredOrders.length === 0 && (
          <div className="p-10 text-center bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
            <EmptyState
              title="Belum ada pesanan pada kategori ini"
              description="Tidak ada transaksi laundry yang memerlukan tindakan pada filter status yang dipilih."
              actionText="Tampilkan semua pesanan"
              onAction={() => setActiveTab('all')}
            />
          </div>
        )}

        {/* Orders List */}
        {!loading && !error && filteredOrders.length > 0 && (
          <div className="space-y-3.5">
            {filteredOrders.map((order) => {
              const statusMeta = ORDER_STATUS_META[order.status] || {
                label: order.status,
                badgeVariant: 'secondary',
                description: '',
              };

              return (
                <div
                  key={order.id}
                  className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs hover:border-slate-300 transition-colors space-y-4"
                >
                  {/* Order Card Header */}
                  <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                      <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                        <span className="font-mono font-bold text-xs text-navy-900 bg-sky-50 px-2.5 py-0.5 rounded-md border border-sky-100">
                          {order.order_number}
                        </span>
                        <span className="text-xs text-slate-300">•</span>
                        <span className="text-xs text-slate-500 flex items-center gap-1 font-mono">
                          <Calendar className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                          {formatDate(order.created_at, 'DD MMMM YYYY, HH:mm')}
                        </span>
                      </div>
                      <h3 className="font-semibold text-sm text-navy-950 flex items-center gap-1.5">
                        <User className="w-4 h-4 text-slate-500 shrink-0" strokeWidth={1.75} />
                        <span>Pelanggan: {order.customer_name || 'Pelanggan'}</span>
                      </h3>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                      <Badge variant={statusMeta.badgeVariant} className="px-2.5 py-0.5 text-[11px] rounded-md font-semibold">
                        {statusMeta.label}
                      </Badge>
                      <Badge
                        variant={order.payment_status === 'PAID' ? 'success' : 'warning'}
                        className="px-2 py-0.5 text-[11px] rounded-md font-semibold"
                      >
                        {order.payment_status === 'PAID' ? 'Lunas' : 'Belum bayar'}
                      </Badge>
                    </div>
                  </div>

                  {/* Order Card Body Details */}
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5 text-xs">
                    <div>
                      <span className="text-slate-400 block mb-0.5 text-[11px]">Alamat penjemputan:</span>
                      <p className="text-slate-700 font-medium line-clamp-1">
                        {order.pickup_address?.address_text && order.pickup_address.address_text.trim() !== '' && order.pickup_address.address_text !== '-'
                          ? order.pickup_address.address_text
                          : 'Alamat tidak tersedia'}
                      </p>
                    </div>

                    <div>
                      <span className="text-slate-400 block mb-0.5 text-[11px]">Berat aktual / kurir:</span>
                      <p className="text-slate-700 font-medium">
                        {order.actual_weight_kg ? `${order.actual_weight_kg} kg` : 'Belum ditimbang'} • {order.courier_name || 'Belum ditugaskan'}
                      </p>
                    </div>

                    <div>
                      <span className="text-slate-400 block mb-0.5 text-[11px]">
                        {order.final_amount !== null ? 'Total final:' : 'Estimasi tagihan:'}
                      </span>
                      <span className="font-bold text-sm text-navy-950 font-mono tabular-nums">
                        {formatRupiah(order.final_amount !== null ? order.final_amount : order.estimated_amount)}
                      </span>
                    </div>
                  </div>

                  {/* Order Card Footer */}
                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
                    <span className="text-[11px] text-slate-400 line-clamp-1">
                      {statusMeta.description}
                    </span>
                    <Link
                      href={`/store/orders/${order.id}`}
                      className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-navy-900 hover:bg-navy-800 text-white rounded-xl text-xs font-semibold transition-all shadow-2xs min-h-[36px] shrink-0"
                    >
                      <span>Proses pesanan</span>
                      <ChevronRight className="w-3.5 h-3.5 text-slate-300" strokeWidth={1.75} />
                    </Link>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
