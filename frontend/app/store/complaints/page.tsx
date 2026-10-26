'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Card,
  Badge,
  Button,
  Modal,
  Select,
  Textarea,
  Skeleton,
  EmptyState,
  ErrorState,
  Input,
} from '@/components/ui';
import {
  getStoreAdminComplaints,
  updateComplaintStatus,
  Complaint,
  COMPLAINT_CATEGORY_LABELS,
  COMPLAINT_STATUS_LABELS,
} from '@/lib/complaint';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  AlertCircle,
  Clock,
  CheckCircle2,
  XCircle,
  Search,
  Filter,
  Image as ImageIcon,
  MessageSquare,
  ArrowRight,
  ShieldAlert,
  ExternalLink,
} from 'lucide-react';

export default function StoreAdminComplaintsPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [complaints, setComplaints] = useState<Complaint[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [totalPages, setTotalPages] = useState<number>(1);

  // Status update modal state
  const [selectedComplaint, setSelectedComplaint] = useState<Complaint | null>(null);
  const [newStatus, setNewStatus] = useState<'OPEN' | 'IN_INVESTIGATION' | 'RESOLVED' | 'REJECTED'>('IN_INVESTIGATION');
  const [resolutionNote, setResolutionNote] = useState<string>('');
  const [updating, setUpdating] = useState<boolean>(false);
  const [updateError, setUpdateError] = useState<string | null>(null);
  const [updateSuccess, setUpdateSuccess] = useState<string | null>(null);

  // Photo preview modal
  const [previewPhoto, setPreviewPhoto] = useState<string | null>(null);

  useEffect(() => {
    setCurrentUser(getStoredUser());
  }, []);

  const fetchComplaints = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getStoreAdminComplaints({
        status: statusFilter !== 'all' ? statusFilter : undefined,
        search: searchQuery.trim() || undefined,
        page: currentPage,
      });
      setComplaints(res.data);
      setTotalPages(res.meta?.last_page as number || 1);
    } catch (err: unknown) {
      const msg = formatFriendlyErrorMessage(err, 'Gagal memuat daftar komplain.');
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchComplaints();
  }, [statusFilter, currentPage]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setCurrentPage(1);
    fetchComplaints();
  };

  const handleOpenUpdateModal = (complaint: Complaint) => {
    setSelectedComplaint(complaint);
    setNewStatus(complaint.status as 'OPEN' | 'IN_INVESTIGATION' | 'RESOLVED' | 'REJECTED');
    setResolutionNote(complaint.resolution_note || '');
    setUpdateError(null);
    setUpdateSuccess(null);
  };

  const handleSaveStatus = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedComplaint) return;

    // MANDATORY resolution_note when closing complaint (RESOLVED / REJECTED)
    if (['RESOLVED', 'REJECTED'].includes(newStatus) && !resolutionNote.trim()) {
      setUpdateError('Catatan penyelesaian (resolution_note) WAJIB diisi saat menyelesaikan atau menolak komplain.');
      return;
    }

    if (['RESOLVED', 'REJECTED'].includes(newStatus) && resolutionNote.trim().length < 5) {
      setUpdateError('Catatan penyelesaian minimal 5 karakter.');
      return;
    }

    setUpdating(true);
    setUpdateError(null);

    try {
      const res = await updateComplaintStatus(
        selectedComplaint.id,
        newStatus,
        resolutionNote.trim() || undefined
      );

      // Update in state
      setComplaints((prev) =>
        prev.map((c) => (c.id === selectedComplaint.id ? res.data : c))
      );
      setUpdateSuccess('Status komplain dan catatan penyelesaian berhasil disimpan!');
      setTimeout(() => {
        setSelectedComplaint(null);
      }, 1000);
    } catch (err: unknown) {
      const msg = formatFriendlyErrorMessage(err, 'Gagal memperbarui status komplain.');
      setUpdateError(msg);
    } finally {
      setUpdating(false);
    }
  };

  const statusOptions = [
    { value: 'all', label: 'Semua Status' },
    { value: 'OPEN', label: 'Baru (Menunggu Tanggapan)' },
    { value: 'IN_INVESTIGATION', label: 'Sedang Diinvestigasi' },
    { value: 'RESOLVED', label: 'Selesai Ditangani' },
    { value: 'REJECTED', label: 'Ditolak' },
  ];

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
      onLogout={logout}
    >
      <div className="space-y-6">
        {/* Header Banner */}
        <Card variant="bento" className="p-6 sm:p-8">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2 mb-2">
                <Badge variant="amber" className="text-xs px-3 py-1 font-semibold rounded-full flex items-center gap-1">
                  <AlertCircle className="w-3.5 h-3.5 text-amber-600" />
                  <span>Manajemen Komplain Pelanggan</span>
                </Badge>
              </div>
              <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900 tracking-tight">
                Pusat Penyelesaian Sengketa Toko
              </h1>
              <p className="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl">
                Tangani laporan kendala pakaian, keterlambatan pengantaran, atau masalah tagihan secara profesional. Berikan catatan penyelesaian yang transparan kepada pelanggan.
              </p>
            </div>
          </div>
        </Card>

        {/* Filters and Search Bar */}
        <Card variant="bento" className="p-4 sm:p-6 space-y-4">
          <div className="flex flex-col md:flex-row items-center justify-between gap-4">
            {/* Status Filter Tabs */}
            <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
              {statusOptions.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  onClick={() => {
                    setStatusFilter(opt.value);
                    setCurrentPage(1);
                  }}
                  className={cn(
                    'px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all min-h-[44px]',
                    statusFilter === opt.value
                      ? 'bg-navy-900 text-white border-navy-900 shadow-xs'
                      : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
                  )}
                >
                  {opt.label}
                </button>
              ))}
            </div>

            {/* Search Input */}
            <form onSubmit={handleSearchSubmit} className="flex items-center gap-2 w-full md:w-72">
              <div className="relative flex-1">
                <Input
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Cari no. order / nama..."
                  className="text-xs pl-9 pr-3 min-h-[44px]"
                />
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              </div>
              <Button type="submit" variant="outline" size="sm" className="rounded-xl min-h-[44px] text-xs">
                Cari
              </Button>
            </form>
          </div>
        </Card>

        {/* Loading State */}
        {loading && (
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <Card key={i} variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 animate-pulse space-y-3">
                <div className="flex justify-between items-center">
                  <div className="h-5 bg-slate-200 rounded w-1/4"></div>
                  <div className="h-5 bg-slate-100 rounded w-20"></div>
                </div>
                <div className="h-16 bg-slate-100 rounded-2xl"></div>
              </Card>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <ErrorState
            title="Gagal Memuat Komplain"
            message={error}
            onRetry={fetchComplaints}
            className="p-8 bg-white rounded-3xl border border-slate-200"
          />
        )}

        {/* Empty State */}
        {!loading && !error && complaints.length === 0 && (
          <EmptyState
            title="Tidak Ada Komplain"
            description={
              statusFilter !== 'all' || searchQuery
                ? 'Tidak ada data komplain yang cocok dengan filter.'
                : 'Bagus! Toko Anda saat ini tidak memiliki komplain dari pelanggan.'
            }
            className="p-12 bg-white rounded-3xl border border-slate-200"
          />
        )}

        {/* Complaints List */}
        {!loading && !error && complaints.length > 0 && (
          <div className="space-y-4">
            {complaints.map((item) => {
              const statusMeta = COMPLAINT_STATUS_LABELS[item.status] || {
                label: item.status,
                variant: 'warning' as const,
              };
              const categoryLabel = COMPLAINT_CATEGORY_LABELS[item.category] || item.category;

              return (
                <Card
                  key={item.id}
                  variant="bento"
                  className="p-6 space-y-4 hover:border-teal-300 transition-all"
                >
                  {/* Card Header */}
                  <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div className="flex flex-wrap items-center gap-2.5">
                      <Badge variant={statusMeta.variant} className="text-xs px-3 py-1 font-semibold rounded-full">
                        {statusMeta.label}
                      </Badge>

                      <span className="font-heading font-extrabold text-sm text-navy-900">
                        Pesanan #{item.order_number}
                      </span>

                      <span className="text-slate-400 text-xs">•</span>

                      <span className="text-xs font-semibold text-slate-600">
                        Pelanggan: {item.customer_name}
                      </span>
                    </div>

                    <div className="flex items-center gap-2 text-[11px] text-slate-400 tabular-nums">
                      <Clock className="w-3.5 h-3.5" />
                      <span>{formatDate(item.created_at)}</span>
                    </div>
                  </div>

                  {/* Category & Description */}
                  <div className="space-y-2 text-xs">
                    <div className="inline-block px-2.5 py-1 rounded-lg bg-sky-50 text-navy-900 font-bold border border-slate-200/80">
                      Kategori: {categoryLabel}
                    </div>

                    <p className="text-slate-700 leading-relaxed bg-slate-50/60 p-4 rounded-2xl border border-slate-100 whitespace-pre-line text-xs">
                      {item.description}
                    </p>
                  </div>

                  {/* Photo Attachments */}
                  {item.attachments && item.attachments.length > 0 && (
                    <div className="space-y-1.5 pt-1">
                      <span className="text-[11px] font-semibold text-slate-500 block">
                        Lampiran Foto Bukti ({item.attachments.length}):
                      </span>
                      <div className="flex flex-wrap gap-2">
                        {item.attachments.map((att) => (
                          <button
                            key={att.id}
                            type="button"
                            onClick={() => setPreviewPhoto(att.url)}
                            className="w-16 h-16 rounded-xl overflow-hidden border border-slate-200 bg-white hover:opacity-80 transition-opacity focus:outline-none focus:ring-2 focus:ring-teal-400"
                          >
                            <img
                              src={att.url}
                              alt={att.file_name || 'Bukti'}
                              className="w-full h-full object-cover"
                            />
                          </button>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Resolution Note if present */}
                  {item.resolution_note && (
                    <div className="p-3.5 bg-teal-50/80 rounded-2xl border border-teal-200 text-xs space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="font-bold text-teal-900 flex items-center gap-1.5">
                          <CheckCircle2 className="w-4 h-4 text-teal-600" />
                          Catatan Penyelesaian Toko:
                        </span>
                        {item.resolved_at && (
                          <span className="text-[10px] text-teal-700 tabular-nums">
                            {formatDate(item.resolved_at)}
                          </span>
                        )}
                      </div>
                      <p className="text-teal-950 font-medium whitespace-pre-line pl-5">
                        {item.resolution_note}
                      </p>
                      {item.resolver_name && (
                        <span className="text-[10px] text-teal-700 block pl-5">
                          Ditangani oleh: {item.resolver_name}
                        </span>
                      )}
                    </div>
                  )}

                  {/* Action Row */}
                  <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
                    <Link
                      href={`/orders/${item.order_id}`}
                      className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-navy-900 min-h-[44px]"
                    >
                      <ExternalLink className="w-3.5 h-3.5 text-teal-600" />
                      <span>Buka Detail Pesanan #{item.order_number}</span>
                    </Link>

                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => handleOpenUpdateModal(item)}
                      className="rounded-xl text-xs min-h-[44px] bg-navy-900 hover:bg-navy-800"
                    >
                      <span>Tanggapi / Perbarui Status</span>
                    </Button>
                  </div>
                </Card>
              );
            })}

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 pt-4">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                  className="rounded-xl text-xs min-h-[44px]"
                >
                  Sebelumnya
                </Button>
                <span className="text-xs text-slate-500 font-semibold px-3">
                  Halaman {currentPage} dari {totalPages}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={currentPage === totalPages}
                  className="rounded-xl text-xs min-h-[44px]"
                >
                  Selanjutnya
                </Button>
              </div>
            )}
          </div>
        )}

        {/* Modal: Update Complaint Status */}
        {selectedComplaint && (
          <Modal
            isOpen={selectedComplaint !== null}
            onClose={() => setSelectedComplaint(null)}
            title={`Tindak Lanjut Komplain #${selectedComplaint.order_number}`}
            maxWidth="lg"
          >
            <form onSubmit={handleSaveStatus} className="space-y-4 text-xs">
              <div className="p-3 bg-sky-50 rounded-2xl border border-sky-200 space-y-1">
                <span className="text-slate-500 block text-[11px]">Keluhan Pelanggan:</span>
                <p className="font-semibold text-navy-900">{selectedComplaint.description}</p>
              </div>

              {updateError && (
                <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                  <span>{updateError}</span>
                </div>
              )}

              {updateSuccess && (
                <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-start gap-2">
                  <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" />
                  <span>{updateSuccess}</span>
                </div>
              )}

              {/* Status Selector */}
              <div className="space-y-1.5">
                <label className="font-bold text-slate-700 block">
                  Perbarui Status Penanganan <span className="text-red-500">*</span>
                </label>
                <Select
                  options={[
                    { value: 'OPEN', label: 'OPEN — Menunggu Tanggapan Toko' },
                    { value: 'IN_INVESTIGATION', label: 'IN_INVESTIGATION — Sedang Diinvestigasi Toko' },
                    { value: 'RESOLVED', label: 'RESOLVED — Selesai Ditangani (Wajib Catatan)' },
                    { value: 'REJECTED', label: 'REJECTED — Ditolak (Wajib Catatan)' },
                  ]}
                  value={newStatus}
                  onChange={(e) => setNewStatus(e.target.value as 'OPEN' | 'IN_INVESTIGATION' | 'RESOLVED' | 'REJECTED')}
                  className="w-full text-xs min-h-[44px]"
                />
              </div>

              {/* Resolution Note */}
              <div className="space-y-1.5">
                <label className="font-bold text-slate-700 flex items-center justify-between">
                  <span>
                    Catatan Penyelesaian Toko{' '}
                    {['RESOLVED', 'REJECTED'].includes(newStatus) ? (
                      <span className="text-red-500 font-bold">(WAJIB DIISI *)</span>
                    ) : (
                      <span className="text-slate-400 font-normal">(Opsional)</span>
                    )}
                  </span>
                </label>
                <Textarea
                  rows={4}
                  value={resolutionNote}
                  onChange={(e) => setResolutionNote(e.target.value)}
                  placeholder={
                    ['RESOLVED', 'REJECTED'].includes(newStatus)
                      ? 'Wajib jelaskan solusi atau alasan penolakan kepada pelanggan (contoh: Pakaian telah diganti dengan kompensasi, atau kendala telah diselesaikan secara damai)...'
                      : 'Catatan internal atau catatan progres investigasi...'
                  }
                  className={cn(
                    'text-xs',
                    ['RESOLVED', 'REJECTED'].includes(newStatus) && !resolutionNote.trim()
                      ? 'border-amber-400 focus:ring-amber-400'
                      : ''
                  )}
                  required={['RESOLVED', 'REJECTED'].includes(newStatus)}
                />
                <span className="text-[11px] text-slate-400 block">
                  Catatan ini akan langsung dapat dilihat oleh pelanggan di detail pesanan mereka.
                </span>
              </div>

              {/* Action Buttons */}
              <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setSelectedComplaint(null)}
                  disabled={updating}
                  className="rounded-xl text-xs min-h-[44px]"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  variant="primary"
                  isLoading={updating}
                  disabled={updating}
                  className="rounded-xl text-xs min-h-[44px] bg-navy-900 hover:bg-navy-800"
                >
                  Simpan Pembaruan
                </Button>
              </div>
            </form>
          </Modal>
        )}

        {/* Modal: Photo Preview */}
        <Modal
          isOpen={previewPhoto !== null}
          onClose={() => setPreviewPhoto(null)}
          title="Bukti Foto Komplain"
          maxWidth="xl"
        >

          <div className="flex flex-col items-center">
            {previewPhoto && (
              <img
                src={previewPhoto}
                alt="Foto Bukti"
                className="max-h-[70vh] w-auto rounded-2xl object-contain border border-slate-200"
              />
            )}
            <div className="pt-4 flex justify-end w-full">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPreviewPhoto(null)}
                className="rounded-xl text-xs min-h-[44px]"
              >
                Tutup
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </DashboardLayout>
  );
}
