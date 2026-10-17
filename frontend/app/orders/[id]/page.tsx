'use client';

import React, { useEffect, useState, use } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
import {
  Button,
  Modal,
  Textarea,
  Skeleton,
} from '@/components/ui';
import {
  getOrder,
  submitPriceApproval,
  payOrder,
  simulatePaymentSuccess,
  PaymentInfo,
  OrderDetail,
  ORDER_STATUS_META,
} from '@/lib/order';
import { getCustomerComplaints, Complaint } from '@/lib/complaint';
import { ComplaintFormModal } from '@/components/complaint/ComplaintFormModal';
import { ComplaintListCard } from '@/components/complaint/ComplaintListCard';
import { OrderRatingCard } from '@/components/rating/OrderRatingCard';
import { OrderStatusBadge } from '@/components/orders/OrderStatusBadge';
import { startOrGetConversation } from '@/lib/chat';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  ArrowLeft,
  CheckCircle2,
  AlertCircle,
  CreditCard,
  QrCode,
  Truck,
  Copy,
  Check,
  MessageSquare,
} from 'lucide-react';

export default function OrderDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const resolvedParams = use(params);
  const orderId = resolvedParams.id;
  const router = useRouter();

  const [order, setOrder] = useState<OrderDetail | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Action states
  const [actionLoading, setActionLoading] = useState<boolean>(false);
  const [actionSuccessMsg, setActionSuccessMsg] = useState<string | null>(null);
  const [actionErrorMsg, setActionErrorMsg] = useState<string | null>(null);

  // Modals
  const [showCancelModal, setShowCancelModal] = useState<boolean>(false);
  const [cancelReason, setCancelReason] = useState<string>('');
  const [chatLoading, setChatLoading] = useState<boolean>(false);

  // Complaints & Rating states
  const [complaints, setComplaints] = useState<Complaint[]>([]);
  const [showComplaintModal, setShowComplaintModal] = useState<boolean>(false);

  // Payment states
  const [showPaymentModal, setShowPaymentModal] = useState<boolean>(false);
  const [paymentMethod, setPaymentMethod] = useState<'qris' | 'va' | 'cod'>('qris');
  const [selectedBank, setSelectedBank] = useState<string>('BCA');
  const [activePaymentInfo, setActivePaymentInfo] = useState<PaymentInfo | null>(null);
  const [copiedVa, setCopiedVa] = useState<boolean>(false);

  const handleStartChat = async () => {
    if (!order) return;
    setChatLoading(true);
    try {
      const conv = await startOrGetConversation({
        store_id: order.store_id,
        order_id: order.id,
      });
      router.push(`/chat/${conv.id}`);
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      alert(errObj.response?.data?.error?.message || 'Gagal membuka percakapan chat.');
    } finally {
      setChatLoading(false);
    }
  };

  const loadComplaints = async () => {
    try {
      const res = await getCustomerComplaints({ order_id: orderId });
      setComplaints(res.data);
    } catch {
      // Non-blocking
    }
  };

  const loadOrderDetail = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getOrder(orderId);
      setOrder(res);
      await loadComplaints();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat detail pesanan.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadOrderDetail();
  }, [orderId]);

  // Handle Approve Price (US-CUS-07)
  const handleApprovePrice = async () => {
    if (!order) return;
    setActionLoading(true);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      const res = await submitPriceApproval(order.id, {
        action: 'approve',
      });
      setOrder(res);
      setActionSuccessMsg('Harga final berhasil disetujui. Silakan lanjutkan ke pembayaran.');
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal menyetujui harga final.');
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Cancel / Reject Price (US-CUS-07)
  const handleCancelOrder = async () => {
    if (!order) return;
    setActionLoading(true);
    setActionErrorMsg(null);
    try {
      const res = await submitPriceApproval(order.id, {
        action: 'reject',
        reason: cancelReason.trim() || 'Pelanggan menolak harga final setelah penimbangan.',
      });
      setOrder(res);
      setShowCancelModal(false);
      setActionSuccessMsg('Pesanan berhasil dibatalkan. Pakaian akan dikembalikan oleh pihak toko.');
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal membatalkan pesanan.');
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Payment Initiation via Backend (Xendit / COD)
  const handleInitiatePayment = async () => {
    if (!order) return;
    setActionLoading(true);
    setActionErrorMsg(null);
    try {
      const paymentRes = await payOrder(order.id, {
        method: paymentMethod,
        bank_code: paymentMethod === 'va' ? selectedBank : undefined,
      });
      setActivePaymentInfo(paymentRes);

      if (paymentMethod === 'cod') {
        const refreshed = await getOrder(order.id);
        setOrder(refreshed);
        setShowPaymentModal(false);
        setActionSuccessMsg('Metode Bayar di Tempat (COD) berhasil dikonfirmasi.');
      }
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal memproses pembayaran.');
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Sandbox Payment Simulation
  const handleSimulatePaymentSuccess = async () => {
    if (!order) return;
    setActionLoading(true);
    setActionErrorMsg(null);
    try {
      await simulatePaymentSuccess(order.id);
      const refreshed = await getOrder(order.id);
      setOrder(refreshed);
      setShowPaymentModal(false);
      setActivePaymentInfo(null);
      setActionSuccessMsg('Pembayaran berhasil diverifikasi.');
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal melakukan simulasi pembayaran.');
    } finally {
      setActionLoading(false);
    }
  };

  // Copy VA Number helper
  const handleCopyVa = (vaNumber: string) => {
    navigator.clipboard.writeText(vaNumber);
    setCopiedVa(true);
    setTimeout(() => setCopiedVa(false), 2000);
  };

  return (
    <PublicLayout>
      <div className="bg-slate-50 min-h-[calc(100vh-4rem)] py-6 sm:py-8">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
          {/* Back Navigation */}
          <div>
            <Link
              href="/orders"
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors"
            >
              <ArrowLeft className="w-4 h-4" />
              <span>Kembali ke Pesanan</span>
            </Link>
          </div>

          {/* Loading Skeleton */}
          {loading && (
            <div className="space-y-4">
              <Skeleton className="h-28 rounded-lg bg-white border border-slate-200" />
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-4">
                  <Skeleton className="h-48 rounded-lg bg-white border border-slate-200" />
                  <Skeleton className="h-36 rounded-lg bg-white border border-slate-200" />
                </div>
                <div className="space-y-4">
                  <Skeleton className="h-56 rounded-lg bg-white border border-slate-200" />
                  <Skeleton className="h-64 rounded-lg bg-white border border-slate-200" />
                </div>
              </div>
            </div>
          )}

          {/* Error State */}
          {!loading && error && (
            <div className="p-8 text-center bg-white border border-slate-200 rounded-lg space-y-3">
              <p className="text-sm text-slate-700">{error}</p>
              <Button
                variant="primary"
                size="sm"
                onClick={() => router.push('/orders')}
                className="bg-[#1A3A5C] hover:bg-[#152e4a] text-white"
              >
                Kembali ke Pesanan
              </Button>
            </div>
          )}

          {/* Order Detail Content */}
          {!loading && !error && order && (
            <div className="space-y-6">
              {/* Header Box */}
              <div className="bg-white border border-slate-200 rounded-lg p-5 sm:p-6 space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-mono text-xs text-slate-500">
                        #{order.order_number}
                      </span>
                      <span className="text-slate-300">·</span>
                      <span className="text-xs text-slate-500">
                        {formatDate(order.created_at, 'DD MMMM YYYY, HH:mm')}
                      </span>
                    </div>
                    <h1 className="text-xl font-semibold text-slate-900">
                      {order.store_name || 'Outlet Laundry'}
                    </h1>
                  </div>

                  <div className="flex items-center gap-3 flex-wrap">
                    <button
                      type="button"
                      onClick={handleStartChat}
                      disabled={chatLoading}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 rounded-lg border border-slate-200 text-xs font-medium transition-colors cursor-pointer"
                    >
                      <MessageSquare className="w-3.5 h-3.5 text-slate-500" />
                      <span>{chatLoading ? 'Membuka...' : 'Chat Toko'}</span>
                    </button>
                    <OrderStatusBadge status={order.status} className="text-sm" />
                  </div>
                </div>

                {/* Notifications & Feedback */}
                {actionSuccessMsg && (
                  <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-2 text-emerald-800 text-xs font-medium">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{actionSuccessMsg}</span>
                  </div>
                )}
                {actionErrorMsg && (
                  <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg flex items-center gap-2 text-rose-800 text-xs font-medium">
                    <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{actionErrorMsg}</span>
                  </div>
                )}

                {/* Action 1: Awaiting Price Approval Banner */}
                {order.status === 'AWAITING_PRICE_APPROVAL' && (
                  <div className="p-4 bg-amber-50 border border-amber-200 rounded-lg space-y-4">
                    <div>
                      <h3 className="font-semibold text-sm text-amber-950">
                        Persetujuan Harga Final
                      </h3>
                      <p className="text-xs text-amber-900 mt-0.5">
                        Pihak toko telah selesai menimbang cucian Anda. Periksa hasil penimbangan dan total tagihan sebelum menyetujui.
                      </p>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs bg-white p-3 rounded-lg border border-amber-200">
                      <div>
                        <span className="text-slate-500 block">Estimasi Berat:</span>
                        <span className="font-medium text-slate-800">
                          {order.estimated_weight_kg ? `${order.estimated_weight_kg} kg` : '-'}
                        </span>
                      </div>
                      <div>
                        <span className="text-slate-500 block">Berat Aktual:</span>
                        <span className="font-semibold text-slate-900">
                          {order.actual_weight_kg ? `${order.actual_weight_kg} kg` : '-'}
                        </span>
                      </div>
                      <div>
                        <span className="text-slate-500 block">Total Final:</span>
                        <span className="font-semibold text-slate-900 tabular-nums">
                          {formatRupiah(order.final_amount || 0)}
                        </span>
                      </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-1">
                      <button
                        type="button"
                        onClick={() => setShowCancelModal(true)}
                        disabled={actionLoading}
                        className="px-3 py-1.5 bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 rounded-lg text-xs font-medium cursor-pointer"
                      >
                        Tolak & Batalkan
                      </button>
                      <button
                        type="button"
                        onClick={handleApprovePrice}
                        disabled={actionLoading}
                        className="px-4 py-1.5 bg-[#1A3A5C] hover:bg-[#152e4a] text-white rounded-lg text-xs font-medium cursor-pointer"
                      >
                        {actionLoading ? 'Memproses...' : 'Setujui Harga'}
                      </button>
                    </div>
                  </div>
                )}

                {/* Action 2: Awaiting Payment Banner */}
                {order.status === 'AWAITING_PAYMENT' && (
                  <div className="p-4 bg-amber-50 border border-amber-200 rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="space-y-0.5">
                      <h3 className="font-semibold text-sm text-amber-950">
                        Menunggu Pembayaran
                      </h3>
                      <p className="text-xs text-amber-900">
                        Total tagihan: <b className="text-slate-900">{formatRupiah(order.final_amount || order.estimated_amount)}</b>
                      </p>
                    </div>

                    <button
                      type="button"
                      onClick={() => {
                        setActivePaymentInfo(null);
                        setShowPaymentModal(true);
                      }}
                      className="px-4 py-2 bg-[#1A3A5C] hover:bg-[#152e4a] text-white rounded-lg text-xs font-medium cursor-pointer shrink-0"
                    >
                      Bayar Sekarang
                    </button>
                  </div>
                )}
              </div>

              {/* Grid 2 Columns: Left (Services & Address), Right (Pricing Table & Timeline) */}
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column */}
                <div className="lg:col-span-2 space-y-6">
                  {/* Services Breakdown */}
                  <div className="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">
                      Layanan & Tarif
                    </h2>

                    <div className="divide-y divide-slate-100">
                      {order.items?.map((item) => (
                        <div
                          key={item.id}
                          className="py-3 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
                        >
                          <div>
                            <span className="font-medium text-slate-900 block">
                              {item.service_name}
                            </span>
                            <span className="text-slate-500">
                              {formatRupiah(item.unit_price)} per {item.pricing_unit} · Kuantitas: {item.quantity_actual ?? item.quantity_estimated} {item.pricing_unit}
                            </span>
                          </div>
                          <div className="sm:text-right font-medium text-slate-900 tabular-nums">
                            {formatRupiah(item.line_final_amount ?? item.line_estimated_amount)}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Pickup & Courier */}
                  <div className="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">
                      Penjemputan & Pengantaran
                    </h2>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                      <div>
                        <span className="text-slate-500 block mb-1">Alamat Penjemputan:</span>
                        <p className="font-medium text-slate-900">
                          {order.pickup_address?.address_text || '-'}
                        </p>
                        {order.pickup_address?.recipient_name && (
                          <p className="text-slate-500 mt-0.5">
                            {order.pickup_address.recipient_name} ({order.pickup_address.recipient_phone || '-'})
                          </p>
                        )}
                      </div>

                      <div>
                        <span className="text-slate-500 block mb-1">Kurir Toko:</span>
                        <p className="font-medium text-slate-900">
                          {order.courier_name || 'Menunggu penugasan kurir'}
                        </p>
                      </div>
                    </div>
                  </div>

                  {/* Rating Card (When COMPLETED) */}
                  {order.status === 'COMPLETED' && (
                    <OrderRatingCard
                      orderId={order.id}
                      orderNumber={order.order_number}
                      isCompleted={true}
                    />
                  )}

                  {/* Complaints Card */}
                  <ComplaintListCard
                    complaints={complaints}
                    onOpenComplaintModal={() => setShowComplaintModal(true)}
                    canFileComplaint={order.status !== 'CANCELLED'}
                  />
                </div>

                {/* Right Column: Pricing Table & Timeline */}
                <div className="space-y-6">
                  {/* Price Summary Table */}
                  <div className="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">
                      Rincian Biaya
                    </h2>

                    <table className="w-full text-xs">
                      <tbody className="divide-y divide-slate-100">
                        <tr>
                          <td className="py-2 text-slate-600">Estimasi Biaya Jasa</td>
                          <td className="py-2 text-right text-slate-900 tabular-nums">
                            {formatRupiah((order.estimated_amount || 0) - (order.delivery_fee || 0))}
                          </td>
                        </tr>
                        {order.final_amount !== null && (
                          <tr>
                            <td className="py-2 text-slate-600">Biaya Jasa Final</td>
                            <td className="py-2 text-right text-slate-900 tabular-nums font-medium">
                              {formatRupiah((order.final_amount || 0) - (order.delivery_fee || 0))}
                            </td>
                          </tr>
                        )}
                        <tr>
                          <td className="py-2 text-slate-600">Ongkos Kirim</td>
                          <td className="py-2 text-right text-slate-900 tabular-nums">
                            {order.delivery_fee > 0 ? formatRupiah(order.delivery_fee) : 'Gratis'}
                          </td>
                        </tr>
                        <tr className="font-medium text-slate-900">
                          <td className="py-2.5 pt-3 font-semibold">Total Tagihan</td>
                          <td className="py-2.5 pt-3 text-right text-sm font-semibold tabular-nums text-slate-900">
                            {formatRupiah(order.final_amount !== null ? order.final_amount : order.estimated_amount)}
                          </td>
                        </tr>
                      </tbody>
                    </table>

                    <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                      <span>Status Pembayaran</span>
                      <span className={order.payment_status === 'PAID' ? 'text-emerald-700 font-medium' : 'text-amber-700 font-medium'}>
                        {order.payment_status === 'PAID' ? 'Lunas' : 'Belum Dibayar'}
                      </span>
                    </div>
                  </div>

                  {/* Vertical Status Timeline */}
                  <div className="bg-white border border-slate-200 rounded-lg p-5 space-y-4">
                    <h2 className="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">
                      Riwayat Status
                    </h2>

                    {order.status_histories && order.status_histories.length > 0 ? (
                      <div className="relative pl-5 space-y-4 text-xs">
                        <div className="absolute left-[4px] top-1.5 bottom-1.5 w-px bg-slate-200" aria-hidden="true" />
                        {order.status_histories.map((hist, idx) => {
                          const isLatest = idx === order.status_histories!.length - 1;
                          const statusMeta = ORDER_STATUS_META[hist.to_status] || { label: hist.to_status };
                          return (
                            <div key={hist.id || idx} className="relative flex flex-col gap-0.5">
                              <div
                                className={cn(
                                  'absolute -left-5 top-1 w-2.5 h-2.5 rounded-full border-2 bg-white',
                                  isLatest
                                    ? 'border-[#1A3A5C] bg-[#1A3A5C]'
                                    : 'border-slate-400 bg-white'
                                )}
                              />
                              <div className="flex items-center justify-between gap-2">
                                <span className={cn('font-medium', isLatest ? 'text-slate-900 font-semibold' : 'text-slate-700')}>
                                  {statusMeta.label}
                                </span>
                                <span className="text-slate-400 tabular-nums text-[11px]">
                                  {formatDate(hist.timestamp, 'DD MMM, HH:mm')}
                                </span>
                              </div>
                              {hist.note && (
                                <p className="text-slate-500 mt-0.5">{hist.note}</p>
                              )}
                              {hist.actor_name && (
                                <span className="text-[11px] text-slate-400">Oleh: {hist.actor_name}</span>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    ) : (
                      <p className="text-xs text-slate-400 italic">Belum ada riwayat status.</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Modal 1: Cancellation Confirmation */}
          <Modal
            isOpen={showCancelModal}
            onClose={() => setShowCancelModal(false)}
            title="Konfirmasi Pembatalan Pesanan"
          >
            <div className="space-y-4 text-xs">
              <p className="text-slate-600">
                Pakaian yang sudah diambil oleh kurir akan dikembalikan ke alamat Anda.
              </p>

              <div>
                <label className="block font-medium text-slate-700 mb-1.5">
                  Alasan Pembatalan *
                </label>
                <Textarea
                  rows={3}
                  value={cancelReason}
                  onChange={(e) => setCancelReason(e.target.value)}
                  placeholder="contoh: Berat cucian berbeda jauh dari perkiraan saya."
                  className="text-xs"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <Button
                  variant="outline"
                  onClick={() => setShowCancelModal(false)}
                  disabled={actionLoading}
                  className="rounded-lg text-xs"
                >
                  Batal
                </Button>
                <button
                  type="button"
                  onClick={handleCancelOrder}
                  disabled={actionLoading || !cancelReason.trim()}
                  className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-medium cursor-pointer disabled:opacity-50"
                >
                  {actionLoading ? 'Membatalkan...' : 'Batalkan Pesanan'}
                </button>
              </div>
            </div>
          </Modal>

          {/* Modal 2: Payment (Xendit VA / QRIS / COD) */}
          <Modal
            isOpen={showPaymentModal}
            onClose={() => {
              setShowPaymentModal(false);
              setActivePaymentInfo(null);
            }}
            title="Pembayaran"
          >
            <div className="space-y-4 text-xs">
              <div className="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between">
                <span className="text-slate-600">Total Tagihan:</span>
                <span className="font-semibold text-sm text-slate-900 tabular-nums">
                  {formatRupiah(order?.final_amount || order?.estimated_amount || 0)}
                </span>
              </div>

              {/* Step 1: Select Payment Method */}
              {!activePaymentInfo && (
                <div className="space-y-3">
                  <p className="font-medium text-slate-700">Pilih Metode Pembayaran:</p>

                  <div className="space-y-2">
                    {/* QRIS */}
                    <button
                      type="button"
                      onClick={() => setPaymentMethod('qris')}
                      className={cn(
                        'w-full p-3 rounded-lg border text-left flex items-center justify-between gap-3 transition-colors cursor-pointer',
                        paymentMethod === 'qris'
                          ? 'border-[#1A3A5C] bg-slate-50'
                          : 'border-slate-200 bg-white hover:border-slate-300'
                      )}
                    >
                      <div className="flex items-center gap-2.5">
                        <QrCode className="w-4 h-4 text-slate-700" />
                        <div>
                          <span className="font-medium text-slate-900 block">QRIS</span>
                          <span className="text-[11px] text-slate-500">GoPay, OVO, DANA, BCA, ShopeePay</span>
                        </div>
                      </div>
                      <div className={cn('w-3.5 h-3.5 rounded-full border flex items-center justify-center', paymentMethod === 'qris' ? 'border-[#1A3A5C] bg-[#1A3A5C]' : 'border-slate-300')}>
                        {paymentMethod === 'qris' && <div className="w-1.5 h-1.5 rounded-full bg-white" />}
                      </div>
                    </button>

                    {/* Virtual Account */}
                    <button
                      type="button"
                      onClick={() => setPaymentMethod('va')}
                      className={cn(
                        'w-full p-3 rounded-lg border text-left flex items-center justify-between gap-3 transition-colors cursor-pointer',
                        paymentMethod === 'va'
                          ? 'border-[#1A3A5C] bg-slate-50'
                          : 'border-slate-200 bg-white hover:border-slate-300'
                      )}
                    >
                      <div className="flex items-center gap-2.5">
                        <CreditCard className="w-4 h-4 text-slate-700" />
                        <div>
                          <span className="font-medium text-slate-900 block">Virtual Account</span>
                          <span className="text-[11px] text-slate-500">BCA, Mandiri, BNI, BRI, Permata</span>
                        </div>
                      </div>
                      <div className={cn('w-3.5 h-3.5 rounded-full border flex items-center justify-center', paymentMethod === 'va' ? 'border-[#1A3A5C] bg-[#1A3A5C]' : 'border-slate-300')}>
                        {paymentMethod === 'va' && <div className="w-1.5 h-1.5 rounded-full bg-white" />}
                      </div>
                    </button>

                    {/* VA Bank Selector */}
                    {paymentMethod === 'va' && (
                      <div className="p-2.5 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                        <span className="text-slate-600 block">Pilih Bank:</span>
                        <div className="grid grid-cols-3 gap-1.5">
                          {['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'].map((bank) => (
                            <button
                              key={bank}
                              type="button"
                              onClick={() => setSelectedBank(bank)}
                              className={cn(
                                'py-1.5 px-2 rounded font-medium text-xs border text-center transition-colors cursor-pointer',
                                selectedBank === bank
                                  ? 'bg-[#1A3A5C] text-white border-[#1A3A5C]'
                                  : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'
                              )}
                            >
                              {bank}
                            </button>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* COD */}
                    <button
                      type="button"
                      onClick={() => setPaymentMethod('cod')}
                      className={cn(
                        'w-full p-3 rounded-lg border text-left flex items-center justify-between gap-3 transition-colors cursor-pointer',
                        paymentMethod === 'cod'
                          ? 'border-[#1A3A5C] bg-slate-50'
                          : 'border-slate-200 bg-white hover:border-slate-300'
                      )}
                    >
                      <div className="flex items-center gap-2.5">
                        <Truck className="w-4 h-4 text-slate-700" />
                        <div>
                          <span className="font-medium text-slate-900 block">Bayar di Tempat (COD)</span>
                          <span className="text-[11px] text-slate-500">Bayar tunai ke kurir saat diantar</span>
                        </div>
                      </div>
                      <div className={cn('w-3.5 h-3.5 rounded-full border flex items-center justify-center', paymentMethod === 'cod' ? 'border-[#1A3A5C] bg-[#1A3A5C]' : 'border-slate-300')}>
                        {paymentMethod === 'cod' && <div className="w-1.5 h-1.5 rounded-full bg-white" />}
                      </div>
                    </button>
                  </div>

                  <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <Button
                      variant="outline"
                      onClick={() => setShowPaymentModal(false)}
                      disabled={actionLoading}
                      className="rounded-lg text-xs"
                    >
                      Batal
                    </Button>
                    <button
                      type="button"
                      onClick={handleInitiatePayment}
                      disabled={actionLoading}
                      className="px-4 py-2 bg-[#1A3A5C] hover:bg-[#152e4a] text-white font-medium rounded-lg text-xs cursor-pointer"
                    >
                      {actionLoading ? 'Memproses...' : 'Lanjutkan'}
                    </button>
                  </div>
                </div>
              )}

              {/* Step 2: Payment Details Active (QRIS / VA Display) */}
              {activePaymentInfo && (
                <div className="space-y-4">
                  {/* QRIS Display */}
                  {activePaymentInfo.method === 'qris' && (
                    <div className="p-4 bg-white rounded-lg border border-slate-200 text-center space-y-3">
                      <div className="w-40 h-40 mx-auto bg-slate-50 border border-slate-200 rounded-lg flex flex-col items-center justify-center p-2">
                        <QrCode className="w-28 h-28 text-slate-900" />
                      </div>
                      <p className="text-xs text-slate-600">
                        Pindai kode QR menggunakan aplikasi e-wallet atau mobile banking Anda.
                      </p>
                    </div>
                  )}

                  {/* Virtual Account Display */}
                  {activePaymentInfo.method === 'va' && (
                    <div className="p-4 bg-white rounded-lg border border-slate-200 space-y-3">
                      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span className="text-slate-600">Bank Virtual Account:</span>
                        <span className="font-medium text-slate-900">
                          {activePaymentInfo.bank_code || selectedBank}
                        </span>
                      </div>
                      <div>
                        <span className="text-slate-500 text-[11px] block mb-1">Nomor Virtual Account:</span>
                        <div className="flex items-center gap-2">
                          <span className="font-mono font-medium text-base text-slate-900 bg-slate-50 px-3 py-1 rounded border border-slate-200 flex-1">
                            {activePaymentInfo.va_number || '88000123456789'}
                          </span>
                          <button
                            type="button"
                            onClick={() => handleCopyVa(activePaymentInfo.va_number || '88000123456789')}
                            className="px-3 py-1 text-xs font-medium border border-slate-200 rounded hover:bg-slate-50 flex items-center gap-1 cursor-pointer"
                          >
                            {copiedVa ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
                            <span>{copiedVa ? 'Tersalin' : 'Salin'}</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Sandbox Simulation */}
                  <div className="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                    <span className="font-medium text-slate-900 block">Simulasi Sandbox:</span>
                    <button
                      type="button"
                      onClick={handleSimulatePaymentSuccess}
                      disabled={actionLoading}
                      className="w-full py-1.5 px-3 bg-emerald-700 hover:bg-emerald-800 text-white font-medium rounded text-xs cursor-pointer"
                    >
                      {actionLoading ? 'Memverifikasi...' : 'Simulasikan Pembayaran Berhasil'}
                    </button>
                  </div>

                  <div className="flex items-center justify-end pt-2">
                    <Button
                      variant="outline"
                      onClick={() => {
                        setShowPaymentModal(false);
                        setActivePaymentInfo(null);
                      }}
                      className="rounded-lg text-xs"
                    >
                      Tutup
                    </Button>
                  </div>
                </div>
              )}
            </div>
          </Modal>

          {/* Modal 3: Complaint Form Modal */}
          {order && (
            <ComplaintFormModal
              isOpen={showComplaintModal}
              onClose={() => setShowComplaintModal(false)}
              orderId={order.id}
              orderNumber={order.order_number}
              onSuccess={(newComplaint) => {
                setComplaints((prev) => [newComplaint, ...prev]);
                setActionSuccessMsg('Komplain Anda berhasil diajukan dan telah diteruskan ke toko.');
              }}
            />
          )}
        </div>
      </div>
    </PublicLayout>
  );
}
