'use client';

import React, { useState, useEffect, use } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Card,
  Badge,
  Button,
  Input,
  Textarea,
  Timeline,
  TimelineEvent,
  Skeleton,
  ErrorState,
  Modal,
} from '@/components/ui';
import {
  getOrder,
  transitionOrder,
  OrderDetail,
  ORDER_STATUS_META,
  OrderStatus,
} from '@/lib/order';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  ArrowLeft,
  Store,
  Calendar,
  User,
  MapPin,
  Clock,
  Scale,
  Sparkles,
  Truck,
  CheckCircle2,
  AlertCircle,
  ShieldCheck,
  RotateCcw,
  ShoppingBag,
  Send,
} from 'lucide-react';

export default function StoreAdminOrderDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const resolvedParams = use(params);
  const orderId = resolvedParams.id;
  const router = useRouter();

  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [order, setOrder] = useState<OrderDetail | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Transition form states
  const [actionLoading, setActionLoading] = useState<boolean>(false);
  const [actionSuccessMsg, setActionSuccessMsg] = useState<string | null>(null);
  const [actionErrorMsg, setActionErrorMsg] = useState<string | null>(null);

  // Weighing inputs
  const [actualWeightKg, setActualWeightKg] = useState<number>(0);
  const [itemQuantities, setItemQuantities] = useState<Record<string, number>>({});
  const [transitionNote, setTransitionNote] = useState<string>('');

  // Courier assignment input
  const [courierNameInput, setCourierNameInput] = useState<string>('Kurir Toko');

  const loadOrderDetail = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);
      const res = await getOrder(orderId);
      setOrder(res);

      if (res.actual_weight_kg) {
        setActualWeightKg(res.actual_weight_kg);
      } else if (res.estimated_weight_kg) {
        setActualWeightKg(res.estimated_weight_kg);
      }

      // Initialize item actual quantities
      if (res.items) {
        const initItemQty: Record<string, number> = {};
        res.items.forEach((it) => {
          initItemQty[it.id] = it.quantity_actual !== null ? it.quantity_actual : it.quantity_estimated;
        });
        setItemQuantities(initItemQty);
      }
    } catch (err: unknown) {
      setError(formatFriendlyErrorMessage(err, 'Gagal memuat detail pesanan.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadOrderDetail();
  }, [orderId]);

  const handleLogout = async () => {
    await logout();
    router.push(LOGIN_PATH);
  };

  // Generic Transition Handler
  const executeTransition = async (toStatus: OrderStatus, customPayload: Record<string, unknown> = {}, defaultNote: string = '') => {
    if (!order) return;
    setActionLoading(true);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      const payload = {
        to_status: toStatus,
        note: transitionNote.trim() || defaultNote,
        ...customPayload,
      };

      const res = await transitionOrder(order.id, payload);
      setOrder(res);
      setTransitionNote('');
      setActionSuccessMsg(`Status pesanan berhasil diubah ke '${ORDER_STATUS_META[toStatus]?.label || toStatus}'.`);
      return res;
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal melakukan transisi status pesanan.');
      return null;
    } finally {
      setActionLoading(false);
    }
  };

  // Submit Weighing
  const handleWeighingSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (actualWeightKg <= 0) {
      setActionErrorMsg('Berat cucian aktual harus lebih dari 0 kg.');
      return;
    }

    const itemsPayload = order?.items?.map((it) => ({
      item_id: it.id,
      id: it.id,
      quantity_actual: itemQuantities[it.id] ?? (it.pricing_unit === 'kg' ? actualWeightKg : it.quantity_estimated),
    }));

    // Step 1: WEIGHED
    const weighedOrder = await executeTransition('WEIGHED', {
      actual_weight_kg: actualWeightKg,
      items: itemsPayload,
    }, `Penimbangan selesai: ${actualWeightKg} kg.`);

    // Step 2: Auto move to AWAITING_PRICE_APPROVAL (per PRD 7.3)
    if (weighedOrder) {
      setTimeout(async () => {
        await executeTransition('AWAITING_PRICE_APPROVAL', {}, 'Menunggu persetujuan harga final dari pelanggan.');
      }, 300);
    }
  };

  // Timeline events
  const timelineEvents: TimelineEvent[] = React.useMemo(() => {
    if (!order || !order.status_histories) return [];

    return order.status_histories.map((hist, idx) => {
      const statusMeta = ORDER_STATUS_META[hist.to_status] || { label: hist.to_status };
      const isLatest = idx === order.status_histories!.length - 1;

      return {
        id: hist.id || String(idx),
        title: statusMeta.label,
        description: hist.note || undefined,
        time: formatDate(hist.timestamp, 'DD MMM YYYY, HH:mm'),
        status: isLatest ? 'current' : 'completed',
      };
    });
  }, [order]);

  const currentStatusMeta = order ? ORDER_STATUS_META[order.status] || { label: order.status, badgeVariant: 'secondary', description: '' } : null;

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || 'admin@toko.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-8">
        {/* Back Navigation */}
        <div>
          <Link
            href="/store/orders"
            className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-navy-900 transition-colors min-h-[44px]"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Kembali ke Daftar Pesanan Toko</span>
          </Link>
        </div>

        {/* Loading Skeleton */}
        {loading && (
          <div className="space-y-6">
            <Skeleton className="h-12 w-2/3" />
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              <div className="lg:col-span-2 space-y-6">
                <Skeleton className="h-48 rounded-3xl" />
                <Skeleton className="h-64 rounded-3xl" />
              </div>
              <Skeleton className="h-96 rounded-3xl" />
            </div>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <Card variant="default" className="p-8 text-center bg-white rounded-3xl border border-red-200">
            <ErrorState
              title="Gagal Memuat Pesanan"
              message={error}
              retryText="Kembali ke Daftar Pesanan"
              onRetry={() => router.push('/store/orders')}
            />
          </Card>
        )}

        {/* Order Details & Operations Control Panel */}
        {!loading && !error && order && (
          <div className="space-y-8">
            {/* Header Card */}
            <Card variant="bento" className="p-6 sm:p-8">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                  <div className="flex items-center gap-2 mb-2 flex-wrap">
                    <span className="font-mono font-extrabold text-sm text-navy-900 bg-sky-50 px-3 py-1 rounded-lg border border-slate-200">
                      {order.order_number}
                    </span>
                    <span className="text-xs text-slate-400">•</span>
                    <span className="text-xs text-slate-500 flex items-center gap-1">
                      <Calendar className="w-3.5 h-3.5" />
                      Diterima: {formatDate(order.created_at, 'DD MMMM YYYY, HH:mm')}
                    </span>
                  </div>
                  <h1 className="font-heading font-extrabold text-2xl text-navy-900 flex items-center gap-2">
                    <User className="w-5 h-5 text-teal-600" />
                    <span>Customer: {order.customer_name || 'Pelanggan'}</span>
                  </h1>
                </div>

                <div className="flex items-center gap-3 flex-wrap">
                  <Badge variant={currentStatusMeta?.badgeVariant} className="px-4 py-1.5 text-xs font-bold">
                    {currentStatusMeta?.label}
                  </Badge>
                  <Badge
                    variant={order.payment_status === 'PAID' ? 'success' : 'warning'}
                    className="px-3 py-1 text-xs"
                  >
                    {order.payment_status === 'PAID' ? 'Lunas' : 'Belum Bayar'}
                  </Badge>
                </div>
              </div>

              {/* Action Feedback */}
              {actionSuccessMsg && (
                <div className="mt-4 p-4 bg-teal-50 border border-teal-200 rounded-2xl flex items-center gap-3 text-teal-900 text-xs font-semibold">
                  <CheckCircle2 className="w-5 h-5 text-teal-600 shrink-0" />
                  <span>{actionSuccessMsg}</span>
                </div>
              )}
              {actionErrorMsg && (
                <div className="mt-4 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-3 text-red-900 text-xs font-semibold">
                  <AlertCircle className="w-5 h-5 text-red-600 shrink-0" />
                  <span>{actionErrorMsg}</span>
                </div>
              )}

              {/* State Machine Action Controls per PRD 7.3 */}
              <div className="mt-6 pt-2">
                {/* 1. Status: PENDING_PICKUP -> Assign Courier */}
                {order.status === 'PENDING_PICKUP' && (
                  <div className="p-6 bg-sky-50 rounded-3xl border border-sky-200 space-y-4">
                    <div className="flex items-center gap-2 text-navy-900 font-heading font-bold text-base">
                      <Truck className="w-5 h-5 text-teal-600" />
                      <span>Tugaskan Kurir untuk Jemput Cucian</span>
                    </div>
                    <p className="text-xs text-slate-600">
                      Pesanan baru masuk dari customer. Tugaskan kurir toko untuk menjemput cucian ke alamat pelanggan.
                    </p>
                    <div className="flex flex-col sm:flex-row items-center gap-3">
                      <Button
                        onClick={() => executeTransition('PICKUP_ASSIGNED', {}, 'Kurir ditugaskan untuk menjemput cucian.')}
                        disabled={actionLoading}
                        className="w-full sm:w-auto bg-navy-900 hover:bg-navy-800 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                      >
                        <CheckCircle2 className="w-4 h-4 mr-2 text-teal-400" />
                        <span>{actionLoading ? 'Menugaskan...' : 'Tugaskan Kurir Jemput (PICKUP_ASSIGNED)'}</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 2. Status: PICKUP_ASSIGNED -> Mark Picked Up */}
                {order.status === 'PICKUP_ASSIGNED' && (
                  <div className="p-6 bg-sky-50 rounded-3xl border border-sky-200 space-y-4">
                    <div className="flex items-center gap-2 text-navy-900 font-heading font-bold text-base">
                      <Truck className="w-5 h-5 text-teal-600" />
                      <span>Kurir Sedang Menjemput Cucian</span>
                    </div>
                    <p className="text-xs text-slate-600">
                      Kurir telah ditugaskan. Setelah kurir menerima cucian dari pelanggan di lokasi, perbarui status menjadi PICKED_UP.
                    </p>
                    <Button
                      onClick={() => executeTransition('PICKED_UP', {}, 'Cucian telah diambil oleh kurir dari pelanggan.')}
                      disabled={actionLoading}
                      className="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <CheckCircle2 className="w-4 h-4 mr-2" />
                      <span>Konfirmasi Cucian Telah Dijemput (PICKED_UP)</span>
                    </Button>
                  </div>
                )}

                {/* 3. Status: PICKED_UP -> Mark AT_STORE */}
                {order.status === 'PICKED_UP' && (
                  <div className="p-6 bg-sky-50 rounded-3xl border border-sky-200 space-y-4">
                    <div className="flex items-center gap-2 text-navy-900 font-heading font-bold text-base">
                      <Store className="w-5 h-5 text-teal-600" />
                      <span>Cucian Menuju Outlet</span>
                    </div>
                    <p className="text-xs text-slate-600">
                      Setelah kurir tiba di outlet membawa cucian pelanggan, konfirmasi penerimaan cucian di toko.
                    </p>
                    <Button
                      onClick={() => executeTransition('AT_STORE', {}, 'Cucian telah tiba di outlet laundry.')}
                      disabled={actionLoading}
                      className="bg-navy-900 hover:bg-navy-800 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <CheckCircle2 className="w-4 h-4 mr-2 text-teal-400" />
                      <span>Konfirmasi Cucian Tiba di Outlet (AT_STORE)</span>
                    </Button>
                  </div>
                )}

                {/* 4. Status: AT_STORE -> Input Weight & Trigger AWAITING_PRICE_APPROVAL */}
                {order.status === 'AT_STORE' && (
                  <form onSubmit={handleWeighingSubmit} className="p-6 bg-amber-50/80 rounded-3xl border-2 border-amber-300 space-y-5">
                    <div className="flex items-start gap-3">
                      <div className="w-10 h-10 rounded-2xl bg-amber-100 flex items-center justify-center text-amber-700 shrink-0">
                        <Scale className="w-5 h-5" />
                      </div>
                      <div>
                        <h3 className="font-heading font-bold text-base text-amber-950">
                          Penimbangan Cucian & Hitung Harga Final
                        </h3>
                        <p className="text-xs text-amber-800 mt-0.5">
                          Masukkan berat aktual hasil timbangan outlet. Sistem akan otomatis menghitung harga final dan mengirimkan permintaan persetujuan ke customer.
                        </p>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                      <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">
                          Berat Aktual Bersih (kg) *
                        </label>
                        <Input
                          type="number"
                          step="0.05"
                          min="0.1"
                          required
                          value={actualWeightKg || ''}
                          onChange={(e) => setActualWeightKg(parseFloat(e.target.value) || 0)}
                          placeholder="contoh: 4.5"
                          className="text-xs"
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">
                          Catatan Hasil Timbang (Opsional)
                        </label>
                        <Input
                          type="text"
                          value={transitionNote}
                          onChange={(e) => setTransitionNote(e.target.value)}
                          placeholder="contoh: Ditimbang 4.5kg, kemeja dan celana lengkap."
                          className="text-xs"
                        />
                      </div>
                    </div>

                    {/* Service item quantities inputs if multi-item */}
                    {order.items && order.items.length > 1 && (
                      <div className="space-y-3 pt-2 border-t border-amber-200">
                        <span className="text-xs font-bold text-amber-950 block">Rincian Kuantitas per Item:</span>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                          {order.items.map((it) => (
                            <div key={it.id} className="p-3 bg-white rounded-xl border border-amber-200 text-xs flex items-center justify-between gap-2">
                              <div>
                                <span className="font-bold text-navy-900 block">{it.service_name}</span>
                                <span className="text-slate-500">/{it.pricing_unit}</span>
                              </div>
                              <div className="w-24">
                                <Input
                                  type="number"
                                  step="0.1"
                                  min="0.1"
                                  value={itemQuantities[it.id] ?? it.quantity_estimated}
                                  onChange={(e) => setItemQuantities({ ...itemQuantities, [it.id]: parseFloat(e.target.value) || 0 })}
                                  className="text-xs"
                                />
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    <Button
                      type="submit"
                      disabled={actionLoading || actualWeightKg <= 0}
                      className="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <CheckCircle2 className="w-4 h-4 mr-2" />
                      <span>{actionLoading ? 'Menyimpan...' : 'Simpan Penimbangan & Kirim ke Customer (WEIGHED)'}</span>
                    </Button>
                  </form>
                )}

                {/* 5. Status: AWAITING_PRICE_APPROVAL -> Info Banner */}
                {order.status === 'AWAITING_PRICE_APPROVAL' && (
                  <div className="p-6 bg-sky-50 rounded-3xl border border-slate-200 flex items-start gap-3 text-xs text-slate-700">
                    <Clock className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                      <strong className="block font-bold text-navy-900">Menunggu Keputusan Pelanggan</strong>
                      <span>
                        Harga final ({formatRupiah(order.final_amount || 0)}) telah dikirimkan ke pelanggan. Pesanan akan dapat diproses setelah pelanggan menyetujui harga dan menyelesaikan pembayaran.
                      </span>
                    </div>
                  </div>
                )}

                {/* 6. Status: PRICE_APPROVED / AWAITING_PAYMENT -> Info Banner */}
                {(order.status === 'PRICE_APPROVED' || order.status === 'AWAITING_PAYMENT') && (
                  <div className="p-6 bg-teal-50 rounded-3xl border border-teal-200 flex items-start gap-3 text-xs text-teal-900">
                    <Clock className="w-5 h-5 text-teal-600 shrink-0 mt-0.5" />
                    <div>
                      <strong className="block font-bold">Harga Disetujui — Menunggu Pembayaran</strong>
                      <span>
                        Customer telah menyetujui harga final ({formatRupiah(order.final_amount || 0)}). Menunggu verifikasi pembayaran atau konfirmasi sistem.
                      </span>
                    </div>
                  </div>
                )}

                {/* 7. Status: PAID -> Start Washing (PROCESSING) */}
                {order.status === 'PAID' && (
                  <div className="p-6 bg-emerald-50 rounded-3xl border-2 border-emerald-300 space-y-4">
                    <div className="flex items-center gap-2 text-emerald-950 font-heading font-bold text-base">
                      <Sparkles className="w-5 h-5 text-emerald-600" />
                      <span>Pembayaran Terverifikasi — Siap Dicuci</span>
                    </div>
                    <p className="text-xs text-emerald-800">
                      Pembayaran telah lunas. Klik tombol di bawah saat proses pencucian / pengeringan dimulai.
                    </p>
                    <Button
                      onClick={() => executeTransition('PROCESSING', {}, 'Cucian mulai masuk tahap pencucian.')}
                      disabled={actionLoading}
                      className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <Sparkles className="w-4 h-4 mr-2" />
                      <span>Mulai Proses Pencucian (PROCESSING)</span>
                    </Button>
                  </div>
                )}

                {/* 8. Status: PROCESSING -> Finish Washing (READY_FOR_DELIVERY) */}
                {order.status === 'PROCESSING' && (
                  <div className="p-6 bg-sky-50 rounded-3xl border border-sky-200 space-y-4">
                    <div className="flex items-center gap-2 text-navy-900 font-heading font-bold text-base">
                      <CheckCircle2 className="w-5 h-5 text-teal-600" />
                      <span>Proses Laundry Sedang Berjalan</span>
                    </div>
                    <p className="text-xs text-slate-600">
                      Setelah cucian selesai dicuci, dikeringkan, disetrika, dan dikemas rapi, tandai pesanan siap untuk diantar.
                    </p>
                    <Button
                      onClick={() => executeTransition('READY_FOR_DELIVERY', {}, 'Cucian selesai dikemas dan siap diantar.')}
                      disabled={actionLoading}
                      className="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <CheckCircle2 className="w-4 h-4 mr-2" />
                      <span>Cucian Selesai & Siap Diantar (READY_FOR_DELIVERY)</span>
                    </Button>
                  </div>
                )}

                {/* 9. Status: READY_FOR_DELIVERY -> Assign Delivery Courier */}
                {order.status === 'READY_FOR_DELIVERY' && (
                  <div className="p-6 bg-sky-50 rounded-3xl border border-sky-200 space-y-4">
                    <div className="flex items-center gap-2 text-navy-900 font-heading font-bold text-base">
                      <Truck className="w-5 h-5 text-teal-600" />
                      <span>Tugaskan Kurir Pengantaran</span>
                    </div>
                    <p className="text-xs text-slate-600">
                      Tugaskan kurir untuk mengantarkan pakaian bersih kembali ke alamat pelanggan.
                    </p>
                    <Button
                      onClick={() => executeTransition('DELIVERY_ASSIGNED', {}, 'Kurir pengantaran ditugaskan.')}
                      disabled={actionLoading}
                      className="bg-navy-900 hover:bg-navy-800 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <Truck className="w-4 h-4 mr-2 text-teal-400" />
                      <span>Tugaskan Kurir Pengantaran (DELIVERY_ASSIGNED)</span>
                    </Button>
                  </div>
                )}

                {/* 10. Status: DELIVERY_ASSIGNED -> OUT_FOR_DELIVERY */}
                {order.status === 'DELIVERY_ASSIGNED' && (
                  <div className="p-6 bg-sky-50 rounded-3xl border border-sky-200 space-y-4">
                    <div className="flex items-center gap-2 text-navy-900 font-heading font-bold text-base">
                      <Truck className="w-5 h-5 text-teal-600" />
                      <span>Kurir Memulai Pengantaran</span>
                    </div>
                    <p className="text-xs text-slate-600">
                      Tandai saat kurir berangkat membawa cucian menuju alamat pelanggan.
                    </p>
                    <Button
                      onClick={() => executeTransition('OUT_FOR_DELIVERY', {}, 'Kurir sedang dalam perjalanan ke alamat pelanggan.')}
                      disabled={actionLoading}
                      className="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <Send className="w-4 h-4 mr-2" />
                      <span>Kurir Menuju Alamat Pelanggan (OUT_FOR_DELIVERY)</span>
                    </Button>
                  </div>
                )}

                {/* 11. Status: OUT_FOR_DELIVERY -> COMPLETED */}
                {order.status === 'OUT_FOR_DELIVERY' && (
                  <div className="p-6 bg-emerald-50 rounded-3xl border border-emerald-200 space-y-4">
                    <div className="flex items-center gap-2 text-emerald-950 font-heading font-bold text-base">
                      <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                      <span>Serah Terima Cucian Bersih</span>
                    </div>
                    <p className="text-xs text-emerald-800">
                      Setelah pelanggan menerima cucian bersih dalam kondisi baik, selesaikan pesanan.
                    </p>
                    <Button
                      onClick={() => executeTransition('COMPLETED', {}, 'Cucian telah diterima pelanggan dengan baik. Pesanan selesai.')}
                      disabled={actionLoading}
                      className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                    >
                      <CheckCircle2 className="w-4 h-4 mr-2" />
                      <span>Selesaikan Pesanan (COMPLETED)</span>
                    </Button>
                  </div>
                )}

                {/* 12. Status: COMPLETED */}
                {order.status === 'COMPLETED' && (
                  <div className="p-6 bg-emerald-50 rounded-3xl border border-emerald-200 flex items-center gap-3 text-xs text-emerald-900 font-semibold">
                    <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                    <span>Pesanan telah selesai secara penuh pada {formatDate(order.completed_at || order.updated_at, 'DD MMMM YYYY, HH:mm')}.</span>
                  </div>
                )}

                {/* 13. Status: CANCELLED */}
                {order.status === 'CANCELLED' && (
                  <div className="p-6 bg-red-50 rounded-3xl border border-red-200 flex items-center gap-3 text-xs text-red-900 font-semibold">
                    <AlertCircle className="w-5 h-5 text-red-600 shrink-0" />
                    <span>Pesanan ini telah dibatalkan pada tahap persetujuan harga.</span>
                  </div>
                )}
              </div>
            </Card>

            {/* Snapshots & Timeline Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Left Column: Item Snapshots & Address */}
              <div className="lg:col-span-2 space-y-6">
                {/* 1. Item Snapshots */}
                <Card variant="bento" className="p-6">
                  <div className="border-b border-slate-100 pb-3 mb-4 flex items-center justify-between">
                    <h2 className="font-heading font-bold text-base text-navy-900">
                      Rincian Layanan (Snapshot Saat Order Dibuat)
                    </h2>
                    <span className="text-xs text-slate-400 font-medium">
                      {order.items?.length || 0} Layanan
                    </span>
                  </div>

                  <div className="space-y-4">
                    {order.items?.map((item) => (
                      <div
                        key={item.id}
                        className="p-4 bg-sky-50/40 rounded-2xl border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                      >
                        <div>
                          <div className="flex items-center gap-2 mb-1">
                            <span className="font-heading font-bold text-sm text-navy-900">
                              {item.service_name}
                            </span>
                            <span className="text-[10px] font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-teal-200/60">
                              /{item.pricing_unit}
                            </span>
                          </div>
                          <p className="text-slate-500">
                            Tarif snapshot: <b>{formatRupiah(item.unit_price)}</b> • Kuantitas estimasi: <b>{item.quantity_estimated} {item.pricing_unit}</b>
                            {item.quantity_actual !== null && (
                              <span className="text-teal-700 block mt-0.5">Kuantitas aktual: <b>{item.quantity_actual} {item.pricing_unit}</b></span>
                            )}
                          </p>
                        </div>

                        <div className="text-left sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-200/60">
                          <span className="text-slate-400 block text-[11px] mb-0.5">
                            {item.line_final_amount !== null ? 'Subtotal Final:' : 'Subtotal Estimasi:'}
                          </span>
                          <span className="font-heading font-extrabold text-base text-teal-600 tabular-nums">
                            {formatRupiah(item.line_final_amount !== null ? item.line_final_amount : item.line_estimated_amount)}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </Card>

                {/* 2. Address & Notes */}
                <Card variant="bento" className="p-6">
                  <div className="border-b border-slate-100 pb-3 mb-4">
                    <h2 className="font-heading font-bold text-base text-navy-900">
                      Alamat Pelanggan
                    </h2>
                  </div>

                  <div className="text-xs space-y-3">
                    <div>
                      <span className="text-slate-400 flex items-center gap-1.5 mb-1 font-semibold">
                        <MapPin className="w-3.5 h-3.5 text-teal-600" />
                        <span>Alamat Lengkap:</span>
                      </span>
                      <p className="font-semibold text-navy-900 text-sm">
                        {order.pickup_address?.address_text || '-'}
                      </p>
                      {order.pickup_address?.recipient_name && (
                        <p className="text-slate-500 mt-1">
                          Penerima: {order.pickup_address.recipient_name} ({order.pickup_address.recipient_phone || '-'})
                        </p>
                      )}
                    </div>

                    {order.customer_notes && (
                      <div className="pt-3 border-t border-slate-100">
                        <span className="text-slate-400 block mb-1 font-semibold">Catatan dari Pelanggan:</span>
                        <p className="p-3 bg-sky-50/60 rounded-xl text-slate-700 italic border border-slate-200/60">
                          &ldquo;{order.customer_notes}&rdquo;
                        </p>
                      </div>
                    )}
                  </div>
                </Card>
              </div>

              {/* Right Column: Financial Breakdown & State Machine Audit Log */}
              <div className="space-y-6">
                {/* Financial Breakdown Card */}
                <Card variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm space-y-4">
                  <div className="border-b border-slate-100 pb-3">
                    <h3 className="font-heading font-bold text-base text-navy-900">
                      Rincian Keuangan
                    </h3>
                  </div>

                  <div className="space-y-2 text-xs">
                    <div className="flex justify-between text-slate-600">
                      <span>Estimasi Awal:</span>
                      <span className="font-bold tabular-nums text-navy-900">{formatRupiah(order.estimated_amount)}</span>
                    </div>
                    {order.actual_weight_kg !== null && (
                      <div className="flex justify-between text-slate-600">
                        <span>Berat Aktual:</span>
                        <span className="font-bold text-teal-700">{order.actual_weight_kg} kg</span>
                      </div>
                    )}
                    <div className="flex justify-between text-slate-600">
                      <span>Komisi Platform (Flat):</span>
                      <span className="font-bold tabular-nums text-navy-900">{formatRupiah(order.commission_amount || 1000)}</span>
                    </div>
                  </div>

                  <div className="border-t border-slate-200 pt-3 flex justify-between items-baseline">
                    <span className="font-heading font-bold text-sm text-navy-900">
                      {order.final_amount !== null ? 'Total Final:' : 'Total Estimasi:'}
                    </span>
                    <span className="font-heading font-extrabold text-2xl text-teal-600 tabular-nums">
                      {formatRupiah(order.final_amount !== null ? order.final_amount : order.estimated_amount)}
                    </span>
                  </div>
                </Card>

                {/* Tracking Audit Log Timeline */}
                <Card variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm space-y-4">
                  <div className="border-b border-slate-100 pb-3">
                    <h3 className="font-heading font-bold text-base text-navy-900 flex items-center gap-2">
                      <Clock className="w-4 h-4 text-teal-600" />
                      <span>Riwayat Perubahan Status (Audit Trail)</span>
                    </h3>
                  </div>

                  {timelineEvents.length === 0 ? (
                    <p className="text-xs text-slate-400 italic py-2">Belum ada catatan transisi.</p>
                  ) : (
                    <Timeline events={timelineEvents} className="pt-2" />
                  )}
                </Card>
              </div>
            </div>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
