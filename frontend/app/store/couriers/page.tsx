'use client';

import React, { useState, useEffect } from 'react';
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
  getStoreCouriers,
  recruitStoreCourier,
  terminateStoreCourier,
  rejectStoreCourier,
  StoreCouriersData,
  AssignedCourierItem,
  AvailableCandidateItem,
} from '@/lib/courier';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, AuthUser, logoutAndRedirectHome } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  Truck,
  CheckCircle2,
  AlertCircle,
  Clock,
  RefreshCw,
  Phone,
  Mail,
  ShieldCheck,
  UserX,
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
    await logoutAndRedirectHome('mitra');
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
      label: 'Kurir terdaftar toko',
      count: storeData?.assigned_couriers?.filter((c) => c.status === 'active').length,
    },
    {
      id: 'candidates',
      label: 'Pelamar kurir toko ini',
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
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
              Kurir toko ({storeData?.store?.name || 'Outlet'})
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
            className="rounded-xl border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-2xs flex items-center gap-1.5 self-start sm:self-auto min-h-[38px] px-3.5 text-xs font-semibold"
          >
            <RefreshCw className={cn('w-3.5 h-3.5', loading && 'animate-spin')} strokeWidth={1.75} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* Feedback Alerts */}
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

        {/* Policy Info Callout */}
        <div className="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-start gap-3 text-xs text-slate-700 shadow-2xs">
          <ShieldCheck className="w-4 h-4 text-navy-800 shrink-0 mt-0.5" strokeWidth={1.75} />
          <div>
            <strong className="block font-semibold text-navy-950 mb-0.5">Ketentuan kemitraan kurir (single-store binding)</strong>
            <span className="leading-relaxed text-slate-600">
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
          <div className="space-y-3.5">
            {[1, 2, 3].map((i) => (
              <div key={i} className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-3">
                <div className="flex justify-between items-center">
                  <Skeleton className="h-4 w-48" />
                  <Skeleton className="h-6 w-20 rounded-lg" />
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
              title="Gagal memuat kurir"
              message={error}
              retryText="Coba lagi"
              onRetry={loadCouriers}
            />
          </div>
        )}

        {/* Tab 1: Assigned Couriers List */}
        {!loading && !error && activeTab === 'assigned' && (
          <div>
            {storeData?.assigned_couriers?.length === 0 ? (
              <div className="p-10 text-center bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
                <EmptyState
                  title="Belum ada kurir terikat pada toko ini"
                  description="Outlet Anda belum memiliki kurir yang aktif. Buka tab 'Pelamar kurir toko ini' untuk menyetujui pengajuan kurir."
                  actionText="Cari kandidat kurir"
                  onAction={() => setActiveTab('candidates')}
                />
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {storeData?.assigned_couriers?.map((courier) => {
                  const isActive = courier.status === 'active';
                  const isActionLoading = actionLoadingId === courier.assignment_id;

                  return (
                    <div
                      key={courier.assignment_id}
                      className={cn(
                        'p-5 rounded-2xl border transition-all shadow-2xs space-y-4',
                        isActive ? 'border-slate-200/80 bg-white hover:border-slate-300' : 'opacity-70 bg-slate-50 border-slate-200'
                      )}
                    >
                      <div className="flex items-start justify-between gap-4 pb-3 border-b border-slate-100">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-xl bg-navy-50 text-navy-800 flex items-center justify-center font-bold text-base border border-navy-100">
                            {courier.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <h3 className="font-bold text-sm text-navy-950">
                              {courier.name}
                            </h3>
                            <span className="text-xs text-slate-500 capitalize">
                              Armada: {courier.vehicle_type || 'Motor'}
                            </span>
                          </div>
                        </div>

                        <Badge variant={isActive ? 'success' : 'secondary'} className="px-2.5 py-0.5 text-[11px] rounded-md font-semibold">
                          {isActive ? 'Aktif di toko' : 'Tidak aktif'}
                        </Badge>
                      </div>

                      <div className="space-y-1.5 text-xs text-slate-600">
                        {courier.phone && (
                          <div className="flex items-center gap-2">
                            <Phone className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                            <span className="font-mono">{courier.phone}</span>
                          </div>
                        )}
                        {courier.email && (
                          <div className="flex items-center gap-2">
                            <Mail className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                            <span>{courier.email}</span>
                          </div>
                        )}
                        <div className="flex items-center gap-2 text-[11px] text-slate-400 pt-1">
                          <Clock className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                          <span className="font-mono">Mulai terikat: {formatDate(courier.started_at, 'DD MMMM YYYY')}</span>
                        </div>
                      </div>

                      {isActive && (
                        <div className="pt-3 border-t border-slate-100 flex justify-end">
                          <Button
                            variant="outline"
                            onClick={() => setTerminatingCourier(courier)}
                            disabled={isActionLoading}
                            className="text-xs text-rose-600 border-rose-200 hover:bg-rose-50 min-h-[36px] rounded-xl px-3 font-semibold"
                          >
                            <UserX className="w-3.5 h-3.5 mr-1.5" strokeWidth={1.75} />
                            <span>Nonaktifkan penugasan</span>
                          </Button>
                        </div>
                      )}
                    </div>
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
              <div className="p-10 text-center bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
                <EmptyState
                  title="Belum ada pengajuan kurir baru"
                  description="Saat ini tidak ada pendaftar kurir baru yang belum terikat toko. Calon kurir dapat mengajukan diri melalui aplikasi pelanggan."
                />
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {storeData?.available_candidates?.map((candidate) => {
                  const isActionLoading = actionLoadingId === candidate.courier_profile_id;

                  return (
                    <div
                      key={candidate.courier_profile_id}
                      className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs hover:border-slate-300 transition-colors space-y-4"
                    >
                      <div className="flex items-start justify-between gap-4 pb-3 border-b border-slate-100">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-xl bg-sky-50 text-navy-900 flex items-center justify-center font-bold text-base border border-sky-100">
                            {candidate.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <h3 className="font-bold text-sm text-navy-950">
                              {candidate.name}
                            </h3>
                            <span className="text-xs text-slate-500 capitalize">
                              Kendaraan: {candidate.vehicle_type || 'Motor'}
                            </span>
                          </div>
                        </div>

                        <Badge variant="warning" className="px-2.5 py-0.5 text-[11px] rounded-md font-semibold">
                          Tersedia
                        </Badge>
                      </div>

                      <div className="space-y-1.5 text-xs text-slate-600">
                        {candidate.phone && (
                          <div className="flex items-center gap-2">
                            <Phone className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                            <span className="font-mono">{candidate.phone}</span>
                          </div>
                        )}
                        {candidate.email && (
                          <div className="flex items-center gap-2">
                            <Mail className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                            <span>{candidate.email}</span>
                          </div>
                        )}
                        <div className="flex items-center gap-2 text-[11px] text-slate-400 pt-1">
                          <Clock className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                          <span className="font-mono">Mendaftar: {formatDate(candidate.applied_at, 'DD MMMM YYYY')}</span>
                        </div>
                      </div>

                      <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                        <Button
                          variant="outline"
                          onClick={() => {
                            setRejectingCandidate(candidate);
                            setRejectReason('');
                            setActionErrorMsg(null);
                          }}
                          disabled={isActionLoading}
                          className="text-xs text-rose-600 border-rose-200 hover:bg-rose-50 rounded-xl min-h-[38px] px-3 font-semibold"
                        >
                          <UserX className="w-3.5 h-3.5 mr-1 text-rose-500" strokeWidth={1.75} />
                          <span>Tolak</span>
                        </Button>

                        <Button
                          onClick={() => handleRecruit(candidate)}
                          disabled={isActionLoading}
                          className="bg-navy-900 hover:bg-navy-800 text-white font-semibold text-xs py-2 px-4 rounded-xl min-h-[38px] shadow-2xs"
                        >
                          <UserCheck className="w-3.5 h-3.5 mr-1.5 text-sky-300" strokeWidth={1.75} />
                          <span>{isActionLoading ? 'Memproses...' : 'Setujui & rekrut'}</span>
                        </Button>
                      </div>
                    </div>
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
          title="Tolak lamaran kurir"
          description={`Anda akan menolak pengajuan kurir dari ${rejectingCandidate?.name || 'Kandidat'}. Lamaran akan dihapus dari antrean toko Anda.`}
        >
          <div className="space-y-4 py-2">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Alasan penolakan (opsional)
              </label>
              <textarea
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder="Contoh: Kuota kurir untuk area ini sudah mencukupi."
                rows={3}
                className="w-full text-xs rounded-xl border border-slate-300 p-3 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-900"
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setRejectingCandidate(null)}
                disabled={actionLoadingId === rejectingCandidate?.courier_profile_id}
                className="rounded-xl text-xs font-semibold"
              >
                Batal
              </Button>
              <Button
                size="sm"
                onClick={handleRejectConfirm}
                disabled={actionLoadingId === rejectingCandidate?.courier_profile_id}
                className="bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold min-h-[38px] px-4"
              >
                {actionLoadingId === rejectingCandidate?.courier_profile_id ? 'Memproses...' : 'Tolak lamaran'}
              </Button>
            </div>
          </div>
        </Modal>

        {/* Modal: Confirm Terminate */}
        <Modal
          isOpen={!!terminatingCourier}
          onClose={() => {
            if (actionLoadingId !== terminatingCourier?.assignment_id) {
              setTerminatingCourier(null);
            }
          }}
          title="Nonaktifkan penugasan kurir"
          description={`Apakah Anda yakin ingin menonaktifkan ikatan tugas kurir ${terminatingCourier?.name || 'ini'} dari toko Anda? Kurir tidak akan menerima pesanan jemput/antar dari gerai ini lagi.`}
        >
          <div className="flex justify-end gap-2 pt-4">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setTerminatingCourier(null)}
              disabled={actionLoadingId === terminatingCourier?.assignment_id}
              className="rounded-xl text-xs font-semibold"
            >
              Batal
            </Button>
            <Button
              size="sm"
              onClick={handleTerminateConfirm}
              disabled={actionLoadingId === terminatingCourier?.assignment_id}
              className="bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold min-h-[38px] px-4"
            >
              {actionLoadingId === terminatingCourier?.assignment_id ? 'Memproses...' : 'Ya, nonaktifkan'}
            </Button>
          </div>
        </Modal>
      </div>
    </DashboardLayout>
  );
}
