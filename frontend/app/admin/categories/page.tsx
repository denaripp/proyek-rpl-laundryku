'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, ServiceCategory } from '@/lib/admin';
import { DataTable, Column } from '@/components/admin/DataTable';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatusText } from '@/components/admin/StatusText';
import { Search, Plus, Edit2, Trash2, X, Layers, AlertTriangle } from 'lucide-react';

export default function AdminCategoriesPage() {
  const [categories, setCategories] = useState<ServiceCategory[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const [search, setSearch] = useState<string>('');

  const [modalOpen, setModalOpen] = useState<boolean>(false);
  const [editingCategory, setEditingCategory] = useState<ServiceCategory | null>(null);
  const [formName, setFormName] = useState<string>('');
  const [formSlug, setFormSlug] = useState<string>('');
  const [formStatus, setFormStatus] = useState<'active' | 'inactive'>('active');
  const [formLoading, setFormLoading] = useState<boolean>(false);

  const [deleteConfirmOpen, setDeleteConfirmOpen] = useState<boolean>(false);
  const [categoryToDelete, setCategoryToDelete] = useState<ServiceCategory | null>(null);
  const [deleteLoading, setDeleteLoading] = useState<boolean>(false);

  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const fetchCategories = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const data = await adminApi.getCategories({ q: search || undefined });
      setCategories(data || []);
    } catch {
      setCategories([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [search]);

  useEffect(() => {
    fetchCategories();
  }, [fetchCategories]);

  const openCreateModal = () => {
    setEditingCategory(null);
    setFormName('');
    setFormSlug('');
    setFormStatus('active');
    setModalOpen(true);
  };

  const openEditModal = (cat: ServiceCategory) => {
    setEditingCategory(cat);
    setFormName(cat.name);
    setFormSlug(cat.slug);
    setFormStatus(cat.status);
    setModalOpen(true);
  };

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formName.trim()) {
      setFeedback({ type: 'error', message: 'Nama kategori layanan wajib diisi.' });
      return;
    }

    setFormLoading(true);
    setFeedback(null);
    try {
      if (editingCategory) {
        await adminApi.updateCategory(editingCategory.id, {
          name: formName.trim(),
          slug: formSlug.trim() || undefined,
          status: formStatus,
        });
        setFeedback({ type: 'success', message: `Kategori "${formName}" berhasil diperbarui.` });
      } else {
        await adminApi.createCategory({
          name: formName.trim(),
          slug: formSlug.trim() || undefined,
          status: formStatus,
        });
        setFeedback({ type: 'success', message: `Kategori "${formName}" berhasil ditambahkan.` });
      }
      setModalOpen(false);
      fetchCategories();
    } catch (err: any) {
      setFeedback({ type: 'error', message: err.message || 'Gagal menyimpan kategori.' });
    } finally {
      setFormLoading(false);
    }
  };

  const handleDeleteConfirm = async () => {
    if (!categoryToDelete) return;

    setDeleteLoading(true);
    setFeedback(null);
    try {
      await adminApi.deleteCategory(categoryToDelete.id);
      setFeedback({ type: 'success', message: `Kategori "${categoryToDelete.name}" berhasil dihapus.` });
      setDeleteConfirmOpen(false);
      fetchCategories();
    } catch (err: any) {
      setFeedback({ type: 'error', message: err.message || 'Gagal menghapus kategori.' });
    } finally {
      setDeleteLoading(false);
    }
  };

  const columns: Column<ServiceCategory>[] = [
    {
      header: 'Nama kategori',
      accessor: (item) => <span className="font-semibold text-slate-900">{item.name}</span>,
    },
    {
      header: 'Slug / identifikasi',
      accessor: (item) => <span className="font-mono text-xs text-slate-500">{item.slug}</span>,
    },
    {
      header: 'Jumlah layanan toko',
      accessor: (item) => (
        <span className="font-mono tabular-nums text-slate-700">
          {item.services_count ?? 0} layanan
        </span>
      ),
    },
    {
      header: 'Status',
      accessor: (item) => <StatusText status={item.status.toUpperCase()} />,
    },
    {
      header: 'Aksi',
      className: 'text-right',
      accessor: (item) => (
        <div className="flex items-center justify-end gap-1.5">
          <button
            onClick={() => openEditModal(item)}
            className="p-1.5 rounded-lg text-slate-500 hover:text-navy-700 hover:bg-navy-50 transition-colors cursor-pointer"
            title="Ubah kategori"
            aria-label="Ubah kategori"
          >
            <Edit2 className="w-3.5 h-3.5" />
          </button>
          <button
            onClick={() => {
              setCategoryToDelete(item);
              setDeleteConfirmOpen(true);
            }}
            className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
            title="Hapus kategori"
            aria-label="Hapus kategori"
          >
            <Trash2 className="w-3.5 h-3.5" />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-5">
      <AdminHeader
        title="Kategori layanan"
        subtitle="Master jenis layanan laundry yang dapat dipilih toko saat membuat paket jasa"
        onRefresh={() => fetchCategories(true)}
        refreshing={refreshing}
        actions={
          <button
            onClick={openCreateModal}
            className="inline-flex items-center gap-1.5 px-3 py-2 bg-navy-900 hover:bg-navy-800 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>Tambah kategori</span>
          </button>
        }
      />

      {feedback && (
        <div
          className={`p-3.5 rounded-xl text-xs flex items-center justify-between border ${
            feedback.type === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
              : 'bg-rose-50 border-rose-200 text-rose-800'
          }`}
        >
          <span className="font-medium">{feedback.message}</span>
          <button onClick={() => setFeedback(null)} className="text-slate-400 hover:text-slate-700">
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Filter */}
      <div className="flex items-center justify-between bg-white border border-slate-200/80 rounded-2xl p-3.5 shadow-2xs">
        <div className="relative w-full sm:w-72">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Cari kategori..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 transition-all font-medium"
          />
        </div>
      </div>

      {/* Table */}
      <DataTable
        columns={columns}
        data={categories}
        keyExtractor={(item) => item.id}
        loading={loading}
        emptyText="Belum ada data kategori layanan."
      />

      {/* Form Modal */}
      {modalOpen && (
        <div className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200/90 max-w-md w-full p-6 space-y-4 shadow-xl">
            <div className="flex items-start justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-navy-50 text-navy-700 flex items-center justify-center">
                  <Layers className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-semibold text-slate-900">
                    {editingCategory ? 'Ubah kategori layanan' : 'Tambah kategori layanan'}
                  </h3>
                  <p className="text-[11px] text-slate-500">Konfigurasi jenis layanan yang tersedia di sistem</p>
                </div>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleFormSubmit} className="space-y-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Nama kategori</label>
                <input
                  type="text"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  placeholder="Misal: Kiloan Reguler, Dry Clean, Sepatu & Tas"
                  required
                  className="w-full text-xs p-2.5 bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 text-slate-800 font-medium transition-all"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Slug URL (opsional)
                </label>
                <input
                  type="text"
                  value={formSlug}
                  onChange={(e) => setFormSlug(e.target.value)}
                  placeholder="Misal: kiloan-reguler"
                  className="w-full text-xs p-2.5 bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 text-slate-800 font-mono transition-all"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Status ketersediaan</label>
                <select
                  value={formStatus}
                  onChange={(e) => setFormStatus(e.target.value as any)}
                  className="w-full text-xs p-2.5 bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 text-slate-700 font-medium cursor-pointer transition-all"
                >
                  <option value="active">Aktif (Tersedia untuk toko)</option>
                  <option value="inactive">Nonaktif (Disembunyikan)</option>
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-3.5 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-200 cursor-pointer transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={formLoading}
                  className="px-4 py-2 bg-navy-900 hover:bg-navy-800 text-white text-xs font-semibold rounded-xl disabled:opacity-50 cursor-pointer shadow-xs transition-colors"
                >
                  {formLoading ? 'Menyimpan...' : 'Simpan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteConfirmOpen && categoryToDelete && (
        <div className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200/90 max-w-sm w-full p-6 space-y-4 shadow-xl">
            <div className="flex items-start justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                  <AlertTriangle className="w-4 h-4" />
                </div>
                <h3 className="text-sm font-semibold text-slate-900">Hapus kategori?</h3>
              </div>
              <button
                onClick={() => setDeleteConfirmOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                aria-label="Tutup"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <p className="text-xs text-slate-600 leading-relaxed">
              Apakah Anda yakin ingin menghapus kategori <strong>{categoryToDelete.name}</strong>? Tindakan ini tidak dapat dibatalkan.
            </p>

            <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setDeleteConfirmOpen(false)}
                className="px-3.5 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-200 cursor-pointer transition-colors"
              >
                Batal
              </button>
              <button
                onClick={handleDeleteConfirm}
                disabled={deleteLoading}
                className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl disabled:opacity-50 cursor-pointer shadow-xs transition-colors"
              >
                {deleteLoading ? 'Menghapus...' : 'Hapus'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
