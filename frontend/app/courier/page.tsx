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
  Modal,
} from '@/components/ui';
import {
  getCourierProfile,
  getCourierTasks,
  claimCourierTask,
  transitionCourierTask,
  CourierProfileSummary,
  CourierTaskItem,
} from '@/lib/courier';
import { ORDER_STATUS_META, OrderStatus, confirmCourierCod } from '@/lib/order';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import {
  Truck,
  Store,
  MapPin,
  Phone,
  Calendar,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
  ShoppingBag,
  ChevronRight,
  Send,
  CreditCard,
} from 'lucide-react';

export default function CourierDashboardPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [courierProfile, setCourierProfile] = useState<CourierProfileSummary | null>(null);
  const [tasks, setTasks] = useState<CourierTaskItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'available' | 'active' | 'completed' | 'all'>('available');

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

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);

      // Load profile & tasks in parallel
      const [profileData, tasksData] = await Promise.all([
        getCourierProfile().catch((err) => {
          throw err;
        }),
        getCourierTasks(activeTab),
      ]);

      setCourierProfile(profileData);
      setTasks(tasksData || []);
      if (profileData?.name && (!user || !user.name)) {
        setCurrentUser((prev) =>
          prev
            ? { ...prev, name: profileData.name! }
            : ({
                id: profileData.courier_profile_id || '',
                name: profileData.name!,
                email: profileData.email || '',
                phone: profileData.phone || '',
                role: 'courier',
                roles: ['courier'],
                status: 'active',
                verified_at: profileData.started_at || null,
              } as AuthUser)
        );
      }
    } catch (err: unknown) {
      setError(formatFriendlyErrorMessage(err, 'Gagal memuat dashboard kurir.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [activeTab]);

  const handleLogout = async () => {
    await fetch('/api/auth/courier/logout', { method: 'POST' }).catch(() => null);
    if (typeof window !== 'undefined') localStorage.removeItem('laundryku_courier_user');
    router.push('/courier/login');
  };

  // Claim Task Handler (Atomic)
  const handleClaim = async (orderId: string) => {
    setActionLoadingId(orderId);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await claimCourierTask(orderId);
      setActionSuccessMsg('Tugas berhasil diambil! Silakan segera jalankan penjemputan/pengantaran.');
      loadData();
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
    const targetOrderId = task.order_id;

    setActionLoadingId(targetOrderId);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await transitionCourierTask(targetOrderId, {
        to_status,
        note: transitionNote.trim() || undefined,
      });
      setShowNoteModal(false);
      setActionSuccessMsg(`Status tugas ${task.order_number} berhasil diperbarui.`);
      loadData();
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
      loadData();
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
      id: 'available',
      label: 'Tugas tersedia',
      count: courierProfile?.stats?.available_tasks,
    },
    {
      id: 'active',
      label: 'Tugas aktif saya',
      count: courierProfile?.stats?.active_tasks,
    },
    {
      id: 'completed',
      label: 'Riwayat selesai',
      count: courierProfile?.stats?.completed_tasks,
    },
    {
      id: 'all',
      label: 'Semua',
    },
  ];

  return (
    <DashboardLayout
      userRole="courier"
      userName={courierProfile?.name || currentUser?.name || 'Kurir LaundryKu'}
      userEmail={courierProfile?.email || currentUser?.email || 'kurir@laundryku.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
              Dashboard tugas kurir
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Ambil tugas penjemputan & pengantaran, perbarui status pengiriman cucian secara berkala.
            </p>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={loadData}
            disabled={loading}
            className="rounded-xl border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-2xs flex items-center gap-1.5 self-start sm:self-auto min-h-[38px] px-3.5 text-xs font-semibold"
          >
            <RefreshCw className={cn('w-3.5 h-3.5', loading && 'animate-spin')} strokeWidth={1.75} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* Action Alerts */}
        {actionSuccessMsg && (
          <div className="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-2.5 text-emerald-900 text-xs font-semibold shadow-2xs">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" strokeWidth={2} />
            <span>{actionSuccessMsg}</span>
          </div>
        )}
        {actionErrorMsg && (
          <div className="p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-2.5 text-rose-900 text-xs font-semibold shadow-2xs">
            <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" strokeWidth={2} />
            <span>{actionErrorMsg}</span>
          </div>
        )}

        {/* Assigned Store Banner & Quick Stats */}
        {courierProfile && courierProfile.is_assigned && courierProfile.store && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {/* Store Information Card */}
            <div className="p-5 lg:col-span-2 bg-navy-950 text-white rounded-2xl border border-navy-900 shadow-2xs space-y-4">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <div className="flex items-center gap-2 text-[10px] font-semibold text-sky-300 uppercase tracking-wider mb-1">
                    <Store className="w-3.5 h-3.5" strokeWidth={1.75} />
                    <span>Toko laundry tempat terikat (1 kurir 1 toko)</span>
                  </div>
                  <h2 className="font-bold text-lg sm:text-xl text-white">
                    {courierProfile.store.name}
                  </h2>
                </div>
                <span className="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-md bg-white/10 text-sky-200 border border-white/10">
                  Kurir aktif
                </span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-300 border-t border-white/10 pt-3">
                <div>
                  <span className="text-slate-400 block mb-0.5 text-[11px]">Alamat outlet:</span>
                  <p className="font-medium text-white line-clamp-2">
                    {courierProfile.store.address_text}
                  </p>
                </div>
                <div>
                  <span className="text-slate-400 block mb-0.5 text-[11px]">Kendaraan & radius layanan:</span>
                  <p className="font-medium text-white capitalize font-mono">
                    {courierProfile.vehicle_type || 'Motor'} • Maks. {courierProfile.store.service_radius_km} km
                  </p>
                </div>
              </div>
            </div>

            {/* Quick Stats Grid */}
            <div className="grid grid-cols-3 lg:grid-cols-1 gap-2.5">
              <div className="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                <div>
                  <span className="text-[11px] font-medium text-slate-500 block">Tugas tersedia</span>
                  <span className="font-mono font-bold text-xl sm:text-2xl text-navy-950 tabular-nums">
                    {courierProfile.stats?.available_tasks || 0}
                  </span>
                </div>
                <div className="w-9 h-9 rounded-xl bg-sky-50 text-navy-800 flex items-center justify-center border border-sky-100">
                  <ShoppingBag className="w-4 h-4" strokeWidth={1.75} />
                </div>
              </div>

              <div className="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                <div>
                  <span className="text-[11px] font-medium text-slate-500 block">Tugas aktif saya</span>
                  <span className="font-mono font-bold text-xl sm:text-2xl text-navy-950 tabular-nums">
                    {courierProfile.stats?.active_tasks || 0}
                  </span>
                </div>
                <div className="w-9 h-9 rounded-xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100">
                  <Truck className="w-4 h-4" strokeWidth={1.75} />
                </div>
              </div>

              <div className="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-2xs flex items-center justify-between">
                <div>
                  <span className="text-[11px] font-medium text-slate-500 block">Riwayat selesai</span>
                  <span className="font-mono font-bold text-xl sm:text-2xl text-navy-950 tabular-nums">
                    {courierProfile.stats?.completed_tasks || 0}
                  </span>
                </div>
                <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100">
                  <CheckCircle2 className="w-4 h-4" strokeWidth={1.75} />
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Filter Tabs */}
        <div className="overflow-x-auto pb-1 [scrollbar-width:none]">
          <Tabs
            tabs={tabs}
            activeTab={activeTab}
            onChange={(tabId) => setActiveTab(tabId as 'available' | 'active' | 'completed' | 'all')}
            variant="boxed"
            className="min-w-max"
          />
        </div>

        {/* Loading State */}
        {loading && (
          <div className="space-y-3.5">
            {[1, 2, 3].map((i) => (
              <div key={i} className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-3">
                <div className="flex justify-between items-center">
                  <Skeleton className="h-4 w-48" />
                  <Skeleton className="h-6 w-24 rounded-lg" />
                </div>
                <Skeleton className="h-16 w-full rounded-xl" />
              </div>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="p-8 text-center bg-white rounded-2xl border border-rose-200/80 shadow-2xs">
            <ErrorState
              title="Terjadi kendala memuat tugas"
              message={error}
              retryText="Coba lagi"
              onRetry={loadData}
            />
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && tasks.length === 0 && (
          <div className="p-10 text-center bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
            <EmptyState
              title={
                activeTab === 'available'
                  ? 'Tidak ada tugas tersedia saat ini'
                  : activeTab === 'active'
                  ? 'Anda tidak memiliki tugas aktif'
                  : 'Belum ada riwayat tugas'
              }
              description={
                activeTab === 'available'
                  ? 'Semua pesanan pickup dan delivery saat ini sudah ditugaskan atau sedang diproses oleh toko.'
                  : activeTab === 'active'
                  ? 'Pilih tab "Tugas tersedia" untuk mengambil order penjemputan atau pengantaran baru.'
                  : 'Riwayat tugas yang telah selesai akan tercatat di sini.'
              }
              actionText={activeTab !== 'available' ? 'Lihat tugas tersedia' : 'Segarkan'}
              onAction={() => (activeTab !== 'available' ? setActiveTab('available') : loadData())}
            />
          </div>
        )}

        {/* Tasks List */}
        {!loading && !error && tasks.length > 0 && (
          <div className="space-y-4">
            {tasks.map((task) => {
              const statusMeta = ORDER_STATUS_META[task.status] || {
                label: task.status,
                badgeVariant: 'secondary',
                description: '',
              };

              const isPickup = task.task_type === 'pickup';
              const isAvailableToClaim = !task.courier_id && (task.status === 'PENDING_PICKUP' || task.status === 'READY_FOR_DELIVERY');
              const isMyActiveTask = task.is_assigned_to_me;
              const isActionLoading = actionLoadingId === task.order_id;

              // Customer phone formatted for WhatsApp
              const cleanPhone = (task.customer_phone || '').replace(/[^0-9]/g, '');
              const waNumber = cleanPhone.startsWith('0') ? `62${cleanPhone.slice(1)}` : cleanPhone;
              const waUrl = cleanPhone ? `https://wa.me/${waNumber}?text=Halo%20${encodeURIComponent(task.customer_name || 'Kak')},%20saya%20kurir%20LaundryKu%20dari%20${encodeURIComponent(task.store_name || 'Outlet')}%20untuk%20pesanan%20${task.order_number}` : null;

              return (
                <div
                  key={task.id}
                  className={cn(
                    'p-5 bg-white rounded-2xl border transition-all shadow-2xs space-y-4',
                    isMyActiveTask ? 'border-navy-900/60 ring-1 ring-navy-900/10' : 'border-slate-200/80 hover:border-slate-300'
                  )}
                >
                  {/* Task Header */}
                  <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                      <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                        {/* Task Type Tag */}
                        <span
                          className={cn(
                            'px-2 py-0.5 rounded-md text-[10px] font-semibold border',
                            isPickup
                              ? 'bg-amber-50 text-amber-800 border-amber-200'
                              : 'bg-sky-50 text-navy-900 border-sky-200'
                          )}
                        >
                          {isPickup ? 'Tugas penjemputan' : 'Tugas pengantaran'}
                        </span>

                        <span className="font-mono font-bold text-xs text-navy-900 bg-sky-50 px-2.5 py-0.5 rounded-md border border-sky-100">
                          {task.order_number}
                        </span>

                        <span className="text-xs text-slate-300">•</span>
                        <span className="text-xs text-slate-500 flex items-center gap-1 font-mono">
                          <Calendar className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                          {formatDate(task.created_at, 'DD MMM YYYY, HH:mm')}
                        </span>
                      </div>

                      <h3 className="font-bold text-sm sm:text-base text-navy-950 flex items-center gap-2">
                        <span>{task.customer_name || 'Pelanggan'}</span>
                      </h3>
                    </div>

                    {/* Status Badges */}
                    <div className="flex items-center gap-2 flex-wrap">
                      <Badge variant={statusMeta.badgeVariant} className="px-2.5 py-0.5 text-[11px] rounded-md font-semibold">
                        {statusMeta.label}
                      </Badge>
                      <Badge
                        variant={task.payment_status === 'PAID' ? 'success' : 'warning'}
                        className="px-2 py-0.5 text-[11px] rounded-md font-semibold"
                      >
                        {task.payment_status === 'PAID' ? 'Lunas' : 'Belum bayar (COD/VA)'}
                      </Badge>
                    </div>
                  </div>

                  {/* Task Body Details */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    {/* Address & Navigation */}
                    <div className="space-y-2.5">
                      <div>
                        <span className="text-slate-400 font-medium text-[11px] flex items-center gap-1 mb-1">
                          <MapPin className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.75} />
                          <span>{isPickup ? 'Alamat penjemputan (pelanggan):' : 'Alamat pengantaran (pelanggan):'}</span>
                        </span>
                        <p className="font-semibold text-navy-950 text-sm">
                          {task.pickup_address?.address_text || '-'}
                        </p>
                        {task.pickup_address?.notes && (
                          <p className="text-slate-500 mt-1 italic">
                            Patokan: {task.pickup_address.notes}
                          </p>
                        )}
                      </div>

                      {/* Contact Actions */}
                      <div className="flex items-center gap-2 pt-1">
                        {task.customer_phone && (
                          <a
                            href={`tel:${task.customer_phone}`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-semibold transition-colors min-h-[36px] text-xs"
                          >
                            <Phone className="w-3.5 h-3.5 text-slate-600" strokeWidth={1.75} />
                            <span className="font-mono">{task.customer_phone}</span>
                          </a>
                        )}

                        {waUrl && (
                          <a
                            href={waUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl font-semibold transition-colors min-h-[36px] text-xs"
                          >
                            <Send className="w-3.5 h-3.5 text-emerald-600" strokeWidth={1.75} />
                            <span>WhatsApp</span>
                          </a>
                        )}
                      </div>
                    </div>

                    {/* Order Meta & Laundry Details */}
                    <div className="space-y-2.5 bg-slate-50/70 p-3.5 rounded-xl border border-slate-200/80">
                      <div className="flex justify-between items-center text-slate-600 pb-2 border-b border-slate-200/70">
                        <span>Estimasi / tagihan:</span>
                        <span className="font-mono font-bold text-sm text-navy-950 tabular-nums">
                          {formatRupiah(task.final_amount !== null ? task.final_amount : task.estimated_amount)}
                        </span>
                      </div>

                      <div className="text-slate-600 space-y-1">
                        <p>
                          Berat: <b className="font-mono text-slate-900">{task.actual_weight_kg ? `${task.actual_weight_kg} kg (aktual)` : `${task.estimated_weight_kg || 0} kg (estimasi)`}</b>
                        </p>
                        <p>
                          Layanan: <b className="font-mono text-slate-900">{task.items_count} item layanan</b>
                        </p>
                      </div>

                      {task.customer_notes && (
                        <p className="p-2.5 bg-white rounded-lg text-slate-700 italic border border-slate-200/80">
                          Catatan: &ldquo;{task.customer_notes}&rdquo;
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Task Action Bar */}
                  <div className="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <span className="text-[11px] text-slate-400 order-2 sm:order-1 line-clamp-1">
                      {statusMeta.description}
                    </span>

                    <div className="flex items-center gap-2 w-full sm:w-auto order-1 sm:order-2">
                      {/* Action 1: Available to claim */}
                      {isAvailableToClaim && (
                        <Button
                          onClick={() => handleClaim(task.order_id)}
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[38px] shadow-2xs"
                        >
                          <Truck className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                          <span>{isActionLoading ? 'Mengambil tugas...' : 'Ambil tugas ini'}</span>
                        </Button>
                      )}

                      {/* Action 2: PICKUP_ASSIGNED -> PICKED_UP */}
                      {isMyActiveTask && task.status === 'PICKUP_ASSIGNED' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'PICKED_UP',
                              'Konfirmasi cucian dijemput',
                              'Pastikan cucian sudah Anda terima dari pelanggan sebelum mengonfirmasi status.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[38px] shadow-2xs"
                        >
                          <CheckCircle2 className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                          <span>Cucian sudah dijemput</span>
                        </Button>
                      )}

                      {/* Action 3: PICKED_UP -> AT_STORE */}
                      {isMyActiveTask && task.status === 'PICKED_UP' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'AT_STORE',
                              'Konfirmasi tiba di outlet',
                              'Konfirmasi bahwa cucian pelanggan telah Anda serahkan ke outlet toko laundry.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[38px] shadow-2xs"
                        >
                          <Store className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                          <span>Tiba di outlet</span>
                        </Button>
                      )}

                      {/* Action 4: DELIVERY_ASSIGNED -> OUT_FOR_DELIVERY */}
                      {isMyActiveTask && task.status === 'DELIVERY_ASSIGNED' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'OUT_FOR_DELIVERY',
                              'Mulai pengantaran cucian',
                              'Konfirmasi saat Anda mulai berangkat mengantarkan pakaian bersih ke pelanggan.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[38px] shadow-2xs"
                        >
                          <Send className="w-4 h-4 mr-1.5 text-sky-300" strokeWidth={1.75} />
                          <span>Mulai pengantaran</span>
                        </Button>
                      )}

                      {/* Action 5: OUT_FOR_DELIVERY -> COMPLETED */}
                      {isMyActiveTask && task.status === 'OUT_FOR_DELIVERY' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'COMPLETED',
                              'Selesaikan pengantaran',
                              'Pastikan pakaian bersih telah diserahkan dan diterima oleh pelanggan dengan baik.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-5 rounded-xl text-xs min-h-[38px] shadow-2xs"
                        >
                          <CheckCircle2 className="w-4 h-4 mr-1.5" strokeWidth={1.75} />
                          <span>Serah terima selesai</span>
                        </Button>
                      )}

                      {/* Action 6: Confirm COD if needed */}
                      {isMyActiveTask && (task.status === 'OUT_FOR_DELIVERY' || task.status === 'COMPLETED') && task.payment_status !== 'PAID' && (
                        <Button
                          onClick={() => handleConfirmCod(task.id)}
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-xl text-xs min-h-[38px] shadow-2xs"
                        >
                          <CreditCard className="w-4 h-4 mr-1.5" strokeWidth={1.75} />
                          <span>Konfirmasi COD tunai</span>
                        </Button>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* Modal: Transition Note Input */}
        {showNoteModal && selectedTaskForNote && (
          <Modal
            isOpen={showNoteModal}
            onClose={() => setShowNoteModal(false)}
            title={selectedTaskForNote.title}
            description={selectedTaskForNote.description}
          >
            <div className="space-y-4 py-2">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Catatan perjalanan / serah terima (opsional)
                </label>
                <textarea
                  value={transitionNote}
                  onChange={(e) => setTransitionNote(e.target.value)}
                  placeholder="Contoh: Diterima oleh Pak RT / diserahkan ke resepsionis kos."
                  rows={3}
                  className="w-full text-xs rounded-xl border border-slate-300 p-3 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-900"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setShowNoteModal(false)}
                  disabled={actionLoadingId === selectedTaskForNote.task.order_id}
                  className="rounded-xl text-xs font-semibold"
                >
                  Batal
                </Button>
                <Button
                  size="sm"
                  onClick={handleSubmitTransition}
                  disabled={actionLoadingId === selectedTaskForNote.task.order_id}
                  className="bg-navy-900 hover:bg-navy-800 text-white rounded-xl text-xs font-semibold min-h-[38px] px-4 shadow-2xs"
                >
                  {actionLoadingId === selectedTaskForNote.task.order_id ? 'Memproses...' : 'Konfirmasi status'}
                </Button>
              </div>
            </div>
          </Modal>
        )}
      </div>
    </DashboardLayout>
  );
}
