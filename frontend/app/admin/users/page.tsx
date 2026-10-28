'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AdminUser } from '@/lib/admin';
import { getUserDisplayContact } from '@/lib/auth';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search, ShieldAlert, X } from 'lucide-react';

export default function AdminUsersPage() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const [roleFilter, setRoleFilter] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [search, setSearch] = useState<string>('');
  const [page, setPage] = useState<number>(1);
  const [total, setTotal] = useState<number>(0);
  const [lastPage, setLastPage] = useState<number>(1);

  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [statusModalOpen, setStatusModalOpen] = useState<boolean>(false);
  const [targetStatus, setTargetStatus] = useState<'active' | 'suspended' | 'banned'>('suspended');
  const [statusReason, setStatusReason] = useState<string>('');
  const [actionLoading, setActionLoading] = useState<boolean>(false);
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const fetchUsers = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const res = await adminApi.getUsers({
        role: roleFilter || undefined,
        status: statusFilter || undefined,
        q: search || undefined,
        page,
      });
      setUsers(res.data || []);
      if (res.meta) {
        setTotal(res.meta.total || 0);
        setLastPage(res.meta.last_page || 1);
      }
    } catch {
      setUsers([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [roleFilter, statusFilter, search, page]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const openStatusModal = (user: AdminUser, defaultStatus: 'active' | 'suspended' | 'banned') => {
    setSelectedUser(user);
    setTargetStatus(defaultStatus);
    setStatusReason(user.status_reason || '');
    setStatusModalOpen(true);
  };

  const handleStatusSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUser) return;

    if (targetStatus !== 'active' && (!statusReason || statusReason.trim().length < 5)) {
      setFeedback({ type: 'error', message: 'Alasan penangguhan/pemblokiran wajib diisi minimal 5 karakter.' });
      return;
    }

    setActionLoading(true);
    setFeedback(null);
    try {
      await adminApi.updateUserStatus(selectedUser.id, targetStatus, statusReason.trim() || undefined);
      setFeedback({
        type: 'success',
        message: `Status pengguna "${selectedUser.name}" berhasil diubah menjadi ${targetStatus}.`,
      });
      setStatusModalOpen(false);
      fetchUsers();
    } catch (err: any) {
      setFeedback({ type: 'error', message: err.message || 'Gagal mengubah status pengguna.' });
    } finally {
      setActionLoading(false);
    }
  };

  const columns: Column<AdminUser>[] = [
    {
      header: 'Nama & kontak',
      accessor: (item) => (
        <div>
          <div className="font-semibold text-slate-900">{item.name}</div>
          <div className="text-[11px] text-slate-500 font-mono">{getUserDisplayContact(item)}</div>
        </div>
      ),
    },
    {
      header: 'Peran (role)',
      accessor: (item) => (
        <div className="flex flex-wrap gap-1">
          {item.roles.map((r) => (
            <span
              key={r}
              className="px-2 py-0.5 rounded-md text-[10px] font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200 uppercase"
            >
              {r}
            </span>
          ))}
        </div>
      ),
    },
    {
      header: 'Status akun',
      accessor: (item) => <StatusText status={item.status.toUpperCase()} />,
    },
    {
      header: 'Terdaftar pada',
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
          {item.status !== 'active' ? (
            <button
              onClick={() => openStatusModal(item, 'active')}
              className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors cursor-pointer"
            >
              Aktifkan
            </button>
          ) : (
            <>
              <button
                onClick={() => openStatusModal(item, 'suspended')}
                className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition-colors cursor-pointer"
              >
                Tangguhkan
              </button>
              <button
                onClick={() => openStatusModal(item, 'banned')}
                className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition-colors cursor-pointer"
              >
                Blokir
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
        title="Manajemen pengguna"
        subtitle="Kelola status akses seluruh aktor pengguna ekosistem (Pelanggan, Mitra Toko, Kurir)"
        onRefresh={() => fetchUsers(true)}
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

      {/* Filter toolbar */}
      <div className="flex flex-col sm:flex-row gap-2.5 items-center justify-between bg-white border border-slate-200/80 rounded-2xl p-4 shadow-2xs">
        <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-72">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" strokeWidth={1.75} />
            <input
              type="text"
              placeholder="Cari nama, email, no. telepon..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-navy-900"
            />
          </div>

          <select
            value={roleFilter}
            onChange={(e) => {
              setRoleFilter(e.target.value);
              setPage(1);
            }}
            className="py-2 px-3 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-navy-900 cursor-pointer font-medium"
          >
            <option value="">Semua peran (role)</option>
            <option value="customer">Customer</option>
            <option value="store_admin">Store admin</option>
            <option value="courier">Courier</option>
            <option value="super_admin">Super admin</option>
          </select>

          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            className="py-2 px-3 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-navy-900 cursor-pointer font-medium"
          >
            <option value="">Semua status</option>
            <option value="active">Aktif</option>
            <option value="suspended">Ditangguhkan</option>
            <option value="banned">Diblokir (Banned)</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <DataTable
        columns={columns}
        data={users}
        keyExtractor={(item) => item.id}
        loading={loading}
        emptyText="Tidak ada data pengguna yang sesuai filter."
        pagination={{
          currentPage: page,
          lastPage: lastPage,
          total: total,
          onPageChange: (p) => setPage(p),
        }}
      />

      {/* Status Modal */}
      {statusModalOpen && selectedUser && (
        <div className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <form
            onSubmit={handleStatusSubmit}
            className="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-xl"
          >
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <ShieldAlert className="w-5 h-5 text-navy-800" strokeWidth={1.75} />
                <h3 className="text-sm font-bold text-navy-950">Ubah status pengguna</h3>
              </div>
              <button
                type="button"
                onClick={() => setStatusModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="text-xs space-y-1">
              <span className="text-slate-500 font-medium">Pengguna:</span>
              <div className="font-bold text-slate-900">{selectedUser.name} ({getUserDisplayContact(selectedUser)})</div>
              <div className="text-slate-500">Status saat ini: <span className="font-semibold uppercase">{selectedUser.status}</span></div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Target status baru:
              </label>
              <select
                value={targetStatus}
                onChange={(e) => setTargetStatus(e.target.value as any)}
                className="w-full py-2 px-3 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-navy-900 font-medium"
              >
                <option value="active">Aktif (Pulihkan akses)</option>
                <option value="suspended">Ditangguhkan (Suspended)</option>
                <option value="banned">Diblokir (Banned)</option>
              </select>
            </div>

            {targetStatus !== 'active' && (
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Alasan tindakan <span className="text-rose-500">* (minimal 5 karakter)</span>
                </label>
                <textarea
                  value={statusReason}
                  onChange={(e) => setStatusReason(e.target.value)}
                  placeholder="Contoh: Terindikasi pelanggaran syarat dan ketentuan layanan."
                  rows={3}
                  required
                  className="w-full p-3 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-navy-900"
                />
              </div>
            )}

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setStatusModalOpen(false)}
                disabled={actionLoading}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer min-h-[38px]"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={actionLoading}
                className="px-4 py-2 text-xs font-semibold rounded-xl bg-navy-900 text-white hover:bg-navy-800 disabled:opacity-50 cursor-pointer shadow-2xs min-h-[38px]"
              >
                {actionLoading ? 'Menyimpan...' : 'Simpan status'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
