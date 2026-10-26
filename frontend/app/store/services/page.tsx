'use client';

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Badge,
  Button,
  Input,
  Select,
  ConfirmDialog,
  Skeleton,
  EmptyState,
  ErrorState,
  Toggle,
  Checkbox,
  Textarea,
  Modal,
} from '@/components/ui';
import {
  getStoreAdminServices,
  createStoreAdminService,
  updateStoreAdminService,
  deleteStoreAdminService,
  toggleStoreAdminServiceStatus,
  bulkUpdateStoreServiceStatus,
  getServiceCategories,
  StoreServiceItem,
  ServiceCategoryItem,
  StoreServicesMeta,
} from '@/lib/store';
import { getStoreStatus, StoreStatusData } from '@/lib/profile';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser, getActiveStoreId } from '@/lib/auth';
import { formatRupiah, cn } from '@/lib/format';
import {
  Plus,
  Edit2,
  Trash2,
  Search,
  Clock,
  CheckCircle2,
  AlertCircle,
  X,
  Eye,
  Layers,
  ArrowUpDown,
  RefreshCw,
  ShieldAlert,
  Scale,
  Package,
  Info,
  Tag,
  Calculator,
  ChevronRight,
  Pencil,
} from 'lucide-react';

export default function StoreServicesPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [storeStatus, setStoreStatus] = useState<StoreStatusData | null>(null);
  const [services, setServices] = useState<StoreServiceItem[]>([]);
  const [categories, setCategories] = useState<ServiceCategoryItem[]>([]);
  const [storeMeta, setStoreMeta] = useState<StoreServicesMeta | null>(null);

  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Filters & Search State
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [selectedStatus, setSelectedStatus] = useState<'all' | 'active' | 'inactive'>('all');
  const [selectedSort, setSelectedSort] = useState<'latest' | 'name_asc' | 'name_desc' | 'price_asc' | 'price_desc'>('latest');

  // Selected Checkboxes for Bulk Action
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [bulkActionLoading, setBulkActionLoading] = useState<boolean>(false);

  // Form Drawer / Modal State
  const [formOpen, setFormOpen] = useState<boolean>(false);
  const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
  const [editingServiceId, setEditingServiceId] = useState<string | null>(null);
  const [formSubmitting, setFormSubmitting] = useState<boolean>(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [isFormDirty, setIsFormDirty] = useState<boolean>(false);
  const [discardConfirmOpen, setDiscardConfirmOpen] = useState<boolean>(false);
  const [activeModalSection, setActiveModalSection] = useState<'info' | 'pricing' | 'status'>('info');

  const scrollToModalSection = (section: 'info' | 'pricing' | 'status') => {
    setActiveModalSection(section);
    const el = document.getElementById(`modal-section-${section}`);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  };

  // Form Input Values
  const [formData, setFormData] = useState({
    name: '',
    category_id: '',
    description: '',
    pricing_unit: 'kg' as 'kg' | 'item',
    price_per_unit: '',
    minimum_charge: '',
    estimated_duration_hours: '24',
    is_active: true,
  });

  // Delete Dialog State
  const [deleteDialogOpen, setDeleteDialogOpen] = useState<boolean>(false);
  const [deletingService, setDeletingService] = useState<StoreServiceItem | null>(null);
  const [deleteLoading, setDeleteLoading] = useState<boolean>(false);

  // Safe Cannot Delete Modal State (for services in order history)
  const [cannotDeleteOpen, setCannotDeleteOpen] = useState<boolean>(false);
  const [cannotDeleteService, setCannotDeleteService] = useState<StoreServiceItem | null>(null);
  const [deactivatingUsedService, setDeactivatingUsedService] = useState<boolean>(false);

  // Toast / notification feedback
  const [feedbackMessage, setFeedbackMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const showFeedback = (text: string, type: 'success' | 'error' = 'success') => {
    setFeedbackMessage({ type, text });
    setTimeout(() => {
      setFeedbackMessage((current) => (current?.text === text ? null : current));
    }, 4000);
  };

  // Fetch Services from API
  const loadServices = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);

      const [statusRes, catRes] = await Promise.all([
        getStoreStatus(),
        getServiceCategories(),
      ]);

      setStoreStatus(statusRes);
      // Filter active categories for selection
      const activeCats = catRes.filter((c) => c.status === 'active' || !c.status);
      setCategories(activeCats);

      const activeStoreId = getActiveStoreId(user) || statusRes.store?.id;

      if (!activeStoreId) {
        setLoading(false);
        return;
      }

      const res = await getStoreAdminServices({
        store_id: activeStoreId,
        search: searchQuery.trim() || undefined,
        category_id: selectedCategory !== 'all' ? selectedCategory : undefined,
        status: selectedStatus !== 'all' ? selectedStatus : undefined,
        sort: selectedSort,
      });

      setServices(res.services);
      setStoreMeta(res.meta);
    } catch (err) {
      const msg = formatFriendlyErrorMessage(err, 'Gagal memuat daftar layanan toko.');
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [searchQuery, selectedCategory, selectedStatus, selectedSort]);

  useEffect(() => {
    const timer = setTimeout(() => {
      loadServices();
    }, 200);

    const handleStoreChange = () => {
      loadServices();
    };

    window.addEventListener('laundryku_active_store_change', handleStoreChange);

    return () => {
      clearTimeout(timer);
      window.removeEventListener('laundryku_active_store_change', handleStoreChange);
    };
  }, [loadServices]);

  // Handle Form Change
  const updateFormField = (field: string, value: any) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setIsFormDirty(true);
    if (formError) setFormError(null);
  };

  // Open Form for Create
  const handleOpenCreate = () => {
    const defaultCatId = categories[0]?.id || '';
    setFormData({
      name: '',
      category_id: defaultCatId,
      description: '',
      pricing_unit: 'kg',
      price_per_unit: '',
      minimum_charge: '',
      estimated_duration_hours: '24',
      is_active: true,
    });
    setFormMode('create');
    setEditingServiceId(null);
    setFormError(null);
    setIsFormDirty(false);
    setActiveModalSection('info');
    setFormOpen(true);
  };

  // Open Form for Edit
  const handleOpenEdit = (svc: StoreServiceItem) => {
    setFormData({
      name: svc.name,
      category_id: svc.category_id,
      description: svc.description || '',
      pricing_unit: svc.pricing_unit as 'kg' | 'item',
      price_per_unit: svc.price_per_unit.toString(),
      minimum_charge: svc.minimum_charge ? svc.minimum_charge.toString() : '0',
      estimated_duration_hours: svc.estimated_duration_hours.toString(),
      is_active: svc.is_active,
    });
    setFormMode('edit');
    setEditingServiceId(svc.id);
    setFormError(null);
    setIsFormDirty(false);
    setActiveModalSection('info');
    setFormOpen(true);
  };

  // Safe Close Form with Unsaved Warning
  const handleRequestCloseForm = () => {
    if (isFormDirty) {
      setDiscardConfirmOpen(true);
    } else {
      setFormOpen(false);
    }
  };

  // Validation Checks
  const validationState = useMemo(() => {
    const nameTrim = formData.name.trim();
    if (!nameTrim) return { isValid: false, reason: 'Nama layanan wajib diisi' };
    if (nameTrim.length < 3) return { isValid: false, reason: 'Nama layanan minimal 3 karakter' };
    if (nameTrim.length > 100) return { isValid: false, reason: 'Nama layanan maksimal 100 karakter' };
    if (!formData.category_id) return { isValid: false, reason: 'Kategori layanan wajib dipilih' };

    const price = parseInt(formData.price_per_unit, 10);
    if (isNaN(price) || price < 0) return { isValid: false, reason: 'Tarif per satuan harus angka >= 0' };

    const minCharge = formData.minimum_charge ? parseInt(formData.minimum_charge, 10) : 0;
    if (isNaN(minCharge) || minCharge < 0) return { isValid: false, reason: 'Biaya minimum harus angka >= 0' };

    const duration = parseInt(formData.estimated_duration_hours, 10);
    if (isNaN(duration) || duration < 1 || duration > 168) {
      return { isValid: false, reason: 'Estimasi pengerjaan harus antara 1 sampai 168 jam' };
    }

    if (formData.description.length > 500) {
      return { isValid: false, reason: 'Deskripsi maksimal 500 karakter' };
    }

    return { isValid: true, reason: null };
  }, [formData]);

  // Submit Form
  const handleSubmitForm = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validationState.isValid) {
      setFormError(validationState.reason);
      return;
    }

    setFormSubmitting(true);
    setFormError(null);

    const price = parseInt(formData.price_per_unit, 10);
    const minCharge = formData.minimum_charge ? parseInt(formData.minimum_charge, 10) : 0;
    const duration = parseInt(formData.estimated_duration_hours, 10);

    try {
      if (formMode === 'edit' && editingServiceId) {
        await updateStoreAdminService(editingServiceId, {
          name: formData.name.trim(),
          category_id: formData.category_id,
          description: formData.description.trim() || null,
          pricing_unit: formData.pricing_unit,
          price_per_unit: price,
          minimum_charge: minCharge,
          estimated_duration_hours: duration,
          is_active: formData.is_active,
        });
        showFeedback('Layanan berhasil diperbarui.');
      } else {
        await createStoreAdminService({
          store_id: getActiveStoreId(currentUser) || storeStatus?.store?.id,
          name: formData.name.trim(),
          category_id: formData.category_id,
          description: formData.description.trim() || null,
          pricing_unit: formData.pricing_unit,
          price_per_unit: price,
          minimum_charge: minCharge,
          estimated_duration_hours: duration,
          is_active: formData.is_active,
        });
        showFeedback('Layanan baru berhasil ditambahkan.');
      }

      setFormOpen(false);
      setIsFormDirty(false);
      loadServices();
    } catch (err: any) {
      const msg =
        err?.message ||
        err?.responseData?.error?.message ||
        err?.response?.data?.error?.message ||
        'Gagal menyimpan layanan. Periksa kembali input Anda.';
      setFormError(msg);
    } finally {
      setFormSubmitting(false);
    }
  };

  // Toggle single service status
  const handleToggleStatus = async (svc: StoreServiceItem) => {
    const nextStatus = !svc.is_active;
    // Optimistic UI update
    setServices((prev) =>
      prev.map((s) => (s.id === svc.id ? { ...s, is_active: nextStatus } : s))
    );

    try {
      await toggleStoreAdminServiceStatus(svc.id, nextStatus);
      showFeedback(
        `Layanan "${svc.name}" ${nextStatus ? 'berhasil diaktifkan' : 'dinonaktifkan'}.`
      );
      loadServices();
    } catch (err) {
      // Rollback on failure
      setServices((prev) =>
        prev.map((s) => (s.id === svc.id ? { ...s, is_active: !nextStatus } : s))
      );
      showFeedback('Gagal mengubah status layanan.', 'error');
    }
  };

  // Bulk Status Update (Activate / Deactivate)
  const handleBulkStatusUpdate = async (isActive: boolean) => {
    if (selectedIds.length === 0) return;
    setBulkActionLoading(true);

    try {
      await bulkUpdateStoreServiceStatus({
        ids: selectedIds,
        is_active: isActive,
      });
      showFeedback(
        `${selectedIds.length} layanan berhasil ${isActive ? 'diaktifkan' : 'dinonaktifkan'}.`
      );
      setSelectedIds([]);
      loadServices();
    } catch (err: any) {
      const msg = err?.message || 'Gagal mengubah status layanan secara massal.';
      showFeedback(msg, 'error');
    } finally {
      setBulkActionLoading(false);
    }
  };

  // Delete service action
  const handleConfirmDelete = async () => {
    if (!deletingService) return;
    setDeleteLoading(true);

    try {
      await deleteStoreAdminService(deletingService.id);
      showFeedback(`Layanan "${deletingService.name}" berhasil dihapus.`);
      setDeleteDialogOpen(false);
      setDeletingService(null);
      setSelectedIds((prev) => prev.filter((id) => id !== deletingService.id));
      loadServices();
    } catch (err: any) {
      setDeleteDialogOpen(false);
      // Check if error is CANNOT_DELETE_USED_SERVICE (supports apiClient format and axios fallback)
      const errCode = err?.code || err?.responseData?.error?.code || err?.response?.data?.error?.code;
      if (errCode === 'CANNOT_DELETE_USED_SERVICE') {
        setCannotDeleteService(deletingService);
        setCannotDeleteOpen(true);
      } else {
        const msg =
          err?.message ||
          err?.responseData?.error?.message ||
          err?.response?.data?.error?.message ||
          'Gagal menghapus layanan.';
        showFeedback(msg, 'error');
      }
    } finally {
      setDeleteLoading(false);
    }
  };

  // Safe Deactivate from Cannot Delete Modal
  const handleDeactivateUsedService = async () => {
    if (!cannotDeleteService) return;
    setDeactivatingUsedService(true);
    try {
      await toggleStoreAdminServiceStatus(cannotDeleteService.id, false);
      showFeedback(`Layanan "${cannotDeleteService.name}" berhasil dinonaktifkan.`);
      setCannotDeleteOpen(false);
      setCannotDeleteService(null);
      loadServices();
    } catch (err: any) {
      showFeedback('Gagal menonaktifkan layanan.', 'error');
    } finally {
      setDeactivatingUsedService(false);
    }
  };

  // Select all checkbox handler
  const isAllSelected = services.length > 0 && selectedIds.length === services.length;
  const isIndeterminate = selectedIds.length > 0 && selectedIds.length < services.length;

  const handleToggleSelectAll = () => {
    if (isAllSelected) {
      setSelectedIds([]);
    } else {
      setSelectedIds(services.map((s) => s.id));
    }
  };

  const handleToggleSelectOne = (id: string) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
    );
  };

  // Calculate stats
  const totalCount = storeMeta?.total ?? services.length;
  const activeCount = storeMeta?.active_count ?? services.filter((s) => s.is_active).length;

  // Selected Category Name for Form Preview
  const selectedCategoryName = useMemo(() => {
    const cat = categories.find((c) => c.id === formData.category_id);
    return cat ? cat.name : 'Kategori Layanan';
  }, [categories, formData.category_id]);

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
      onLogout={logout}
    >
      <div className="space-y-6">
        {/* Feedback Alert Toast */}
        {feedbackMessage && (
          <div
            role="status"
            aria-live="polite"
            className={cn(
              'px-4 py-3 rounded-xl text-sm font-medium border flex items-center justify-between transition-all shadow-xs',
              feedbackMessage.type === 'success'
                ? 'bg-slate-900 text-white border-slate-800'
                : 'bg-rose-50 text-rose-900 border-rose-200'
            )}
          >
            <div className="flex items-center gap-2">
              {feedbackMessage.type === 'success' ? (
                <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0" />
              ) : (
                <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
              )}
              <span>{feedbackMessage.text}</span>
            </div>
            <button
              onClick={() => setFeedbackMessage(null)}
              className="text-slate-400 hover:text-white p-1"
              aria-label="Tutup notifikasi"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        )}

        {/* 1. Header (Tugas C.1) */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-slate-900 tracking-tight">
                Layanan
              </h1>
              <span className="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                {totalCount} total · {activeCount} aktif
              </span>
            </div>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Kelola katalog tarif, satuan berat atau satuan bijian, dan waktu estimasi pengerjaan toko Anda.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="primary"
              onClick={handleOpenCreate}
              className="rounded-xl min-h-[40px] px-4 font-semibold text-sm flex items-center gap-2 shadow-xs bg-[#1A3A5C] hover:bg-[#142e4a] text-white border-transparent"
              id="btn-tambah-layanan"
            >
              <Plus className="w-4 h-4" />
              <span>Tambah layanan</span>
            </Button>
          </div>
        </div>

        {/* 2. Toolbar (Tugas C.2) - Tanpa wrapper kartu */}
        <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
          {/* Search Input */}
          <div className="sm:col-span-5 relative">
            <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Cari nama layanan..."
              className="w-full h-10 pl-10 pr-4 text-sm bg-white border border-slate-300 rounded-xl text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all"
              aria-label="Cari nama layanan"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
                aria-label="Hapus pencarian"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          {/* Category Filter */}
          <div className="sm:col-span-3">
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="w-full h-10 px-3 text-sm bg-white border border-slate-300 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all font-medium"
              aria-label="Filter kategori layanan"
            >
              <option value="all">Semua Kategori</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
          </div>

          {/* Status Filter */}
          <div className="sm:col-span-2">
            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value as any)}
              className="w-full h-10 px-3 text-sm bg-white border border-slate-300 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all font-medium"
              aria-label="Filter status layanan"
            >
              <option value="all">Semua Status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>

          {/* Sort Filter */}
          <div className="sm:col-span-2">
            <select
              value={selectedSort}
              onChange={(e) => setSelectedSort(e.target.value as any)}
              className="w-full h-10 px-3 text-sm bg-white border border-slate-300 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all font-medium"
              aria-label="Urutkan layanan"
            >
              <option value="latest">Terbaru</option>
              <option value="name_asc">Nama (A-Z)</option>
              <option value="name_desc">Nama (Z-A)</option>
              <option value="price_asc">Harga Terendah</option>
              <option value="price_desc">Harga Tertinggi</option>
            </select>
          </div>
        </div>

        {/* 4. Bulk Action Bar (Tugas C.4) - Muncul saat ada checkbox terpilih */}
        {selectedIds.length > 0 && (
          <div
            role="region"
            aria-label="Aksi massal layanan"
            className="flex items-center justify-between px-4 py-2.5 bg-slate-900 text-white rounded-xl text-xs sm:text-sm font-medium shadow-sm animate-fadeIn"
          >
            <div className="flex items-center gap-2">
              <span className="font-semibold text-teal-300">{selectedIds.length}</span>
              <span>layanan dipilih</span>
            </div>
            <div className="flex items-center gap-2">
              <button
                disabled={bulkActionLoading}
                onClick={() => handleBulkStatusUpdate(true)}
                className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-emerald-400 font-semibold transition-colors disabled:opacity-50"
              >
                Aktifkan
              </button>
              <button
                disabled={bulkActionLoading}
                onClick={() => handleBulkStatusUpdate(false)}
                className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold transition-colors disabled:opacity-50"
              >
                Nonaktifkan
              </button>
              <button
                disabled={bulkActionLoading}
                onClick={() => setSelectedIds([])}
                className="px-2.5 py-1.5 rounded-lg text-slate-400 hover:text-white transition-colors"
                aria-label="Batal pilih semua"
              >
                Batal
              </button>
            </div>
          </div>
        )}

        {/* 3. Tabel / List Padat (Tugas C.3 & C.5) */}
        {loading ? (
          <div className="bg-white border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100 p-4 space-y-4">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
          </div>
        ) : error ? (
          <ErrorState
            title="Gagal Memuat Layanan"
            message={error}
            onRetry={loadServices}
            retryText="Coba lagi"
          />
        ) : services.length === 0 ? (
          <div className="bg-white border border-slate-200 rounded-xl p-8 sm:p-12 text-center">
            <div className="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center mx-auto mb-4 text-slate-500">
              <Layers className="w-6 h-6" />
            </div>
            <h3 className="font-heading font-bold text-lg text-slate-900">
              Belum ada layanan
            </h3>
            <p className="text-xs sm:text-sm text-slate-500 max-w-md mx-auto mt-1 mb-6">
              {searchQuery || selectedCategory !== 'all' || selectedStatus !== 'all'
                ? 'Tidak ada layanan yang cocok dengan filter pencarian Anda. Coba atur ulang filter.'
                : 'Toko Anda belum memiliki katalog layanan. Tambahkan layanan pertama agar pelanggan dapat mulai memesan.'}
            </p>
            <Button
              variant="primary"
              onClick={handleOpenCreate}
              className="rounded-xl min-h-[40px] px-4 font-semibold text-sm bg-[#1A3A5C] hover:bg-[#142e4a] text-white border-transparent"
            >
              <Plus className="w-4 h-4 mr-1.5" />
              <span>Tambah layanan pertama Anda</span>
            </Button>
          </div>
        ) : (
          <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xs">
            {/* Desktop Table Header */}
            <div className="hidden md:grid md:grid-cols-12 gap-4 px-4 py-3 bg-slate-50/80 border-b border-slate-200 text-xs font-semibold text-slate-600 select-none">
              <div className="col-span-1 flex items-center">
                <input
                  type="checkbox"
                  checked={isAllSelected}
                  ref={(el) => {
                    if (el) el.indeterminate = isIndeterminate;
                  }}
                  onChange={handleToggleSelectAll}
                  className="w-4 h-4 rounded border-slate-300 text-[#1A3A5C] focus:ring-[#1A3A5C] cursor-pointer"
                  aria-label="Pilih semua layanan"
                />
              </div>
              <div className="col-span-4">Layanan & Kategori</div>
              <div className="col-span-3">Satuan & Tarif</div>
              <div className="col-span-1">Estimasi</div>
              <div className="col-span-1 text-center">Status</div>
              <div className="col-span-2 text-right pr-2">Aksi</div>
            </div>

            {/* Table Rows (Divide-y) */}
            <div className="divide-y divide-slate-200">
              {services.map((svc) => {
                const isChecked = selectedIds.includes(svc.id);
                return (
                  <div
                    key={svc.id}
                    className={cn(
                      'p-4 transition-colors hover:bg-slate-50/60 flex flex-col md:grid md:grid-cols-12 md:items-center gap-3 md:gap-4',
                      isChecked && 'bg-slate-50'
                    )}
                  >
                    {/* Checkbox (Desktop & Mobile) */}
                    <div className="flex items-center justify-between md:col-span-1">
                      <div className="flex items-center gap-3">
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => handleToggleSelectOne(svc.id)}
                          className="w-4 h-4 rounded border-slate-300 text-[#1A3A5C] focus:ring-[#1A3A5C] cursor-pointer"
                          aria-label={`Pilih layanan ${svc.name}`}
                        />
                        {/* Mobile Status Tag */}
                        <div className="md:hidden">
                          {svc.is_active ? (
                            <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                              <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                              Aktif
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                              <span className="w-2 h-2 rounded-full bg-slate-300"></span>
                              Nonaktif
                            </span>
                          )}
                        </div>
                      </div>

                      {/* Mobile Row Action */}
                      <div className="md:hidden flex items-center gap-1">
                        <Toggle
                          checked={svc.is_active}
                          onChange={() => handleToggleStatus(svc)}
                          aria-label={`Ubah status aktif ${svc.name}`}
                        />
                        <button
                          type="button"
                          onClick={() => handleOpenEdit(svc)}
                          className="p-1.5 text-slate-600 hover:text-[#1A3A5C] hover:bg-slate-100 rounded-lg transition-colors min-h-[34px] min-w-[34px] flex items-center justify-center"
                          aria-label={`Ubah layanan ${svc.name}`}
                          title="Ubah Layanan"
                        >
                          <Pencil className="w-4 h-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            setDeletingService(svc);
                            setDeleteDialogOpen(true);
                          }}
                          className="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors min-h-[34px] min-w-[34px] flex items-center justify-center"
                          aria-label={`Hapus layanan ${svc.name}`}
                          title="Hapus Layanan"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>

                    {/* Column 2: Nama Layanan & Kategori */}
                    <div className="md:col-span-4 min-w-0">
                      <div className="flex items-baseline gap-2 flex-wrap">
                        <span className="font-medium text-slate-900 text-sm leading-snug">
                          {svc.name}
                        </span>
                      </div>
                      <div className="flex items-center gap-2 mt-0.5">
                        <span className="text-xs text-slate-500 font-medium">
                          {svc.category_name || 'Kategori Umum'}
                        </span>
                        {svc.description && (
                          <>
                            <span className="text-slate-300 text-xs">·</span>
                            <span className="text-xs text-slate-400 truncate max-w-[200px]" title={svc.description}>
                              {svc.description}
                            </span>
                          </>
                        )}
                      </div>
                    </div>

                    {/* Column 3: Satuan & Tarif */}
                    <div className="md:col-span-3 text-xs sm:text-sm">
                      <div className="flex items-baseline gap-1.5 font-medium text-slate-900 tabular-nums">
                        <span className="font-semibold">{formatRupiah(svc.price_per_unit)}</span>
                        <span className="text-slate-500 font-normal text-xs">/ {svc.pricing_unit}</span>
                      </div>
                      {svc.minimum_charge > 0 && (
                        <div className="text-[11px] text-slate-500 mt-0.5 tabular-nums">
                          Min. charge: {formatRupiah(svc.minimum_charge)}
                        </div>
                      )}
                    </div>

                    {/* Column 4: Estimasi Durasi */}
                    <div className="md:col-span-1 text-xs text-slate-700 flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                      <span className="tabular-nums font-medium">
                        {svc.estimated_duration_hours} jam
                      </span>
                    </div>

                    {/* Column 5: Status Desktop */}
                    <div className="hidden md:flex md:col-span-1 items-center justify-center">
                      <Toggle
                        checked={svc.is_active}
                        onChange={() => handleToggleStatus(svc)}
                        aria-label={`Ubah status aktif ${svc.name}`}
                      />
                    </div>

                    {/* Column 6: Aksi Desktop */}
                    <div className="hidden md:flex md:col-span-2 items-center justify-end gap-1">
                      <button
                        type="button"
                        onClick={() => handleOpenEdit(svc)}
                        className="p-2 text-slate-600 hover:text-[#1A3A5C] hover:bg-slate-100 rounded-lg transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                        aria-label={`Ubah layanan ${svc.name}`}
                        title="Ubah Layanan"
                      >
                        <Pencil className="w-4 h-4" />
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          setDeletingService(svc);
                          setDeleteDialogOpen(true);
                        }}
                        className="p-2 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                        aria-label={`Hapus layanan ${svc.name}`}
                        title="Hapus Layanan"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* -------------------------------------------------------------------------------- */}
        {/* TUGAS D: FORM TAMBAH / UBAH LAYANAN (Modal / Drawer dengan Live Preview) */}
        {/* -------------------------------------------------------------------------------- */}
        <Modal
          isOpen={formOpen}
          onClose={handleRequestCloseForm}
          title={formMode === 'create' ? 'Tambah Layanan Baru' : 'Ubah Layanan'}
          description={
            formMode === 'create'
              ? 'Lengkapi rincian formulir di bawah ini untuk menerbitkan layanan laundry pada katalog toko Anda.'
              : 'Perbarui rincian informasi, skema tarif, atau status ketersediaan layanan toko Anda.'
          }
          headerExtra={
            <div className="flex flex-wrap items-center gap-1.5 sm:gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                onClick={() => scrollToModalSection('info')}
                className={cn(
                  'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all shrink-0',
                  activeModalSection === 'info'
                    ? 'bg-[#1A3A5C] text-white shadow-xs'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70 hover:text-slate-900'
                )}
              >
                <span
                  className={cn(
                    'w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold',
                    activeModalSection === 'info' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'
                  )}
                >
                  1
                </span>
                <span>Informasi</span>
              </button>

              <ChevronRight className="w-3.5 h-3.5 text-slate-300 shrink-0" />

              <button
                type="button"
                onClick={() => scrollToModalSection('pricing')}
                className={cn(
                  'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all shrink-0',
                  activeModalSection === 'pricing'
                    ? 'bg-[#1A3A5C] text-white shadow-xs'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70 hover:text-slate-900'
                )}
              >
                <span
                  className={cn(
                    'w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold',
                    activeModalSection === 'pricing' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'
                  )}
                >
                  2
                </span>
                <span>Tarif</span>
              </button>

              <ChevronRight className="w-3.5 h-3.5 text-slate-300 shrink-0" />

              <button
                type="button"
                onClick={() => scrollToModalSection('status')}
                className={cn(
                  'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all shrink-0',
                  activeModalSection === 'status'
                    ? 'bg-[#1A3A5C] text-white shadow-xs'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70 hover:text-slate-900'
                )}
              >
                <span
                  className={cn(
                    'w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold',
                    activeModalSection === 'status' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'
                  )}
                >
                  3
                </span>
                <span>Status</span>
              </button>
            </div>
          }
          maxWidth="4xl"
          className="rounded-[12px] border border-slate-200 shadow-xl p-6 sm:p-8 animate-modal-scale max-sm:rounded-none max-sm:h-full max-sm:max-h-full max-sm:p-4 max-sm:flex max-sm:flex-col"
          containerClassName="max-sm:p-0"
          bodyClassName="max-h-[calc(100vh-170px)] sm:max-h-[75vh] overflow-y-auto overflow-x-hidden pr-1 max-sm:flex-1 max-sm:max-h-none"
        >
          {(() => {
            const parsedPrice = parseInt(formData.price_per_unit, 10) || 0;
            const parsedMinCharge = parseInt(formData.minimum_charge, 10) || 0;
            const parsedDuration = parseInt(formData.estimated_duration_hours, 10) || 24;
            const simQty = formData.pricing_unit === 'kg' ? 3 : 2;
            const simSubtotal = parsedPrice * simQty;
            const simFinalTotal = Math.max(simSubtotal, parsedMinCharge);
            const isMinChargeApplied = parsedMinCharge > simSubtotal && simSubtotal > 0;

            return (
              <form onSubmit={handleSubmitForm} className="space-y-6 pt-2">
                {formError && (
                  <div
                    role="alert"
                    className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm font-medium flex items-center gap-2"
                  >
                    <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{formError}</span>
                  </div>
                )}

                {/* 2-Column Grid: Form Fields on Left, Real-Time Customer Preview on Right */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                  {/* Left Column: Form Sections */}
                  <div className="lg:col-span-7 space-y-6">
                    {/* Section 1: Informasi Dasar */}
                    <div
                      id="modal-section-info"
                      className="bg-white rounded-xl border border-slate-200/90 p-4 sm:p-5 shadow-xs space-y-4"
                    >
                      <div className="flex items-start gap-3 border-b border-slate-100 pb-3">
                        <span className="w-6 h-6 rounded-md bg-[#1A3A5C]/10 text-[#1A3A5C] font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                          1
                        </span>
                        <div>
                          <div className="flex items-center gap-1.5">
                            <Info className="w-4 h-4 text-[#1A3A5C] shrink-0" />
                            <h4 className="font-heading font-bold text-sm text-slate-900">
                              Informasi Layanan
                            </h4>
                          </div>
                          <p className="text-xs text-slate-500 mt-0.5">
                            Lengkapi identitas layanan, klasifikasi kategori, dan deskripsi penjelasan.
                          </p>
                        </div>
                      </div>

                      {/* Nama Layanan */}
                      <div>
                        <label
                          htmlFor="service-name-input"
                          className="block text-xs font-semibold text-slate-800 mb-1"
                        >
                          Nama Layanan <span className="text-rose-500">*</span>
                        </label>
                        <input
                          id="service-name-input"
                          type="text"
                          value={formData.name}
                          maxLength={100}
                          onChange={(e) => updateFormField('name', e.target.value)}
                          placeholder="Contoh: Cuci Kering Setrika Kiloan"
                          className="w-full h-[44px] px-3.5 text-sm bg-white border border-slate-300 rounded-xl text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all"
                          required
                        />
                        <p className="text-[11px] text-slate-500 mt-1">
                          Nama jelas yang mudah dipahami pelanggan (3-100 karakter).
                        </p>
                      </div>

                      {/* Kategori Layanan */}
                      <div>
                        <label
                          htmlFor="service-category-select"
                          className="block text-xs font-semibold text-slate-800 mb-1"
                        >
                          Kategori Layanan <span className="text-rose-500">*</span>
                        </label>
                        <select
                          id="service-category-select"
                          value={formData.category_id}
                          onChange={(e) => updateFormField('category_id', e.target.value)}
                          className="w-full h-[44px] px-3.5 text-sm bg-white border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all font-medium"
                          required
                        >
                          <option value="" disabled>
                            Pilih kategori...
                          </option>
                          {categories.map((cat) => (
                            <option key={cat.id} value={cat.id}>
                              {cat.name}
                            </option>
                          ))}
                        </select>
                      </div>

                      {/* Deskripsi Layanan (Opsional) */}
                      <div>
                        <div className="flex items-center justify-between mb-1">
                          <label
                            htmlFor="service-desc-input"
                            className="block text-xs font-semibold text-slate-800"
                          >
                            Deskripsi Layanan <span className="text-slate-400 font-normal">(Opsional)</span>
                          </label>
                          <span className="text-[11px] text-slate-400 tabular-nums">
                            {formData.description.length}/500
                          </span>
                        </div>
                        <textarea
                          id="service-desc-input"
                          value={formData.description}
                          maxLength={500}
                          rows={2}
                          onChange={(e) => updateFormField('description', e.target.value)}
                          placeholder="Contoh: Termasuk detergen aromatik, pewangi anti-bakteri, dan setrika uap."
                          className="w-full p-3 text-sm bg-white border border-slate-300 rounded-xl text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all resize-none"
                        />
                      </div>
                    </div>

                    {/* Section 2: Tarif & Pengerjaan */}
                    <div
                      id="modal-section-pricing"
                      className="bg-white rounded-xl border border-slate-200/90 p-4 sm:p-5 shadow-xs space-y-4"
                    >
                      <div className="flex items-start gap-3 border-b border-slate-100 pb-3">
                        <span className="w-6 h-6 rounded-md bg-[#1A3A5C]/10 text-[#1A3A5C] font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                          2
                        </span>
                        <div>
                          <div className="flex items-center gap-1.5">
                            <Tag className="w-4 h-4 text-[#1A3A5C] shrink-0" />
                            <h4 className="font-heading font-bold text-sm text-slate-900">
                              Tarif & Waktu Pengerjaan
                            </h4>
                          </div>
                          <p className="text-xs text-slate-500 mt-0.5">
                            Atur satuan hitung, biaya per unit, batas minimum tagihan, serta durasi kerja.
                          </p>
                        </div>
                      </div>

                      {/* Satuan Tarif (Segmented Control Besar) */}
                      <div>
                        <label className="block text-xs font-semibold text-slate-800 mb-1.5">
                          Satuan Tarif <span className="text-rose-500">*</span>
                        </label>
                        <div className="grid grid-cols-2 gap-2 p-1.5 bg-slate-100 rounded-xl border border-slate-200">
                          <button
                            type="button"
                            onClick={() => updateFormField('pricing_unit', 'kg')}
                            className={cn(
                              'flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-xs sm:text-sm font-semibold transition-all',
                              formData.pricing_unit === 'kg'
                                ? 'bg-[#1A3A5C] text-white shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                            )}
                          >
                            <Scale className="w-4 h-4 shrink-0" />
                            <span>Kiloan (kg)</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => updateFormField('pricing_unit', 'item')}
                            className={cn(
                              'flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-xs sm:text-sm font-semibold transition-all',
                              formData.pricing_unit === 'item'
                                ? 'bg-[#1A3A5C] text-white shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
                            )}
                          >
                            <Package className="w-4 h-4 shrink-0" />
                            <span>Satuan / Biji (item)</span>
                          </button>
                        </div>
                      </div>

                      {/* Grid Tarif & Biaya Minimum */}
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        {/* Tarif per Satuan */}
                        <div>
                          <label
                            htmlFor="price-input"
                            className="block text-xs font-semibold text-slate-800 mb-1"
                          >
                            Tarif per {formData.pricing_unit === 'kg' ? 'Kilogram' : 'Item'}{' '}
                            <span className="text-rose-500">*</span>
                          </label>
                          <div className="relative">
                            <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400 pointer-events-none">
                              Rp
                            </span>
                            <input
                              id="price-input"
                              type="text"
                              inputMode="numeric"
                              value={
                                formData.price_per_unit
                                  ? new Intl.NumberFormat('id-ID').format(
                                      parseInt(formData.price_per_unit, 10) || 0
                                    )
                                  : ''
                              }
                              onChange={(e) => {
                                const raw = e.target.value.replace(/\D/g, '');
                                updateFormField('price_per_unit', raw);
                              }}
                              placeholder="0"
                              className="w-full h-[44px] pl-10 pr-3.5 text-sm bg-white border border-slate-300 rounded-xl text-slate-900 font-medium tabular-nums focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all"
                              required
                            />
                          </div>
                          {formData.price_per_unit && (
                            <p className="text-[11px] text-slate-500 mt-1 tabular-nums">
                              Format: {formatRupiah(parseInt(formData.price_per_unit, 10) || 0)} / {formData.pricing_unit}
                            </p>
                          )}
                        </div>

                        {/* Biaya Minimum */}
                        <div>
                          <label
                            htmlFor="min-charge-input"
                            className="block text-xs font-semibold text-slate-800 mb-1"
                          >
                            Biaya Minimum Pesanan
                          </label>
                          <div className="relative">
                            <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400 pointer-events-none">
                              Rp
                            </span>
                            <input
                              id="min-charge-input"
                              type="text"
                              inputMode="numeric"
                              value={
                                formData.minimum_charge
                                  ? new Intl.NumberFormat('id-ID').format(
                                      parseInt(formData.minimum_charge, 10) || 0
                                    )
                                  : ''
                              }
                              onChange={(e) => {
                                const raw = e.target.value.replace(/\D/g, '');
                                updateFormField('minimum_charge', raw);
                              }}
                              placeholder="0"
                              className="w-full h-[44px] pl-10 pr-3.5 text-sm bg-white border border-slate-300 rounded-xl text-slate-900 font-medium tabular-nums focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all"
                            />
                          </div>
                          <p className="text-[11px] text-slate-500 mt-1">
                            Isi 0 jika tidak ada tagihan minimum per order.
                          </p>
                        </div>
                      </div>

                      {/* Estimasi Durasi Pengerjaan */}
                      <div>
                        <label
                          htmlFor="duration-input"
                          className="block text-xs font-semibold text-slate-800 mb-1"
                        >
                          Estimasi Durasi Pengerjaan <span className="text-rose-500">*</span>
                        </label>
                        <div className="relative">
                          <input
                            id="duration-input"
                            type="number"
                            min="1"
                            max="168"
                            value={formData.estimated_duration_hours}
                            onChange={(e) =>
                              updateFormField('estimated_duration_hours', e.target.value)
                            }
                            placeholder="24"
                            className="w-full h-[44px] px-3.5 pr-14 text-sm bg-white border border-slate-300 rounded-xl text-slate-900 font-medium tabular-nums focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] focus:border-transparent transition-all"
                            required
                          />
                          <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400 pointer-events-none">
                            Jam
                          </span>
                        </div>
                        <p className="text-[11px] text-slate-500 mt-1">
                          1 hingga 168 jam (7 hari). Contoh: 24 = 1 hari, 48 = 2 hari.
                        </p>
                      </div>
                    </div>

                    {/* Section 3: Status Aktif */}
                    <div
                      id="modal-section-status"
                      className="bg-white rounded-xl border border-slate-200/90 p-4 sm:p-5 shadow-xs space-y-4"
                    >
                      <div className="flex items-start gap-3 border-b border-slate-100 pb-3">
                        <span className="w-6 h-6 rounded-md bg-[#1A3A5C]/10 text-[#1A3A5C] font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                          3
                        </span>
                        <div>
                          <div className="flex items-center gap-1.5">
                            <CheckCircle2 className="w-4 h-4 text-[#1A3A5C] shrink-0" />
                            <h4 className="font-heading font-bold text-sm text-slate-900">
                              Status Layanan
                            </h4>
                          </div>
                          <p className="text-xs text-slate-500 mt-0.5">
                            Tentukan apakah layanan ini langsung dapat dilihat dan dipesan oleh pelanggan.
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                        <div>
                          <span className="text-xs font-bold text-slate-900 block">
                            Status Layanan: {formData.is_active ? 'Aktif' : 'Nonaktif'}
                          </span>
                          <span className="text-[11px] text-slate-500 block mt-0.5">
                            {formData.is_active
                              ? 'Layanan akan langsung ditampilkan pada katalog toko Anda.'
                              : 'Layanan disembunyikan dan tidak dapat dipesan oleh pelanggan.'}
                          </span>
                        </div>
                        <Toggle
                          checked={formData.is_active}
                          onChange={(e) => updateFormField('is_active', e.target.checked)}
                          aria-label="Status aktif layanan"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Right Column: Real-Time Customer Preview */}
                  <div className="lg:col-span-5 sticky top-2 space-y-4">
                    <div className="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 sm:p-5 space-y-4">
                      <div className="flex items-center justify-between pb-2 border-b border-slate-200/70">
                        <div className="flex items-center gap-1.5 text-xs font-bold text-slate-700 uppercase tracking-wider">
                          <Eye className="w-4 h-4 text-[#1A3A5C]" />
                          <span>Pratinjau Pelanggan</span>
                        </div>
                        <span className="text-[11px] text-slate-500 font-medium">Tampilan Kartu</span>
                      </div>

                      {/* Customer Service Card Mockup */}
                      <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-xs space-y-3">
                        <div className="flex items-start justify-between gap-2">
                          <div className="space-y-1">
                            <span className="inline-block px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-semibold">
                              {selectedCategoryName}
                            </span>
                            <h4 className="font-heading font-bold text-slate-900 text-base leading-snug">
                              {formData.name.trim() || 'Nama Layanan Laundry'}
                            </h4>
                          </div>
                          <span
                            className={cn(
                              'text-[10px] font-semibold px-2 py-0.5 rounded-full shrink-0 border',
                              formData.is_active
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : 'bg-slate-100 text-slate-600 border-slate-200'
                            )}
                          >
                            {formData.is_active ? 'Tersedia' : 'Nonaktif'}
                          </span>
                        </div>

                        {formData.description ? (
                          <p className="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                            {formData.description}
                          </p>
                        ) : (
                          <p className="text-xs text-slate-400 italic">
                            Deskripsi layanan akan ditampilkan di sini.
                          </p>
                        )}

                        {/* Box Harga Besar */}
                        <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-100 flex items-baseline justify-between">
                          <div>
                            <span className="text-[10px] font-medium text-slate-500 block uppercase tracking-wider">
                              Tarif Layanan
                            </span>
                            <div className="text-2xl font-bold font-heading text-[#1A3A5C] tabular-nums">
                              {formatRupiah(parsedPrice)}
                            </div>
                          </div>
                          <span className="text-xs font-semibold text-slate-600 bg-white px-2.5 py-1 rounded-md border border-slate-200">
                            / {formData.pricing_unit}
                          </span>
                        </div>

                        <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                          <span className="flex items-center gap-1.5">
                            <Clock className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <span>
                              Estimasi: <strong className="text-slate-900 font-semibold">{parsedDuration} jam</strong>
                            </span>
                          </span>
                          {parsedMinCharge > 0 ? (
                            <span className="tabular-nums text-slate-700">
                              Min. <strong className="text-slate-900 font-semibold">{formatRupiah(parsedMinCharge)}</strong>
                            </span>
                          ) : (
                            <span className="text-slate-400 text-[11px]">Bebas min. order</span>
                          )}
                        </div>
                      </div>

                      {/* Ringkasan Simulasi Perhitungan (Dihitung dari nilai form, tanpa hardcode) */}
                      <div className="p-3.5 bg-white border border-slate-200 rounded-xl space-y-2 text-xs">
                        <div className="flex items-center gap-1.5 font-bold text-slate-800">
                          <Calculator className="w-3.5 h-3.5 text-[#1A3A5C]" />
                          <span>Ringkasan Simulasi Perhitungan</span>
                        </div>

                        <div className="space-y-1.5 text-slate-600 pt-1">
                          <div className="flex justify-between items-center py-0.5 border-b border-slate-100">
                            <span>Simulasi pesanan:</span>
                            <span className="font-semibold text-slate-900">
                              {simQty} {formData.pricing_unit}
                            </span>
                          </div>
                          <div className="flex justify-between items-center py-0.5 border-b border-slate-100">
                            <span>Subtotal ({simQty} × {formatRupiah(parsedPrice)}):</span>
                            <span className="font-medium text-slate-900 tabular-nums">
                              {formatRupiah(simSubtotal)}
                            </span>
                          </div>
                          {parsedMinCharge > 0 && (
                            <div className="flex justify-between items-center py-0.5 border-b border-slate-100">
                              <span>Batas minimum order:</span>
                              <span className="font-medium text-slate-900 tabular-nums">
                                {formatRupiah(parsedMinCharge)}
                              </span>
                            </div>
                          )}
                          <div className="flex justify-between items-center pt-1 font-bold text-slate-900">
                            <span>Total tagihan simulasi:</span>
                            <span className="text-sm font-bold text-[#1A3A5C] tabular-nums">
                              {formatRupiah(simFinalTotal)}
                            </span>
                          </div>
                        </div>

                        {isMinChargeApplied && (
                          <div className="text-[11px] text-amber-800 bg-amber-50 p-2 rounded-lg border border-amber-200 mt-2">
                            Ketentuan batas minimum berlaku karena subtotal lebih kecil dari biaya minimum.
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Sticky Modal Footer */}
                <div className="sticky bottom-0 bg-white/95 backdrop-blur-xs border-t border-slate-200 pt-3.5 pb-1 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 z-10">
                  <button
                    type="button"
                    onClick={handleRequestCloseForm}
                    disabled={formSubmitting}
                    className="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-semibold text-xs sm:text-sm hover:bg-slate-50 transition-colors min-h-[44px] order-2 sm:order-1"
                  >
                    Batal
                  </button>

                  <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 order-1 sm:order-2">
                    {!validationState.isValid && validationState.reason && (
                      <span className="text-xs text-amber-700 font-medium text-center sm:text-right max-sm:order-2">
                        {validationState.reason}
                      </span>
                    )}
                    <Button
                      type="submit"
                      variant="primary"
                      disabled={!validationState.isValid || formSubmitting}
                      isLoading={formSubmitting}
                      className="px-6 py-2.5 rounded-xl text-xs sm:text-sm font-semibold min-h-[44px] bg-[#1A3A5C] hover:bg-[#142e4a] text-white border-transparent disabled:opacity-50 max-sm:order-1"
                      id="btn-simpan-layanan"
                    >
                      {formMode === 'create' ? 'Simpan Layanan' : 'Perbarui Layanan'}
                    </Button>
                  </div>
                </div>
              </form>
            );
          })()}
        </Modal>

        {/* Unsaved Changes Confirmation Dialog */}
        <ConfirmDialog
          isOpen={discardConfirmOpen}
          onClose={() => setDiscardConfirmOpen(false)}
          title="Tutup Formulir?"
          description="Perubahan pada layanan ini belum disimpan. Apakah Anda yakin ingin membatalkan dan keluar?"
          confirmText="Ya, Buang Perubahan"
          cancelText="Lanjutkan Mengedit"
          variant="danger"
          onConfirm={() => {
            setDiscardConfirmOpen(false);
            setFormOpen(false);
            setIsFormDirty(false);
          }}
        />

        {/* Delete Confirmation Dialog */}
        <ConfirmDialog
          isOpen={deleteDialogOpen}
          onClose={() => {
            setDeleteDialogOpen(false);
            setDeletingService(null);
          }}
          title="Hapus Layanan?"
          description={`Apakah Anda yakin ingin menghapus layanan "${deletingService?.name}"? Tindakan ini tidak dapat dibatalkan jika layanan belum pernah dipesan.`}
          confirmText="Hapus Layanan"
          cancelText="Batal"
          variant="danger"
          isLoading={deleteLoading}
          onConfirm={handleConfirmDelete}
        />

        {/* Cannot Delete Modal (Service Has Order History) */}
        <Modal
          isOpen={cannotDeleteOpen}
          onClose={() => {
            setCannotDeleteOpen(false);
            setCannotDeleteService(null);
          }}
          title="Layanan Tidak Dapat Dihapus"
          maxWidth="md"
        >
          <div className="space-y-4 pt-2">
            <div className="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto text-amber-600">
              <ShieldAlert className="w-6 h-6" />
            </div>

            <div className="text-center space-y-1">
              <h4 className="font-heading font-bold text-slate-900 text-base">
                Layanan Memiliki Riwayat Pesanan
              </h4>
              <p className="text-xs sm:text-sm text-slate-600">
                Layanan <strong>"{cannotDeleteService?.name}"</strong> sudah pernah digunakan dalam transaksi pesanan pelanggan dan tidak dapat dihapus demi menjaga integritas faktur dan riwayat laporan.
              </p>
            </div>

            <div className="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600">
              <strong>Saran:</strong> Nonaktifkan layanan ini agar tidak dapat dipesan lagi oleh pelanggan baru di halaman toko Anda.
            </div>

            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
              <button
                type="button"
                onClick={() => {
                  setCannotDeleteOpen(false);
                  setCannotDeleteService(null);
                }}
                className="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 font-semibold text-xs sm:text-sm hover:bg-slate-50 transition-colors"
              >
                Tutup
              </button>
              <Button
                variant="primary"
                onClick={handleDeactivateUsedService}
                isLoading={deactivatingUsedService}
                className="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-[#1A3A5C] hover:bg-[#142e4a] text-white border-transparent"
              >
                Nonaktifkan saja
              </Button>
            </div>
          </div>
        </Modal>
      </div>
    </DashboardLayout>
  );
}
