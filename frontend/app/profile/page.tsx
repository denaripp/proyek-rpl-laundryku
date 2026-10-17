'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Camera,
  Plus,
  Trash2,
  ShieldCheck,
} from 'lucide-react';
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
import { getStoredToken } from '@/lib/auth';
import {
  getProfile,
  updateProfile,
  getAddresses,
  createAddress,
  deleteAddress,
  setDefaultAddress,
  ProfileData,
  AddressItem,
} from '@/lib/profile';

export default function ProfilePage() {
  const router = useRouter();
  const { success, error: toastError } = useToast();
  const [loading, setLoading] = useState(true);
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [addresses, setAddresses] = useState<AddressItem[]>([]);

  // Edit Profile Form State
  const [name, setName] = useState('');
  const [dateOfBirth, setDateOfBirth] = useState('');
  const [savingProfile, setSavingProfile] = useState(false);

  // New Address Modal State
  const [showAddressModal, setShowAddressModal] = useState(false);
  const [addrLabel, setAddrLabel] = useState('Rumah');
  const [addrText, setAddrText] = useState('');
  const [addrLat, setAddrLat] = useState('');
  const [addrLng, setAddrLng] = useState('');
  const [addrIsDefault, setAddrIsDefault] = useState(false);
  const [savingAddr, setSavingAddr] = useState(false);

  // Delete Address Confirm Dialog State
  const [addressToDelete, setAddressToDelete] = useState<string | null>(null);
  const [deletingAddr, setDeletingAddr] = useState(false);

  useEffect(() => {
    const token = getStoredToken();
    if (!token) {
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
        setAddresses(addrData);
      } catch (err: unknown) {
        toastError('Gagal Memuat Profil', err instanceof Error ? err.message : 'Terjadi kesalahan');
      } finally {
        setLoading(false);
      }
    }

    loadData();
  }, [router, toastError]);

  // Handle Save Profile Info
  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingProfile(true);

    try {
      const updated = await updateProfile({
        name,
        date_of_birth: dateOfBirth || undefined,
      });
      setProfile(updated);
      success('Profil Diperbarui', 'Data diri Anda berhasil disimpan.');
    } catch (err: unknown) {
      toastError('Gagal Menyimpan', err instanceof Error ? err.message : 'Gagal memperbarui profil');
    } finally {
      setSavingProfile(false);
    }
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
        is_default: addrIsDefault,
      });

      const updated = await getAddresses();
      setAddresses(updated);
      setShowAddressModal(false);
      setAddrText('');
      setAddrLat('');
      setAddrLng('');
      setAddrIsDefault(false);
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

                  <div className="space-y-1.5">
                    <label className="block text-xs font-semibold text-slate-500">Kontak Terdaftar</label>
                    <div className="px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-700 select-all">
                      {profile?.phone || profile?.email}
                    </div>
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
                  onClick={() => setShowAddressModal(true)}
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
                    onAction={() => setShowAddressModal(true)}
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
        onClose={() => setShowAddressModal(false)}
        title="Tambah Alamat Baru"
        description="Masukkan rincian alamat lengkap untuk mempermudah kurir menjemput cucian."
      >
        <form onSubmit={handleCreateAddress} className="space-y-4">
          <Input
            label="Label Alamat"
            required
            value={addrLabel}
            onChange={(e) => setAddrLabel(e.target.value)}
            placeholder="Contoh: Rumah, Kantor, Kos Aster No. 12"
          />

          <Textarea
            label="Alamat Lengkap"
            required
            rows={3}
            value={addrText}
            onChange={(e) => setAddrText(e.target.value)}
            placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, patokan dekat..."
          />

          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Latitude (Opsional)"
              type="number"
              step="any"
              value={addrLat}
              onChange={(e) => setAddrLat(e.target.value)}
              placeholder="-6.9175"
            />
            <Input
              label="Longitude (Opsional)"
              type="number"
              step="any"
              value={addrLng}
              onChange={(e) => setAddrLng(e.target.value)}
              placeholder="107.6191"
            />
          </div>

          <Checkbox
            label="Jadikan sebagai alamat utama"
            description="Alamat ini akan otomatis terpilih saat melakukan pesanan baru."
            checked={addrIsDefault}
            onChange={(e) => setAddrIsDefault(e.target.checked)}
          />

          <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setShowAddressModal(false)}
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
