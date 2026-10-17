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
  getCourierProfile,
  getCourierTasks,
  claimCourierTask,
  transitionCourierTask,
  CourierProfileSummary,
  CourierTaskItem,
} from '@/lib/courier';
import { ORDER_STATUS_META, OrderStatus, confirmCourierCod } from '@/lib/order';
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
  Clock,
  RefreshCw,
  ShoppingBag,
  ExternalLink,
  ChevronRight,
  Send,
  Sparkles,
  ArrowRight,
  ShieldCheck,
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
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat dashboard kurir.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [activeTab]);

  const handleLogout = async () => {
    await logout();
    router.push(LOGIN_PATH);
  };

  // Claim Task Handler (Atomic)
  const handleClaim = async (taskId: string) => {
    setActionLoadingId(taskId);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await claimCourierTask(taskId);
      setActionSuccessMsg('Tugas berhasil diambil! Silakan segera jalankan penjemputan/pengantaran.');
      loadData();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal mengambil tugas.');
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

    setActionLoadingId(task.id);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await transitionCourierTask(task.id, {
        to_status,
        note: transitionNote.trim() || undefined,
      });
      setShowNoteModal(false);
      setActionSuccessMsg(`Status tugas ${task.order_number} berhasil diperbarui.`);
      loadData();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal memperbarui status tugas.');
    } finally {
      setActionLoadingId(null);
    }
  };

  // Courier confirms COD cash received
  const handleConfirmCod = async (taskId: string) => {
    setActionLoadingId(taskId);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await confirmCourierCod(taskId);
      setActionSuccessMsg('Penerimaan uang COD berhasil dikonfirmasi dan dicatat ke sistem!');
      loadData();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal mengonfirmasi penerimaan COD.');
    } finally {
      setActionLoadingId(null);
    }
  };

  const tabs = [
    {
      id: 'available',
      label: 'Tugas Tersedia',
      count: courierProfile?.stats?.available_tasks,
    },
    {
      id: 'active',
      label: 'Tugas Aktif Saya',
      count: courierProfile?.stats?.active_tasks,
    },
    {
      id: 'completed',
      label: 'Riwayat Selesai',
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
      userName={currentUser?.name || 'Kurir LaundryKu'}
      userEmail={currentUser?.email || 'kurir@laundryku.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-8">
        {/* Header & Store Info Banner */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-3 py-1 rounded-full border border-teal-200/60 w-fit mb-2">
              <Truck className="w-3.5 h-3.5" />
              <span>Operasional Kurir Laundry</span>
            </div>
            <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900">
              Dashboard Tugas Kurir
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Ambil tugas penjemputan & pengantaran, perbarui status pengiriman cucian secara realtime.
            </p>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={loadData}
            disabled={loading}
            className="rounded-xl flex items-center gap-1.5 self-start sm:self-auto"
          >
            <RefreshCw className={cn('w-4 h-4', loading && 'animate-spin')} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* Action Alerts */}
        {actionSuccessMsg && (
          <div className="p-4 bg-teal-50 border border-teal-200 rounded-2xl flex items-center gap-3 text-teal-900 text-xs font-semibold">
            <CheckCircle2 className="w-5 h-5 text-teal-600 shrink-0" />
            <span>{actionSuccessMsg}</span>
          </div>
        )}
        {actionErrorMsg && (
          <div className="p-4 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-3 text-red-900 text-xs font-semibold">
            <AlertCircle className="w-5 h-5 text-red-600 shrink-0" />
            <span>{actionErrorMsg}</span>
          </div>
        )}

        {/* Assigned Store Banner & Quick Stats */}
        {courierProfile && courierProfile.is_assigned && courierProfile.store && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Store Information Card */}
            <Card variant="bento" className="p-6 lg:col-span-2 bg-gradient-to-br from-navy-900 to-navy-950 text-white border-0 shadow-xl">
              <div className="flex items-start justify-between gap-4 mb-4">
                <div>
                  <div className="flex items-center gap-2 text-[11px] font-bold text-teal-300 uppercase tracking-wider mb-1">
                    <Store className="w-4 h-4" />
                    <span>Toko Laundry Tempat Anda Terikat (1 Kurir 1 Toko)</span>
                  </div>
                  <h2 className="font-heading font-extrabold text-xl sm:text-2xl text-white">
                    {courierProfile.store.name}
                  </h2>
                </div>
                <Badge variant="teal" className="text-xs">
                  Kurir Aktif
                </Badge>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-300 border-t border-navy-800 pt-4">
                <div>
                  <span className="text-slate-400 block mb-0.5">Alamat Outlet:</span>
                  <p className="font-medium text-white line-clamp-2">
                    {courierProfile.store.address_text}
                  </p>
                </div>
                <div>
                  <span className="text-slate-400 block mb-0.5">Kendaraan & Radius Layanan:</span>
                  <p className="font-medium text-white capitalize">
                    {courierProfile.vehicle_type || 'Motor'} • Maks. {courierProfile.store.service_radius_km} km
                  </p>
                </div>
              </div>
            </Card>

            {/* Quick Stats Grid */}
            <div className="grid grid-cols-3 lg:grid-cols-1 gap-3">
              <Card variant="default" className="p-4 bg-white rounded-2xl border border-slate-200 flex items-center justify-between">
                <div>
                  <span className="text-[11px] font-semibold text-slate-500 block">Tugas Tersedia</span>
                  <span className="font-heading font-extrabold text-2xl text-teal-600">
                    {courierProfile.stats?.available_tasks || 0}
                  </span>
                </div>
                <div className="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                  <ShoppingBag className="w-5 h-5" />
                </div>
              </Card>

              <Card variant="default" className="p-4 bg-white rounded-2xl border border-slate-200 flex items-center justify-between">
                <div>
                  <span className="text-[11px] font-semibold text-slate-500 block">Tugas Aktif Saya</span>
                  <span className="font-heading font-extrabold text-2xl text-navy-900">
                    {courierProfile.stats?.active_tasks || 0}
                  </span>
                </div>
                <div className="w-10 h-10 rounded-xl bg-sky-50 text-navy-900 flex items-center justify-center">
                  <Truck className="w-5 h-5" />
                </div>
              </Card>

              <Card variant="default" className="p-4 bg-white rounded-2xl border border-slate-200 flex items-center justify-between">
                <div>
                  <span className="text-[11px] font-semibold text-slate-500 block">Riwayat Selesai</span>
                  <span className="font-heading font-extrabold text-2xl text-emerald-600">
                    {courierProfile.stats?.completed_tasks || 0}
                  </span>
                </div>
                <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                  <CheckCircle2 className="w-5 h-5" />
                </div>
              </Card>
            </div>
          </div>
        )}

        {/* Filter Tabs */}
        <div className="overflow-x-auto pb-2">
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
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <Card key={i} variant="default" className="p-6 bg-white rounded-3xl border border-slate-200">
                <div className="flex justify-between items-center mb-4">
                  <Skeleton className="h-5 w-48" />
                  <Skeleton className="h-7 w-28 rounded-full" />
                </div>
                <Skeleton className="h-20 w-full rounded-2xl" />
              </Card>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <Card variant="default" className="p-8 text-center bg-white rounded-3xl border border-red-200">
            <ErrorState
              title="Terjadi Kendala Memuat Tugas"
              message={error}
              retryText="Coba Lagi"
              onRetry={loadData}
            />
          </Card>
        )}

        {/* Empty State */}
        {!loading && !error && tasks.length === 0 && (
          <Card variant="default" className="p-12 text-center bg-white rounded-3xl border border-slate-200">
            <EmptyState
              title={
                activeTab === 'available'
                  ? 'Tidak Ada Tugas Tersedia Saat Ini'
                  : activeTab === 'active'
                  ? 'Anda Tidak Memiliki Tugas Aktif'
                  : 'Belum Ada Riwayat Tugas'
              }
              description={
                activeTab === 'available'
                  ? 'Semua pesanan pickup dan delivery saat ini sudah ditugaskan atau sedang diproses oleh toko.'
                  : activeTab === 'active'
                  ? 'Pilih tab "Tugas Tersedia" untuk mengambil order penjemputan atau pengantaran baru.'
                  : 'Riwayat tugas yang telah selesai akan tercatat di sini.'
              }
              actionText={activeTab !== 'available' ? 'Lihat Tugas Tersedia' : 'Segarkan'}
              onAction={() => (activeTab !== 'available' ? setActiveTab('available') : loadData())}
            />
          </Card>
        )}

        {/* Tasks List */}
        {!loading && !error && tasks.length > 0 && (
          <div className="space-y-6">
            {tasks.map((task) => {
              const statusMeta = ORDER_STATUS_META[task.status] || {
                label: task.status,
                badgeVariant: 'secondary',
                description: '',
              };

              const isPickup = task.task_type === 'pickup';
              const isAvailableToClaim = !task.courier_id && (task.status === 'PENDING_PICKUP' || task.status === 'READY_FOR_DELIVERY');
              const isMyActiveTask = task.is_assigned_to_me;
              const isActionLoading = actionLoadingId === task.id;

              // Customer phone formatted for WhatsApp
              const cleanPhone = (task.customer_phone || '').replace(/[^0-9]/g, '');
              const waNumber = cleanPhone.startsWith('0') ? `62${cleanPhone.slice(1)}` : cleanPhone;
              const waUrl = cleanPhone ? `https://wa.me/${waNumber}?text=Halo%20${encodeURIComponent(task.customer_name || 'Kak')},%20saya%20kurir%20LaundryKu%20dari%20${encodeURIComponent(task.store_name || 'Outlet')}%20untuk%20pesanan%20${task.order_number}` : null;

              return (
                <Card
                  key={task.id}
                  variant="bento"
                  className={cn(
                    'p-6 transition-all duration-300',
                    isMyActiveTask ? 'border-teal-400 bg-sky-50/20' : 'hover:border-slate-300'
                  )}
                >
                  {/* Task Header */}
                  <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-4 border-b border-slate-100">
                    <div>
                      <div className="flex items-center gap-2 mb-1.5 flex-wrap">
                        {/* Task Type Tag */}
                        <span
                          className={cn(
                            'px-2.5 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider',
                            isPickup
                              ? 'bg-amber-100 text-amber-900 border border-amber-300'
                              : 'bg-teal-100 text-teal-900 border border-teal-300'
                          )}
                        >
                          {isPickup ? 'Tugas Penjemputan' : 'Tugas Pengantaran'}
                        </span>

                        <span className="font-mono font-bold text-xs text-navy-900 bg-sky-50 px-2.5 py-0.5 rounded-md border border-slate-200/80">
                          {task.order_number}
                        </span>

                        <span className="text-xs text-slate-400">•</span>
                        <span className="text-xs text-slate-500 flex items-center gap-1">
                          <Calendar className="w-3.5 h-3.5" />
                          {formatDate(task.created_at, 'DD MMM YYYY, HH:mm')}
                        </span>
                      </div>

                      <h3 className="font-heading font-extrabold text-lg text-navy-900 flex items-center gap-2">
                        <span>{task.customer_name || 'Pelanggan'}</span>
                      </h3>
                    </div>

                    {/* Status Badges */}
                    <div className="flex items-center gap-2 flex-wrap">
                      <Badge variant={statusMeta.badgeVariant} className="px-3 py-1 text-xs">
                        {statusMeta.label}
                      </Badge>
                      <Badge
                        variant={task.payment_status === 'PAID' ? 'success' : 'warning'}
                        className="px-2.5 py-0.5 text-[11px]"
                      >
                        {task.payment_status === 'PAID' ? 'Lunas' : 'Belum Bayar (COD/VA)'}
                      </Badge>
                    </div>
                  </div>

                  {/* Task Body Details */}
                  <div className="py-4 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    {/* Address & Navigation */}
                    <div className="space-y-3">
                      <div>
                        <span className="text-slate-400 font-semibold flex items-center gap-1 mb-1">
                          <MapPin className="w-3.5 h-3.5 text-teal-600" />
                          <span>{isPickup ? 'Alamat Penjemputan (Customer):' : 'Alamat Pengantaran (Customer):'}</span>
                        </span>
                        <p className="font-bold text-navy-900 text-sm">
                          {task.pickup_address?.address_text || '-'}
                        </p>
                        {task.pickup_address?.notes && (
                          <p className="text-slate-500 mt-1 italic">
                            Patokan: {task.pickup_address.notes}
                          </p>
                        )}
                      </div>

                      {/* Contact Actions */}
                      <div className="flex items-center gap-3 pt-1">
                        {task.customer_phone && (
                          <a
                            href={`tel:${task.customer_phone}`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold transition-colors min-h-[38px]"
                          >
                            <Phone className="w-3.5 h-3.5 text-teal-600" />
                            <span>Telepon: {task.customer_phone}</span>
                          </a>
                        )}

                        {waUrl && (
                          <a
                            href={waUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl font-bold transition-colors min-h-[38px]"
                          >
                            <Send className="w-3.5 h-3.5 text-emerald-600" />
                            <span>WhatsApp</span>
                          </a>
                        )}
                      </div>
                    </div>

                    {/* Order Meta & Laundry Details */}
                    <div className="space-y-3 bg-white p-4 rounded-2xl border border-slate-100">
                      <div className="flex justify-between items-center text-slate-600 pb-2 border-b border-slate-100">
                        <span>Estimasi / Tagihan:</span>
                        <span className="font-heading font-extrabold text-base text-teal-600 tabular-nums">
                          {formatRupiah(task.final_amount !== null ? task.final_amount : task.estimated_amount)}
                        </span>
                      </div>

                      <div className="text-slate-500 space-y-1">
                        <p>
                          Berat: <b>{task.actual_weight_kg ? `${task.actual_weight_kg} kg (Aktual)` : `${task.estimated_weight_kg || 0} kg (Estimasi)`}</b>
                        </p>
                        <p>
                          Layanan: <b>{task.items_count} item layanan</b>
                        </p>
                      </div>

                      {task.customer_notes && (
                        <p className="p-2.5 bg-sky-50 rounded-xl text-slate-700 italic border border-slate-200/60">
                          Catatan: &ldquo;{task.customer_notes}&rdquo;
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Task Action Bar */}
                  <div className="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span className="text-[11px] text-slate-400 order-2 sm:order-1">
                      {statusMeta.description}
                    </span>

                    <div className="flex items-center gap-3 w-full sm:w-auto order-1 sm:order-2">
                      {/* Action 1: Available to claim */}
                      {isAvailableToClaim && (
                        <Button
                          onClick={() => handleClaim(task.id)}
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                        >
                          <Truck className="w-4 h-4 mr-2" />
                          <span>{isActionLoading ? 'Mengambil Tugas...' : 'Ambil Tugas Ini'}</span>
                        </Button>
                      )}

                      {/* Action 2: PICKUP_ASSIGNED -> PICKED_UP */}
                      {isMyActiveTask && task.status === 'PICKUP_ASSIGNED' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'PICKED_UP',
                              'Konfirmasi Cucian Dijemput',
                              'Pastikan cucian sudah Anda terima dari pelanggan sebelum mengonfirmasi status.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                        >
                          <CheckCircle2 className="w-4 h-4 mr-2" />
                          <span>Cucian Sudah Dijemput (PICKED_UP)</span>
                        </Button>
                      )}

                      {/* Action 3: PICKED_UP -> AT_STORE */}
                      {isMyActiveTask && task.status === 'PICKED_UP' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'AT_STORE',
                              'Konfirmasi Tiba di Toko Laundry',
                              'Serahkan cucian ke pihak toko untuk penimbangan dan proses cuci.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-navy-900 hover:bg-navy-800 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                        >
                          <Store className="w-4 h-4 mr-2 text-teal-400" />
                          <span>Serahkan Cucian ke Toko (AT_STORE)</span>
                        </Button>
                      )}

                      {/* Action 4: DELIVERY_ASSIGNED -> OUT_FOR_DELIVERY */}
                      {isMyActiveTask && task.status === 'DELIVERY_ASSIGNED' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'OUT_FOR_DELIVERY',
                              'Mulai Perjalanan Pengantaran',
                              'Pastikan pakaian bersih dan rapi sudah Anda bawa dari toko menuju alamat pelanggan.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                        >
                          <Send className="w-4 h-4 mr-2" />
                          <span>Mulai Pengantaran (OUT_FOR_DELIVERY)</span>
                        </Button>
                      )}

                      {/* Action 5: COD Confirmation if unpaid */}
                      {isMyActiveTask && (task.status === 'OUT_FOR_DELIVERY' || task.status === 'COMPLETED') && task.payment_status !== 'PAID' && (
                        <Button
                          onClick={() => handleConfirmCod(task.id)}
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 px-5 rounded-xl text-xs min-h-[44px]"
                        >
                          <CreditCard className="w-4 h-4 mr-2" />
                          <span>Terima Uang COD ({formatRupiah(task.final_amount || task.estimated_amount)})</span>
                        </Button>
                      )}

                      {/* Action 6: OUT_FOR_DELIVERY -> COMPLETED */}
                      {isMyActiveTask && task.status === 'OUT_FOR_DELIVERY' && (
                        <Button
                          onClick={() =>
                            handleInitiateTransition(
                              task,
                              'COMPLETED',
                              'Selesaikan Pengantaran (Serah Terima)',
                              'Konfirmasi bahwa cucian bersih telah diterima oleh customer dengan baik.'
                            )
                          }
                          disabled={isActionLoading}
                          className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-xl text-xs min-h-[44px]"
                        >
                          <CheckCircle2 className="w-4 h-4 mr-2" />
                          <span>Cucian Diterima Pelanggan (COMPLETED)</span>
                        </Button>
                      )}
                    </div>
                  </div>
                </Card>
              );
            })}
          </div>
        )}

        {/* Modal: Status Update Note */}
        <Modal
          isOpen={showNoteModal}
          onClose={() => setShowNoteModal(false)}
          title={selectedTaskForNote?.title || 'Perbarui Status Tugas'}
        >
          <div className="space-y-4 text-xs">
            <div className="p-3.5 bg-sky-50 rounded-2xl border border-sky-200 text-slate-700">
              <p className="font-semibold text-navy-900 mb-1">
                Pesanan: {selectedTaskForNote?.task.order_number} ({selectedTaskForNote?.task.customer_name})
              </p>
              <p className="text-slate-600 leading-relaxed">
                {selectedTaskForNote?.description}
              </p>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1.5">
                Catatan Kurir (Opsional)
              </label>
              <Textarea
                value={transitionNote}
                onChange={(e) => setTransitionNote(e.target.value)}
                placeholder="Contoh: Diterima oleh kak Rina langsung, kondisi bersih dan rapi."
                rows={3}
                className="text-xs"
              />
            </div>

            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
              <Button
                variant="outline"
                onClick={() => setShowNoteModal(false)}
                className="rounded-xl text-xs min-h-[40px]"
              >
                Batal
              </Button>
              <Button
                onClick={handleSubmitTransition}
                className="bg-navy-900 hover:bg-navy-800 text-white rounded-xl text-xs font-bold min-h-[40px]"
              >
                Konfirmasi Perubahan Status
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </DashboardLayout>
  );
}
