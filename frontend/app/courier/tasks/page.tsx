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
  Modal,
  Textarea,
} from '@/components/ui';
import {
  getCourierTasks,
  claimCourierTask,
  transitionCourierTask,
  CourierTaskItem,
} from '@/lib/courier';
import { ORDER_STATUS_META, OrderStatus, confirmCourierCod } from '@/lib/order';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  Truck,
  MapPin,
  Phone,
  CheckCircle2,
  AlertCircle,
  Clock,
  RefreshCw,
  ShoppingBag,
  ExternalLink,
  ChevronRight,
  Send,
  CreditCard,
} from 'lucide-react';

export default function CourierTasksPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [tasks, setTasks] = useState<CourierTaskItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'active' | 'available' | 'all' | 'completed'>('active');

  // Action states
  const [actionLoadingId, setActionLoadingId] = useState<string | null>(null);
  const [actionSuccessMsg, setActionSuccessMsg] = useState<string | null>(null);
  const [actionErrorMsg, setActionErrorMsg] = useState<string | null>(null);

  // Transition Note Modal
  const [showNoteModal, setShowNoteModal] = useState<boolean>(false);
  const [selectedTaskForNote, setSelectedTaskForNote] = useState<{
    task: CourierTaskItem;
    to_status: OrderStatus;
    title: string;
    description: string;
  } | null>(null);
  const [transitionNote, setTransitionNote] = useState<string>('');

  const loadTasks = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);

      const tasksData = await getCourierTasks(activeTab);
      setTasks(tasksData || []);
    } catch (err: unknown) {
      setError(formatFriendlyErrorMessage(err, 'Gagal memuat tugas pengantaran.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadTasks();

    const intervalId = setInterval(async () => {
      try {
        const tasksData = await getCourierTasks(activeTab);
        setTasks(tasksData || []);
      } catch {
        // Silent catch for background auto-refresh
      }
    }, 10000);

    return () => clearInterval(intervalId);
  }, [activeTab]);

  const handleLogout = async () => {
    await fetch('/api/auth/courier/logout', { method: 'POST' }).catch(() => null);
    router.push('/courier/login');
  };

  // Claim Task Handler
  const handleClaim = async (orderId: string) => {
    setActionLoadingId(orderId);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await claimCourierTask(orderId);
      setActionSuccessMsg('Tugas berhasil diambil! Silakan segera jalankan tugas.');
      loadTasks();
    } catch (err: unknown) {
      const anyErr = err as any;
      const errorMsg =
        anyErr?.responseData?.message ||
        anyErr?.responseData?.error?.message ||
        anyErr?.response?.data?.message ||
        anyErr?.response?.data?.error?.message ||
        anyErr?.message ||
        'Gagal mengambil tugas.';
      setActionErrorMsg(errorMsg);
    } finally {
      setActionLoadingId(null);
    }
  };

  // Open note modal or execute transition
  const handleInitiateTransition = (task: CourierTaskItem, toStatus: OrderStatus, title: string, description: string) => {
    setSelectedTaskForNote({
      task,
      to_status: toStatus,
      title,
      description,
    });
    setTransitionNote('');
    setShowNoteModal(true);
  };

  // Submit Transition
  const handleSubmitTransition = async () => {
    if (!selectedTaskForNote) return;
    const { task, to_status } = selectedTaskForNote;

    setActionLoadingId(task.order_id);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await transitionCourierTask(task.order_id, {
        to_status,
        note: transitionNote.trim() || undefined,
      });
      setShowNoteModal(false);
      setActionSuccessMsg(`Status tugas ${task.order_number} berhasil diperbarui.`);
      loadTasks();
    } catch (err: unknown) {
      const anyErr = err as any;
      const errorMsg =
        anyErr?.responseData?.message ||
        anyErr?.responseData?.error?.message ||
        anyErr?.response?.data?.message ||
        anyErr?.response?.data?.error?.message ||
        anyErr?.message ||
        'Gagal memperbarui status tugas.';
      setActionErrorMsg(errorMsg);
    } finally {
      setActionLoadingId(null);
    }
  };

  // Courier confirms COD cash received
  const handleConfirmCod = async (orderId: string) => {
    setActionLoadingId(orderId);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await confirmCourierCod(orderId);
      setActionSuccessMsg('Penerimaan uang COD berhasil dikonfirmasi dan dicatat ke sistem!');
      loadTasks();
    } catch (err: unknown) {
      const anyErr = err as any;
      const errorMsg =
        anyErr?.responseData?.message ||
        anyErr?.responseData?.error?.message ||
        anyErr?.response?.data?.message ||
        anyErr?.response?.data?.error?.message ||
        anyErr?.message ||
        'Gagal mengonfirmasi penerimaan COD.';
      setActionErrorMsg(errorMsg);
    } finally {
      setActionLoadingId(null);
    }
  };

  const tabs = [
    {
      id: 'active',
      label: 'Tugas Aktif Saya',
    },
    {
      id: 'available',
      label: 'Tugas Tersedia',
    },
    {
      id: 'all',
      label: 'Semua Tugas',
    },
    {
      id: 'completed',
      label: 'Riwayat Selesai',
    },
  ];

  return (
    <DashboardLayout
      userRole="courier"
      userName={currentUser?.name || 'Kurir LaundryKu'}
      userEmail={currentUser?.email || 'kurir@laundryku.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs font-semibold text-slate-600 mb-1">
              <Truck className="w-4 h-4 text-[#1A3A5C]" />
              <span>Operasional Kurir</span>
            </div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
              Tugas Pengantaran & Penjemputan
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Kelola proses penjemputan dari pelanggan dan pengantaran cucian kembali ke alamat tujuan.
            </p>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={loadTasks}
            disabled={loading}
            className="flex items-center gap-1.5 self-start sm:self-auto"
          >
            <RefreshCw className={cn('w-3.5 h-3.5', loading && 'animate-spin')} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* Action feedback banners */}
        {actionSuccessMsg && (
          <div className="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-md text-xs flex items-center justify-between animate-fadeIn">
            <div className="flex items-center gap-2">
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
              <span>{actionSuccessMsg}</span>
            </div>
            <button
              onClick={() => setActionSuccessMsg(null)}
              className="text-emerald-600 hover:text-emerald-900 font-bold ml-2 text-sm"
              aria-label="Tutup pesan sukses"
            >
              &times;
            </button>
          </div>
        )}

        {actionErrorMsg && (
          <div className="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-md text-xs flex items-center justify-between animate-fadeIn">
            <div className="flex items-center gap-2">
              <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
              <span>{actionErrorMsg}</span>
            </div>
            <button
              onClick={() => setActionErrorMsg(null)}
              className="text-rose-600 hover:text-rose-900 font-bold ml-2 text-sm"
              aria-label="Tutup pesan error"
            >
              &times;
            </button>
          </div>
        )}

        {/* Filter Tabs */}
        <div className="border-b border-slate-200">
          <Tabs
            tabs={tabs}
            activeTab={activeTab}
            onChange={(tabId) => setActiveTab(tabId as typeof activeTab)}
          />
        </div>

        {/* Content Section: Loading Skeleton / Error / Empty / List */}
        {loading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="bg-white border border-slate-200 rounded-[8px] p-5 space-y-4 animate-pulse">
                <div className="flex justify-between items-center">
                  <div className="h-4 bg-slate-200 rounded w-1/3" />
                  <div className="h-5 bg-slate-200 rounded w-16" />
                </div>
                <div className="space-y-2">
                  <div className="h-3 bg-slate-100 rounded w-3/4" />
                  <div className="h-3 bg-slate-100 rounded w-1/2" />
                </div>
                <div className="h-9 bg-slate-100 rounded" />
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="bg-white border border-slate-200 rounded-[8px] p-8 text-center max-w-md mx-auto space-y-4">
            <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-500">
              <AlertCircle className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-semibold text-slate-900">Tugas belum bisa dimuat</h3>
              <p className="text-xs text-slate-500 mt-1">{error}</p>
            </div>
            <Button size="sm" onClick={loadTasks} className="bg-[#1A3A5C] text-white hover:bg-[#132d47]">
              Coba lagi
            </Button>
          </div>
        ) : tasks.length === 0 ? (
          <div className="bg-white border border-slate-200 rounded-[8px] p-12 text-center max-w-md mx-auto space-y-3">
            <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
              <ShoppingBag className="w-5 h-5" />
            </div>
            <h3 className="text-sm font-semibold text-slate-900">Belum ada tugas yang ditugaskan.</h3>
            <p className="text-xs text-slate-500">
              {activeTab === 'available'
                ? 'Saat ini belum ada tugas baru yang menunggu untuk diambil.'
                : 'Tugas yang Anda ambil atau ditugaskan akan tampil di halaman ini.'}
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {tasks.map((task) => {
              const meta = ORDER_STATUS_META[task.status as OrderStatus] || {
                label: task.status,
                variant: 'neutral' as const,
              };
              const isAssignedToMe = task.is_assigned_to_me;
              const isClaimable = !task.courier_id && (task.status === 'PENDING_PICKUP' || task.status === 'READY_FOR_DELIVERY');

              return (
                <div
                  key={task.id}
                  className="bg-white border border-slate-200 hover:border-slate-300 rounded-[8px] p-5 flex flex-col justify-between transition-colors shadow-xs"
                >
                  <div className="space-y-4">
                    {/* Header: Order Number & Status Badge */}
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-mono font-bold text-sm text-[#1A3A5C]">
                            {task.order_number}
                          </span>
                          <span
                            className={cn(
                              'text-[10px] font-semibold px-2 py-0.5 rounded-[4px] uppercase tracking-wider',
                              task.task_type === 'pickup'
                                ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                : 'bg-sky-50 text-sky-700 border border-sky-200'
                            )}
                          >
                            {task.task_type === 'pickup' ? 'Penjemputan' : 'Pengantaran'}
                          </span>
                        </div>
                        <div className="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1.5">
                          <Clock className="w-3 h-3 text-slate-400" />
                          <span>{formatDate(task.created_at)}</span>
                        </div>
                      </div>

                      <Badge variant={meta.badgeVariant} size="sm">
                        {meta.label}
                      </Badge>
                    </div>

                    {/* Customer & Address Details */}
                    <div className="space-y-2 pt-2 border-t border-slate-100 text-xs">
                      <div className="flex items-start gap-2">
                        <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                        <div className="text-slate-700 leading-snug">
                          <span className="font-medium text-slate-900">{task.customer_name || 'Pelanggan'}</span>
                          <p className="text-[11px] text-slate-500 mt-0.5">
                            {task.task_type === 'pickup'
                              ? task.pickup_address?.address_text || '-'
                              : task.delivery_address?.address_text || '-'}
                          </p>
                        </div>
                      </div>

                      {task.customer_phone && (
                        <div className="flex items-center gap-2 pl-5.5 text-[11px] text-slate-600">
                          <Phone className="w-3 h-3 text-slate-400" />
                          <span>{task.customer_phone}</span>
                        </div>
                      )}
                    </div>

                    {/* Order Metrics summary */}
                    <div className="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs text-slate-600">
                      <div>
                        <span className="text-[11px] text-slate-400 block">Estimasi Biaya:</span>
                        <span className="font-semibold text-slate-900 tabular-nums">
                          {formatRupiah(task.estimated_amount)}
                        </span>
                      </div>
                      <div>
                        <span className="text-[11px] text-slate-400 block">Status Bayar:</span>
                        <span className="font-medium text-slate-700">
                          {task.payment_status === 'PAID' ? 'Lunas' : 'Belum Lunas'}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Actions according to courier state machine */}
                  <div className="pt-4 mt-4 border-t border-slate-100 flex flex-wrap gap-2 justify-end">
                    {/* Claim task if available */}
                    {isClaimable && (
                      <Button
                        size="sm"
                        onClick={() => handleClaim(task.order_id)}
                        disabled={actionLoadingId === task.order_id}
                        className="bg-[#1A3A5C] text-white hover:bg-[#132d47]"
                      >
                        {actionLoadingId === task.order_id ? 'Memproses...' : 'Ambil Tugas Ini'}
                      </Button>
                    )}

                    {/* Pickup Task State Machine */}
                    {isAssignedToMe && task.status === 'PICKUP_ASSIGNED' && (
                      <Button
                        size="sm"
                        onClick={() =>
                          handleInitiateTransition(
                            task,
                            'PICKED_UP',
                            'Konfirmasi Penjemputan Selesai',
                            'Pastikan cucian telah diterima dari pelanggan dan ditandai dengan benar.'
                          )
                        }
                        disabled={actionLoadingId === task.order_id}
                        className="bg-[#1A3A5C] text-white hover:bg-[#132d47]"
                      >
                        Selesai Jemput Cucian
                      </Button>
                    )}

                    {isAssignedToMe && task.status === 'PICKED_UP' && (
                      <Button
                        size="sm"
                        onClick={() =>
                          handleInitiateTransition(
                            task,
                            'AT_STORE',
                            'Konfirmasi Tiba di Outlet',
                            'Serahkan cucian kepada admin outlet untuk penimbangan nota.'
                          )
                        }
                        disabled={actionLoadingId === task.order_id}
                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                      >
                        Tiba di Outlet
                      </Button>
                    )}

                    {/* Delivery Task State Machine */}
                    {isAssignedToMe && task.status === 'DELIVERY_ASSIGNED' && (
                      <Button
                        size="sm"
                        onClick={() =>
                          handleInitiateTransition(
                            task,
                            'OUT_FOR_DELIVERY',
                            'Mulai Pengantaran ke Pelanggan',
                            'Mulai perjalanan mengantarkan cucian bersih ke alamat pelanggan.'
                          )
                        }
                        disabled={actionLoadingId === task.order_id}
                        className="bg-[#1A3A5C] text-white hover:bg-[#132d47]"
                      >
                        Mulai Pengantaran
                      </Button>
                    )}

                    {isAssignedToMe && task.status === 'OUT_FOR_DELIVERY' && (
                      <Button
                        size="sm"
                        onClick={() =>
                          handleInitiateTransition(
                            task,
                            'COMPLETED',
                            'Selesaikan Pengantaran',
                            'Pastikan pakaian bersih telah diterima dengan baik oleh pelanggan.'
                          )
                        }
                        disabled={actionLoadingId === task.order_id}
                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                      >
                        Selesaikan Pengantaran
                      </Button>
                    )}

                    {/* Confirm COD Payment button if applicable */}
                    {isAssignedToMe &&
                      task.payment_status === 'UNPAID' &&
                      (task.status === 'OUT_FOR_DELIVERY' || task.status === 'COMPLETED') && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleConfirmCod(task.order_id)}
                          disabled={actionLoadingId === task.order_id}
                          className="border-emerald-300 text-emerald-800 hover:bg-emerald-50"
                        >
                          <CreditCard className="w-3.5 h-3.5 mr-1 text-emerald-600" />
                          Terima Uang COD
                        </Button>
                      )}
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* Modal Note / Transition Confirmation */}
        {showNoteModal && selectedTaskForNote && (
          <Modal
            isOpen={showNoteModal}
            onClose={() => setShowNoteModal(false)}
            title={selectedTaskForNote.title}
          >
            <div className="space-y-4">
              <p className="text-xs text-slate-600">
                {selectedTaskForNote.description}
              </p>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Catatan Pengantaran (Opsional):
                </label>
                <Textarea
                  value={transitionNote}
                  onChange={(e) => setTransitionNote(e.target.value)}
                  placeholder="Contoh: Diterima oleh satpam / Cucian dalam kantong rapat"
                  rows={3}
                  className="text-xs"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setShowNoteModal(false)}
                  disabled={Boolean(actionLoadingId)}
                >
                  Batal
                </Button>
                <Button
                  size="sm"
                  onClick={handleSubmitTransition}
                  disabled={Boolean(actionLoadingId)}
                  className="bg-[#1A3A5C] text-white hover:bg-[#132d47]"
                >
                  {actionLoadingId ? 'Menyimpan...' : 'Konfirmasi Update'}
                </Button>
              </div>
            </div>
          </Modal>
        )}
      </div>
    </DashboardLayout>
  );
}
