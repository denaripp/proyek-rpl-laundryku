'use client';

import React, { useState, useEffect, use } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Badge,
  Button,
  Input,
  Timeline,
  TimelineEvent,
  Skeleton,
  ErrorState,
} from '@/components/ui';
import {
  getOrder,
  transitionOrder,
  assignCourier,
  OrderDetail,
  ORDER_STATUS_META,
  OrderStatus,
} from '@/lib/order';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatRupiah, formatDate } from '@/lib/format';
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
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('laundryku_sidebar_count_refresh'));
      }
      return res;
    } catch (err: unknown) {
      const anyErr = err as any;
      const errorMsg =
        anyErr?.responseData?.message ||
        anyErr?.responseData?.error?.message ||
        anyErr?.response?.data?.message ||
        anyErr?.response?.data?.error?.message ||
        anyErr?.message ||
        'Gagal melakukan transisi status pesanan.';
      setActionErrorMsg(errorMsg);
      return null;
    } finally {
      setActionLoading(false);
    }
  };

  // Assign Pickup Courier Handler
  const handleAssignPickupCourier = async () => {
    if (!order) return;
    setActionLoading(true);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await assignCourier(order.id, { task_type: 'pickup' });
      await loadOrderDetail();
      setActionSuccessMsg('Kurir berhasil ditugaskan untuk menjemput cucian.');
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('laundryku_sidebar_count_refresh'));
      }
    } catch (err: unknown) {
      const anyErr = err as any;
      const rawErrorField = anyErr?.responseData?.error ?? anyErr?.response?.data?.error;
      const errorString = typeof rawErrorField === 'string' ? rawErrorField : rawErrorField?.message;
      const errorMsg =
        anyErr?.responseData?.message ||
        errorString ||
        anyErr?.response?.data?.message ||
        anyErr?.message ||
        'Gagal menugaskan kurir penjemputan.';
      setActionErrorMsg(errorMsg);
    } finally {
      setActionLoading(false);
    }
  };

  // Assign Delivery Courier Handler
  const handleAssignDeliveryCourier = async () => {
    if (!order) return;
    setActionLoading(true);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await assignCourier(order.id, { task_type: 'delivery' });
      await loadOrderDetail();
      setActionSuccessMsg('Kurir berhasil ditugaskan untuk mengantar cucian.');
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('laundryku_sidebar_count_refresh'));
      }
    } catch (err: unknown) {
      const anyErr = err as any;
      const rawErrorField = anyErr?.responseData?.error ?? anyErr?.response?.data?.error;
      const errorString = typeof rawErrorField === 'string' ? rawErrorField : rawErrorField?.message;
      const errorMsg =
        anyErr?.responseData?.message ||
        errorString ||
        anyErr?.response?.data?.message ||
        anyErr?.message ||
        'Gagal menugaskan kurir pengantaran.';
      setActionErrorMsg(errorMsg);
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
      <div className="space-y-6">
        {/* Back Navigation */}
        <div>
          <Link
            href="/store/orders"
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-navy-950 transition-colors min-h-[38px]"
          >
            <ArrowLeft className="w-4 h-4" strokeWidth={1.75} />
            <span>Kembali ke daftar pesanan toko</span>
          </Link>
        </div>

        {/* Loading Skeleton */}
        {loading && (
          <div className="space-y-6 animate-pulse">
            <Skeleton className="h-10 w-2/3 rounded-xl" />
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <div className="lg:col-span-2 space-y-6">
                <Skeleton className="h-44 rounded-2xl" />
                <Skeleton className="h-60 rounded-2xl" />
              </div>
              <Skeleton className="h-80 rounded-2xl" />
            </div>
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="p-8 text-center bg-white rounded-2xl border border-rose-200/80 shadow-2xs">
            <ErrorState
              title="Gagal memuat pesanan"
              message={error}
              retryText="Kembali ke daftar pesanan"
              onRetry={() => router.push('/store/orders')}
            />
          </div>
        )}

        {/* Order Details & Operations Control Panel */}
        {!loading && !error && order && (
          <div className="space-y-6">
            {/* Header Bento Card */}
            <div className="p-5 sm:p-6 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-5">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                  <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                    <span className="font-mono font-bold text-xs text-navy-900 bg-sky-50 px-2.5 py-0.5 rounded-md border border-sky-100">
                      {order.order_number}
                    </span>
                    <span className="text-xs text-slate-300">•</span>
                    <span className="text-xs text-slate-500 flex items-center gap-1 font-mono">
                      <Calendar className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                      Diterima: {formatDate(order.created_at, 'DD MMMM YYYY, HH:mm')}
                    </span>
                  </div>
                  <h1 className="font-bold text-lg sm:text-xl text-navy-950 flex items-center gap-2">
                    <User className="w-4 h-4 text-slate-500 shrink-0" strokeWidth={1.75} />
                    <span>Pelanggan: {order.customer_name || 'Pelanggan'}</span>
                  </h1>
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                  <Badge variant={currentStatusMeta?.badgeVariant} className="px-3 py-1 text-xs rounded-md font-semibold">
                    {currentStatusMeta?.label}
                  </Badge>
                  <Badge
                    variant={order.payment_status === 'PAID' ? 'success' : 'warning'}
                    className="px-2.5 py-0.5 text-[11px] rounded-md font-semibold"
                  >
                    {order.payment_status === 'PAID' ? 'Lunas' : 'Belum bayar'}
                  </Badge>
                </div>
              </div>

              {/* Action Feedback */}
              {actionSuccessMsg && (
                <div className="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-2.5 text-emerald-900 text-xs font-semibold">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" strokeWidth={2} />
                  <span>{actionSuccessMsg}</span>
                </div>
              )}
              {actionErrorMsg && (
                <div className="p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-2.5 text-rose-900 text-xs font-semibold">
                  <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" strokeWidth={2} />
                  <span>{actionErrorMsg}</span>
                </div>
              )}

              {/* State Machine Action Controls per PRD 7.3 */}
              <div className="pt-1">
                {/* 1. Status: PENDING_PICKUP -> Assign Courier */}
                {order.status === 'PENDING_PICKUP' && (
                  <div className="p-4 sm:p-5 bg-sky-50/70 rounded-2xl border border-sky-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-navy-950 font-bold text-sm">
                      <Truck className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Tugaskan kurir untuk jemput cucian</span>
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Pesanan baru masuk dari customer. Tugaskan kurir toko untuk menjemput cucian ke alamat pelanggan.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={handleAssignPickupCourier}
                        disabled={actionLoading}
                        className="bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <CheckCircle2 className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                        <span>{actionLoading ? 'Menugaskan...' : 'Tugaskan kurir jemput (PICKUP_ASSIGNED)'}</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 2. Status: PICKUP_ASSIGNED -> Mark Picked Up */}
                {order.status === 'PICKUP_ASSIGNED' && (
                  <div className="p-4 sm:p-5 bg-sky-50/70 rounded-2xl border border-sky-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-navy-950 font-bold text-sm">
                      <Truck className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Kurir sedang menjemput cucian</span>
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Kurir telah ditugaskan. Setelah kurir menerima cucian dari pelanggan di lokasi, perbarui status menjadi PICKED_UP.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={() => executeTransition('PICKED_UP', {}, 'Cucian telah diambil oleh kurir dari pelanggan.')}
                        disabled={actionLoading}
                        className="bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <CheckCircle2 className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                        <span>Konfirmasi cucian telah dijemput (PICKED_UP)</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 3. Status: PICKED_UP -> Mark AT_STORE */}
                {order.status === 'PICKED_UP' && (
                  <div className="p-4 sm:p-5 bg-sky-50/70 rounded-2xl border border-sky-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-navy-950 font-bold text-sm">
                      <Store className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Cucian menuju outlet</span>
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Setelah kurir tiba di outlet membawa cucian pelanggan, konfirmasi penerimaan cucian di toko.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={() => executeTransition('AT_STORE', {}, 'Cucian telah tiba di outlet laundry.')}
                        disabled={actionLoading}
                        className="bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <CheckCircle2 className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                        <span>Konfirmasi cucian tiba di outlet (AT_STORE)</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 4. Status: AT_STORE -> Input Weight & Trigger AWAITING_PRICE_APPROVAL */}
                {order.status === 'AT_STORE' && (
                  <form onSubmit={handleWeighingSubmit} className="p-4 sm:p-5 bg-amber-50/70 rounded-2xl border border-amber-200/80 space-y-4">
                    <div className="flex items-start gap-3">
                      <div className="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 border border-amber-200">
                        <Scale className="w-4 h-4" strokeWidth={1.75} />
                      </div>
                      <div>
                        <h3 className="font-bold text-sm text-amber-950">
                          Penimbangan cucian & hitung harga final
                        </h3>
                        <p className="text-xs text-amber-800 mt-0.5 leading-relaxed">
                          Masukkan berat aktual hasil timbangan outlet. Sistem akan otomatis menghitung harga final dan mengirimkan permintaan persetujuan ke pelanggan.
                        </p>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                          Berat aktual bersih (kg) *
                        </label>
                        <Input
                          type="number"
                          step="0.05"
                          min="0.1"
                          required
                          value={actualWeightKg || ''}
                          onChange={(e) => setActualWeightKg(parseFloat(e.target.value) || 0)}
                          placeholder="contoh: 4.5"
                          className="text-xs rounded-xl"
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                          Catatan hasil timbang (opsional)
                        </label>
                        <Input
                          type="text"
                          value={transitionNote}
                          onChange={(e) => setTransitionNote(e.target.value)}
                          placeholder="contoh: Ditimbang 4.5kg, kemeja dan celana lengkap."
                          className="text-xs rounded-xl"
                        />
                      </div>
                    </div>

                    {/* Service item quantities inputs if multi-item */}
                    {order.items && order.items.length > 1 && (
                      <div className="space-y-3 pt-2 border-t border-amber-200/70">
                        <span className="text-xs font-semibold text-amber-950 block">Rincian kuantitas per item:</span>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                          {order.items.map((it) => (
                            <div key={it.id} className="p-3 bg-white rounded-xl border border-amber-200/80 text-xs flex items-center justify-between gap-2 shadow-2xs">
                              <div>
                                <span className="font-semibold text-navy-950 block">{it.service_name}</span>
                                <span className="text-slate-500">/{it.pricing_unit}</span>
                              </div>
                              <div className="w-24">
                                <Input
                                  type="number"
                                  step="0.1"
                                  min="0.1"
                                  value={itemQuantities[it.id] ?? it.quantity_estimated}
                                  onChange={(e) => setItemQuantities({ ...itemQuantities, [it.id]: parseFloat(e.target.value) || 0 })}
                                  className="text-xs rounded-lg"
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
                      className="bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                    >
                      <CheckCircle2 className="w-4 h-4 mr-1.5" strokeWidth={1.75} />
                      <span>{actionLoading ? 'Menyimpan...' : 'Simpan penimbangan & kirim ke pelanggan (WEIGHED)'}</span>
                    </Button>
                  </form>
                )}

                {/* 5. Status: AWAITING_PRICE_APPROVAL -> Info Banner */}
                {order.status === 'AWAITING_PRICE_APPROVAL' && (
                  <div className="p-4 sm:p-5 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-start gap-3 text-xs text-slate-700">
                    <Clock className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" strokeWidth={2} />
                    <div>
                      <strong className="block font-bold text-navy-950">Menunggu keputusan pelanggan</strong>
                      <span className="leading-relaxed">
                        Harga final ({formatRupiah(order.final_amount || 0)}) telah dikirimkan ke pelanggan. Pesanan akan dapat diproses setelah pelanggan menyetujui harga dan menyelesaikan pembayaran.
                      </span>
                    </div>
                  </div>
                )}

                {/* 6. Status: PRICE_APPROVED / AWAITING_PAYMENT -> Info Banner */}
                {(order.status === 'PRICE_APPROVED' || order.status === 'AWAITING_PAYMENT') && (
                  <div className="p-4 sm:p-5 bg-sky-50/70 rounded-2xl border border-sky-200/80 flex items-start gap-3 text-xs text-navy-950">
                    <Clock className="w-4 h-4 text-navy-700 shrink-0 mt-0.5" strokeWidth={2} />
                    <div>
                      <strong className="block font-bold">Harga disetujui — menunggu pembayaran</strong>
                      <span className="leading-relaxed text-slate-600">
                        Pelanggan telah menyetujui harga final ({formatRupiah(order.final_amount || 0)}). Menunggu verifikasi pembayaran atau konfirmasi sistem.
                      </span>
                    </div>
                  </div>
                )}

                {/* 7. Status: PAID -> Start Washing (PROCESSING) */}
                {order.status === 'PAID' && (
                  <div className="p-4 sm:p-5 bg-emerald-50/70 rounded-2xl border border-emerald-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-emerald-950 font-bold text-sm">
                      <Sparkles className="w-4 h-4 text-emerald-600" strokeWidth={1.75} />
                      <span>Pembayaran terverifikasi — siap dicuci</span>
                    </div>
                    <p className="text-xs text-emerald-800 leading-relaxed">
                      Pembayaran telah lunas. Klik tombol di bawah saat proses pencucian / pengeringan dimulai.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={() => executeTransition('PROCESSING', {}, 'Cucian mulai masuk tahap pencucian.')}
                        disabled={actionLoading}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <Sparkles className="w-4 h-4 mr-1.5" strokeWidth={1.75} />
                        <span>Mulai proses pencucian (PROCESSING)</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 8. Status: PROCESSING -> Finish Washing (READY_FOR_DELIVERY) */}
                {order.status === 'PROCESSING' && (
                  <div className="p-4 sm:p-5 bg-sky-50/70 rounded-2xl border border-sky-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-navy-950 font-bold text-sm">
                      <CheckCircle2 className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Proses laundry sedang berjalan</span>
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Setelah cucian selesai dicuci, dikeringkan, disetrika, dan dikemas rapi, tandai pesanan siap untuk diantar.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={() => executeTransition('READY_FOR_DELIVERY', {}, 'Cucian selesai dikemas dan siap diantar.')}
                        disabled={actionLoading}
                        className="bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <CheckCircle2 className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                        <span>Cucian selesai & siap diantar (READY_FOR_DELIVERY)</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 9. Status: READY_FOR_DELIVERY -> Assign Delivery Courier */}
                {order.status === 'READY_FOR_DELIVERY' && (
                  <div className="p-4 sm:p-5 bg-sky-50/70 rounded-2xl border border-sky-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-navy-950 font-bold text-sm">
                      <Truck className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Tugaskan kurir pengantaran</span>
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Tugaskan kurir untuk mengantarkan pakaian bersih kembali ke alamat pelanggan.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={handleAssignDeliveryCourier}
                        disabled={actionLoading}
                        className="bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <Truck className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                        <span>Tugaskan kurir pengantaran (DELIVERY_ASSIGNED)</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 10. Status: DELIVERY_ASSIGNED -> OUT_FOR_DELIVERY */}
                {order.status === 'DELIVERY_ASSIGNED' && (
                  <div className="p-4 sm:p-5 bg-sky-50/70 rounded-2xl border border-sky-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-navy-950 font-bold text-sm">
                      <Truck className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Kurir memulai pengantaran</span>
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Tandai saat kurir berangkat membawa cucian menuju alamat pelanggan.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={() => executeTransition('OUT_FOR_DELIVERY', {}, 'Kurir sedang dalam perjalanan ke alamat pelanggan.')}
                        disabled={actionLoading}
                        className="bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <Send className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                        <span>Kurir menuju alamat pelanggan (OUT_FOR_DELIVERY)</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 11. Status: OUT_FOR_DELIVERY -> COMPLETED */}
                {order.status === 'OUT_FOR_DELIVERY' && (
                  <div className="p-4 sm:p-5 bg-emerald-50/70 rounded-2xl border border-emerald-200/80 space-y-3">
                    <div className="flex items-center gap-2 text-emerald-950 font-bold text-sm">
                      <CheckCircle2 className="w-4 h-4 text-emerald-600" strokeWidth={1.75} />
                      <span>Serah terima cucian bersih</span>
                    </div>
                    <p className="text-xs text-emerald-800 leading-relaxed">
                      Setelah pelanggan menerima cucian bersih dalam kondisi baik, selesaikan pesanan.
                    </p>
                    <div className="pt-1">
                      <Button
                        onClick={() => executeTransition('COMPLETED', {}, 'Cucian telah diterima pelanggan dengan baik. Pesanan selesai.')}
                        disabled={actionLoading}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[40px] shadow-2xs"
                      >
                        <CheckCircle2 className="w-4 h-4 mr-1.5" strokeWidth={1.75} />
                        <span>Selesaikan pesanan (COMPLETED)</span>
                      </Button>
                    </div>
                  </div>
                )}

                {/* 12. Status: COMPLETED */}
                {order.status === 'COMPLETED' && (
                  <div className="p-4 bg-emerald-50/80 rounded-2xl border border-emerald-200/80 flex items-center gap-3 text-xs text-emerald-900 font-semibold">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" strokeWidth={2} />
                    <span>Pesanan telah selesai secara penuh pada {formatDate(order.completed_at || order.updated_at, 'DD MMMM YYYY, HH:mm')}.</span>
                  </div>
                )}

                {/* 13. Status: CANCELLED */}
                {order.status === 'CANCELLED' && (
                  <div className="p-4 bg-rose-50/80 rounded-2xl border border-rose-200/80 flex items-center gap-3 text-xs text-rose-900 font-semibold">
                    <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" strokeWidth={2} />
                    <span>Pesanan ini telah dibatalkan pada tahap persetujuan harga.</span>
                  </div>
                )}
              </div>
            </div>

            {/* Snapshots & Timeline Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Left Column: Item Snapshots & Address */}
              <div className="lg:col-span-2 space-y-6">
                {/* 1. Item Snapshots */}
                <div className="p-5 sm:p-6 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-4">
                  <div className="border-b border-slate-100 pb-3 flex items-center justify-between">
                    <h2 className="font-bold text-sm sm:text-base text-navy-950">
                      Rincian layanan (snapshot saat order dibuat)
                    </h2>
                    <span className="text-xs text-slate-500 font-mono">
                      {order.items?.length || 0} layanan
                    </span>
                  </div>

                  <div className="space-y-3">
                    {order.items?.map((item) => (
                      <div
                        key={item.id}
                        className="p-4 bg-slate-50/60 rounded-xl border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                      >
                        <div>
                          <div className="flex items-center gap-2 mb-1">
                            <span className="font-bold text-navy-950">
                              {item.service_name}
                            </span>
                            <span className="text-[10px] font-semibold text-slate-600 bg-white px-2 py-0.5 rounded border border-slate-200">
                              /{item.pricing_unit}
                            </span>
                          </div>
                          <p className="text-slate-500">
                            Tarif snapshot: <b className="font-mono text-slate-800">{formatRupiah(item.unit_price)}</b> • Kuantitas estimasi: <b className="font-mono text-slate-800">{item.quantity_estimated} {item.pricing_unit}</b>
                            {item.quantity_actual !== null && (
                              <span className="text-navy-900 block mt-0.5">Kuantitas aktual: <b className="font-mono">{item.quantity_actual} {item.pricing_unit}</b></span>
                            )}
                          </p>
                        </div>

                        <div className="text-left sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-200">
                          <span className="text-slate-400 block text-[11px] mb-0.5">
                            {item.line_final_amount !== null ? 'Subtotal final:' : 'Subtotal estimasi:'}
                          </span>
                          <span className="font-mono font-bold text-sm text-navy-950 tabular-nums">
                            {formatRupiah(item.line_final_amount !== null ? item.line_final_amount : (item.line_estimated_amount || ((item.unit_price || 0) * (item.quantity_estimated || 0))))}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* 2. Address & Notes */}
                <div className="p-5 sm:p-6 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-4">
                  <div className="border-b border-slate-100 pb-3">
                    <h2 className="font-bold text-sm sm:text-base text-navy-950">
                      Alamat pelanggan
                    </h2>
                  </div>

                  <div className="text-xs space-y-3">
                    <div>
                      <span className="text-slate-400 flex items-center gap-1.5 mb-1 font-medium text-[11px]">
                        <MapPin className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.75} />
                        <span>Alamat penjemputan & pengantaran:</span>
                      </span>
                      <p className="font-semibold text-navy-950 text-sm">
                        {order.pickup_address?.address_text && order.pickup_address.address_text.trim() !== '' && order.pickup_address.address_text !== '-'
                          ? order.pickup_address.address_text
                          : 'Alamat tidak tersedia'}
                      </p>
                      {order.pickup_address?.recipient_name && (
                        <p className="text-slate-500 mt-1">
                          Penerima: {order.pickup_address.recipient_name} ({order.pickup_address.recipient_phone || '-'})
                        </p>
                      )}
                    </div>

                    {order.customer_notes && (
                      <div className="pt-3 border-t border-slate-100">
                        <span className="text-slate-400 block mb-1 font-medium text-[11px]">Catatan dari pelanggan:</span>
                        <p className="p-3 bg-slate-50 rounded-xl text-slate-700 italic border border-slate-200/80">
                          &ldquo;{order.customer_notes}&rdquo;
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Right Column: Financial Breakdown & State Machine Audit Log */}
              <div className="space-y-6">
                {/* Financial Breakdown Card */}
                <div className="p-5 sm:p-6 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-4">
                  <div className="border-b border-slate-100 pb-3">
                    <h3 className="font-bold text-sm sm:text-base text-navy-950">
                      Rincian keuangan
                    </h3>
                  </div>

                  <div className="space-y-2 text-xs">
                    <div className="flex justify-between text-slate-600">
                      <span>Estimasi biaya jasa:</span>
                      <span className="font-mono font-semibold tabular-nums text-navy-950">
                        {formatRupiah(
                          order.items && order.items.length > 0
                            ? order.items.reduce((acc, it) => acc + (it.line_estimated_amount || ((it.unit_price || 0) * (it.quantity_estimated || 0))), 0)
                            : Math.max(0, (order.estimated_amount || 0) - (order.delivery_fee || 0) - (order.platform_fee ?? 1000))
                        )}
                      </span>
                    </div>
                    {order.actual_weight_kg !== null && (
                      <div className="flex justify-between text-slate-600">
                        <span>Berat aktual:</span>
                        <span className="font-mono font-semibold text-navy-900">{order.actual_weight_kg} kg</span>
                      </div>
                    )}
                    <div className="flex justify-between text-slate-600">
                      <span>Biaya layanan (platform):</span>
                      <span className="font-mono font-semibold tabular-nums text-navy-950">{formatRupiah(order.platform_fee ?? 1000)}</span>
                    </div>
                    <div className="flex justify-between text-slate-600">
                      <span>Ongkos kirim:</span>
                      <span className="font-mono font-semibold tabular-nums text-navy-950">{order.delivery_fee > 0 ? formatRupiah(order.delivery_fee) : 'Gratis'}</span>
                    </div>
                  </div>

                  <div className="border-t border-slate-100 pt-3 flex justify-between items-baseline">
                    <span className="font-semibold text-xs text-slate-600">
                      {order.final_amount !== null ? 'Total final:' : 'Total estimasi:'}
                    </span>
                    <span className="font-mono font-bold text-xl text-navy-950 tabular-nums">
                      {formatRupiah(order.final_amount !== null ? order.final_amount : order.estimated_amount)}
                    </span>
                  </div>
                </div>

                {/* Tracking Audit Log Timeline */}
                <div className="p-5 sm:p-6 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-4">
                  <div className="border-b border-slate-100 pb-3">
                    <h3 className="font-bold text-sm sm:text-base text-navy-950 flex items-center gap-2">
                      <Clock className="w-4 h-4 text-slate-500" strokeWidth={1.75} />
                      <span>Riwayat status</span>
                    </h3>
                  </div>

                  {timelineEvents.length === 0 ? (
                    <p className="text-xs text-slate-400 italic py-2">Belum ada catatan transisi.</p>
                  ) : (
                    <Timeline events={timelineEvents} className="pt-2" />
                  )}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
