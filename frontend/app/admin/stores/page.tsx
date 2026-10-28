'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AdminStore } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search, CheckCircle, XCircle, Eye, X } from 'lucide-react';

export default function AdminStoresPage() {
  const [stores, setStores] = useState<AdminStore[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const [statusFilter, setStatusFilter] = useState<string>('pending');
  const [search, setSearch] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const [total, setTotal] = useState<number>(0);
  const [lastPage, setLastPage] = useState<number>(1);

  const [selectedStore, setSelectedStore] = useState<AdminStore | null>(null);
  const [detailModalOpen, setDetailModalOpen] = useState<boolean>(false);
  const [rejectModalOpen, setRejectModalOpen] = useState<boolean>(false);
  const [rejectionReason, setRejectionReason] = useState<string>('');
  const [actionLoading, setActionLoading] = useState<boolean>(false);
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const fetchStores = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const res = await adminApi.getStores({
        approval_status: statusFilter === 'all' ? undefined : statusFilter,
        q: search || undefined,
        page,
      });
      setStores(res.data || []);
      if (res.meta) {
        setTotal(res.meta.total || 0);
        setLastPage(res.meta.last_page || 1);
      }
    } catch {
      setStores([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [statusFilter, search, page]);

  useEffect(() => {
    fetchStores();
  }, [fetchStores]);

  const handleApprove = async (store: AdminStore) => {
    setActionLoading(true);
    setFeedback(null);
    try {
      await adminApi.approveStore(store.id);
      setFeedback({ type: 'success', message: `Toko "${store.name}" berhasil disetujui.` });
      setDetailModalOpen(false);
      fetchStores();
    } catch (err: any) {
      setFeedback({ type: 'error', message: err.message || 'Gagal menyetujui toko.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleRejectSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedStore) return;
    if (!rejectionReason || rejectionReason.trim().length < 5) {
      setFeedback({ type: 'error', message: 'Alasan penolakan minimal 5 karakter.' });
      return;
    }

    setActionLoading(true);
    setFeedback(null);
    try {
      await adminApi.rejectStore(selectedStore.id, rejectionReason.trim());
      setFeedback({ type: 'success', message: `Pengajuan toko "${selectedStore.name}" telah ditolak.` });
      setRejectModalOpen(false);
      setDetailModalOpen(false);
      setRejectionReason('');
      fetchStores();
    } catch (err: any) {
      setFeedback({ type: 'error', message: err.message || 'Gagal menolak toko.' });
    } finally {
      setActionLoading(false);
    }
  };

  const columns: Column<AdminStore>[] = [
    {
      header: 'Nama toko',
      accessor: (item) => (
        <div>
          <div className="font-semibold text-slate-900">{item.name}</div>
          <div className="text-[11px] text-slate-500 truncate max-w-xs">{item.address_text}</div>
        </div>
      ),
    },
    {
      header: 'Pemilik (owner)',
      accessor: (item) => (
        <div>
          <div className="text-slate-800 font-medium">{item.owner?.name || '-'}</div>
          <div className="text-[11px] text-slate-500 font-mono">{item.owner?.phone || item.owner?.email || ''}</div>
        </div>
      ),
    },
    {
      header: 'Status persetujuan',
      accessor: (item) => <StatusText status={item.approval_status.toUpperCase()} />,
    },
    {
      header: 'Status operasional',
      accessor: (item) => (
        <span className="font-mono text-xs text-slate-600 uppercase">
          {item.operational_status}
        </span>
      ),
    },
    {
      header: 'Tanggal diajukan',
      accessor: (item) => (
        <span className="font-mono text-slate-500">
          {new Date(item.created_at).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
          })}
        </span>
      ),
    },
    {
      header: 'Aksi',
      className: 'text-right',
      accessor: (item) => (
        <div className="flex items-center justify-end gap-1.5">
          <button
            onClick={async () => {
              try {
                const fullStore = await adminApi.getStore(item.id);
                setSelectedStore(fullStore);
              } catch {
                setSelectedStore(item);
              }
              setDetailModalOpen(true);
            }}
            className="p-1.5 rounded-lg text-slate-500 hover:text-navy-950 hover:bg-slate-100 transition-colors cursor-pointer min-h-[32px] min-w-[32px] flex items-center justify-center"
            title="Lihat detail"
            aria-label="Lihat detail toko"
          >
            <Eye className="w-4 h-4" strokeWidth={1.75} />
          </button>
          {item.approval_status === 'pending' && (
            <>
              <button
                onClick={() => handleApprove(item)}
                disabled={actionLoading}
                className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors cursor-pointer min-h-[32px] min-w-[32px] flex items-center justify-center"
                title="Setujui toko"
                aria-label="Setujui toko"
              >
                <CheckCircle className="w-4 h-4" strokeWidth={1.75} />
              </button>
              <button
                onClick={() => {
                  setSelectedStore(item);
                  setRejectModalOpen(true);
                }}
                disabled={actionLoading}
                className="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer min-h-[32px] min-w-[32px] flex items-center justify-center"
                title="Tolak toko"
                aria-label="Tolak toko"
              >
                <XCircle className="w-4 h-4" strokeWidth={1.75} />
              </button>
            </>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-5">
      <AdminHeader
        title="Persetujuan & manajemen toko"
        subtitle="Verifikasi pendaftaran gerai toko baru dan tata kelola outlet laundry"
        onRefresh={() => fetchStores(true)}
        refreshing={refreshing}
      />

      {feedback && (
        <div
          className={`p-3.5 rounded-xl text-xs flex items-center justify-between shadow-2xs ${
            feedback.type === 'success'
              ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
              : 'bg-rose-50 border border-rose-200 text-rose-800'
          }`}
        >
          <span>{feedback.message}</span>
          <button onClick={() => setFeedback(null)} className="text-slate-400 hover:text-slate-700 cursor-pointer p-1" aria-label="Tutup notifikasi">
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Filter bar */}
      <div className="flex flex-col sm:flex-row gap-2.5 items-center justify-between bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs">
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-72">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" strokeWidth={1.75} />
            <input
              type="text"
              placeholder="Cari nama toko, alamat, pemilik..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-navy-900"
            />
          </div>

          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            className="py-2 px-3 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-navy-900 cursor-pointer font-medium"
          >
            <option value="pending">Menunggu persetujuan</option>
            <option value="approved">Disetujui (Approved)</option>
            <option value="rejected">Ditolak (Rejected)</option>
            <option value="all">Semua status</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <DataTable
        columns={columns}
        data={stores}
        keyExtractor={(item) => item.id}
        loading={loading}
        emptyText="Tidak ada permohonan toko yang ditemukan."
        pagination={{
          currentPage: page,
          lastPage: lastPage,
          total: total,
          onPageChange: (p) => setPage(p),
        }}
      />

      {/* Detail Modal */}
      {detailModalOpen && selectedStore && (
        <div className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200/90 max-w-lg w-full p-6 space-y-4 shadow-xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 className="text-sm font-bold text-navy-950">{selectedStore.name}</h3>
                <p className="text-xs text-slate-500 mt-0.5">{selectedStore.address_text}</p>
              </div>
              <button
                onClick={() => setDetailModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
              <div>
                <span className="text-slate-500 font-medium">Status persetujuan:</span>
                <div className="mt-1"><StatusText status={selectedStore.approval_status.toUpperCase()} /></div>
              </div>
              <div>
                <span className="text-slate-500 font-medium">Status operasional:</span>
                <div className="mt-1 font-mono font-semibold uppercase text-slate-700">{selectedStore.operational_status}</div>
              </div>
              <div>
                <span className="text-slate-500 font-medium">Radius layanan:</span>
                <div className="mt-1 font-mono font-semibold text-slate-800">{selectedStore.service_radius_km} km</div>
              </div>
              <div>
                <span className="text-slate-500 font-medium">Pemilik gerai:</span>
                <div className="mt-1 font-semibold text-slate-900">{selectedStore.owner?.name || '-'}</div>
                <div className="text-[11px] text-slate-500 font-mono">{selectedStore.owner?.phone || selectedStore.owner?.email || ''}</div>
              </div>
            </div>

            {selectedStore.description && (
              <div className="text-xs bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                <span className="text-slate-500 font-medium block mb-1">Deskripsi gerai:</span>
                <p className="text-slate-700 leading-relaxed">{selectedStore.description}</p>
              </div>
            )}

            {selectedStore.rejection_reason && (
              <div className="text-xs bg-rose-50 p-3.5 rounded-xl border border-rose-200">
                <span className="text-rose-700 font-semibold block mb-1">Alasan penolakan sebelumnya:</span>
                <p className="text-rose-900">{selectedStore.rejection_reason}</p>
              </div>
            )}

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setDetailModalOpen(false)}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer min-h-[38px]"
              >
                Tutup
              </button>
              {selectedStore.approval_status === 'pending' && (
                <>
                  <button
                    type="button"
                    onClick={() => {
                      setRejectModalOpen(true);
                    }}
                    disabled={actionLoading}
                    className="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 cursor-pointer min-h-[38px]"
                  >
                    Tolak pengajuan
                  </button>
                  <button
                    type="button"
                    onClick={() => handleApprove(selectedStore)}
                    disabled={actionLoading}
                    className="px-4 py-2 text-xs font-semibold rounded-xl bg-navy-900 text-white hover:bg-navy-800 cursor-pointer shadow-2xs min-h-[38px]"
                  >
                    Setujui gerai
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {rejectModalOpen && selectedStore && (
        <div className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <form
            onSubmit={handleRejectSubmit}
            className="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-xl"
          >
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="text-sm font-bold text-rose-900">Tolak pengajuan toko</h3>
              <button
                type="button"
                onClick={() => setRejectModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <p className="text-xs text-slate-600 leading-relaxed">
              Tuliskan alasan penolakan permohonan toko <strong>{selectedStore.name}</strong>. Alasan ini akan tercatat dalam audit log dan dikirimkan ke pemilik toko.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Alasan penolakan <span className="text-rose-500">* (minimal 5 karakter)</span>
              </label>
              <textarea
                value={rejectionReason}
                onChange={(e) => setRejectionReason(e.target.value)}
                placeholder="Contoh: Dokumen izin usaha tidak valid atau alamat belum terverifikasi."
                rows={3}
                required
                className="w-full p-3 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-navy-900"
              />
            </div>

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setRejectModalOpen(false)}
                disabled={actionLoading}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer min-h-[38px]"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={actionLoading}
                className="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 text-white hover:bg-rose-700 disabled:opacity-50 cursor-pointer shadow-2xs min-h-[38px]"
              >
                {actionLoading ? 'Menolak...' : 'Konfirmasi tolak'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
