'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Input, Textarea, Badge } from '@/components/ui';
import { getStoredUser, fetchPortalSession, AuthUser } from '@/lib/auth';
import { Store, User, Clock, MapPin, CheckCircle2, AlertCircle, ShieldAlert, Settings } from 'lucide-react';

interface StoreData {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  phone: string;
  address: string;
  city: string;
  province: string;
  status: string;
  is_active: boolean;
  is_approved: boolean;
}

export default function StoreSettingsPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [store, setStore] = useState<StoreData | null>(null);
  const [isOwner, setIsOwner] = useState(true);
  const [loading, setLoading] = useState(true);

  // Form State
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [phone, setPhone] = useState('');
  const [address, setAddress] = useState('');
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  useEffect(() => {
    async function loadData() {
      setLoading(true);
      const stored = getStoredUser('mitra');
      if (stored) {
        setCurrentUser(stored);
      }

      const freshUser = await fetchPortalSession('mitra');
      if (freshUser) {
        setCurrentUser(freshUser);
      }

      try {
        const res = await fetch('/api/proxy/mitra/store', {
          headers: {
            'Accept': 'application/json',
            'x-portal': 'mitra',
          },
          cache: 'no-store',
          credentials: 'include',
        });

        if (res.ok) {
          const json = await res.json();
          const storeInfo = json?.data?.store || json?.store;
          const membership = json?.data?.membership || json?.membership;

          if (storeInfo) {
            setStore(storeInfo);
            setName(storeInfo.name || '');
            setDescription(storeInfo.description || '');
            setPhone(storeInfo.phone || '');
            setAddress(storeInfo.address || '');
          }

          if (membership) {
            setIsOwner(Boolean(membership.is_owner));
          }
        }
      } catch {
        // Handled silently
      } finally {
        setLoading(false);
      }
    }

    loadData();
  }, []);

  const handleSaveStore = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isOwner) return;

    setSaving(true);
    setSuccessMsg(null);
    setErrorMsg(null);

    try {
      const res = await fetch('/api/proxy/mitra/store', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'x-portal': 'mitra',
        },
        credentials: 'include',
        body: JSON.stringify({
          name: name.trim(),
          description: description.trim(),
          phone: phone.trim(),
          address: address.trim(),
        }),
      });

      const json = await res.json().catch(() => null);

      if (!res.ok || json?.success === false) {
        const msg = json?.message || json?.error?.message || 'Gagal menyimpan profil gerai.';
        throw new Error(msg);
      }

      setSuccessMsg('Profil gerai berhasil diperbarui.');
      if (json?.data?.store) {
        setStore(json.data.store);
      }
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Terjadi kesalahan sistem.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
    >
      <div className="space-y-6 max-w-4xl">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold text-slate-900 tracking-tight">
              Pengaturan Toko
            </h1>
            <p className="text-xs text-slate-500 mt-0.5">
              Konfigurasi profil gerai laundry, jam operasional, dan radius jangkauan.
            </p>
          </div>

          <Link href="/store/profile">
            <Button variant="outline" size="sm" className="text-xs inline-flex items-center gap-1.5 border-slate-300">
              <User className="w-3.5 h-3.5 text-[#1A3A5C]" />
              <span>Buka Profil Akun</span>
            </Button>
          </Link>
        </div>

        {/* Section 1: Profil Gerai */}
        <div className="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <div className="flex items-center gap-2">
              <Store className="w-5 h-5 text-[#1A3A5C]" />
              <h2 className="text-sm font-bold text-slate-900">Profil Gerai</h2>
            </div>
            {!isOwner && (
              <Badge variant="amber" size="sm" className="text-[11px] bg-amber-50 text-amber-700 border border-amber-200">
                Mode Lihat Saja (Staf Toko)
              </Badge>
            )}
          </div>

          {!isOwner && (
            <div className="p-3 bg-amber-50 border border-amber-200 rounded-md text-amber-800 text-xs flex items-center gap-2">
              <ShieldAlert className="w-4 h-4 shrink-0 text-amber-600" />
              <span>Hanya pemilik toko yang memiliki wewenang untuk mengubah informasi profil gerai.</span>
            </div>
          )}

          {successMsg && (
            <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-md text-emerald-700 text-xs flex items-center gap-2">
              <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
              <span>{successMsg}</span>
            </div>
          )}

          {errorMsg && (
            <div className="p-3 bg-rose-50 border border-rose-200 rounded-md text-rose-700 text-xs flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
              <span>{errorMsg}</span>
            </div>
          )}

          <form onSubmit={handleSaveStore} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Nama Gerai
                </label>
                <Input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Nama toko laundry"
                  required
                  disabled={loading || saving || !isOwner}
                  className="text-xs"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Nomor Telepon Toko
                </label>
                <Input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="08123456789"
                  required
                  disabled={loading || saving || !isOwner}
                  className="text-xs"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Deskripsi Toko
              </label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Ceritakan layanan unggulan, fasilitas, dan kebersihan gerai Anda..."
                rows={3}
                disabled={loading || saving || !isOwner}
                className="text-xs"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Alamat Lengkap Gerai
              </label>
              <Textarea
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                placeholder="Jalan, nomor, RT/RW, kelurahan, kecamatan"
                rows={2}
                required
                disabled={loading || saving || !isOwner}
                className="text-xs"
              />
            </div>

            {store && (
              <div className="text-[11px] text-slate-500 bg-slate-50 p-2.5 rounded border border-slate-100 flex flex-wrap gap-x-4 gap-y-1">
                <span>Kota: <strong className="text-slate-700">{store.city || '-'}</strong></span>
                <span>Provinsi: <strong className="text-slate-700">{store.province || '-'}</strong></span>
                <span>Status Gerai: <strong className="capitalize text-[#1A3A5C]">{store.status || '-'}</strong></span>
              </div>
            )}

            {isOwner && (
              <div className="pt-2 flex justify-end">
                <Button
                  type="submit"
                  variant="primary"
                  size="sm"
                  className="bg-[#1A3A5C] text-xs font-medium px-4"
                  disabled={saving || loading}
                >
                  {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                </Button>
              </div>
            )}
          </form>
        </div>

        {/* Section 2: Fitur Tambahan (Jam Operasional & Radius Layanan) - Segera Hadir */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="border border-slate-200 rounded-lg p-5 bg-white space-y-2">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-slate-600" />
                <h3 className="text-xs font-bold text-slate-900">Jam Operasional</h3>
              </div>
              <Badge variant="secondary" size="sm" className="text-[10px] bg-slate-100 text-slate-600 border-slate-200">
                Segera Hadir
              </Badge>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed">
              Konfigurasi jam buka dan tutup per hari sedang dipersiapkan. Toko Anda saat ini beroperasi berdasarkan status operasional aktif/nonaktif di sidebar.
            </p>
          </div>

          <div className="border border-slate-200 rounded-lg p-5 bg-white space-y-2">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-slate-600" />
                <h3 className="text-xs font-bold text-slate-900">Radius Jangkauan Layanan</h3>
              </div>
              <Badge variant="secondary" size="sm" className="text-[10px] bg-slate-100 text-slate-600 border-slate-200">
                Segera Hadir
              </Badge>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed">
              Batas radius jangkauan kurir dan ongkos kirim otomatis per kilometer akan dapat disesuaikan pada pembaruan tahap berikutnya.
            </p>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
