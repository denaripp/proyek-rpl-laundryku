'use client';

import React, { useState, useEffect, useMemo, useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Truck,
  Store as StoreIcon,
  MapPin,
  Loader2,
  Check,
  RotateCcw,
  AlertCircle,
  ExternalLink,
  ArrowRight,
} from 'lucide-react';
import { PublicLayout } from '@/components/layout/PublicLayout';
import {
  Button,
  Input,
  Textarea,
  Select,
  FileField,
  FormSection,
  StoreCombobox,
  Skeleton,
  useToast,
} from '@/components/ui';
import { DynamicStoreMap } from '@/components/map/DynamicStoreMap';
import { useAuth } from '@/lib/AuthContext';
import {
  getCourierStatus,
  applyCourier,
  getStoreStatus,
  applyStore,
  CourierStatusData,
  StoreStatusData,
} from '@/lib/profile';
import { StoreLookupItem } from '@/lib/store';
import { formatTanggal } from '@/lib/format';

const DRAFT_KEY_COURIER = 'laundryku_courier_draft';
const DRAFT_KEY_STORE = 'laundryku_store_draft';

export default function UpgradeClient() {
  const router = useRouter();
  const { success: toastSuccess, error: toastError } = useToast();
  const { isAuthenticated, loading: authLoading } = useAuth();

  const [activeTab, setActiveTab] = useState<'courier' | 'store'>('courier');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [draftNotice, setDraftNotice] = useState<string | null>(null);

  // Status Data
  const [courierStatus, setCourierStatus] = useState<CourierStatusData | null>(null);
  const [storeStatus, setStoreStatus] = useState<StoreStatusData | null>(null);
  const [isReapplyingCourier, setIsReapplyingCourier] = useState(false);

  // Courier Form State & Errors
  const [courierStoreId, setCourierStoreId] = useState('');
  const [selectedStore, setSelectedStore] = useState<StoreLookupItem | null>(null);
  const [courierNik, setCourierNik] = useState('');
  const [courierVehicleType, setCourierVehicleType] = useState('motor');
  const [courierVehiclePlate, setCourierVehiclePlate] = useState('');
  const [courierKtpFile, setCourierKtpFile] = useState<File | null>(null);
  const [courierPhotoFile, setCourierPhotoFile] = useState<File | null>(null);
  const [courierErrors, setCourierErrors] = useState<Record<string, string>>({});

  // Store Form State & Errors
  const [storeName, setStoreName] = useState('');
  const [storeDesc, setStoreDesc] = useState('');
  const [storeAddress, setStoreAddress] = useState('');
  const [storeLat, setStoreLat] = useState('-6.8900');
  const [storeLng, setStoreLng] = useState('107.6150');
  const [serviceRadius, setServiceRadius] = useState('5.0');
  const [docType, setDocType] = useState('nib');
  const [docFile, setDocFile] = useState<File | null>(null);
  const [isLocating, setIsLocating] = useState(false);
  const [storeErrors, setStoreErrors] = useState<Record<string, string>>({});

  // Active section tracking (for desktop highlight)
  const [activeSectionId, setActiveSectionId] = useState<string>('');

  // Initial Data & Draft Loading
  useEffect(() => {
    if (authLoading) return;
    if (!isAuthenticated) {
      router.push('/auth?redirect=/upgrade');
      return;
    }

    async function loadInitialData() {
      try {
        setLoading(true);
        const [cStatus, sStatus] = await Promise.all([
          getCourierStatus(),
          getStoreStatus(),
        ]);
        setCourierStatus(cStatus);
        setStoreStatus(sStatus);

        // Restore Drafts
        if (!cStatus.applied) {
          try {
            const savedDraft = localStorage.getItem(DRAFT_KEY_COURIER);
            if (savedDraft) {
              const parsed = JSON.parse(savedDraft);
              if (parsed.store_id) {
                setCourierStoreId(parsed.store_id);
                if (parsed.store_name) {
                  setSelectedStore({
                    id: parsed.store_id,
                    name: parsed.store_name,
                    address_text: parsed.store_address || '',
                  });
                }
              }
              if (parsed.nik) setCourierNik(parsed.nik);
              if (parsed.vehicle_type) setCourierVehicleType(parsed.vehicle_type);
              if (parsed.vehicle_plate) setCourierVehiclePlate(parsed.vehicle_plate);
            }
          } catch {
            // ignore
          }
        }

        if (!sStatus.applied) {
          try {
            const savedDraft = localStorage.getItem(DRAFT_KEY_STORE);
            if (savedDraft) {
              const parsed = JSON.parse(savedDraft);
              if (parsed.name) setStoreName(parsed.name);
              if (parsed.description) setStoreDesc(parsed.description);
              if (parsed.address_text) setStoreAddress(parsed.address_text);
              if (parsed.latitude) setStoreLat(parsed.latitude);
              if (parsed.longitude) setStoreLng(parsed.longitude);
              if (parsed.service_radius_km) setServiceRadius(parsed.service_radius_km);
              if (parsed.document_type) setDocType(parsed.document_type);
            }
          } catch {
            // ignore
          }
        }
      } catch (err: unknown) {
        toastError('Gagal Memuat Status', err instanceof Error ? err.message : 'Terjadi kesalahan');
      } finally {
        setLoading(false);
      }
    }

    loadInitialData();
  }, [router, toastError]);


  // Map Coordinates parsing
  const parsedLat = parseFloat(storeLat) || -6.8900;
  const parsedLng = parseFloat(storeLng) || 107.6150;

  // Handle GPS location detection for store
  const handleDetectLocation = () => {
    if (!navigator.geolocation) {
      toastError('GPS Tidak Didukung', 'Browser Anda tidak mendukung deteksi lokasi.');
      return;
    }
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setIsLocating(false);
        setStoreLat(pos.coords.latitude.toFixed(6));
        setStoreLng(pos.coords.longitude.toFixed(6));
        toastSuccess('Lokasi Ditemukan', 'Koordinat lokasi telah diperbarui.');
      },
      () => {
        setIsLocating(false);
        toastError('Lokasi Gagal Didapatkan', 'Pastikan izin akses lokasi browser telah aktif.');
      },
      { timeout: 8000 }
    );
  };

  // Courier Progress Calculation
  const courierProgress = useMemo(() => {
    const sections = [
      {
        id: 'sec-courier-store',
        number: 1,
        title: 'Toko Tujuan',
        status: courierStoreId ? 'lengkap' : 'belum',
        hasError: !!courierErrors['courier-store'],
      },
      {
        id: 'sec-courier-bio',
        number: 2,
        title: 'Data Diri',
        status:
          courierNik.length === 16 && courierKtpFile
            ? 'lengkap'
            : courierNik.length > 0 || courierKtpFile || courierPhotoFile
            ? 'sebagian'
            : 'belum',
        hasError: !!courierErrors['courier-nik'] || !!courierErrors['courier-ktp'],
      },
      {
        id: 'sec-courier-vehicle',
        number: 3,
        title: 'Kendaraan',
        status: courierVehiclePlate.trim() ? 'lengkap' : 'belum',
        hasError: !!courierErrors['courier-plate'],
      },
    ];

    // Required fields check: store, nik(16), ktp, plate
    const requiredTotal = 4;
    let requiredFilled = 0;
    if (courierStoreId) requiredFilled++;
    if (courierNik.length === 16) requiredFilled++;
    if (courierKtpFile) requiredFilled++;
    if (courierVehiclePlate.trim()) requiredFilled++;

    const isComplete = requiredFilled === requiredTotal;
    const remaining = requiredTotal - requiredFilled;

    return {
      sections,
      requiredTotal,
      requiredFilled,
      isComplete,
      remaining,
      percent: Math.round((requiredFilled / requiredTotal) * 100),
    };
  }, [courierStoreId, courierNik, courierKtpFile, courierPhotoFile, courierVehiclePlate, courierErrors]);

  // Store Progress Calculation
  const storeProgress = useMemo(() => {
    const radiusNum = parseFloat(serviceRadius);
    const isRadiusValid = !isNaN(radiusNum) && radiusNum > 0 && radiusNum <= 10;

    const sections = [
      {
        id: 'sec-store-profile',
        number: 1,
        title: 'Data Laundry',
        status: storeName.trim() ? 'lengkap' : 'belum',
        hasError: !!storeErrors['store-name'],
      },
      {
        id: 'sec-store-location',
        number: 2,
        title: 'Lokasi & Jangkauan',
        status:
          storeAddress.trim() && isRadiusValid
            ? 'lengkap'
            : storeAddress.trim() || serviceRadius
            ? 'sebagian'
            : 'belum',
        hasError: !!storeErrors['store-address'] || !!storeErrors['store-radius'],
      },
      {
        id: 'sec-store-doc',
        number: 3,
        title: 'Dokumen Usaha',
        status: docFile ? 'lengkap' : 'belum',
        hasError: !!storeErrors['store-doc'],
      },
    ];

    // Required fields check: name, address, radius, docFile
    const requiredTotal = 4;
    let requiredFilled = 0;
    if (storeName.trim()) requiredFilled++;
    if (storeAddress.trim()) requiredFilled++;
    if (isRadiusValid) requiredFilled++;
    if (docFile) requiredFilled++;

    const isComplete = requiredFilled === requiredTotal;
    const remaining = requiredTotal - requiredFilled;

    return {
      sections,
      requiredTotal,
      requiredFilled,
      isComplete,
      remaining,
      percent: Math.round((requiredFilled / requiredTotal) * 100),
    };
  }, [storeName, storeAddress, serviceRadius, docFile, storeErrors]);

  // Scroll smoothly to section
  const scrollToSection = (sectionId: string) => {
    setActiveSectionId(sectionId);
    const el = document.getElementById(sectionId);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  // Courier Draft Saving
  const handleSaveCourierDraft = () => {
    try {
      localStorage.setItem(
        DRAFT_KEY_COURIER,
        JSON.stringify({
          store_id: courierStoreId,
          store_name: selectedStore?.name || '',
          store_address: selectedStore?.address_text || '',
          nik: courierNik,
          vehicle_type: courierVehicleType,
          vehicle_plate: courierVehiclePlate,
        })
      );
      setDraftNotice('Draft pengajuan kurir berhasil disimpan.');
      setTimeout(() => setDraftNotice(null), 3000);
    } catch {
      toastError('Gagal Menyimpan', 'Penyimpanan lokal tidak tersedia.');
    }
  };

  // Store Draft Saving
  const handleSaveStoreDraft = () => {
    try {
      localStorage.setItem(
        DRAFT_KEY_STORE,
        JSON.stringify({
          name: storeName,
          description: storeDesc,
          address_text: storeAddress,
          latitude: storeLat,
          longitude: storeLng,
          service_radius_km: serviceRadius,
          document_type: docType,
        })
      );
      setDraftNotice('Draft pendaftaran toko berhasil disimpan.');
      setTimeout(() => setDraftNotice(null), 3000);
    } catch {
      toastError('Gagal Menyimpan', 'Penyimpanan lokal tidak tersedia.');
    }
  };

  // Validate Courier Form
  const validateCourierForm = (): boolean => {
    const errors: Record<string, string> = {};
    if (!courierStoreId) errors['courier-store'] = 'Pilih toko dari daftar';
    if (!courierNik.trim()) {
      errors['courier-nik'] = 'Nomor Induk Kependudukan (NIK) wajib diisi.';
    } else if (!/^\d{16}$/.test(courierNik.trim())) {
      errors['courier-nik'] = 'NIK harus berupa 16 digit angka.';
    }
    if (!courierVehiclePlate.trim()) {
      errors['courier-plate'] = 'Nomor plat kendaraan wajib diisi.';
    }
    if (!courierKtpFile) {
      errors['courier-ktp'] = 'Foto atau scan KTP wajib diunggah.';
    }

    setCourierErrors(errors);

    const firstErrorKey = Object.keys(errors)[0];
    if (firstErrorKey) {
      const el = document.getElementById(firstErrorKey);
      if (el) el.focus();
      return false;
    }
    return true;
  };

  // Handle Courier Submit
  const handleCourierSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateCourierForm()) return;

    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('store_id', courierStoreId);
      formData.append('nik', courierNik.trim());
      formData.append('vehicle_type', courierVehicleType);
      formData.append('vehicle_plate', courierVehiclePlate.trim().toUpperCase());
      if (courierKtpFile) formData.append('ktp_file', courierKtpFile);
      if (courierPhotoFile) formData.append('photo_file', courierPhotoFile);

      await applyCourier(formData);
      localStorage.removeItem(DRAFT_KEY_COURIER);
      toastSuccess(
        'Pengajuan Terkirim',
        `Pengajuan kurir ke toko ${selectedStore?.name || 'tujuan'} telah terkirim.`
      );
      const updatedStatus = await getCourierStatus();
      setCourierStatus(updatedStatus);
      setIsReapplyingCourier(false);
    } catch (err: unknown) {
      toastError('Gagal Mengirim', err instanceof Error ? err.message : 'Terjadi kesalahan.');
    } finally {
      setSubmitting(false);
    }
  };

  // Validate Store Form
  const validateStoreForm = (): boolean => {
    const errors: Record<string, string> = {};
    if (!storeName.trim()) errors['store-name'] = 'Nama gerai laundry wajib diisi.';
    if (!storeAddress.trim()) errors['store-address'] = 'Alamat lengkap gerai wajib diisi.';
    const radiusNum = parseFloat(serviceRadius);
    if (isNaN(radiusNum) || radiusNum <= 0 || radiusNum > 10) {
      errors['store-radius'] = 'Radius jangkauan harus antara 0.5 hingga 10 km.';
    }
    if (!docFile) errors['store-doc'] = 'File dokumen legalitas usaha wajib diunggah.';

    setStoreErrors(errors);

    const firstErrorKey = Object.keys(errors)[0];
    if (firstErrorKey) {
      const el = document.getElementById(firstErrorKey);
      if (el) el.focus();
      return false;
    }
    return true;
  };

  // Handle Store Submit
  const handleStoreSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateStoreForm()) return;

    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('name', storeName.trim());
      formData.append('description', storeDesc.trim());
      formData.append('address_text', storeAddress.trim());
      if (storeLat) formData.append('latitude', storeLat);
      if (storeLng) formData.append('longitude', storeLng);
      formData.append('service_radius_km', serviceRadius);
      formData.append('document_type', docType);
      if (docFile) formData.append('document_file', docFile);

      await applyStore(formData);
      localStorage.removeItem(DRAFT_KEY_STORE);
      toastSuccess('Pendaftaran Terkirim', 'Pendaftaran toko berhasil dikirim.');
      setStoreStatus(await getStoreStatus());
    } catch (err: unknown) {
      toastError('Gagal Mendaftar', err instanceof Error ? err.message : 'Terjadi kesalahan.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <PublicLayout>
        <div className="max-w-5xl mx-auto px-4 py-12 space-y-6">
          <Skeleton className="h-8 w-48" />
          <Skeleton className="h-4 w-96" />
          <div className="grid grid-cols-1 md:grid-cols-12 gap-8 pt-4">
            <div className="md:col-span-4 space-y-3">
              <Skeleton className="h-28 w-full" />
            </div>
            <div className="md:col-span-8 space-y-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-24 w-full" />
            </div>
          </div>
        </div>
      </PublicLayout>
    );
  }

  const isCourierApplied = courierStatus?.applied && !isReapplyingCourier;
  const isCourierRejected = courierStatus?.application?.verification_status === 'rejected';
  const isCourierRevision = courierStatus?.application?.verification_status === 'revision_required';

  const currentProgress = activeTab === 'courier' ? courierProgress : storeProgress;

  return (
    <PublicLayout>
      <div className="min-h-screen bg-white pb-24">
        {/* Top Header Section */}
        <div className="border-b border-slate-200 bg-slate-50/50 py-8 px-4 sm:px-6">
          <div className="max-w-5xl mx-auto space-y-4">
            {/* Segmented Switcher */}
            <div className="inline-flex p-1 bg-slate-200/70 border border-slate-200 rounded-lg text-xs font-medium">
              <button
                type="button"
                onClick={() => {
                  setActiveTab('courier');
                  setDraftNotice(null);
                }}
                className={`inline-flex items-center gap-1.5 px-4 py-1.5 rounded-md transition-colors cursor-pointer ${
                  activeTab === 'courier'
                    ? 'bg-white text-slate-900 shadow-2xs font-semibold'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Truck className="w-3.5 h-3.5" strokeWidth={1.5} />
                <span>Pengajuan Kurir</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setActiveTab('store');
                  setDraftNotice(null);
                }}
                className={`inline-flex items-center gap-1.5 px-4 py-1.5 rounded-md transition-colors cursor-pointer ${
                  activeTab === 'store'
                    ? 'bg-white text-slate-900 shadow-2xs font-semibold'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <StoreIcon className="w-3.5 h-3.5" strokeWidth={1.5} />
                <span>Daftarkan Laundry</span>
              </button>
            </div>

            {/* Header Text */}
            <div className="space-y-1">
              <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">
                {activeTab === 'courier' ? 'Ajukan sebagai kurir' : 'Daftarkan laundry'}
              </h1>
              <p className="text-xs sm:text-sm text-slate-600">
                {activeTab === 'courier'
                  ? 'Dibutuhkan KTP, SIM C, dan STNK kendaraan operasional yang masih berlaku.'
                  : 'Dibutuhkan NIB/SIUP/SKU dan alamat fisik gerai laundry yang terverifikasi.'}
              </p>
            </div>
          </div>
        </div>

        {/* Main Content Area: 2 Columns Layout on Desktop */}
        <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8">
          {draftNotice && (
            <div className="mb-6 p-3 bg-slate-900 text-white text-xs rounded-md flex items-center justify-between animate-fadeIn">
              <span>{draftNotice}</span>
              <button
                type="button"
                onClick={() => setDraftNotice(null)}
                className="text-slate-400 hover:text-white"
              >
                ✕
              </button>
            </div>
          )}

          {/* ============================================================ */}
          {/* POST-SUBMIT STATUS VIEW (COURIER)                            */}
          {/* ============================================================ */}
          {activeTab === 'courier' && isCourierApplied ? (
            <div className="max-w-2xl mx-auto border border-slate-200 rounded-lg p-6 bg-slate-50/50 space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-slate-200">
                <div>
                  <h2 className="text-base font-semibold text-slate-900">Pengajuan dikirim</h2>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Status: {isCourierRejected ? 'Ditolak' : isCourierRevision ? 'Perlu Revisi' : 'Menunggu review'}
                  </p>
                </div>
                <span
                  className={`text-xs px-2.5 py-1 rounded font-medium ${
                    isCourierRejected
                      ? 'bg-rose-100 text-rose-800'
                      : isCourierRevision
                      ? 'bg-amber-100 text-amber-800'
                      : courierStatus.application?.verification_status === 'approved'
                      ? 'bg-emerald-100 text-emerald-800'
                      : 'bg-slate-200 text-slate-800'
                  }`}
                >
                  {isCourierRejected
                    ? 'Ditolak'
                    : isCourierRevision
                    ? 'Revisi Diminta'
                    : courierStatus.application?.verification_status === 'approved'
                    ? 'Disetujui'
                    : 'Menunggu review'}
                </span>
              </div>

              <div className="space-y-2.5 text-xs text-slate-600">
                <div className="flex justify-between">
                  <span className="text-slate-500">Toko Tujuan:</span>
                  <span className="font-medium text-slate-900">
                    {courierStatus.application?.store_name || '-'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Tanggal Pengajuan:</span>
                  <span className="tabular-nums font-mono text-slate-700">
                    {courierStatus.application?.created_at
                      ? formatTanggal(courierStatus.application.created_at)
                      : '-'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Jenis Kendaraan:</span>
                  <span className="text-slate-900 capitalize">
                    {courierStatus.application?.vehicle_type || '-'}
                  </span>
                </div>

                {courierStatus.application?.rejection_reason && (
                  <div className="pt-3 border-t border-slate-200 space-y-1">
                    <span className="text-rose-700 font-semibold block">Catatan Toko / Admin:</span>
                    <p className="text-rose-600 leading-relaxed bg-rose-50 p-3 rounded border border-rose-200">
                      {courierStatus.application.rejection_reason}
                    </p>
                  </div>
                )}
              </div>

              {(isCourierRejected || isCourierRevision) && (
                <div className="pt-3 border-t border-slate-200">
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => setIsReapplyingCourier(true)}
                    className="w-full bg-[#1A3A5C]"
                  >
                    Perbarui pengajuan ke toko lain
                  </Button>
                </div>
              )}
            </div>
          ) : activeTab === 'store' ? (
            /* ============================================================ */
            /* SEPARATE MITRA ACCOUNT NOTICE                                */
            /* ============================================================ */
            <div className="max-w-2xl mx-auto border border-slate-200 rounded-2xl p-8 bg-slate-50 text-center space-y-4 my-8">
              <div className="w-14 h-14 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mx-auto shadow-sm">
                <StoreIcon className="w-7 h-7" strokeWidth={1.5} />
              </div>
              <div className="space-y-1.5">
                <h2 className="text-lg font-bold text-slate-900 tracking-tight">
                  Pendaftaran Mitra Toko Terpisah
                </h2>
                <p className="text-sm text-slate-600 max-w-md mx-auto">
                  Untuk membuka toko, daftar akun mitra terpisah.
                </p>
              </div>
              <div className="pt-2">
                <button
                  type="button"
                  onClick={() => {
                    if (typeof document !== 'undefined') {
                      document.cookie = 'mitra_entry_flag=1; path=/; max-age=600; SameSite=Lax';
                    }
                    if (typeof window !== 'undefined') {
                      sessionStorage.setItem('mitra_entry_flag', String(Date.now() + 10 * 60 * 1000));
                    }
                    router.push('/mitra/register');
                  }}
                  className="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-md cursor-pointer"
                >
                  <span>Daftar Akun Mitra</span>
                  <ArrowRight className="w-4 h-4" />
                </button>
              </div>

              {/* Preserved Store Partner UI (Hidden, code preserved) */}
              {false && (
                <div className="hidden">
                  <h2>Pendaftaran dikirim</h2>
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => router.push('/store')}
                    className="w-full bg-[#1A3A5C]"
                  >
                    Buka Dashboard Toko
                  </Button>
                </div>
              )}
            </div>
          ) : (
            /* ============================================================ */
            /* TWO-COLUMN FORM LAYOUT (DESKTOP)                             */
            /* ============================================================ */
            <div className="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
              {/* Left Column: Navigation & Progress Summary (Sticky on Desktop) */}
              <aside className="md:col-span-4 md:sticky md:top-24 space-y-4">
                <div className="border border-slate-200 rounded-lg p-4 bg-slate-50/60 space-y-4">
                  <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Tahapan Pengisian
                  </h3>

                  {/* Section list with status dots */}
                  <nav className="space-y-1.5" aria-label="Navigasi tahapan formulir">
                    {currentProgress.sections.map((sec) => (
                      <button
                        key={sec.id}
                        type="button"
                        onClick={() => scrollToSection(sec.id)}
                        className={`w-full flex items-center justify-between p-2 rounded-md text-left text-xs transition-colors cursor-pointer ${
                          sec.hasError
                            ? 'bg-rose-50 text-rose-800'
                            : activeSectionId === sec.id
                            ? 'bg-white text-slate-900 shadow-2xs font-semibold'
                            : 'text-slate-700 hover:bg-slate-100'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <span
                            className={`w-2 h-2 rounded-full shrink-0 ${
                              sec.hasError
                                ? 'bg-rose-500'
                                : sec.status === 'lengkap'
                                ? 'bg-emerald-600'
                                : sec.status === 'sebagian'
                                ? 'bg-slate-400'
                                : 'bg-slate-300'
                            }`}
                          />
                          <span>
                            {sec.number}. {sec.title}
                          </span>
                        </div>

                        <span
                          className={`text-[11px] font-mono ${
                            sec.hasError
                              ? 'text-rose-600 font-semibold'
                              : sec.status === 'lengkap'
                              ? 'text-emerald-700 font-medium'
                              : sec.status === 'sebagian'
                              ? 'text-slate-500'
                              : 'text-slate-400'
                          }`}
                        >
                          {sec.hasError
                            ? 'Error'
                            : sec.status === 'lengkap'
                            ? 'Lengkap'
                            : sec.status === 'sebagian'
                            ? 'Sebagian'
                            : 'Belum diisi'}
                        </span>
                      </button>
                    ))}
                  </nav>

                  {/* Progress Bar & Counter */}
                  <div className="pt-3 border-t border-slate-200 space-y-1.5">
                    <div className="flex justify-between text-xs text-slate-600">
                      <span>Kelengkapan</span>
                      <span className="font-mono tabular-nums">
                        {currentProgress.requiredFilled} dari {currentProgress.requiredTotal} wajib terisi
                      </span>
                    </div>

                    <div className="h-1.5 bg-slate-200 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-[#1A3A5C] transition-all duration-300 rounded-full"
                        style={{ width: `${currentProgress.percent}%` }}
                      />
                    </div>
                  </div>
                </div>
              </aside>

              {/* Right Column: Form Container (Max 640px) */}
              <div className="md:col-span-8 max-w-[640px]">
                {/* -------------------------------------------------------- */}
                {/* FORM: COURIER APPLICATION                                */}
                {/* -------------------------------------------------------- */}
                {activeTab === 'courier' && (
                  <form onSubmit={handleCourierSubmit} className="space-y-8">
                    {/* Section 1: Toko Tujuan */}
                    <div id="sec-courier-store" className="scroll-mt-24 space-y-4">
                      <FormSection
                        title="1. Toko Tujuan"
                        description="Pilih gerai laundry mitra yang membuka lowongan kurir penjemputan."
                      >
                        <StoreCombobox
                          id="courier-store"
                          label="Outlet Laundry Mitra"
                          required
                          value={courierStoreId}
                          selectedStore={selectedStore}
                          onChange={(id, store) => {
                            setCourierStoreId(id);
                            setSelectedStore(store);
                            if (courierErrors['courier-store']) {
                              setCourierErrors((prev) => ({ ...prev, 'courier-store': '' }));
                            }
                          }}
                          error={courierErrors['courier-store']}
                          helperText="Cari nama gerai laundry mitra terdekat di area operasional Anda."
                        />
                      </FormSection>
                    </div>

                    {/* Section 2: Data Diri */}
                    <div id="sec-courier-bio" className="scroll-mt-24 space-y-4">
                      <FormSection
                        title="2. Data Diri"
                        description="Identitas resmi sesuai Kartu Tanda Penduduk (KTP)."
                      >
                        <Input
                          id="courier-nik"
                          label="Nomor Induk Kependudukan (NIK)"
                          required
                          placeholder="16 digit nomor NIK"
                          maxLength={16}
                          value={courierNik}
                          rightElement={
                            <span className="text-[11px] text-slate-400 font-mono">
                              {courierNik.length}/16
                            </span>
                          }
                          onChange={(e) => {
                            const val = e.target.value.replace(/\D/g, '');
                            setCourierNik(val);
                            if (courierErrors['courier-nik']) {
                              setCourierErrors((prev) => ({ ...prev, 'courier-nik': '' }));
                            }
                          }}
                          error={courierErrors['courier-nik']}
                          helperText="Data NIK disimpan dengan enkripsi untuk keperluan verifikasi keamanan."
                        />

                        <FileField
                          id="courier-ktp"
                          label="Foto / Scan KTP"
                          required
                          accept=".pdf,.jpg,.jpeg,.png"
                          value={courierKtpFile}
                          onChange={(file) => {
                            setCourierKtpFile(file);
                            if (courierErrors['courier-ktp']) {
                              setCourierErrors((prev) => ({ ...prev, 'courier-ktp': '' }));
                            }
                          }}
                          error={courierErrors['courier-ktp']}
                          helperText="Unggah foto KTP yang jelas dan terbaca (PDF, JPG, atau PNG, maks. 2 MB)."
                        />

                        <FileField
                          id="courier-photo"
                          label="Pas Foto Terbaru"
                          optional
                          accept=".jpg,.jpeg,.png"
                          value={courierPhotoFile}
                          onChange={setCourierPhotoFile}
                          helperText="Foto setengah badan dengan latar belakang netral (maks. 2 MB)."
                        />
                      </FormSection>
                    </div>

                    {/* Section 3: Kendaraan */}
                    <div id="sec-courier-vehicle" className="scroll-mt-24 space-y-4">
                      <FormSection
                        title="3. Kendaraan Operasional"
                        description="Informasi kendaraan yang digunakan untuk penjemputan dan pengantaran cucian."
                      >
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                          <Select
                            id="courier-vehicle-type"
                            label="Jenis Kendaraan"
                            required
                            value={courierVehicleType}
                            onChange={(e) => setCourierVehicleType(e.target.value)}
                            options={[
                              { value: 'motor', label: 'Sepeda Motor' },
                              { value: 'mobil', label: 'Mobil / Van' },
                              { value: 'sepeda', label: 'Sepeda' },
                            ]}
                          />

                          <Input
                            id="courier-plate"
                            label="Nomor Plat Kendaraan"
                            required
                            placeholder="Contoh: D 1234 ABC"
                            value={courierVehiclePlate}
                            onChange={(e) => {
                              setCourierVehiclePlate(e.target.value.toUpperCase());
                              if (courierErrors['courier-plate']) {
                                setCourierErrors((prev) => ({ ...prev, 'courier-plate': '' }));
                              }
                            }}
                            error={courierErrors['courier-plate']}
                            helperText="Nomor polisi resmi kendaraan operasional."
                          />
                        </div>
                      </FormSection>
                    </div>
                  </form>
                )}

                {/* -------------------------------------------------------- */}
                {/* FORM: STORE PARTNER APPLICATION                          */}
                {/* -------------------------------------------------------- */}
                {((activeTab as string) === 'store') && (
                  <form onSubmit={handleStoreSubmit} className="space-y-8">
                    {/* Section 1: Data Laundry */}
                    <div id="sec-store-profile" className="scroll-mt-24 space-y-4">
                      <FormSection
                        title="1. Data Laundry"
                        description="Nama gerai dan profil usaha yang akan ditampilkan pada marketplace pelanggan."
                      >
                        <Input
                          id="store-name"
                          label="Nama Gerai Laundry"
                          required
                          placeholder="Contoh: Berkah Laundry Express"
                          value={storeName}
                          onChange={(e) => {
                            setStoreName(e.target.value);
                            if (storeErrors['store-name']) {
                              setStoreErrors((prev) => ({ ...prev, 'store-name': '' }));
                            }
                          }}
                          error={storeErrors['store-name']}
                          helperText="Nama resmi gerai laundry yang mudah dikenali pelanggan."
                        />

                        <Textarea
                          id="store-desc"
                          label="Deskripsi Singkat Gerai"
                          optional
                          placeholder="Jelaskan fasilitas pencucian, jam operasional khusus, atau keunggulan gerai Anda."
                          value={storeDesc}
                          onChange={(e) => setStoreDesc(e.target.value)}
                          helperText="Maksimal 500 karakter."
                        />
                      </FormSection>
                    </div>

                    {/* Section 2: Lokasi & Jangkauan */}
                    <div id="sec-store-location" className="scroll-mt-24 space-y-4">
                      <FormSection
                        title="2. Lokasi & Jangkauan"
                        description="Tentukan alamat fisik gerai untuk titik penjemputan dan radius kurir."
                      >
                        <Textarea
                          id="store-address"
                          label="Alamat Lengkap Gerai"
                          required
                          placeholder="Nama jalan, nomor ruko/rumah, RT/RW, kelurahan, kecamatan, kota"
                          value={storeAddress}
                          onChange={(e) => {
                            setStoreAddress(e.target.value);
                            if (storeErrors['store-address']) {
                              setStoreErrors((prev) => ({ ...prev, 'store-address': '' }));
                            }
                          }}
                          error={storeErrors['store-address']}
                          helperText="Alamat fisik tempat cucian ditimbang dan diproses."
                        />

                        <div className="space-y-2 pt-1">
                          <div className="flex items-center justify-between text-xs">
                            <label className="font-medium text-slate-900">
                              Titik Peta Lokasi (OpenStreetMap)
                            </label>
                            <button
                              type="button"
                              onClick={handleDetectLocation}
                              disabled={isLocating}
                              className="inline-flex items-center gap-1 text-slate-700 hover:text-slate-900 font-medium cursor-pointer disabled:opacity-60"
                            >
                              {isLocating ? (
                                <Loader2 className="w-3.5 h-3.5 animate-spin" />
                              ) : (
                                <MapPin className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.5} />
                              )}
                              <span>Gunakan lokasi saat ini</span>
                            </button>
                          </div>

                          {/* Mini Map (Height 200px) */}
                          <div className="h-[200px] rounded-lg overflow-hidden border border-slate-300">
                            <DynamicStoreMap
                              customerLocation={{ lat: parsedLat, lng: parsedLng }}
                              stores={[]}
                              onLocationChange={(lat, lng) => {
                                setStoreLat(lat.toFixed(6));
                                setStoreLng(lng.toFixed(6));
                              }}
                              searchRadiusKm={parseFloat(serviceRadius) || 5}
                              className="h-full w-full"
                            />
                          </div>

                          {/* Coordinates */}
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <Input
                              id="store-lat"
                              label="Latitude"
                              value={storeLat}
                              onChange={(e) => setStoreLat(e.target.value)}
                              placeholder="-6.890000"
                              helperText="Koordinat lintang geografis gerai."
                            />
                            <Input
                              id="store-lng"
                              label="Longitude"
                              value={storeLng}
                              onChange={(e) => setStoreLng(e.target.value)}
                              placeholder="107.615000"
                              helperText="Koordinat bujur geografis gerai."
                            />
                          </div>
                        </div>

                        <Input
                          id="store-radius"
                          label="Radius Jangkauan Layanan (km)"
                          type="number"
                          step="0.5"
                          min="0.5"
                          max="10.0"
                          required
                          value={serviceRadius}
                          onChange={(e) => {
                            setServiceRadius(e.target.value);
                            if (storeErrors['store-radius']) {
                              setStoreErrors((prev) => ({ ...prev, 'store-radius': '' }));
                            }
                          }}
                          error={storeErrors['store-radius']}
                          helperText="Jangkauan maksimal layanan antar-jemput kurir (antara 0.5 hingga 10.0 km)."
                        />
                      </FormSection>
                    </div>

                    {/* Section 3: Dokumen Usaha */}
                    <div id="sec-store-doc" className="scroll-mt-24 space-y-4">
                      <FormSection
                        title="3. Dokumen Usaha"
                        description="Lampirkan dokumen legalitas usaha untuk verifikasi super admin."
                      >
                        <Select
                          id="store-doc-type"
                          label="Jenis Dokumen Legalitas"
                          required
                          value={docType}
                          onChange={(e) => setDocType(e.target.value)}
                          options={[
                            { value: 'nib', label: 'Nomor Induk Berusaha (NIB)' },
                            { value: 'siup', label: 'Surat Izin Usaha Perdagangan (SIUP)' },
                            { value: 'sku', label: 'Surat Keterangan Usaha (SKU) / KTP Pemilik' },
                          ]}
                        />

                        <FileField
                          id="store-doc"
                          label="Unggah File Dokumen"
                          required
                          accept=".pdf,.jpg,.jpeg,.png"
                          value={docFile}
                          onChange={(file) => {
                            setDocFile(file);
                            if (storeErrors['store-doc']) {
                              setStoreErrors((prev) => ({ ...prev, 'store-doc': '' }));
                            }
                          }}
                          error={storeErrors['store-doc']}
                          helperText="Format PDF, JPG, atau PNG (maks. 2 MB)."
                        />
                      </FormSection>
                    </div>
                  </form>
                )}
              </div>
            </div>
          )}
        </div>

        {/* ============================================================ */}
        {/* STICKY BOTTOM ACTION BAR (FOOTER AKSI)                       */}
        {/* ============================================================ */}
        {((activeTab === 'courier' && !isCourierApplied) ||
          (activeTab === 'store' && !storeStatus?.applied)) && (
          <div className="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-xs border-t border-slate-200 py-3 px-4 sm:px-6 shadow-xs">
            <div className="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
              {/* Left: Status text */}
              <div className="text-xs text-slate-600 text-center sm:text-left">
                {currentProgress.isComplete ? (
                  <span className="text-slate-800 font-medium">
                    Semua field wajib telah terisi (siap dikirim)
                  </span>
                ) : (
                  <span>
                    Belum bisa dikirim:{' '}
                    <strong className="text-slate-900 font-semibold font-mono">
                      {currentProgress.remaining}
                    </strong>{' '}
                    field wajib belum diisi
                  </span>
                )}
              </div>

              {/* Right: Draft & Submit Buttons */}
              <div className="flex items-center gap-2.5 w-full sm:w-auto">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={activeTab === 'courier' ? handleSaveCourierDraft : handleSaveStoreDraft}
                  className="flex-1 sm:flex-initial"
                >
                  Simpan draft
                </Button>

                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  disabled={!currentProgress.isComplete || submitting}
                  onClick={activeTab === 'courier' ? handleCourierSubmit : handleStoreSubmit}
                  className="flex-1 sm:flex-initial bg-[#1A3A5C] hover:bg-[#132d47] disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  {submitting ? (
                    <>
                      <Loader2 className="w-3.5 h-3.5 animate-spin mr-1.5" />
                      <span>Mengirim...</span>
                    </>
                  ) : (
                    <span>{activeTab === 'courier' ? 'Kirim pengajuan' : 'Kirim pendaftaran'}</span>
                  )}
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </PublicLayout>
  );
}
