'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AdminStore } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search, CheckCircle, XCircle, Eye, X, AlertCircle } from 'lucide-react';

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
      header: 'Nama Toko',
      accessor: (item) => (
        <div>
          <div className="font-semibold text-slate-900">{item.name}</div>
          <div className="text-[11px] text-slate-500 truncate max-w-xs">{item.address_text}</div>
        </div>
      ),
    },
    {
      header: 'Pemilik (Owner)',
      accessor: (item) => (
        <div>
          <div className="text-slate-800 font-medium">{item.owner?.name || '-'}</div>
          <div className="text-[11px] text-slate-500">{item.owner?.phone || item.owner?.email || ''}</div>
        </div>
      ),
    },
    {
      header: 'Status Persetujuan',
      accessor: (item) => <StatusText status={item.approval_status.toUpperCase()} />,
    },
    {
      header: 'Status Operasional',
      accessor: (item) => (
        <span className="font-mono text-xs text-slate-600 uppercase">
          {item.operational_status}
        </span>
      ),
    },
    {
      header: 'Tanggal Diajukan',
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
            className="p-1 rounded text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors cursor-pointer"
            title="Lihat Detail"
            aria-label="Lihat detail toko"
          >
            <Eye className="w-4 h-4" strokeWidth={1.5} />
          </button>
          {item.approval_status === 'pending' && (
            <>
              <button
                onClick={() => handleApprove(item)}
                disabled={actionLoading}
                className="p-1 rounded text-emerald-600 hover:bg-emerald-50 transition-colors cursor-pointer"
                title="Setujui Toko"
                aria-label="Setujui toko"
              >
                <CheckCircle className="w-4 h-4" strokeWidth={1.5} />
              </button>
              <button
                onClick={() => {
                  setSelectedStore(item);
                  setRejectModalOpen(true);
                }}
                disabled={actionLoading}
                className="p-1 rounded text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                title="Tolak Toko"
                aria-label="Tolak toko"
              >
                <XCircle className="w-4 h-4" strokeWidth={1.5} />
              </button>
            </>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <AdminHeader
        title="Persetujuan & Manajemen Toko"
        subtitle="Verifikasi pendaftaran gerai toko baru dan tata kelola outlet laundry"
        onRefresh={() => fetchStores(true)}
        refreshing={refreshing}
      />

      {feedback && (
        <div
          className={`p-3 rounded-md text-xs flex items-center justify-between ${
            feedback.type === 'success'
              ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
              : 'bg-rose-50 border border-rose-200 text-rose-800'
          }`}
        >
          <span>{feedback.message}</span>
          <button onClick={() => setFeedback(null)} className="text-slate-400 hover:text-slate-700">
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Filter bar */}
      <div className="flex flex-col sm:flex-row gap-2.5 items-center justify-between bg-white border border-slate-200 rounded-lg p-3">
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" strokeWidth={1.5} />
            <input
              type="text"
              placeholder="Cari nama toko, alamat, pemilik..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              className="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400"
            />
          </div>

          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            className="py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-200 rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer"
          >
            <option value="pending">Menunggu Persetujuan</option>
            <option value="approved">Disetujui (Approved)</option>
            <option value="rejected">Ditolak (Rejected)</option>
            <option value="all">Semua Status</option>
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
        <div className="fixed inset-0 bg-slate-950/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg border border-slate-200 max-w-lg w-full p-5 space-y-4 shadow-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <div>
                <h3 className="text-sm font-semibold text-slate-900">{selectedStore.name}</h3>
                <p className="text-xs text-slate-500">{selectedStore.address_text}</p>
              </div>
              <button
                onClick={() => setDetailModalOpen(false)}
                className="p-1 rounded text-slate-400 hover:text-slate-700"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-2 gap-3 text-xs">
              <div>
                <span className="text-slate-500">Status Persetujuan:</span>
                <div className="mt-0.5"><StatusText status={selectedStore.approval_status.toUpperCase()} /></div>
              </div>
              <div>
                <span className="text-slate-500">Radius Layanan:</span>
                <div className="font-mono text-slate-800 font-medium">{selectedStore.service_radius_km} KM</div>
              </div>
              <div>
                <span className="text-slate-500">Nama Pemilik:</span>
                <div className="font-medium text-slate-800">{selectedStore.owner?.name || '-'}</div>
              </div>
              <div>
                <span className="text-slate-500">Kontak:</span>
                <div className="font-mono text-slate-800">{selectedStore.owner?.phone || selectedStore.owner?.email || '-'}</div>
              </div>
            </div>

            {selectedStore.rejection_reason && (
              <div className="p-3 bg-rose-50 border border-rose-200 rounded text-xs text-rose-800">
                <span className="font-semibold block mb-0.5">Alasan Penolakan:</span>
                {selectedStore.rejection_reason}
              </div>
            )}

            {selectedStore.services && selectedStore.services.length > 0 && (
              <div className="pt-2 border-t border-slate-100">
                <span className="text-xs font-semibold text-slate-700 block mb-2">Layanan yang Ditawarkan:</span>
                <div className="space-y-1">
                  {selectedStore.services.map((srv) => (
                    <div key={srv.id} className="text-xs flex justify-between p-1.5 bg-slate-50 rounded">
                      <span className="text-slate-700">{srv.name} ({srv.pricing_unit})</span>
                      <span className="font-mono font-medium text-slate-900">Rp {srv.price_per_unit.toLocaleString('id-ID')}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="pt-3 border-t border-slate-200 flex justify-end gap-2">
              {selectedStore.approval_status === 'pending' && (
                <>
                  <button
                    onClick={() => {
                      setRejectModalOpen(true);
                    }}
                    disabled={actionLoading}
                    className="px-3 py-1.5 border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-medium rounded-md transition-colors cursor-pointer"
                  >
                    Tolak Pengajuan
                  </button>
                  <button
                    onClick={() => handleApprove(selectedStore)}
                    disabled={actionLoading}
                    className="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium rounded-md transition-colors cursor-pointer"
                  >
                    Setujui Toko
                  </button>
                </>
              )}
              <button
                onClick={() => setDetailModalOpen(false)}
                className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-md transition-colors cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {rejectModalOpen && selectedStore && (
        <div className="fixed inset-0 bg-slate-950/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg border border-slate-200 max-w-md w-full p-5 space-y-4 shadow-lg">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <h3 className="text-sm font-semibold text-slate-900">Tolak Pengajuan Toko</h3>
              <button
                onClick={() => setRejectModalOpen(false)}
                className="p-1 rounded text-slate-400 hover:text-slate-700"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleRejectSubmit} className="space-y-3">
              <p className="text-xs text-slate-600">
                Berikan alasan penolakan yang jelas agar pemilik toko dapat memperbaiki permohonannya.
              </p>
              <div>
                <textarea
                  rows={3}
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                  placeholder="Contoh: Dokumen perizinan tidak terbaca atau lokasi di luar jangkauan operasional."
                  required
                  className="w-full text-xs p-2.5 border border-slate-300 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setRejectModalOpen(false)}
                  className="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-md hover:bg-slate-200 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded-md disabled:opacity-50 cursor-pointer"
                >
                  {actionLoading ? 'Memproses...' : 'Konfirmasi Penolakan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
