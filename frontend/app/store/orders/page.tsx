'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Card,
  Badge,
  Button,
  Tabs,
  Skeleton,
  EmptyState,
  ErrorState,
} from '@/components/ui';
import { getOrders, OrderDetail, ORDER_STATUS_META } from '@/lib/order';
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  ShoppingBag,
  Store,
  Calendar,
  User,
  MapPin,
  ChevronRight,
  RefreshCw,
  Clock,
  Scale,
  Sparkles,
  Truck,
  CheckCircle2,
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
      const res = await getOrders({ role: 'store_admin' });
      setOrders(res || []);
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat pesanan toko.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadStoreOrders();
  }, []);

  const handleLogout = async () => {
    await logout();
    router.push(LOGIN_PATH);
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
      label: 'Perlu Kurir Jemput',
      count: orders.filter((o) => o.status === 'PENDING_PICKUP').length,
    },
    {
      id: 'need_weigh',
      label: 'Penjemputan & Timbang',
      count: orders.filter((o) => o.status === 'AT_STORE' || o.status === 'PICKED_UP' || o.status === 'PICKUP_ASSIGNED').length,
    },
    {
      id: 'processing',
      label: 'Sedang Dicuci',
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
      <div className="space-y-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-3 py-1 rounded-full border border-teal-200/60 w-fit mb-2">
              <Store className="w-3.5 h-3.5" />
              <span>Manajemen Operasional Pesanan</span>
            </div>
            <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900">
              Daftar Pesanan Masuk
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
            className="rounded-xl flex items-center gap-1.5 self-start sm:self-auto"
          >
            <RefreshCw className={cn('w-4 h-4', loading && 'animate-spin')} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* Filter Tabs */}
        <div className="overflow-x-auto pb-2">
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
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <Card key={i} variant="default" className="p-6 bg-white rounded-3xl border border-slate-200">
                <div className="flex justify-between items-center mb-4">
                  <Skeleton className="h-5 w-40" />
                  <Skeleton className="h-7 w-28 rounded-full" />
                </div>
                <Skeleton className="h-16 w-full rounded-2xl" />
              </Card>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <Card variant="default" className="p-8 text-center bg-white rounded-3xl border border-red-200">
            <ErrorState
              title="Gagal Memuat Pesanan Toko"
              message={error}
              retryText="Coba Lagi"
              onRetry={loadStoreOrders}
            />
          </Card>
        )}

        {/* Empty State */}
        {!loading && !error && filteredOrders.length === 0 && (
          <Card variant="default" className="p-12 text-center bg-white rounded-3xl border border-slate-200">
            <EmptyState
              title="Belum Ada Pesanan pada Kategori Ini"
              description="Tidak ada transaksi laundry yang memerlukan tindakan pada filter status yang dipilih."
              actionText="Tampilkan Semua Pesanan"
              onAction={() => setActiveTab('all')}
            />
          </Card>
        )}

        {/* Orders List */}
        {!loading && !error && filteredOrders.length > 0 && (
          <div className="space-y-4">
            {filteredOrders.map((order) => {
              const statusMeta = ORDER_STATUS_META[order.status] || {
                label: order.status,
                badgeVariant: 'secondary',
                description: '',
              };

              return (
                <Card
                  key={order.id}
                  variant="bento"
                  className="p-6 transition-all duration-300 hover:border-teal-300"
                >
                  <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-4 border-b border-slate-100">
                    <div>
                      <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                        <span className="font-mono font-bold text-xs text-navy-900 bg-sky-50 px-2.5 py-0.5 rounded-md border border-slate-200/80">
                          {order.order_number}
                        </span>
                        <span className="text-xs text-slate-400">•</span>
                        <span className="text-xs text-slate-500 flex items-center gap-1">
                          <Calendar className="w-3.5 h-3.5" />
                          {formatDate(order.created_at, 'DD MMMM YYYY, HH:mm')}
                        </span>
                      </div>
                      <h3 className="font-heading font-bold text-base text-navy-900 flex items-center gap-2">
                        <User className="w-4 h-4 text-teal-600 shrink-0" />
                        <span>Pelanggan: {order.customer_name || 'Customer'}</span>
                      </h3>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                      <Badge variant={statusMeta.badgeVariant} className="px-3 py-1 text-xs">
                        {statusMeta.label}
                      </Badge>
                      <Badge
                        variant={order.payment_status === 'PAID' ? 'success' : 'warning'}
                        className="px-2.5 py-0.5 text-[11px]"
                      >
                        {order.payment_status === 'PAID' ? 'Lunas' : 'Belum Bayar'}
                      </Badge>
                    </div>
                  </div>

                  {/* Order Body Details */}
                  <div className="py-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                      <span className="text-slate-400 block mb-0.5">Alamat Penjemputan:</span>
                      <p className="text-slate-700 font-medium line-clamp-1">
                        {order.pickup_address?.address_text || '-'}
                      </p>
                    </div>

                    <div>
                      <span className="text-slate-400 block mb-0.5">Berat Aktual / Kurir:</span>
                      <p className="text-slate-700 font-medium">
                        {order.actual_weight_kg ? `${order.actual_weight_kg} kg` : 'Belum ditimbang'} • {order.courier_name || 'Belum ditugaskan'}
                      </p>
                    </div>

                    <div>
                      <span className="text-slate-400 block mb-0.5">
                        {order.final_amount !== null ? 'Total Final:' : 'Estimasi Tagihan:'}
                      </span>
                      <span className="font-heading font-extrabold text-base text-teal-600 tabular-nums">
                        {formatRupiah(order.final_amount !== null ? order.final_amount : order.estimated_amount)}
                      </span>
                    </div>
                  </div>

                  {/* Footer & Action CTA */}
                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span className="text-[11px] text-slate-400">
                      {statusMeta.description}
                    </span>
                    <Link
                      href={`/store/orders/${order.id}`}
                      className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-900 hover:bg-navy-800 text-white rounded-xl text-xs font-bold transition-all shadow-sm min-h-[38px]"
                    >
                      <span>Proses Pesanan</span>
                      <ChevronRight className="w-4 h-4 text-teal-400" />
                    </Link>
                  </div>
                </Card>
              );
            })}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
