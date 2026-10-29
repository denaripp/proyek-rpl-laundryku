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
  ShieldCheck,
  FileText,
  Clock,
  X,
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
        toastError('Gagal memuat status', err instanceof Error ? err.message : 'Terjadi kesalahan sistem.');
      } finally {
        setLoading(false);
      }
    }

    loadInitialData();
  }, [router, toastError, authLoading, isAuthenticated]);

  // Map Coordinates parsing
  const parsedLat = parseFloat(storeLat) || -6.8900;
  const parsedLng = parseFloat(storeLng) || 107.6150;

  // Handle GPS location detection for store
  const handleDetectLocation = () => {
    if (!navigator.geolocation) {
      toastError('GPS tidak didukung', 'Browser Anda tidak mendukung deteksi lokasi.');
      return;
    }
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setIsLocating(false);
        setStoreLat(pos.coords.latitude.toFixed(6));
        setStoreLng(pos.coords.longitude.toFixed(6));
        toastSuccess('Lokasi ditemukan', 'Koordinat lokasi telah diperbarui.');
      },
      () => {
        setIsLocating(false);
        toastError('Lokasi gagal didapatkan', 'Pastikan izin akses lokasi browser telah aktif.');
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
        title: 'Toko tujuan',
        status: courierStoreId ? 'lengkap' : 'belum',
        hasError: !!courierErrors['courier-store'],
      },
      {
        id: 'sec-courier-bio',
        number: 2,
        title: 'Data diri',
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
        title: 'Data laundry',
        status: storeName.trim() ? 'lengkap' : 'belum',
        hasError: !!storeErrors['store-name'],
      },
      {
        id: 'sec-store-location',
        number: 2,
        title: 'Lokasi & jangkauan',
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
        title: 'Dokumen usaha',
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
      toastError('Gagal menyimpan', 'Penyimpanan lokal tidak tersedia.');
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
      toastError('Gagal menyimpan', 'Penyimpanan lokal tidak tersedia.');
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
        'Pengajuan terkirim',
        `Pengajuan kurir ke toko ${selectedStore?.name || 'tujuan'} telah terkirim.`
      );
      const updatedStatus = await getCourierStatus();
      setCourierStatus(updatedStatus);
      setIsReapplyingCourier(false);
    } catch (err: unknown) {
      toastError('Gagal mengirim', err instanceof Error ? err.message : 'Terjadi kesalahan.');
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
      toastSuccess('Pendaftaran terkirim', 'Pendaftaran toko berhasil dikirim.');
      setStoreStatus(await getStoreStatus());
    } catch (err: unknown) {
      toastError('Gagal mendaftar', err instanceof Error ? err.message : 'Terjadi kesalahan.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <PublicLayout>
        <div className="min-h-screen bg-slate-50">
          <div className="bg-navy-900 text-white pt-8 pb-14 px-4 sm:px-6 lg:px-8">
            <div className="max-w-5xl mx-auto space-y-3">
              <Skeleton className="h-8 w-44 bg-navy-800" />
              <Skeleton className="h-4 w-72 bg-navy-800" />
            </div>
          </div>
          <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8">
            <div className="grid grid-cols-1 md:grid-cols-12 gap-8 pt-2">
              <div className="md:col-span-4 space-y-3">
                <Skeleton className="h-44 w-full rounded-2xl" />
              </div>
              <div className="md:col-span-8 space-y-4">
                <Skeleton className="h-32 w-full rounded-2xl" />
                <Skeleton className="h-48 w-full rounded-2xl" />
                <Skeleton className="h-36 w-full rounded-2xl" />
              </div>
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
      <div className="min-h-screen bg-slate-50/70 pb-28">
        {/* Top Header Section (Navy-900 with Wave & Decorative Bubbles) */}
        <div className="bg-navy-900 text-white relative overflow-hidden pt-8 pb-14 px-4 sm:px-6 lg:px-8 select-none">
          {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
          <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
            {/* Bubble 1 (Top Left) */}
            <div className="absolute top-4 left-10 w-24 h-24 rounded-full border border-sky-300/30 bg-sky-400/10 backdrop-blur-[1px] shadow-inner animate-gentle-float">
              <div className="w-2.5 h-2.5 rounded-full bg-white/60 ml-3.5 mt-3.5" />
            </div>

            {/* Bubble 2 (Top Right) */}
            <div className="absolute top-6 right-16 w-32 h-32 rounded-full border border-sky-300/20 bg-sky-300/10 animate-gentle-float-slow">
              <div className="w-3.5 h-3.5 rounded-full bg-white/50 ml-4 mt-4" />
            </div>
          </div>

          {/* Bottom Decorative Wave Accent (aria-hidden="true") */}
          <div className="absolute bottom-0 left-0 right-0 pointer-events-none overflow-hidden leading-none z-0" aria-hidden="true">
            <svg
              viewBox="0 0 1440 64"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              className="w-full h-8 sm:h-12 text-slate-50/70 preserve-3d"
            >
              <path
                d="M0 24C240 48 480 8 720 28C960 48 1200 12 1440 24V64H0V24Z"
                fill="currentColor"
              />
            </svg>
          </div>

          <div className="max-w-5xl mx-auto relative z-10 space-y-4">
            {/* Segmented Switcher */}
            <div className="inline-flex p-1 bg-navy-950/70 border border-white/10 rounded-xl text-xs font-medium backdrop-blur-xs shadow-inner">
              <button
                type="button"
                onClick={() => {
                  setActiveTab('courier');
                  setDraftNotice(null);
                }}
                className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg transition-all cursor-pointer text-xs ${
                  activeTab === 'courier'
                    ? 'bg-white text-navy-950 font-bold shadow-xs'
                    : 'text-sky-100/80 hover:text-white hover:bg-white/10'
                }`}
              >
                <Truck className="w-4 h-4" />
                <span>Pengajuan kurir</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setActiveTab('store');
                  setDraftNotice(null);
                }}
                className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg transition-all cursor-pointer text-xs ${
                  activeTab === 'store'
                    ? 'bg-white text-navy-950 font-bold shadow-xs'
                    : 'text-sky-100/80 hover:text-white hover:bg-white/10'
                }`}
              >
                <StoreIcon className="w-4 h-4" />
                <span>Daftarkan laundry</span>
              </button>
            </div>

            {/* Header Text */}
            <div className="space-y-1">
              <h1 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                {activeTab === 'courier' ? 'Ajukan sebagai kurir' : 'Daftarkan laundry'}
              </h1>
              <p className="text-xs sm:text-sm text-sky-100/90 leading-relaxed max-w-2xl">
                {activeTab === 'courier'
                  ? 'Dibutuhkan KTP, SIM C, dan identitas kendaraan operasional yang masih berlaku untuk bergabung.'
                  : 'Dibutuhkan NIB/SIUP/SKU dan alamat fisik gerai laundry yang terverifikasi.'}
              </p>
            </div>
          </div>
        </div>

        {/* Main Content Area */}
        <div className="max-w-5xl mx-auto px-4 sm:px-6 py-8">
          {draftNotice && (
            <div className="mb-6 p-4 bg-navy-50 border border-navy-200 text-navy-900 text-xs font-medium rounded-2xl flex items-center justify-between shadow-2xs animate-fadeIn">
              <div className="flex items-center gap-2.5">
                <ShieldCheck className="w-4 h-4 text-navy-700 shrink-0" />
                <span>{draftNotice}</span>
              </div>
              <button
                type="button"
                onClick={() => setDraftNotice(null)}
                className="text-slate-400 hover:text-slate-700 p-1 rounded-lg"
                aria-label="Tutup notifikasi"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          )}

          {/* ============================================================ */}
          {/* POST-SUBMIT STATUS VIEW (COURIER)                            */}
          {/* ============================================================ */}
          {activeTab === 'courier' && isCourierApplied ? (
            <div className="max-w-2xl mx-auto border border-slate-200/90 rounded-2xl p-6 sm:p-8 bg-white shadow-2xs space-y-6">
              <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-xl bg-navy-50 text-navy-700 flex items-center justify-center">
                    <FileText className="w-5 h-5" />
                  </div>
                  <div>
                    <h2 className="text-base sm:text-lg font-bold text-slate-900">Pengajuan kurir dikirim</h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                      Status verifikasi terkini berkas pendaftaran Anda
                    </p>
                  </div>
                </div>
                <span
                  className={`text-xs px-3 py-1 rounded-lg font-semibold border ${
                    isCourierRejected
                      ? 'bg-rose-50 border-rose-200 text-rose-800'
                      : isCourierRevision
                      ? 'bg-amber-50 border-amber-200 text-amber-800'
                      : courierStatus.application?.verification_status === 'approved'
                      ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                      : 'bg-navy-50 border-navy-100 text-navy-800'
                  }`}
                >
                  {isCourierRejected
                    ? 'Ditolak'
                    : isCourierRevision
                    ? 'Revisi diminta'
                    : courierStatus.application?.verification_status === 'approved'
                    ? 'Disetujui'
                    : 'Menunggu review'}
                </span>
              </div>

              <div className="space-y-3 text-xs">
                <div className="p-3.5 rounded-xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                  <span className="text-slate-500">Toko tujuan</span>
                  <span className="font-semibold text-slate-900">
                    {courierStatus.application?.store_name || '-'}
                  </span>
                </div>
                <div className="p-3.5 rounded-xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                  <span className="text-slate-500">Tanggal pengajuan</span>
                  <span className="tabular-nums font-mono font-medium text-slate-800">
                    {courierStatus.application?.created_at
                      ? formatTanggal(courierStatus.application.created_at)
                      : '-'}
                  </span>
                </div>
                <div className="p-3.5 rounded-xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                  <span className="text-slate-500">Jenis kendaraan</span>
                  <span className="text-slate-900 font-medium capitalize">
                    {courierStatus.application?.vehicle_type || '-'}
                  </span>
                </div>

                {courierStatus.application?.rejection_reason && (
                  <div className="pt-2">
                    <div className="p-4 rounded-xl bg-rose-50/80 border border-rose-200 text-rose-900 space-y-1.5">
                      <span className="text-rose-800 font-semibold block">Catatan verifikasi toko / admin:</span>
                      <p className="text-rose-700 leading-relaxed text-xs">
                        {courierStatus.application.rejection_reason}
                      </p>
                    </div>
                  </div>
                )}
              </div>

              {(isCourierRejected || isCourierRevision) && (
                <div className="pt-2">
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => setIsReapplyingCourier(true)}
                    className="w-full bg-navy-900 hover:bg-navy-800 text-white rounded-xl py-2.5 text-xs font-semibold shadow-xs"
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
            <div className="max-w-2xl mx-auto border border-slate-200/90 rounded-2xl p-8 sm:p-10 bg-white shadow-2xs text-center space-y-5 my-6">
              <div className="w-14 h-14 rounded-2xl bg-navy-50 text-navy-700 border border-navy-100 flex items-center justify-center mx-auto shadow-2xs">
                <StoreIcon className="w-7 h-7" />
              </div>
              <div className="space-y-2">
                <h2 className="text-xl font-bold text-slate-900 tracking-tight">
                  Pendaftaran mitra toko terpisah
                </h2>
                <p className="text-xs sm:text-sm text-slate-600 max-w-md mx-auto leading-relaxed">
                  Untuk mendaftarkan dan mengelola gerai laundry, gunakan formulir pendaftaran akun mitra toko khusus.
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
                  className="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-white bg-navy-900 hover:bg-navy-800 transition-colors shadow-xs cursor-pointer"
                >
                  <span>Daftar akun mitra</span>
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
                <div className="border border-slate-200/90 rounded-2xl p-5 bg-white shadow-2xs space-y-5">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                      Tahapan pengisian
                    </h3>
                    <span className="text-xs font-mono font-semibold text-navy-900">
                      {currentProgress.percent}%
                    </span>
                  </div>

                  {/* Section list with status dots */}
                  <nav className="space-y-1.5" aria-label="Navigasi tahapan formulir">
                    {currentProgress.sections.map((sec) => (
                      <button
                        key={sec.id}
                        type="button"
                        onClick={() => scrollToSection(sec.id)}
                        className={`w-full flex items-center justify-between p-2.5 rounded-xl text-left text-xs transition-all cursor-pointer ${
                          sec.hasError
                            ? 'bg-rose-50 text-rose-800 border border-rose-200/80 font-medium'
                            : activeSectionId === sec.id
                            ? 'bg-navy-50 text-navy-950 border border-navy-100 font-semibold shadow-2xs'
                            : 'text-slate-700 hover:bg-slate-50 font-medium'
                        }`}
                      >
                        <div className="flex items-center gap-2.5">
                          <span
                            className={`w-2 h-2 rounded-full shrink-0 ${
                              sec.hasError
                                ? 'bg-rose-500'
                                : sec.status === 'lengkap'
                                ? 'bg-emerald-600'
                                : sec.status === 'sebagian'
                                ? 'bg-navy-600'
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
                              ? 'text-emerald-700 font-semibold'
                              : sec.status === 'sebagian'
                              ? 'text-navy-700 font-medium'
                              : 'text-slate-400'
                          }`}
                        >
                          {sec.hasError
                            ? 'Periksa'
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
                  <div className="pt-4 border-t border-slate-100 space-y-2">
                    <div className="flex justify-between text-xs text-slate-600">
                      <span>Kelengkapan</span>
                      <span className="font-mono tabular-nums font-semibold text-slate-800">
                        {currentProgress.requiredFilled} dari {currentProgress.requiredTotal} wajib
                      </span>
                    </div>

                    <div className="h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200/50">
                      <div
                        className="h-full bg-navy-900 transition-all duration-300 rounded-full"
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
                  <form onSubmit={handleCourierSubmit} className="space-y-6">
                    {/* Section 1: Toko Tujuan */}
                    <div id="sec-courier-store" className="scroll-mt-24">
                      <div className="border border-slate-200/90 rounded-2xl p-6 sm:p-7 bg-white shadow-2xs space-y-5">
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
                    </div>

                    {/* Section 2: Data Diri */}
                    <div id="sec-courier-bio" className="scroll-mt-24">
                      <div className="border border-slate-200/90 rounded-2xl p-6 sm:p-7 bg-white shadow-2xs space-y-5">
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
                    </div>

                    {/* Section 3: Kendaraan */}
                    <div id="sec-courier-vehicle" className="scroll-mt-24">
                      <div className="border border-slate-200/90 rounded-2xl p-6 sm:p-7 bg-white shadow-2xs space-y-5">
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
                    </div>
                  </form>
                )}

                {/* -------------------------------------------------------- */}
                {/* FORM: STORE PARTNER APPLICATION                          */}
                {/* -------------------------------------------------------- */}
                {((activeTab as string) === 'store') && (
                  <form onSubmit={handleStoreSubmit} className="space-y-6">
                    {/* Section 1: Data Laundry */}
                    <div id="sec-store-profile" className="scroll-mt-24">
                      <div className="border border-slate-200/90 rounded-2xl p-6 sm:p-7 bg-white shadow-2xs space-y-5">
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
                    </div>

                    {/* Section 2: Lokasi & Jangkauan */}
                    <div id="sec-store-location" className="scroll-mt-24">
                      <div className="border border-slate-200/90 rounded-2xl p-6 sm:p-7 bg-white shadow-2xs space-y-5">
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

                          <div className="space-y-2.5 pt-1">
                            <div className="flex items-center justify-between text-xs">
                              <label className="font-semibold text-slate-800">
                                Titik peta lokasi (OpenStreetMap)
                              </label>
                              <button
                                type="button"
                                onClick={handleDetectLocation}
                                disabled={isLocating}
                                className="inline-flex items-center gap-1.5 text-navy-800 hover:text-navy-950 font-semibold cursor-pointer disabled:opacity-60 transition-colors"
                              >
                                {isLocating ? (
                                  <Loader2 className="w-3.5 h-3.5 animate-spin" />
                                ) : (
                                  <MapPin className="w-3.5 h-3.5 text-navy-700" />
                                )}
                                <span>Gunakan lokasi saat ini</span>
                              </button>
                            </div>

                            {/* Mini Map (Height 200px) */}
                            <div className="h-[220px] rounded-xl overflow-hidden border border-slate-200/90 shadow-2xs">
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
                    </div>

                    {/* Section 3: Dokumen Usaha */}
                    <div id="sec-store-doc" className="scroll-mt-24">
                      <div className="border border-slate-200/90 rounded-2xl p-6 sm:p-7 bg-white shadow-2xs space-y-5">
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
          <div className="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 py-3.5 px-4 sm:px-6 shadow-lg">
            <div className="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
              {/* Left: Status text */}
              <div className="text-xs text-slate-600 text-center sm:text-left">
                {currentProgress.isComplete ? (
                  <span className="text-navy-950 font-semibold flex items-center gap-1.5 justify-center sm:justify-start">
                    <Check className="w-4 h-4 text-emerald-600" />
                    <span>Semua field wajib telah terisi (siap dikirim)</span>
                  </span>
                ) : (
                  <span className="text-slate-500">
                    Sisa <strong className="text-slate-800 font-mono">{currentProgress.remaining}</strong> isian wajib untuk mengirim pengajuan
                  </span>
                )}
              </div>

              {/* Right: Actions */}
              <div className="flex items-center gap-2.5 w-full sm:w-auto">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={activeTab === 'courier' ? handleSaveCourierDraft : handleSaveStoreDraft}
                  className="flex-1 sm:flex-initial rounded-xl border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs px-4 py-2 min-h-[38px] shadow-2xs"
                >
                  Simpan draft
                </Button>

                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  disabled={!currentProgress.isComplete || submitting}
                  onClick={activeTab === 'courier' ? handleCourierSubmit : handleStoreSubmit}
                  className="flex-1 sm:flex-initial bg-navy-900 hover:bg-navy-800 text-white rounded-xl font-semibold text-xs px-5 py-2 min-h-[38px] shadow-xs disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
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
