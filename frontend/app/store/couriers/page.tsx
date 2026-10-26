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
} from '@/components/ui';
import {
  getStoreCouriers,
  recruitStoreCourier,
  terminateStoreCourier,
  rejectStoreCourier,
  StoreCouriersData,
  AssignedCourierItem,
  AvailableCandidateItem,
} from '@/lib/courier';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  Truck,
  Store,
  UserPlus,
  CheckCircle2,
  AlertCircle,
  Clock,
  RefreshCw,
  Phone,
  Mail,
  ShieldCheck,
  UserX,
  ChevronRight,
  UserCheck,
} from 'lucide-react';

export default function StoreAdminCouriersPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [storeData, setStoreData] = useState<StoreCouriersData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'assigned' | 'candidates'>('assigned');

  // Action states
  const [actionLoadingId, setActionLoadingId] = useState<string | null>(null);
  const [actionSuccessMsg, setActionSuccessMsg] = useState<string | null>(null);
  const [actionErrorMsg, setActionErrorMsg] = useState<string | null>(null);

  // Terminate Modal
  const [terminatingCourier, setTerminatingCourier] = useState<AssignedCourierItem | null>(null);

  // Reject Modal
  const [rejectingCandidate, setRejectingCandidate] = useState<AvailableCandidateItem | null>(null);
  const [rejectReason, setRejectReason] = useState<string>('');

  const loadCouriers = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);
      const data = await getStoreCouriers();
      setStoreData(data);
    } catch (err: unknown) {
      setError(formatFriendlyErrorMessage(err, 'Gagal memuat data kurir toko.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCouriers();
  }, []);

  const handleLogout = async () => {
    await logout();
    router.push(LOGIN_PATH);
  };

  // Recruit candidate (Enforces single-store constraint)
  const handleRecruit = async (candidate: AvailableCandidateItem) => {
    setActionLoadingId(candidate.courier_profile_id);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await recruitStoreCourier(candidate.courier_profile_id);
      setActionSuccessMsg(`Kurir ${candidate.name} berhasil disetujui dan aktif bertugas untuk toko Anda.`);
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('laundryku_sidebar_count_refresh'));
      }
      loadCouriers();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal menyetujui pengajuan kurir.');
    } finally {
      setActionLoadingId(null);
    }
  };

  // Reject candidate with optional reason
  const handleRejectConfirm = async () => {
    if (!rejectingCandidate) return;
    setActionLoadingId(rejectingCandidate.courier_profile_id);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await rejectStoreCourier(rejectingCandidate.courier_profile_id, rejectReason.trim() || undefined);
      const rejectedName = rejectingCandidate.name;
      setRejectingCandidate(null);
      setRejectReason('');
      setActionSuccessMsg(`Lamaran kurir ${rejectedName} telah ditolak.`);
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('laundryku_sidebar_count_refresh'));
      }
      await loadCouriers();
    } catch (err: unknown) {
      const errObj = err as { responseData?: { message?: string; error?: { message?: string } }; message?: string };
      const msg = errObj.responseData?.error?.message || errObj.responseData?.message || errObj.message || 'Gagal menolak lamaran kurir.';
      setActionErrorMsg(msg);
    } finally {
      setActionLoadingId(null);
    }
  };

  // Terminate courier assignment
  const handleTerminateConfirm = async () => {
    if (!terminatingCourier) return;
    setActionLoadingId(terminatingCourier.assignment_id);
    setActionErrorMsg(null);
    setActionSuccessMsg(null);
    try {
      await terminateStoreCourier(terminatingCourier.assignment_id);
      setTerminatingCourier(null);
      setActionSuccessMsg(`Penugasan kurir ${terminatingCourier.name} berhasil dinonaktifkan.`);
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('laundryku_sidebar_count_refresh'));
      }
      loadCouriers();
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setActionErrorMsg(errObj.response?.data?.error?.message || 'Gagal menonaktifkan kurir.');
    } finally {
      setActionLoadingId(null);
    }
  };

  const tabs = [
    {
      id: 'assigned',
      label: 'Kurir Terdaftar Toko',
      count: storeData?.assigned_couriers?.filter((c) => c.status === 'active').length,
    },
    {
      id: 'candidates',
      label: 'Pelamar Kurir Toko Ini',
      count: storeData?.available_candidates?.length,
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
              <Truck className="w-3.5 h-3.5" />
              <span>Manajemen Kurir Outlet</span>
            </div>
            <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900">
              Kurir Toko ({storeData?.store?.name || 'Outlet'})
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Rekrut dan kelola armada kurir jemput/antar. Sesuai ketentuan, satu kurir hanya boleh terikat pada 1 toko aktif.
            </p>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={loadCouriers}
            disabled={loading}
            className="rounded-xl flex items-center gap-1.5 self-start sm:self-auto"
          >
            <RefreshCw className={cn('w-4 h-4', loading && 'animate-spin')} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* Feedback Alerts */}
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

        {/* Policy Info Callout */}
        <div className="p-4 bg-sky-50 rounded-2xl border border-sky-200 flex items-start gap-3 text-xs text-slate-700">
          <ShieldCheck className="w-5 h-5 text-teal-600 shrink-0 mt-0.5" />
          <div>
            <strong className="block font-bold text-navy-900 mb-0.5">Ketentuan Kemitraan Kurir (Single-Store Binding)</strong>
            <span>
              Kurir yang Anda rekrut akan terikat secara eksklusif pada toko Anda dan hanya dapat menerima tugas pickup/delivery dari toko ini. Jika kurir sudah terikat pada toko lain, sistem akan otomatis menolak rekrutmen.
            </span>
          </div>
        </div>

        {/* Filter Tabs */}
        <div>
          <Tabs
            tabs={tabs}
            activeTab={activeTab}
            onChange={(tabId) => setActiveTab(tabId as 'assigned' | 'candidates')}
            variant="boxed"
            className="max-w-md"
          />
        </div>

        {/* Loading State */}
        {loading && (
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <Card key={i} variant="default" className="p-6 bg-white rounded-3xl border border-slate-200">
                <div className="flex justify-between items-center mb-4">
                  <Skeleton className="h-5 w-48" />
                  <Skeleton className="h-7 w-24 rounded-full" />
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
              title="Gagal Memuat Kurir"
              message={error}
              retryText="Coba Lagi"
              onRetry={loadCouriers}
            />
          </Card>
        )}

        {/* Tab 1: Assigned Couriers List */}
        {!loading && !error && activeTab === 'assigned' && (
          <div>
            {storeData?.assigned_couriers?.length === 0 ? (
              <Card variant="default" className="p-12 text-center bg-white rounded-3xl border border-slate-200">
                <EmptyState
                  title="Belum Ada Kurir Terikat pada Toko Ini"
                  description="Outlet Anda belum memiliki kurir yang aktif. Buka tab 'Kandidat Kurir Tersedia' untuk merekrut kurir ke toko Anda."
                  actionText="Cari Kandidat Kurir"
                  onAction={() => setActiveTab('candidates')}
                />
              </Card>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {storeData?.assigned_couriers?.map((courier) => {
                  const isActive = courier.status === 'active';
                  const isActionLoading = actionLoadingId === courier.assignment_id;

                  return (
                    <Card
                      key={courier.assignment_id}
                      variant="bento"
                      className={cn(
                        'p-6 transition-all',
                        isActive ? 'border-teal-300 bg-white' : 'opacity-70 bg-slate-50'
                      )}
                    >
                      <div className="flex items-start justify-between gap-4 pb-4 border-b border-slate-100">
                        <div className="flex items-center gap-3">
                          <div className="w-12 h-12 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center font-heading font-extrabold text-lg border border-teal-200/60">
                            {courier.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <h3 className="font-heading font-extrabold text-base text-navy-900">
                              {courier.name}
                            </h3>
                            <span className="text-xs text-slate-500 capitalize">
                              Armada: {courier.vehicle_type || 'Motor'}
                            </span>
                          </div>
                        </div>

                        <Badge variant={isActive ? 'success' : 'secondary'}>
                          {isActive ? 'Aktif di Toko' : 'Tidak Aktif'}
                        </Badge>
                      </div>

                      <div className="py-4 space-y-2 text-xs text-slate-600">
                        {courier.phone && (
                          <div className="flex items-center gap-2">
                            <Phone className="w-3.5 h-3.5 text-teal-600" />
                            <span>{courier.phone}</span>
                          </div>
                        )}
                        {courier.email && (
                          <div className="flex items-center gap-2">
                            <Mail className="w-3.5 h-3.5 text-teal-600" />
                            <span>{courier.email}</span>
                          </div>
                        )}
                        <div className="flex items-center gap-2 text-[11px] text-slate-400 pt-1">
                          <Clock className="w-3.5 h-3.5" />
                          <span>Mulai terikat: {formatDate(courier.started_at, 'DD MMMM YYYY')}</span>
                        </div>
                      </div>

                      {isActive && (
                        <div className="pt-4 border-t border-slate-100 flex justify-end">
                          <Button
                            variant="outline"
                            onClick={() => setTerminatingCourier(courier)}
                            disabled={isActionLoading}
                            className="text-xs text-rose-600 border-rose-200 hover:bg-rose-50 min-h-[38px] rounded-xl"
                          >
                            <UserX className="w-3.5 h-3.5 mr-1.5" />
                            <span>Nonaktifkan Penugasan</span>
                          </Button>
                        </div>
                      )}
                    </Card>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* Tab 2: Available Candidates List */}
        {!loading && !error && activeTab === 'candidates' && (
          <div>
            {storeData?.available_candidates?.length === 0 ? (
              <Card variant="default" className="p-12 text-center bg-white rounded-3xl border border-slate-200">
                <EmptyState
                  title="Belum Ada Pengajuan Kurir Baru"
                  description="Saat ini tidak ada pendaftar kurir baru yang belum terikat toko. Calon kurir dapat mengajukan diri melalui aplikasi customer."
                />
              </Card>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {storeData?.available_candidates?.map((candidate) => {
                  const isActionLoading = actionLoadingId === candidate.courier_profile_id;

                  return (
                    <Card
                      key={candidate.courier_profile_id}
                      variant="bento"
                      className="p-6 transition-all hover:border-teal-300"
                    >
                      <div className="flex items-start justify-between gap-4 pb-4 border-b border-slate-100">
                        <div className="flex items-center gap-3">
                          <div className="w-12 h-12 rounded-2xl bg-sky-50 text-navy-900 flex items-center justify-center font-heading font-extrabold text-lg border border-sky-200/60">
                            {candidate.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <h3 className="font-heading font-extrabold text-base text-navy-900">
                              {candidate.name}
                            </h3>
                            <span className="text-xs text-slate-500 capitalize">
                              Kendaraan: {candidate.vehicle_type || 'Motor'}
                            </span>
                          </div>
                        </div>

                        <Badge variant="warning">Tersedia</Badge>
                      </div>

                      <div className="py-4 space-y-2 text-xs text-slate-600">
                        {candidate.phone && (
                          <div className="flex items-center gap-2">
                            <Phone className="w-3.5 h-3.5 text-teal-600" />
                            <span>{candidate.phone}</span>
                          </div>
                        )}
                        {candidate.email && (
                          <div className="flex items-center gap-2">
                            <Mail className="w-3.5 h-3.5 text-teal-600" />
                            <span>{candidate.email}</span>
                          </div>
                        )}
                        <div className="flex items-center gap-2 text-[11px] text-slate-400 pt-1">
                          <Clock className="w-3.5 h-3.5" />
                          <span>Mendaftar: {formatDate(candidate.applied_at, 'DD MMMM YYYY')}</span>
                        </div>
                      </div>

                      <div className="pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                        <Button
                          variant="outline"
                          onClick={() => {
                            setRejectingCandidate(candidate);
                            setRejectReason('');
                            setActionErrorMsg(null);
                          }}
                          disabled={isActionLoading}
                          className="text-xs text-rose-600 border-rose-200 hover:bg-rose-50 rounded-xl min-h-[40px]"
                        >
                          <UserX className="w-3.5 h-3.5 mr-1 text-rose-500" />
                          <span>Tolak</span>
                        </Button>

                        <Button
                          onClick={() => handleRecruit(candidate)}
                          disabled={isActionLoading}
                          className="bg-navy-900 hover:bg-navy-800 text-white font-bold text-xs py-2 px-4 rounded-xl min-h-[40px]"
                        >
                          <UserCheck className="w-3.5 h-3.5 mr-1.5 text-teal-400" />
                          <span>{isActionLoading ? 'Memproses...' : 'Setujui & Rekrut'}</span>
                        </Button>
                      </div>
                    </Card>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* Modal: Confirm Reject */}
        <Modal
          isOpen={!!rejectingCandidate}
          onClose={() => {
            if (actionLoadingId !== rejectingCandidate?.courier_profile_id) {
              setRejectingCandidate(null);
            }
          }}
          title="Tolak Pengajuan Kurir"
        >
          <div className="space-y-4 text-xs">
            <p className="text-slate-600">
              Apakah Anda yakin ingin menolak pengajuan kurir dari <b>{rejectingCandidate?.name}</b> untuk toko ini?
            </p>

            {actionErrorMsg && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-xl flex items-center gap-2 text-red-900 text-xs font-medium">
                <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />
                <span>{actionErrorMsg}</span>
              </div>
            )}

            <div>
              <label className="block font-semibold text-slate-700 mb-1">
                Alasan Penolakan (Opsional)
              </label>
              <textarea
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder="Contoh: Kuota kurir untuk outlet ini sudah penuh..."
                rows={3}
                disabled={actionLoadingId === rejectingCandidate?.courier_profile_id}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 resize-none disabled:opacity-50"
              />
            </div>

            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
              <Button
                variant="outline"
                onClick={() => setRejectingCandidate(null)}
                disabled={actionLoadingId === rejectingCandidate?.courier_profile_id}
                className="rounded-xl text-xs min-h-[40px]"
              >
                Batal
              </Button>
              <Button
                variant="danger"
                onClick={handleRejectConfirm}
                isLoading={actionLoadingId === rejectingCandidate?.courier_profile_id}
                disabled={actionLoadingId === rejectingCandidate?.courier_profile_id}
                className="rounded-xl text-xs min-h-[40px]"
              >
                {actionLoadingId === rejectingCandidate?.courier_profile_id ? 'Memproses...' : 'Tolak Pengajuan'}
              </Button>
            </div>
          </div>
        </Modal>

        {/* Modal: Confirm Deactivation */}
        <Modal
          isOpen={!!terminatingCourier}
          onClose={() => setTerminatingCourier(null)}
          title="Konfirmasi Penonaktifan Kurir"
        >
          <div className="space-y-4 text-xs">
            <div className="p-3.5 bg-rose-50 rounded-2xl border border-rose-200 text-rose-900 flex items-start gap-2.5">
              <AlertCircle className="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
              <div>
                <strong className="block font-bold">Perhatian:</strong>
                <span>
                  Apakah Anda yakin ingin menonaktifkan kurir <b>{terminatingCourier?.name}</b> dari toko Anda? Kurir ini tidak akan dapat mengambil tugas baru sampai direkrut kembali. Riwayat tugas sebelumnya tetap tersimpan aman.
                </span>
              </div>
            </div>

            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
              <Button
                variant="outline"
                onClick={() => setTerminatingCourier(null)}
                className="rounded-xl text-xs min-h-[40px]"
              >
                Batal
              </Button>
              <Button
                variant="danger"
                onClick={handleTerminateConfirm}
                className="rounded-xl text-xs min-h-[40px]"
              >
                Ya, Nonaktifkan
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </DashboardLayout>
  );
}
