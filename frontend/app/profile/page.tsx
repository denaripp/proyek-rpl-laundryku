'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Plus,
  Trash2,
  ShieldCheck,
  Mail,
  Phone,
  MapPin,
  Navigation,
  CheckCircle2,
  AlertCircle,
  User,
  Calendar,
} from 'lucide-react';
import { formatPhone } from '@/lib/auth';
import { cn } from '@/lib/format';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/Textarea';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Skeleton } from '@/components/ui/Skeleton';
import { AvatarUploader } from '@/components/ui/AvatarUploader';
import { useToast } from '@/components/ui/Toast';
import { useAuth } from '@/lib/AuthContext';
import {
  getProfile,
  updateProfile,
  getAddresses,
  createAddress,
  deleteAddress,
  setDefaultAddress,
  reverseGeocode,
  ProfileData,
  AddressItem,
} from '@/lib/profile';
import { formatFriendlyErrorMessage } from '@/lib/api';

export default function ProfilePage() {
  const router = useRouter();
  const { success, error: toastError } = useToast();
  const { isAuthenticated, loading: authLoading } = useAuth();
  const [loading, setLoading] = useState(true);
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [addresses, setAddresses] = useState<AddressItem[]>([]);

  // Edit Profile Form State
  const [name, setName] = useState('');
  const [dateOfBirth, setDateOfBirth] = useState('');
  const [email, setEmail] = useState('');
  const [showAddEmail, setShowAddEmail] = useState(false);
  const [savingProfile, setSavingProfile] = useState(false);

  // New Address Modal State
  const [showAddressModal, setShowAddressModal] = useState(false);
  const [addrLabel, setAddrLabel] = useState('Rumah');
  const [addrText, setAddrText] = useState('');
  const [addrLat, setAddrLat] = useState('');
  const [addrLng, setAddrLng] = useState('');
  const [addrCity, setAddrCity] = useState('');
  const [addrProvince, setAddrProvince] = useState('');
  const [addrPostalCode, setAddrPostalCode] = useState('');
  const [addrKelurahan, setAddrKelurahan] = useState('');
  const [addrKecamatan, setAddrKecamatan] = useState('');
  const [addrNote, setAddrNote] = useState('');
  const [addrIsDefault, setAddrIsDefault] = useState(false);
  const [savingAddr, setSavingAddr] = useState(false);

  // GPS Geolocation State
  const [isDetectingLocation, setIsDetectingLocation] = useState(false);
  const [gpsDetected, setGpsDetected] = useState(false);
  const [gpsNotice, setGpsNotice] = useState<string | null>(null);

  // Delete Address Confirm Dialog State
  const [addressToDelete, setAddressToDelete] = useState<string | null>(null);
  const [deletingAddr, setDeletingAddr] = useState(false);

  useEffect(() => {
    if (authLoading) return;
    if (!isAuthenticated) {
      router.push('/auth');
      return;
    }

    async function loadData() {
      try {
        setLoading(true);
        const [profData, addrData] = await Promise.all([getProfile(), getAddresses()]);
        setProfile(profData);
        setName(profData.name);
        setDateOfBirth(profData.profile.date_of_birth || '');
        if (profData.has_real_email && profData.email) {
          setEmail(profData.email);
        } else {
          setEmail('');
        }
        setAddresses(addrData);
      } catch (err: unknown) {
        toastError('Gagal memuat profil', formatFriendlyErrorMessage(err, 'Terjadi kesalahan sistem'));
      } finally {
        setLoading(false);
      }
    }

    loadData();
  }, [authLoading, isAuthenticated, router, toastError]);

  // Handle Save Profile Info
  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingProfile(true);

    try {
      const payload: { name: string; date_of_birth?: string; email?: string } = {
        name,
        date_of_birth: dateOfBirth || undefined,
      };

      if (email.trim() && (!profile?.has_real_email || email.trim() !== profile.email)) {
        payload.email = email.trim();
      }

      const updated = await updateProfile(payload);
      setProfile(updated);
      if (updated.has_real_email && updated.email) {
        setEmail(updated.email);
      }
      setShowAddEmail(false);
      success('Profil diperbarui', 'Data diri Anda berhasil disimpan.');
    } catch (err: unknown) {
      toastError('Gagal menyimpan', formatFriendlyErrorMessage(err, 'Gagal memperbarui profil'));
    } finally {
      setSavingProfile(false);
    }
  };

  const resetAddressForm = () => {
    setAddrLabel('Rumah');
    setAddrText('');
    setAddrLat('');
    setAddrLng('');
    setAddrCity('');
    setAddrProvince('');
    setAddrPostalCode('');
    setAddrKelurahan('');
    setAddrKecamatan('');
    setAddrNote('');
    setAddrIsDefault(false);
    setGpsDetected(false);
    setGpsNotice(null);
    setIsDetectingLocation(false);
  };

  // Handle GPS Geolocation Button Click
  const handleUseCurrentLocation = () => {
    if (typeof window === 'undefined' || !('geolocation' in navigator)) {
      const msg = 'Browser Anda tidak mendukung fitur deteksi lokasi GPS. Silakan isi alamat secara manual.';
      setGpsNotice(msg);
      toastError('GPS tidak didukung', msg);
      return;
    }

    setIsDetectingLocation(true);
    setGpsNotice(null);

    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const { latitude, longitude } = position.coords;
        const latStr = latitude.toFixed(6);
        const lngStr = longitude.toFixed(6);

        setAddrLat(latStr);
        setAddrLng(lngStr);

        try {
          const result = await reverseGeocode({ latitude, longitude });
          if (result.formatted_address) {
            setAddrText(result.formatted_address);
          }
          if (result.city) setAddrCity(result.city);
          if (result.province) setAddrProvince(result.province);
          if (result.postal_code) setAddrPostalCode(result.postal_code);
          if (result.kelurahan) setAddrKelurahan(result.kelurahan);
          if (result.kecamatan) setAddrKecamatan(result.kecamatan);

          setGpsDetected(true);
          setGpsNotice(
            `Lokasi GPS terdeteksi (${latStr}, ${lngStr}). Silakan periksa atau lengkapi nomor rumah, RT/RW, dan patokan sebelum menekan simpan.`
          );
          success(
            'Lokasi terdeteksi',
            'Koordinat dan perkiraan alamat berhasil dimuat dari GPS. Anda dapat menyesuaikannya sebelum menyimpan.'
          );
        } catch {
          setGpsDetected(true);
          setGpsNotice(
            `Titik koordinat GPS berhasil disimpan (${latStr}, ${lngStr}), namun rincian nama jalan otomatis tidak dapat dimuat. Silakan isi nama jalan secara manual.`
          );
        } finally {
          setIsDetectingLocation(false);
        }
      },
      (error) => {
        setIsDetectingLocation(false);
        let msg = 'Gagal mendeteksi lokasi saat ini. Silakan isi alamat secara manual.';
        if (error.code === error.PERMISSION_DENIED) {
          msg = 'Izin akses lokasi ditolak oleh browser. Anda dapat mengizinkannya di pengaturan browser atau mengetik alamat manual.';
        } else if (error.code === error.POSITION_UNAVAILABLE) {
          msg = 'Informasi lokasi GPS tidak tersedia saat ini. Silakan isi alamat secara manual.';
        } else if (error.code === error.TIMEOUT) {
          msg = 'Waktu permintaan lokasi GPS habis. Silakan coba lagi atau isi alamat secara manual.';
        }
        setGpsNotice(msg);
        toastError('Lokasi gagal diambil', msg);
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
      }
    );
  };

  // Handle Create Address
  const handleCreateAddress = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingAddr(true);

    try {
      await createAddress({
        label: addrLabel,
        address_text: addrText,
        latitude: addrLat ? parseFloat(addrLat) : null,
        longitude: addrLng ? parseFloat(addrLng) : null,
        city: addrCity || undefined,
        province: addrProvince || undefined,
        postal_code: addrPostalCode || undefined,
        kelurahan: addrKelurahan || undefined,
        kecamatan: addrKecamatan || undefined,
        address_note: addrNote || undefined,
        is_default: addrIsDefault,
      });

      const updated = await getAddresses();
      setAddresses(updated);
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('address-changed'));
        localStorage.setItem('laundryku_active_address_updated', Date.now().toString());
      }
      setShowAddressModal(false);
      resetAddressForm();
      success('Alamat ditambahkan', 'Alamat baru siap digunakan untuk pesanan.');
    } catch (err: unknown) {
      toastError('Gagal menambahkan alamat', formatFriendlyErrorMessage(err, 'Terjadi kesalahan sistem'));
    } finally {
      setSavingAddr(false);
    }
  };

  // Handle Confirm Delete Address
  const handleConfirmDeleteAddress = async () => {
    if (!addressToDelete) return;
    setDeletingAddr(true);

    try {
      await deleteAddress(addressToDelete);
      const updated = await getAddresses();
      setAddresses(updated);
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('address-changed'));
        localStorage.setItem('laundryku_active_address_updated', Date.now().toString());
      }
      setAddressToDelete(null);
      success('Alamat dihapus', 'Alamat telah berhasil dihapus dari daftar.');
    } catch (err: unknown) {
      toastError('Gagal menghapus alamat', formatFriendlyErrorMessage(err, 'Terjadi kesalahan sistem'));
    } finally {
      setDeletingAddr(false);
    }
  };

  // Handle Set Default Address
  const handleSetDefault = async (id: string) => {
    try {
      await setDefaultAddress(id);
      const updated = await getAddresses();
      setAddresses(updated);
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('address-changed'));
        localStorage.setItem('laundryku_active_address_updated', Date.now().toString());
      }
      success('Alamat utama diubah', 'Alamat telah diatur sebagai default penjemputan.');
    } catch (err: unknown) {
      toastError('Gagal mengubah alamat utama', formatFriendlyErrorMessage(err, 'Terjadi kesalahan sistem'));
    }
  };

  if (loading) {
    return (
      <PublicLayout>
        {/* Skeleton Hero Header */}
        <div className="bg-navy-900 text-white relative overflow-hidden pt-8 pb-14 px-4 sm:px-6 lg:px-8 select-none">
          <div className="max-w-6xl mx-auto space-y-2">
            <Skeleton className="h-8 w-64 bg-navy-800" />
            <Skeleton className="h-4 w-96 max-w-full bg-navy-800" />
          </div>
        </div>
        <div className="bg-slate-50 min-h-[calc(100vh-14rem)] pb-12 pt-2">
          <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 sm:-mt-7 relative z-20">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
              <Skeleton className="h-96 w-full rounded-3xl bg-white" />
              <div className="lg:col-span-2 space-y-4">
                <Skeleton className="h-36 w-full rounded-3xl bg-white" />
                <Skeleton className="h-36 w-full rounded-3xl bg-white" />
              </div>
            </div>
          </div>
        </div>
      </PublicLayout>
    );
  }

  return (
    <PublicLayout>
      {/* 1. Hero Header Section (Navy-900 with Wave & Soap Bubbles SVG) */}
      <div className="bg-navy-900 text-white relative overflow-hidden pt-8 pb-14 px-4 sm:px-6 lg:px-8 select-none">
        {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
        <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
          <div className="absolute top-4 left-10 w-24 h-24 rounded-full border border-sky-300/30 bg-sky-400/10 backdrop-blur-[1px] shadow-inner animate-gentle-float">
            <div className="w-2.5 h-2.5 rounded-full bg-white/60 ml-3.5 mt-3.5" />
          </div>
          <div className="absolute top-6 right-16 w-32 h-32 rounded-full border border-sky-300/20 bg-sky-300/10 animate-gentle-float-slow">
            <div className="w-3.5 h-3.5 rounded-full bg-white/50 ml-4 mt-4" />
          </div>
          <div className="absolute bottom-8 right-1/3 w-16 h-16 rounded-full border border-sky-200/20 bg-sky-400/5">
            <div className="w-2 h-2 rounded-full bg-white/40 ml-2 mt-2" />
          </div>
        </div>

        {/* Main Hero Content */}
        <div className="max-w-6xl mx-auto relative z-10 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div className="space-y-1.5 max-w-xl">
              <div className="flex items-center gap-2.5 flex-wrap">
                <div className="w-10 h-10 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-xs flex items-center justify-center text-sky-300 shadow-2xs">
                  <User className="w-5 h-5" strokeWidth={1.75} />
                </div>
                <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                  Profil &amp; alamat akun
                </h1>
              </div>
              <p className="text-xs text-navy-200 leading-relaxed">
                Kelola informasi data diri, kontak login, dan alamat penjemputan cucian Anda.
              </p>
            </div>

            {/* Upgrade Partner Link */}
            <div className="shrink-0 self-start sm:self-end">
              <Link
                href="/upgrade"
                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-navy-800/90 hover:bg-navy-800 text-sky-200 border border-navy-700/90 text-xs font-semibold transition-all shadow-2xs active:scale-95"
              >
                <ShieldCheck className="w-4 h-4 text-teal-400" strokeWidth={2} />
                <span>Upgrade mitra toko / kurir</span>
              </Link>
            </div>
          </div>
        </div>

        {/* Wave SVG Divider at Bottom of Hero */}
        <div className="absolute -bottom-1 left-0 right-0 pointer-events-none overflow-hidden leading-none z-10" aria-hidden="true">
          <svg
            viewBox="0 0 1440 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className="w-full h-8 sm:h-12 text-slate-50 preserve-3d"
          >
            <path
              d="M0 20C240 45 480 5 720 25C960 45 1200 10 1440 20V48H0V20Z"
              fill="currentColor"
            />
          </svg>
        </div>
      </div>

      {/* 2. Main Body Content (Slate-50) */}
      <div className="bg-slate-50 min-h-[calc(100vh-14rem)] pb-14 pt-2">
        <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 sm:-mt-7 relative z-20">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 items-start">
            
            {/* Column 1: Profile Details & Photo Card */}
            <div className="space-y-6">
              <div className="rounded-3xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-6">
                
                {/* Section Header */}
                <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
                  <div className="w-10 h-10 rounded-2xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100 shrink-0">
                    <User className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
                  </div>
                  <div>
                    <h2 className="font-heading font-bold text-base text-navy-900 leading-tight">
                      Identitas diri
                    </h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                      Foto profil dan data pribadi Anda
                    </p>
                  </div>
                </div>

                {/* Avatar with photo change & crop */}
                <div className="flex flex-col items-center justify-center py-1">
                  <AvatarUploader
                    name={profile?.name || ''}
                    currentAvatarUrl={profile?.avatar_url || profile?.profile.avatar_url}
                    size="xl"
                    onUploadSuccess={(newUrl) => {
                      setProfile((prev) =>
                        prev
                          ? {
                              ...prev,
                              avatar_url: newUrl,
                              profile: {
                                ...prev.profile,
                                has_photo: true,
                                avatar_url: newUrl,
                              },
                            }
                          : null
                      );
                    }}
                    onDeleteSuccess={() => {
                      setProfile((prev) =>
                        prev
                          ? {
                              ...prev,
                              avatar_url: null,
                              profile: {
                                ...prev.profile,
                                has_photo: false,
                                avatar_url: null,
                              },
                            }
                          : null
                      );
                    }}
                  />
                </div>

                {/* Profile Form */}
                <form onSubmit={handleSaveProfile} className="space-y-4">
                  <div>
                    <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                      Nama lengkap
                    </label>
                    <Input
                      required
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      placeholder="Nama lengkap Anda"
                      className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                      Tanggal lahir
                    </label>
                    <div className="relative">
                      <Input
                        type="date"
                        value={dateOfBirth}
                        onChange={(e) => setDateOfBirth(e.target.value)}
                        className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                      />
                    </div>
                  </div>

                  <div className="space-y-2.5 pt-2 border-t border-slate-100">
                    <label className="block text-xs font-bold text-slate-800">
                      Kontak akun
                    </label>

                    {/* Nomor WhatsApp */}
                    {profile?.phone && (
                      <div className="flex items-center justify-between p-3 rounded-2xl bg-slate-50/80 border border-slate-200/80 gap-2">
                        <div className="flex items-center gap-2.5 min-w-0">
                          <div className="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-100">
                            <Phone className="w-4 h-4 text-emerald-600" strokeWidth={1.75} />
                          </div>
                          <div className="min-w-0">
                            <span className="font-mono text-xs text-slate-800 font-medium block truncate">
                              {formatPhone(profile.phone)}
                            </span>
                          </div>
                        </div>
                        <Badge variant="teal" size="sm" className="shrink-0 text-[10px] font-bold">
                          WhatsApp aktif
                        </Badge>
                      </div>
                    )}

                    {/* Email Akun */}
                    {profile?.has_real_email && profile?.email ? (
                      <div className="flex items-center justify-between p-3 rounded-2xl bg-slate-50/80 border border-slate-200/80 gap-2">
                        <div className="flex items-center gap-2.5 min-w-0">
                          <div className="w-8 h-8 rounded-xl bg-sky-50 text-navy-700 flex items-center justify-center shrink-0 border border-sky-100">
                            <Mail className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                          </div>
                          <span className="font-mono text-xs text-slate-800 font-medium truncate block">
                            {profile.email}
                          </span>
                        </div>
                        <Badge variant="secondary" size="sm" className="shrink-0 text-[10px] font-bold">
                          Terverifikasi
                        </Badge>
                      </div>
                    ) : showAddEmail ? (
                      <div className="p-3.5 rounded-2xl border border-sky-200 bg-sky-50/60 space-y-2.5">
                        <div className="flex items-center justify-between">
                          <label className="block text-xs font-semibold text-navy-900">
                            Tambahkan alamat email
                          </label>
                          <button
                            type="button"
                            onClick={() => {
                              setShowAddEmail(false);
                              setEmail('');
                            }}
                            className="text-[11px] font-semibold text-slate-500 hover:text-slate-700 cursor-pointer"
                          >
                            Batal
                          </button>
                        </div>
                        <Input
                          type="email"
                          placeholder="nama@email.com"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          className="rounded-xl border-slate-200 focus:border-navy-700 text-xs bg-white"
                        />
                        <p className="text-[11px] text-sky-800 leading-snug">
                          Klik &quot;Simpan perubahan&quot; di bawah untuk menerapkan email baru.
                        </p>
                      </div>
                    ) : (
                      <div className="flex items-center justify-between p-3 rounded-2xl bg-slate-50/80 border border-dashed border-slate-300 gap-2">
                        <div className="flex items-center gap-2.5 text-xs text-slate-500 min-w-0">
                          <Mail className="w-4 h-4 text-slate-400 shrink-0" strokeWidth={1.75} />
                          <span className="truncate">Email belum ditambahkan</span>
                        </div>
                        <button
                          type="button"
                          onClick={() => setShowAddEmail(true)}
                          className="text-xs font-bold text-navy-700 hover:text-navy-900 hover:underline cursor-pointer shrink-0"
                        >
                          + Tambah email
                        </button>
                      </div>
                    )}
                  </div>

                  <Button
                    type="submit"
                    variant="primary"
                    className="w-full bg-navy-700 hover:bg-navy-800 active:scale-[0.98] text-white rounded-xl font-bold py-2.5 shadow-2xs transition-all cursor-pointer mt-2"
                    isLoading={savingProfile}
                  >
                    Simpan perubahan
                  </Button>
                </form>
              </div>
            </div>

            {/* Column 2 & 3: Address Management Section */}
            <div className="lg:col-span-2 space-y-6">
              <div className="rounded-3xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-6">
                
                {/* Section Header */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-2xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100 shrink-0">
                      <MapPin className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
                    </div>
                    <div>
                      <h2 className="font-heading font-bold text-base text-navy-900 leading-tight">
                        Daftar alamat penjemputan
                      </h2>
                      <p className="text-xs text-slate-500 mt-0.5">
                        Tentukan alamat utama agar kurir langsung menuju lokasi Anda
                      </p>
                    </div>
                  </div>

                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => {
                      resetAddressForm();
                      setShowAddressModal(true);
                    }}
                    className="bg-navy-700 hover:bg-navy-800 active:scale-[0.98] text-white rounded-xl text-xs sm:text-sm font-bold px-3.5 py-2 shadow-2xs shrink-0 self-start sm:self-auto cursor-pointer"
                  >
                    <Plus className="w-4 h-4 mr-1.5" strokeWidth={2} />
                    Tambah alamat
                  </Button>
                </div>

                {/* Address List Body */}
                {addresses.length === 0 ? (
                  <div className="py-8">
                    <EmptyState
                      title="Belum ada alamat tersimpan"
                      description="Tambahkan alamat rumah, kos, atau kantor untuk memulai pesanan laundry antar-jemput."
                      actionText="+ Tambah alamat sekarang"
                      onAction={() => {
                        resetAddressForm();
                        setShowAddressModal(true);
                      }}
                    />
                  </div>
                ) : (
                  <div className="space-y-3.5">
                    {addresses.map((addr) => (
                      <div
                        key={addr.id}
                        className={cn(
                          'p-4 sm:p-5 rounded-2xl border transition-all',
                          addr.is_default
                            ? 'border-navy-300 bg-navy-50/30 shadow-2xs ring-1 ring-navy-700/10'
                            : 'border-slate-200/90 bg-white hover:border-slate-300 hover:shadow-2xs'
                        )}
                      >
                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3.5">
                          <div className="space-y-1.5 flex-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                              <span className="font-heading font-bold text-sm sm:text-base text-navy-900">
                                {addr.label}
                              </span>
                              {addr.is_default && (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-bold bg-navy-700 text-white shadow-2xs">
                                  Alamat utama
                                </span>
                              )}
                            </div>
                            <p className="text-xs sm:text-sm text-slate-600 leading-relaxed break-words">
                              {addr.address_text}
                            </p>
                            {addr.latitude !== null && addr.longitude !== null && (
                              <p className="text-[11px] font-mono text-slate-400 tabular-nums pt-0.5">
                                Koordinat: {addr.latitude}, {addr.longitude}
                              </p>
                            )}
                          </div>

                          <div className="flex items-center gap-2 pt-2 sm:pt-0 shrink-0 self-end sm:self-start">
                            {!addr.is_default && (
                              <button
                                type="button"
                                onClick={() => handleSetDefault(addr.id)}
                                className="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-all shadow-2xs cursor-pointer active:scale-95"
                              >
                                Jadikan utama
                              </button>
                            )}
                            <button
                              type="button"
                              onClick={() => setAddressToDelete(addr.id)}
                              className="w-8 h-8 rounded-xl border border-transparent hover:border-rose-100 text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors cursor-pointer active:scale-95"
                              aria-label="Hapus alamat"
                              title="Hapus alamat ini"
                            >
                              <Trash2 className="w-4 h-4" strokeWidth={1.75} />
                            </button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        </main>
      </div>

      {/* Modal: Tambah Alamat Baru */}
      <Modal
        isOpen={showAddressModal}
        maxWidth="lg"
        onClose={() => {
          setShowAddressModal(false);
          resetAddressForm();
        }}
        title="Tambah alamat baru"
        description="Masukkan rincian alamat lengkap atau gunakan GPS lokasi Anda saat ini."
        bodyClassName="p-0 overflow-hidden flex flex-col"
      >
        <form onSubmit={handleCreateAddress} className="flex flex-col min-h-0 flex-1">
          {/* Scrollable Form Content */}
          <div className="overflow-y-auto px-4 sm:px-6 py-4 space-y-4 max-h-[62vh] sm:max-h-[68vh] [scrollbar-width:thin] [scrollbar-color:#cbd5e1_transparent]">
            
            {/* Opsi Cepat: Gunakan Lokasi GPS */}
            <div className="bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="flex items-center gap-2.5 min-w-0">
                  <div className="w-9 h-9 rounded-xl bg-navy-50 text-navy-800 flex items-center justify-center shrink-0 border border-navy-100">
                    <MapPin className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                  </div>
                  <div className="min-w-0">
                    <p className="text-xs font-bold text-navy-900 leading-tight">Gunakan lokasi GPS</p>
                    <p className="text-[11px] text-slate-500 truncate">Isi titik koordinat &amp; alamat otomatis</p>
                  </div>
                </div>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  className="w-full sm:w-auto shrink-0 whitespace-nowrap text-xs font-semibold h-8.5 px-3.5 rounded-xl border-slate-200 bg-white hover:bg-slate-50 cursor-pointer shadow-2xs"
                  onClick={handleUseCurrentLocation}
                  isLoading={isDetectingLocation}
                >
                  <Navigation className="w-3.5 h-3.5 mr-1.5 text-navy-700 shrink-0" strokeWidth={1.75} />
                  Ambil lokasi GPS
                </Button>
              </div>

              {gpsNotice && (
                <p
                  className={cn(
                    'text-xs flex items-center gap-1.5 pt-2.5 mt-2.5 border-t border-slate-200/80 leading-snug',
                    gpsDetected ? 'text-emerald-700 font-medium' : 'text-amber-700'
                  )}
                >
                  {gpsDetected ? (
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" strokeWidth={2} />
                  ) : (
                    <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" strokeWidth={2} />
                  )}
                  <span>{gpsNotice}</span>
                </p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Label alamat
              </label>
              <Input
                required
                value={addrLabel}
                onChange={(e) => setAddrLabel(e.target.value)}
                placeholder="Contoh: Rumah, Kantor, Kos Melati No. 8"
                className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Alamat lengkap
              </label>
              <Textarea
                required
                rows={3}
                style={{ resize: 'none' }}
                className="resize-none rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                value={addrText}
                onChange={(e) => setAddrText(e.target.value)}
                placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, patokan dekat..."
              />
            </div>

            {/* Row 1: Kota/Kabupaten & Provinsi */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Kota / Kabupaten
                </label>
                <Input
                  required
                  value={addrCity}
                  onChange={(e) => setAddrCity(e.target.value)}
                  placeholder="Contoh: Bandung"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Provinsi
                </label>
                <Input
                  required
                  value={addrProvince}
                  onChange={(e) => setAddrProvince(e.target.value)}
                  placeholder="Contoh: Jawa Barat"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
            </div>

            {/* Row 2: Kecamatan & Kelurahan */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Kecamatan <span className="text-slate-400 font-normal">(opsional)</span>
                </label>
                <Input
                  value={addrKecamatan}
                  onChange={(e) => setAddrKecamatan(e.target.value)}
                  placeholder="Contoh: Sumur Bandung"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Kelurahan / Desa <span className="text-slate-400 font-normal">(opsional)</span>
                </label>
                <Input
                  value={addrKelurahan}
                  onChange={(e) => setAddrKelurahan(e.target.value)}
                  placeholder="Contoh: Babakan Ciamis"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
            </div>

            {/* Row 3: Kode Pos & Patokan Khusus */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Kode pos <span className="text-slate-400 font-normal">(opsional)</span>
                </label>
                <Input
                  value={addrPostalCode}
                  onChange={(e) => setAddrPostalCode(e.target.value)}
                  placeholder="Contoh: 40115"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Patokan khusus <span className="text-slate-400 font-normal">(opsional)</span>
                </label>
                <Input
                  value={addrNote}
                  onChange={(e) => setAddrNote(e.target.value)}
                  placeholder="Contoh: Pagar abu-abu, depan warung"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
            </div>

            {/* Row 4: Titik Koordinat */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Latitude <span className="text-slate-400 font-normal">(opsional)</span>
                </label>
                <Input
                  type="number"
                  step="any"
                  value={addrLat}
                  onChange={(e) => setAddrLat(e.target.value)}
                  placeholder="-6.917500"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Longitude <span className="text-slate-400 font-normal">(opsional)</span>
                </label>
                <Input
                  type="number"
                  step="any"
                  value={addrLng}
                  onChange={(e) => setAddrLng(e.target.value)}
                  placeholder="107.619100"
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-sm"
                />
              </div>
            </div>

            <div className="pt-1">
              <Checkbox
                label="Jadikan sebagai alamat utama"
                description="Alamat ini akan otomatis terpilih saat melakukan pesanan baru."
                checked={addrIsDefault}
                onChange={(e) => setAddrIsDefault(e.target.checked)}
              />
            </div>
          </div>

          {/* Sticky Footer */}
          <div className="sticky bottom-0 bg-white border-t border-slate-100 px-4 sm:px-6 py-3.5 flex items-center justify-end gap-2.5 shrink-0 z-10">
            <Button
              type="button"
              variant="secondary"
              onClick={() => {
                setShowAddressModal(false);
                resetAddressForm();
              }}
              className="rounded-xl border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-semibold px-4 py-2 cursor-pointer"
            >
              Batal
            </Button>
            <Button
              type="submit"
              variant="primary"
              isLoading={savingAddr}
              className="rounded-xl bg-navy-700 hover:bg-navy-800 active:scale-[0.98] text-white text-xs sm:text-sm font-bold px-5 py-2 shadow-2xs cursor-pointer"
            >
              Simpan alamat
            </Button>
          </div>
        </form>
      </Modal>

      {/* Confirm Dialog: Hapus Alamat */}
      <ConfirmDialog
        isOpen={Boolean(addressToDelete)}
        onClose={() => setAddressToDelete(null)}
        onConfirm={handleConfirmDeleteAddress}
        title="Hapus alamat?"
        description="Alamat yang dihapus tidak dapat dipulihkan kembali. Lanjutkan tindakan ini?"
        confirmText="Ya, hapus"
        cancelText="Batal"
        variant="danger"
        isLoading={deletingAddr}
      />
    </PublicLayout>
  );
}
