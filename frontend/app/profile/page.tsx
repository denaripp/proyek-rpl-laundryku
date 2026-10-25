'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Camera,
  Plus,
  Trash2,
  ShieldCheck,
  Mail,
  Phone,
  MapPin,
  Navigation,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react';
import { formatPhone } from '@/lib/auth';
import { cn } from '@/lib/format';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/Textarea';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
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
        toastError('Gagal Memuat Profil', err instanceof Error ? err.message : 'Terjadi kesalahan');
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
      success('Profil Diperbarui', 'Data diri Anda berhasil disimpan.');
    } catch (err: unknown) {
      toastError('Gagal Menyimpan', err instanceof Error ? err.message : 'Gagal memperbarui profil');
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
      toastError('Geolocation Tidak Didukung', msg);
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
            `Lokasi GPS terdeteksi (${latStr}, ${lngStr}). Silakan periksa atau lengkapi nomor rumah, RT/RW, dan patokan sebelum menekan Simpan.`
          );
          success(
            'Lokasi Berhasil Dideteksi',
            'Koordinat dan alamat berhasil dimuat dari GPS. Anda dapat menyesuaikannya sebelum menyimpan.'
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
        toastError('Lokasi Gagal Diambil', msg);
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
      setShowAddressModal(false);
      resetAddressForm();
      success('Alamat Ditambahkan', 'Alamat baru siap digunakan untuk pesanan.');
    } catch (err: unknown) {
      toastError('Gagal Menambahkan Alamat', err instanceof Error ? err.message : 'Terjadi kesalahan');
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
      setAddresses(await getAddresses());
      setAddressToDelete(null);
      success('Alamat Dihapus', 'Alamat telah berhasil dihapus dari daftar.');
    } catch (err: unknown) {
      toastError('Gagal Menghapus Alamat', err instanceof Error ? err.message : 'Terjadi kesalahan');
    } finally {
      setDeletingAddr(false);
    }
  };

  // Handle Set Default Address
  const handleSetDefault = async (id: string) => {
    try {
      await setDefaultAddress(id);
      setAddresses(await getAddresses());
      success('Alamat Utama Diubah', 'Alamat telah diatur sebagai default penjemputan.');
    } catch (err: unknown) {
      toastError('Gagal Mengubah Alamat Utama', err instanceof Error ? err.message : 'Terjadi kesalahan');
    }
  };

  if (loading) {
    return (
      <PublicLayout>
        <div className="max-w-6xl mx-auto px-4 sm:px-6 py-10 space-y-8">
          <div className="space-y-2">
            <Skeleton className="h-8 w-64" />
            <Skeleton className="h-4 w-96" />
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <Skeleton className="h-96 w-full" />
            <div className="lg:col-span-2 space-y-4">
              <Skeleton className="h-32 w-full" />
              <Skeleton className="h-32 w-full" />
            </div>
          </div>
        </div>
      </PublicLayout>
    );
  }

  return (
    <PublicLayout>
      <div className="bg-sky-50/60 border-b border-slate-200/80 py-8">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900 tracking-tight">
              Profil &amp; Alamat Pelanggan
            </h1>
            <p className="text-sm text-slate-500 mt-1">
              Kelola informasi akun dan alamat pengantaran/penjemputan cucian Anda.
            </p>
          </div>

          <Link href="/upgrade">
            <Button variant="secondary" size="sm" className="shadow-sm">
              <ShieldCheck className="w-4 h-4 mr-2 text-teal-600" />
              Upgrade Mitra Toko / Kurir
            </Button>
          </Link>
        </div>
      </div>

      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Column 1: Profile Details & Photo */}
          <div className="space-y-6">
            <Card variant="default">
              <CardHeader>
                <CardTitle>Identitas Diri</CardTitle>
                <CardDescription>Foto profil dan data pribadi Anda</CardDescription>
              </CardHeader>

              <CardContent className="space-y-6">
                {/* Avatar with photo change & crop */}
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

                <form onSubmit={handleSaveProfile} className="space-y-4">
                  <Input
                    label="Nama Lengkap"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                  />

                  <Input
                    label="Tanggal Lahir"
                    type="date"
                    value={dateOfBirth}
                    onChange={(e) => setDateOfBirth(e.target.value)}
                  />

                  <div className="space-y-3 pt-1">
                    <label className="block text-xs font-semibold text-slate-500">Kontak Akun</label>
                    
                    {/* Nomor WhatsApp */}
                    {profile?.phone && (
                      <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div className="flex items-center gap-2.5">
                          <Phone className="w-4 h-4 text-teal-600" />
                          <span className="font-mono text-xs text-slate-800 font-medium">
                            {formatPhone(profile.phone)}
                          </span>
                        </div>
                        <Badge variant="teal" size="sm">WhatsApp Terdaftar</Badge>
                      </div>
                    )}

                    {/* Email Akun */}
                    {profile?.has_real_email && profile?.email ? (
                      <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div className="flex items-center gap-2.5 min-w-0">
                          <Mail className="w-4 h-4 text-teal-600 shrink-0" />
                          <span className="font-mono text-xs text-slate-800 font-medium truncate">
                            {profile.email}
                          </span>
                        </div>
                        <Badge variant="secondary" size="sm">Email Terverifikasi</Badge>
                      </div>
                    ) : showAddEmail ? (
                      <div className="p-3.5 rounded-xl border border-teal-200 bg-teal-50/50 space-y-2">
                        <div className="flex items-center justify-between">
                          <label className="block text-xs font-medium text-teal-900">Tambahkan Alamat Email</label>
                          <button
                            type="button"
                            onClick={() => {
                              setShowAddEmail(false);
                              setEmail('');
                            }}
                            className="text-[11px] font-semibold text-slate-500 hover:text-slate-700"
                          >
                            Batal
                          </button>
                        </div>
                        <Input
                          type="email"
                          placeholder="nama@email.com"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                        />
                        <p className="text-[11px] text-teal-700">
                          Klik &quot;Simpan Perubahan&quot; di bawah setelah mengisi email.
                        </p>
                      </div>
                    ) : (
                      <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-dashed border-slate-300">
                        <div className="flex items-center gap-2.5 text-xs text-slate-500">
                          <Mail className="w-4 h-4 text-slate-400" />
                          <span>Email belum ditambahkan</span>
                        </div>
                        <button
                          type="button"
                          onClick={() => setShowAddEmail(true)}
                          className="text-xs font-semibold text-teal-600 hover:text-teal-700 hover:underline cursor-pointer"
                        >
                          + Tambahkan Email
                        </button>
                      </div>
                    )}
                  </div>

                  <Button
                    type="submit"
                    variant="primary"
                    className="w-full"
                    isLoading={savingProfile}
                  >
                    Simpan Perubahan
                  </Button>
                </form>
              </CardContent>
            </Card>
          </div>

          {/* Column 2 & 3: Address Management */}
          <div className="lg:col-span-2 space-y-6">
            <Card variant="default">
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                  <CardTitle>Daftar Alamat Penjemputan</CardTitle>
                  <CardDescription>
                    Pilih alamat utama agar kurir LaundryKu langsung menuju lokasi Anda
                  </CardDescription>
                </div>
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => {
                    resetAddressForm();
                    setShowAddressModal(true);
                  }}
                >
                  <Plus className="w-4 h-4 mr-1.5" />
                  Tambah Alamat
                </Button>
              </div>

              <CardContent className="pt-6">
                {addresses.length === 0 ? (
                  <EmptyState
                    title="Belum Ada Alamat Tersimpan"
                    description="Tambahkan alamat rumah, kos, atau kantor untuk memulai pesanan laundry antar-jemput."
                    actionText="+ Tambah Alamat Sekarang"
                    onAction={() => {
                      resetAddressForm();
                      setShowAddressModal(true);
                    }}
                  />
                ) : (
                  <div className="space-y-3.5">
                    {addresses.map((addr) => (
                      <div
                        key={addr.id}
                        className={`p-4 sm:p-5 rounded-2xl border transition-all ${
                          addr.is_default
                            ? 'border-teal-400/80 bg-teal-50/40 shadow-sm'
                            : 'border-slate-200 bg-white hover:border-slate-300'
                        }`}
                      >
                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                          <div className="space-y-1.5 flex-1">
                            <div className="flex items-center gap-2">
                              <span className="font-heading font-bold text-sm sm:text-base text-navy-900">
                                {addr.label}
                              </span>
                              {addr.is_default && (
                                <Badge variant="teal" size="sm">
                                  Alamat Utama
                                </Badge>
                              )}
                            </div>
                            <p className="text-sm text-slate-600 leading-relaxed">
                              {addr.address_text}
                            </p>
                            {addr.latitude !== null && addr.longitude !== null && (
                              <p className="text-xs font-mono text-slate-400 tabular-nums">
                                Koordinat: {addr.latitude}, {addr.longitude}
                              </p>
                            )}
                          </div>

                          <div className="flex items-center gap-2 pt-2 sm:pt-0 shrink-0">
                            {!addr.is_default && (
                              <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => handleSetDefault(addr.id)}
                              >
                                Jadikan Utama
                              </Button>
                            )}
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => setAddressToDelete(addr.id)}
                              className="text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                              aria-label="Hapus alamat"
                            >
                              <Trash2 className="w-4 h-4" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </main>

      {/* Modal: Tambah Alamat Baru */}
      <Modal
        isOpen={showAddressModal}
        maxWidth="lg"
        onClose={() => {
          setShowAddressModal(false);
          resetAddressForm();
        }}
        title="Tambah Alamat Baru"
        description="Masukkan rincian alamat lengkap atau gunakan GPS lokasi Anda saat ini."
        bodyClassName="p-0 overflow-hidden flex flex-col"
      >
        <form onSubmit={handleCreateAddress} className="flex flex-col min-h-0 flex-1">
          {/* Scrollable Form Content */}
          <div className="overflow-y-auto px-1 sm:px-1.5 py-4 space-y-3.5 max-h-[62vh] sm:max-h-[68vh] pr-2 [scrollbar-width:thin] [scrollbar-color:#cbd5e1_transparent]">
            {/* Opsi Cepat: Gunakan Lokasi GPS */}
            <div className="bg-slate-50/80 rounded-xl p-3 border border-slate-100">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div className="flex items-center gap-2.5 min-w-0">
                  <div className="w-8 h-8 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center shrink-0">
                    <MapPin className="w-4 h-4 text-teal-600" />
                  </div>
                  <div className="min-w-0">
                    <p className="text-xs font-semibold text-slate-800 leading-tight">Gunakan Lokasi GPS</p>
                    <p className="text-[11px] text-slate-500 truncate">Isi titik koordinat &amp; alamat otomatis</p>
                  </div>
                </div>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  className="w-full sm:w-auto shrink-0 whitespace-nowrap text-xs font-medium h-8 px-3"
                  onClick={handleUseCurrentLocation}
                  isLoading={isDetectingLocation}
                >
                  <Navigation className="w-3.5 h-3.5 mr-1.5 text-teal-600 shrink-0" />
                  Ambil Lokasi GPS
                </Button>
              </div>

              {gpsNotice && (
                <p
                  className={cn(
                    "text-xs flex items-center gap-1.5 pt-2 mt-2 border-t border-slate-200/50 leading-snug",
                    gpsDetected ? "text-emerald-700 font-medium" : "text-amber-700"
                  )}
                >
                  {gpsDetected ? (
                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                  ) : (
                    <AlertCircle className="w-3.5 h-3.5 text-amber-600 shrink-0" />
                  )}
                  <span>{gpsNotice}</span>
                </p>
              )}
            </div>

            <Input
              label="Label Alamat"
              required
              value={addrLabel}
              onChange={(e) => setAddrLabel(e.target.value)}
              placeholder="Contoh: Rumah, Kantor, Kos Melati No. 8"
            />

            <Textarea
              label="Alamat Lengkap"
              required
              rows={3}
              style={{ resize: 'none' }}
              className="resize-none"
              value={addrText}
              onChange={(e) => setAddrText(e.target.value)}
              placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, patokan dekat..."
            />

            {/* Row 1: Kota/Kabupaten & Provinsi (2 kolom) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <Input
                label="Kota / Kabupaten"
                required
                value={addrCity}
                onChange={(e) => setAddrCity(e.target.value)}
                placeholder="Contoh: Bandung"
              />
              <Input
                label="Provinsi"
                required
                value={addrProvince}
                onChange={(e) => setAddrProvince(e.target.value)}
                placeholder="Contoh: Jawa Barat"
              />
            </div>

            {/* Row 2: Kecamatan & Kelurahan (2 kolom, bukan 3 kolom sempit) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <Input
                label="Kecamatan"
                optional
                value={addrKecamatan}
                onChange={(e) => setAddrKecamatan(e.target.value)}
                placeholder="Contoh: Sumur Bandung"
              />
              <Input
                label="Kelurahan / Desa"
                optional
                value={addrKelurahan}
                onChange={(e) => setAddrKelurahan(e.target.value)}
                placeholder="Contoh: Babakan Ciamis"
              />
            </div>

            {/* Row 3: Kode Pos & Patokan Khusus (2 kolom) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <Input
                label="Kode Pos"
                optional
                value={addrPostalCode}
                onChange={(e) => setAddrPostalCode(e.target.value)}
                placeholder="Contoh: 40115"
              />
              <Input
                label="Patokan Khusus"
                optional
                value={addrNote}
                onChange={(e) => setAddrNote(e.target.value)}
                placeholder="Contoh: Pagar abu-abu, depan warung"
              />
            </div>

            {/* Row 4: Titik Koordinat (2 kolom) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <Input
                label="Latitude (Koordinat)"
                optional
                type="number"
                step="any"
                value={addrLat}
                onChange={(e) => setAddrLat(e.target.value)}
                placeholder="-6.917500"
              />
              <Input
                label="Longitude (Koordinat)"
                optional
                type="number"
                step="any"
                value={addrLng}
                onChange={(e) => setAddrLng(e.target.value)}
                placeholder="107.619100"
              />
            </div>

            <Checkbox
              label="Jadikan sebagai alamat utama"
              description="Alamat ini akan otomatis terpilih saat melakukan pesanan baru."
              checked={addrIsDefault}
              onChange={(e) => setAddrIsDefault(e.target.checked)}
            />
          </div>

          {/* Sticky Footer */}
          <div className="sticky bottom-0 bg-white border-t border-slate-100 pt-3.5 pb-1 flex items-center justify-end gap-2.5 shrink-0 z-10">
            <Button
              type="button"
              variant="secondary"
              onClick={() => {
                setShowAddressModal(false);
                resetAddressForm();
              }}
            >
              Batal
            </Button>
            <Button
              type="submit"
              variant="primary"
              isLoading={savingAddr}
            >
              Simpan Alamat
            </Button>
          </div>
        </form>
      </Modal>

      {/* Confirm Dialog: Hapus Alamat */}
      <ConfirmDialog
        isOpen={Boolean(addressToDelete)}
        onClose={() => setAddressToDelete(null)}
        onConfirm={handleConfirmDeleteAddress}
        title="Hapus Alamat?"
        description="Alamat yang dihapus tidak dapat dipulihkan kembali. Lanjutkan tindakan ini?"
        confirmText="Ya, Hapus"
        cancelText="Batal"
        variant="danger"
        isLoading={deletingAddr}
      />
    </PublicLayout>
  );
}
