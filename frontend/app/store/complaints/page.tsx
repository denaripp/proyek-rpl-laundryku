'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Badge,
  Button,
  Modal,
  Select,
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
import { getStoredUser, AuthUser, logoutAndRedirectHome } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  AlertCircle,
  Clock,
  CheckCircle2,
  Search,
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
    { value: 'all', label: 'Semua status' },
    { value: 'OPEN', label: 'Baru (menunggu tanggapan)' },
    { value: 'IN_INVESTIGATION', label: 'Sedang diinvestigasi' },
    { value: 'RESOLVED', label: 'Selesai ditangani' },
    { value: 'REJECTED', label: 'Ditolak' },
  ];

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
      onLogout={() => logoutAndRedirectHome('mitra')}
    >
      <div className="space-y-6">
        {/* Header */}
        <div className="pb-1">
          <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
            Pusat komplain pelanggan
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Tangani laporan kendala pakaian, keterlambatan pengantaran, atau masalah tagihan secara profesional.
          </p>
        </div>

        {/* Filters and Search Bar */}
        <div className="p-4 sm:p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-4">
          <div className="flex flex-col md:flex-row items-center justify-between gap-4">
            {/* Status Filter Tabs */}
            <div className="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
              {statusOptions.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  onClick={() => {
                    setStatusFilter(opt.value);
                    setCurrentPage(1);
                  }}
                  className={cn(
                    'px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all min-h-[36px] cursor-pointer',
                    statusFilter === opt.value
                      ? 'bg-navy-900 text-white border-navy-900 shadow-2xs'
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
                  className="text-xs pl-9 pr-3 min-h-[38px] rounded-xl"
                />
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" strokeWidth={1.75} />
              </div>
              <Button type="submit" variant="outline" size="sm" className="rounded-xl min-h-[38px] text-xs font-semibold px-3.5">
                Cari
              </Button>
            </form>
          </div>
        </div>

        {/* Loading State */}
        {loading && (
          <div className="space-y-3.5">
            {[1, 2, 3].map((i) => (
              <div key={i} className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs animate-pulse space-y-3">
                <div className="flex justify-between items-center">
                  <div className="h-4 bg-slate-200 rounded w-1/4"></div>
                  <div className="h-4 bg-slate-100 rounded w-20"></div>
                </div>
                <div className="h-14 bg-slate-100 rounded-xl"></div>
              </div>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="p-8 bg-white rounded-2xl border border-rose-200/80 shadow-2xs">
            <ErrorState
              title="Gagal memuat komplain"
              message={error}
              onRetry={fetchComplaints}
            />
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && complaints.length === 0 && (
          <div className="p-10 bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
            <EmptyState
              title="Tidak ada komplain"
              description={
                statusFilter !== 'all' || searchQuery
                  ? 'Tidak ada data komplain yang cocok dengan filter.'
                  : 'Bagus! Toko Anda saat ini tidak memiliki komplain dari pelanggan.'
              }
            />
          </div>
        )}

        {/* Complaints List */}
        {!loading && !error && complaints.length > 0 && (
          <div className="space-y-3.5">
            {complaints.map((item) => {
              const statusMeta = COMPLAINT_STATUS_LABELS[item.status] || {
                label: item.status,
                variant: 'warning' as const,
              };
              const categoryLabel = COMPLAINT_CATEGORY_LABELS[item.category] || item.category;

              return (
                <div
                  key={item.id}
                  className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-4 hover:border-slate-300 transition-colors"
                >
                  {/* Card Header */}
                  <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div className="flex flex-wrap items-center gap-2.5">
                      <Badge variant={statusMeta.variant} className="text-[11px] px-2.5 py-0.5 font-semibold rounded-md">
                        {statusMeta.label}
                      </Badge>

                      <span className="font-mono font-bold text-xs text-navy-900 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-100">
                        Pesanan #{item.order_number}
                      </span>

                      <span className="text-slate-300 text-xs">•</span>

                      <span className="text-xs font-semibold text-slate-700">
                        Pelanggan: {item.customer_name}
                      </span>
                    </div>

                    <div className="flex items-center gap-1.5 text-[11px] text-slate-400 font-mono">
                      <Clock className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                      <span>{formatDate(item.created_at)}</span>
                    </div>
                  </div>

                  {/* Category & Description */}
                  <div className="space-y-2 text-xs">
                    <div className="inline-block px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-semibold border border-slate-200">
                      Kategori: {categoryLabel}
                    </div>

                    <p className="text-slate-700 leading-relaxed bg-slate-50/60 p-3.5 rounded-xl border border-slate-100 whitespace-pre-line text-xs">
                      {item.description}
                    </p>
                  </div>

                  {/* Photo Attachments */}
                  {item.attachments && item.attachments.length > 0 && (
                    <div className="space-y-1.5 pt-1">
                      <span className="text-[11px] font-semibold text-slate-500 block">
                        Lampiran foto bukti ({item.attachments.length}):
                      </span>
                      <div className="flex flex-wrap gap-2">
                        {item.attachments.map((att) => (
                          <button
                            key={att.id}
                            type="button"
                            onClick={() => setPreviewPhoto(att.url)}
                            className="w-16 h-16 rounded-xl overflow-hidden border border-slate-200 bg-white hover:opacity-80 transition-opacity focus:outline-none focus:ring-2 focus:ring-navy-900"
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
                    <div className="p-3.5 bg-emerald-50/70 rounded-xl border border-emerald-200/80 text-xs space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-emerald-900 flex items-center gap-1.5">
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" strokeWidth={2} />
                          Catatan penyelesaian toko:
                        </span>
                        {item.resolved_at && (
                          <span className="text-[10px] text-emerald-700 font-mono">
                            {formatDate(item.resolved_at)}
                          </span>
                        )}
                      </div>
                      <p className="text-emerald-950 font-medium whitespace-pre-line pl-5 leading-relaxed">
                        {item.resolution_note}
                      </p>
                      {item.resolver_name && (
                        <span className="text-[10px] text-emerald-700 block pl-5">
                          Ditangani oleh: {item.resolver_name}
                        </span>
                      )}
                    </div>
                  )}

                  {/* Action Row */}
                  <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
                    <Link
                      href={`/orders/${item.order_id}`}
                      className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-navy-950 min-h-[38px]"
                    >
                      <ExternalLink className="w-3.5 h-3.5 text-navy-800" strokeWidth={1.75} />
                      <span>Buka detail pesanan #{item.order_number}</span>
                    </Link>

                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => handleOpenUpdateModal(item)}
                      className="rounded-xl text-xs min-h-[38px] bg-navy-900 hover:bg-navy-800 text-white font-semibold shadow-2xs"
                    >
                      <span>Tanggapi / perbarui status</span>
                    </Button>
                  </div>
                </div>
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
                  className="rounded-xl text-xs min-h-[38px] font-semibold"
                >
                  Sebelumnya
                </Button>
                <span className="text-xs text-slate-500 font-semibold px-3 font-mono">
                  Halaman {currentPage} dari {totalPages}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={currentPage === totalPages}
                  className="rounded-xl text-xs min-h-[38px] font-semibold"
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
            title={`Tindak lanjut komplain #${selectedComplaint.order_number}`}
            maxWidth="lg"
          >
            <form onSubmit={handleSaveStatus} className="space-y-4 text-xs">
              <div className="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-1">
                <span className="text-slate-500 block text-[11px]">Keluhan pelanggan:</span>
                <p className="font-semibold text-navy-950">{selectedComplaint.description}</p>
              </div>

              {updateError && (
                <div className="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" strokeWidth={2} />
                  <span>{updateError}</span>
                </div>
              )}

              {updateSuccess && (
                <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-start gap-2">
                  <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" strokeWidth={2} />
                  <span>{updateSuccess}</span>
                </div>
              )}

              {/* Status Selector */}
              <div className="space-y-1.5">
                <label className="font-semibold text-slate-700 block">
                  Perbarui status penanganan <span className="text-rose-500">*</span>
                </label>
                <Select
                  options={[
                    { value: 'OPEN', label: 'OPEN — Menunggu tanggapan toko' },
                    { value: 'IN_INVESTIGATION', label: 'IN_INVESTIGATION — Sedang diinvestigasi' },
                    { value: 'RESOLVED', label: 'RESOLVED — Selesai ditangani' },
                    { value: 'REJECTED', label: 'REJECTED — Ditolak' },
                  ]}
                  value={newStatus}
                  onChange={(val) => setNewStatus(val as any)}
                  className="text-xs"
                />
              </div>

              {/* Resolution Note Input */}
              <div className="space-y-1.5">
                <label className="font-semibold text-slate-700 block">
                  Catatan penyelesaian / tanggapan toko {['RESOLVED', 'REJECTED'].includes(newStatus) && <span className="text-rose-500">* (wajib)</span>}
                </label>
                <textarea
                  value={resolutionNote}
                  onChange={(e) => setResolutionNote(e.target.value)}
                  placeholder="Tuliskan solusi atau tindak lanjut (mis. penggantian biaya cuci, penjelasan kendala)..."
                  rows={4}
                  className="w-full text-xs rounded-xl border border-slate-300 p-3 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-900"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setSelectedComplaint(null)}
                  disabled={updating}
                  className="rounded-xl text-xs font-semibold"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={updating}
                  className="bg-navy-900 hover:bg-navy-800 text-white rounded-xl text-xs font-semibold min-h-[38px] px-4 shadow-2xs"
                >
                  {updating ? 'Menyimpan...' : 'Simpan tanggapan'}
                </Button>
              </div>
            </form>
          </Modal>
        )}

        {/* Modal: Photo Preview */}
        {previewPhoto && (
          <Modal
            isOpen={previewPhoto !== null}
            onClose={() => setPreviewPhoto(null)}
            title="Bukti foto komplain"
            maxWidth="xl"
          >
            <div className="flex justify-center p-2">
              <img
                src={previewPhoto}
                alt="Pratinjau Bukti"
                className="max-h-[80vh] w-auto rounded-xl object-contain"
              />
            </div>
          </Modal>
        )}
      </div>
    </DashboardLayout>
  );
}
