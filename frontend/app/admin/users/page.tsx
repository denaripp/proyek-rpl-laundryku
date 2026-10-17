'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, AdminUser } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search, ShieldAlert, CheckCircle2, XCircle, X } from 'lucide-react';

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
      header: 'Nama & Kontak',
      accessor: (item) => (
        <div>
          <div className="font-semibold text-slate-900">{item.name}</div>
          <div className="text-[11px] text-slate-500">{item.email || item.phone || '-'}</div>
        </div>
      ),
    },
    {
      header: 'Peran (Role)',
      accessor: (item) => (
        <div className="flex flex-wrap gap-1">
          {item.roles.map((r) => (
            <span
              key={r}
              className="px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-slate-100 text-slate-700 border border-slate-200 uppercase"
            >
              {r}
            </span>
          ))}
        </div>
      ),
    },
    {
      header: 'Status Akun',
      accessor: (item) => <StatusText status={item.status.toUpperCase()} />,
    },
    {
      header: 'Terdaftar Pada',
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
              className="px-2 py-1 text-xs font-medium rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors cursor-pointer"
            >
              Aktifkan
            </button>
          ) : (
            <>
              <button
                onClick={() => openStatusModal(item, 'suspended')}
                className="px-2 py-1 text-xs font-medium rounded bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors cursor-pointer"
              >
                Tangguhkan
              </button>
              <button
                onClick={() => openStatusModal(item, 'banned')}
                className="px-2 py-1 text-xs font-medium rounded bg-rose-50 text-rose-700 hover:bg-rose-100 transition-colors cursor-pointer"
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
    <div className="space-y-4">
      <AdminHeader
        title="Manajemen Pengguna"
        subtitle="Kelola status akses seluruh pelanggan, admin toko, dan kurir terdaftar"
        onRefresh={() => fetchUsers(true)}
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

      {/* Filter toolbar */}
      <div className="flex flex-col sm:flex-row gap-2.5 items-center justify-between bg-white border border-slate-200 rounded-lg p-3">
        <div className="flex items-center gap-2 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" strokeWidth={1.5} />
            <input
              type="text"
              placeholder="Cari nama, email, nomor HP..."
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              className="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400"
            />
          </div>

          <select
            value={roleFilter}
            onChange={(e) => {
              setRoleFilter(e.target.value);
              setPage(1);
            }}
            className="py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-200 rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer"
          >
            <option value="">Semua Peran</option>
            <option value="customer">Pelanggan</option>
            <option value="store_admin">Admin Toko</option>
            <option value="courier">Kurir</option>
            <option value="super_admin">Super Admin</option>
          </select>

          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            className="py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-200 rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer"
          >
            <option value="">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="suspended">Ditangguhkan</option>
            <option value="banned">Diblokir</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <DataTable
        columns={columns}
        data={users}
        keyExtractor={(item) => item.id}
        loading={loading}
        emptyText="Tidak ada pengguna yang sesuai kriteria pencarian."
        pagination={{
          currentPage: page,
          lastPage: lastPage,
          total: total,
          onPageChange: (p) => setPage(p),
        }}
      />

      {/* Change Status Modal */}
      {statusModalOpen && selectedUser && (
        <div className="fixed inset-0 bg-slate-950/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg border border-slate-200 max-w-md w-full p-5 space-y-4 shadow-lg">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <h3 className="text-sm font-semibold text-slate-900">Ubah Status Akun</h3>
              <button
                onClick={() => setStatusModalOpen(false)}
                className="p-1 rounded text-slate-400 hover:text-slate-700"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleStatusSubmit} className="space-y-3">
              <div className="text-xs text-slate-600">
                Pengguna: <strong className="text-slate-900">{selectedUser.name}</strong> ({selectedUser.email || selectedUser.phone})
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-700 mb-1">Status Baru</label>
                <select
                  value={targetStatus}
                  onChange={(e) => setTargetStatus(e.target.value as any)}
                  className="w-full text-xs p-2 bg-slate-50 border border-slate-300 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer"
                >
                  <option value="active">Aktifkan Akun (Active)</option>
                  <option value="suspended">Tangguhkan Sementara (Suspended)</option>
                  <option value="banned">Blokir Permanen (Banned)</option>
                </select>
              </div>

              {targetStatus !== 'active' && (
                <div>
                  <label className="block text-xs font-medium text-slate-700 mb-1">
                    Alasan Penangguhan / Pemblokiran (Wajib)
                  </label>
                  <textarea
                    rows={3}
                    value={statusReason}
                    onChange={(e) => setStatusReason(e.target.value)}
                    placeholder="Contoh: Terindikasi melakukan kecurangan pesanan atau pelanggaran ketentuan layanan."
                    required
                    className="w-full text-xs p-2.5 border border-slate-300 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                  />
                  <p className="text-[11px] text-slate-500 mt-1">
                    Menangguhkan akun akan mencabut seluruh sesi login dan token aktif secara instan.
                  </p>
                </div>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setStatusModalOpen(false)}
                  className="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-md hover:bg-slate-200 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium rounded-md disabled:opacity-50 cursor-pointer"
                >
                  {actionLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
