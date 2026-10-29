'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Input, Textarea, Badge } from '@/components/ui';
import { getStoredUser, fetchPortalSession, AuthUser } from '@/lib/auth';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { reverseGeocode } from '@/lib/profile';
import { StoreLocationPicker, isDefaultCoordinates } from '@/components/map/StoreLocationPicker';
import {
  Store,
  User,
  Clock,
  MapPin,
  CheckCircle2,
  AlertCircle,
  ShieldAlert,
} from 'lucide-react';
import { cn } from '@/lib/format';

interface DaySchedule {
  open: string;
  close: string;
  is_closed: boolean;
}

type OperationalHours = Record<
  'senin' | 'selasa' | 'rabu' | 'kamis' | 'jumat' | 'sabtu' | 'minggu',
  DaySchedule
>;

const DAYS: { key: keyof OperationalHours; label: string }[] = [
  { key: 'senin', label: 'Senin' },
  { key: 'selasa', label: 'Selasa' },
  { key: 'rabu', label: 'Rabu' },
  { key: 'kamis', label: 'Kamis' },
  { key: 'jumat', label: 'Jumat' },
  { key: 'sabtu', label: 'Sabtu' },
  { key: 'minggu', label: 'Minggu' },
];

const DEFAULT_HOURS: OperationalHours = {
  senin: { open: '08:00', close: '20:00', is_closed: false },
  selasa: { open: '08:00', close: '20:00', is_closed: false },
  rabu: { open: '08:00', close: '20:00', is_closed: false },
  kamis: { open: '08:00', close: '20:00', is_closed: false },
  jumat: { open: '08:00', close: '20:00', is_closed: false },
  sabtu: { open: '08:00', close: '20:00', is_closed: false },
  minggu: { open: '09:00', close: '17:00', is_closed: true },
};

interface StoreData {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  phone: string;
  address: string;
  city: string;
  province: string;
  latitude?: number | null;
  longitude?: number | null;
  service_radius_km?: number | null;
  operational_hours?: OperationalHours | null;
  status: string;
  is_active: boolean;
  is_approved: boolean;
}

export default function StoreSettingsPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [store, setStore] = useState<StoreData | null>(null);
  const [isOwner, setIsOwner] = useState(true);
  const [loading, setLoading] = useState(true);

  // Form State: Profil Toko & Lokasi
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [phone, setPhone] = useState('');
  const [address, setAddress] = useState('');
  const [city, setCity] = useState('');
  const [province, setProvince] = useState('');
  const [latitude, setLatitude] = useState('');
  const [longitude, setLongitude] = useState('');
  const [isLocating, setIsLocating] = useState(false);

  // Form State: Radius Layanan (km)
  const [serviceRadius, setServiceRadius] = useState('5.0');

  // Form State: Jam Operasional (7 hari, WIB)
  const [operationalHours, setOperationalHours] = useState<OperationalHours>(DEFAULT_HOURS);

  // UI state
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    async function loadData() {
      setLoading(true);

      // Instant hydration from local stored user (no render delay)
      const stored = getStoredUser('mitra');
      if (stored && isMounted) {
        setCurrentUser(stored);
      }

      try {
        // Parallel fetch: session and store data executed concurrently (eliminates sequential waterfall)
        const [freshUser, storeRes] = await Promise.all([
          fetchPortalSession('mitra'),
          fetch('/api/proxy/mitra/store', {
            headers: {
              Accept: 'application/json',
              'x-portal': 'mitra',
            },
            cache: 'no-store',
            credentials: 'include',
          }),
        ]);

        if (!isMounted) return;

        if (freshUser) {
          setCurrentUser(freshUser);
        }

        if (storeRes.ok) {
          const json = await storeRes.json();
          const storeInfo = json?.data?.store || json?.store;
          const membership = json?.data?.membership || json?.membership;

          if (storeInfo) {
            setStore(storeInfo);
            setName(storeInfo.name || '');
            setDescription(storeInfo.description || '');
            setPhone(storeInfo.phone || '');
            setAddress(storeInfo.address || '');
            setCity(storeInfo.city || '');
            setProvince(storeInfo.province || '');
            setLatitude(
              storeInfo.latitude !== null && storeInfo.latitude !== undefined
                ? String(storeInfo.latitude)
                : ''
            );
            setLongitude(
              storeInfo.longitude !== null && storeInfo.longitude !== undefined
                ? String(storeInfo.longitude)
                : ''
            );

            if (
              storeInfo.service_radius_km !== null &&
              storeInfo.service_radius_km !== undefined
            ) {
              setServiceRadius(String(storeInfo.service_radius_km));
            } else {
              setServiceRadius('5.0');
            }

            if (
              storeInfo.operational_hours &&
              typeof storeInfo.operational_hours === 'object'
            ) {
              setOperationalHours({
                ...DEFAULT_HOURS,
                ...storeInfo.operational_hours,
              });
            } else {
              setOperationalHours(DEFAULT_HOURS);
            }
          }

          if (membership) {
            setIsOwner(Boolean(membership.is_owner));
          }
        }
      } catch {
        // Handled gracefully
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    }

    loadData();

    return () => {
      isMounted = false;
    };
  }, []);

  const handleDetectGps = () => {
    if (typeof window === 'undefined' || !navigator.geolocation) {
      setErrorMsg('Browser tidak mendukung deteksi lokasi otomatis.');
      return;
    }

    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        setLatitude(lat.toFixed(6));
        setLongitude(lng.toFixed(6));

        try {
          const result = await reverseGeocode({ latitude: lat, longitude: lng });
          if (result.city && !city) setCity(result.city);
          if (result.province && !province) setProvince(result.province);
          if (result.formatted_address && !address) setAddress(result.formatted_address);
        } catch {
          // Gracefully continue with coordinates
        } finally {
          setIsLocating(false);
        }
      },
      () => {
        setIsLocating(false);
        setErrorMsg('Gagal mendeteksi koordinat GPS. Silakan masukkan koordinat secara manual.');
      },
      { timeout: 10000 }
    );
  };

  const handleDayChange = (
    dayKey: keyof OperationalHours,
    field: keyof DaySchedule,
    value: string | boolean
  ) => {
    setOperationalHours((prev) => ({
      ...prev,
      [dayKey]: {
        ...prev[dayKey],
        [field]: value,
      },
    }));
  };

  const handleSaveStore = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isOwner) return;

    setSaving(true);
    setSuccessMsg(null);
    setErrorMsg(null);

    // Validate Radius
    const parsedRadius = parseFloat(serviceRadius);
    if (isNaN(parsedRadius) || parsedRadius < 0.5 || parsedRadius > 50) {
      setErrorMsg('Radius jangkauan layanan harus di antara 0,5 dan 50 km.');
      setSaving(false);
      return;
    }

    // Validate Coordinates
    const latNum = parseFloat(latitude);
    const lngNum = parseFloat(longitude);
    if (!latitude || !longitude || isNaN(latNum) || isNaN(lngNum) || isDefaultCoordinates(latNum, lngNum)) {
      setErrorMsg('Titik koordinat lokasi toko wajib ditentukan pada peta. Koordinat tidak boleh kosong atau masih nilai default (-6.2088, 106.8456).');
      setSaving(false);
      return;
    }
    if (latNum < -90 || latNum > 90 || lngNum < -180 || lngNum > 180) {
      setErrorMsg('Koordinat lokasi tidak valid.');
      setSaving(false);
      return;
    }

    // Validate Operational Hours
    for (const day of DAYS) {
      const schedule = operationalHours[day.key];
      if (!schedule.is_closed) {
        if (!schedule.open || !schedule.close) {
          setErrorMsg(`Jam buka dan jam tutup hari ${day.label} wajib diisi.`);
          setSaving(false);
          return;
        }
        if (schedule.close <= schedule.open) {
          setErrorMsg(`Jam tutup hari ${day.label} harus lebih besar dari jam buka.`);
          setSaving(false);
          return;
        }
      }
    }

    try {
      const payload = {
        name: name.trim(),
        description: description.trim(),
        phone: phone.trim(),
        address: address.trim(),
        city: city.trim(),
        province: province.trim(),
        latitude: latitude ? parseFloat(latitude) : null,
        longitude: longitude ? parseFloat(longitude) : null,
        service_radius_km: parsedRadius,
        operational_hours: operationalHours,
      };

      const res = await fetch('/api/proxy/mitra/store', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'x-portal': 'mitra',
        },
        credentials: 'include',
        body: JSON.stringify(payload),
      });

      const json = await res.json().catch(() => null);

      if (!res.ok || json?.success === false) {
        const errorDetail = json?.errors ? Object.values(json.errors).flat().join(', ') : null;
        const msg = errorDetail || json?.message || json?.error?.message || 'Gagal menyimpan pengaturan toko.';
        throw new Error(msg);
      }

      setSuccessMsg('Pengaturan toko berhasil diperbarui.');
      if (json?.data?.store) {
        setStore(json.data.store);
      }
    } catch (err: unknown) {
      setErrorMsg(formatFriendlyErrorMessage(err, 'Terjadi kesalahan sistem.'));
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
        {/* Hero Banner Section (Navy-900 with Wave & Bubbles SVG) */}
        <div className="bg-navy-900 text-white rounded-3xl relative overflow-hidden p-6 sm:p-7 select-none shadow-sm">
          {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
          <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
            <div className="absolute top-2 right-8 w-24 h-24 rounded-full border border-sky-300/25 bg-sky-400/10 backdrop-blur-[1px]">
              <div className="w-2 h-2 rounded-full bg-white/60 ml-3 mt-3" />
            </div>
            <div className="absolute -bottom-6 right-36 w-20 h-20 rounded-full border border-sky-200/20 bg-sky-300/10">
              <div className="w-1.5 h-1.5 rounded-full bg-white/40 ml-2 mt-2" />
            </div>
          </div>

          <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="space-y-1.5 max-w-xl">
              <div className="flex items-center gap-3">
                <div className="w-11 h-11 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-xs flex items-center justify-center text-sky-300 shadow-2xs">
                  <Store className="w-5 h-5" strokeWidth={1.75} />
                </div>
                <h1 className="text-xl sm:text-2xl font-extrabold tracking-tight text-white leading-tight">
                  Pengaturan profil laundry
                </h1>
              </div>
              <p className="text-xs text-navy-200 leading-relaxed">
                Atur identitas gerai laundry, titik lokasi di peta, radius layanan kurir, dan jadwal jam operasional.
              </p>
            </div>

            <Link href="/store/profile" className="shrink-0 self-start sm:self-auto">
              <span className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-navy-800/90 hover:bg-navy-800 text-sky-200 border border-navy-700/90 text-xs font-semibold transition-all shadow-2xs active:scale-95 cursor-pointer">
                <User className="w-3.5 h-3.5 text-sky-300" strokeWidth={2} />
                <span>Profil akun pengelola</span>
              </span>
            </Link>
          </div>

          {/* Mini Wave SVG Accent at Bottom */}
          <div className="absolute -bottom-1 left-0 right-0 pointer-events-none overflow-hidden leading-none opacity-20" aria-hidden="true">
            <svg
              viewBox="0 0 1440 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              className="w-full h-4 text-sky-300 preserve-3d"
            >
              <path
                d="M0 10C240 22 480 2 720 12C960 22 1200 5 1440 10V24H0V10Z"
                fill="currentColor"
              />
            </svg>
          </div>
        </div>

        {/* Status / Alert Messages */}
        {successMsg && (
          <div className="p-3.5 bg-emerald-50 border border-emerald-200/90 rounded-2xl text-emerald-800 text-xs flex items-center gap-2.5 shadow-2xs">
            <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" strokeWidth={2} />
            <span>{successMsg}</span>
          </div>
        )}

        {errorMsg && (
          <div className="p-3.5 bg-rose-50 border border-rose-200/90 rounded-2xl text-rose-800 text-xs flex items-center gap-2.5 shadow-2xs">
            <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" strokeWidth={2} />
            <span>{errorMsg}</span>
          </div>
        )}

        {loading ? (
          /* Skeleton Loader */
          <div className="space-y-6 animate-pulse">
            <div className="rounded-3xl bg-white border border-slate-200/90 p-6 space-y-4 shadow-2xs">
              <div className="h-5 bg-slate-200 rounded-xl w-1/4"></div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="h-10 bg-slate-100 rounded-xl"></div>
                <div className="h-10 bg-slate-100 rounded-xl"></div>
              </div>
              <div className="h-24 bg-slate-100 rounded-xl"></div>
              <div className="h-48 bg-slate-100 rounded-2xl"></div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div className="h-44 bg-white rounded-3xl border border-slate-200/90"></div>
              <div className="h-44 bg-white rounded-3xl border border-slate-200/90"></div>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSaveStore} className="space-y-6">
            {/* Section 1: Profil & Lokasi Gerai */}
            <div className="rounded-3xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-7 space-y-5">
              <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-2xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100 shrink-0">
                    <Store className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
                  </div>
                  <div>
                    <h2 className="font-heading font-bold text-base text-navy-900 leading-tight">
                      Profil &amp; lokasi gerai
                    </h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                      Informasi gerai yang tampil di katalog pencarian bagi pelanggan
                    </p>
                  </div>
                </div>

                {!isOwner && (
                  <Badge
                    variant="amber"
                    size="sm"
                    className="text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-2.5 py-1"
                  >
                    Mode lihat saja (staf toko)
                  </Badge>
                )}
              </div>

              {!isOwner && (
                <div className="p-3.5 bg-amber-50 border border-amber-200/90 rounded-2xl text-amber-800 text-xs flex items-center gap-2.5 shadow-2xs">
                  <ShieldAlert className="w-4 h-4 shrink-0 text-amber-600" strokeWidth={2} />
                  <span>
                    Hanya pemilik toko yang memiliki wewenang untuk mengubah informasi profil dan pengaturan gerai.
                  </span>
                </div>
              )}

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                    Nama gerai
                  </label>
                  <Input
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Nama toko laundry"
                    required
                    disabled={saving || !isOwner}
                    className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                    Nomor telepon toko
                  </label>
                  <Input
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    placeholder="08123456789"
                    required
                    disabled={saving || !isOwner}
                    className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Deskripsi toko
                </label>
                <Textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Ceritakan layanan unggulan, fasilitas, dan kebersihan gerai Anda..."
                  rows={2}
                  disabled={saving || !isOwner}
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm resize-none"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Alamat lengkap gerai
                </label>
                <Textarea
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  placeholder="Nama jalan, nomor ruko/rumah, RT/RW, kelurahan, kecamatan"
                  rows={2}
                  required
                  disabled={saving || !isOwner}
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm resize-none"
                />
              </div>

              {/* Kota & Provinsi (Editable) */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                    Kota / Kabupaten
                  </label>
                  <Input
                    value={city}
                    onChange={(e) => setCity(e.target.value)}
                    placeholder="Contoh: Kota Bandung"
                    disabled={saving || !isOwner}
                    className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                    Provinsi
                  </label>
                  <Input
                    value={province}
                    onChange={(e) => setProvince(e.target.value)}
                    placeholder="Contoh: Jawa Barat"
                    disabled={saving || !isOwner}
                    className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                  />
                </div>
              </div>

              {/* Peta Penentuan Titik Gerai */}
              <div className="pt-2">
                <div className="rounded-2xl border border-slate-200/90 overflow-hidden bg-slate-50/50 p-3 sm:p-4 space-y-2">
                  <StoreLocationPicker
                    latitude={latitude ? parseFloat(latitude) : null}
                    longitude={longitude ? parseFloat(longitude) : null}
                    onChange={(lat, lng) => {
                      setLatitude(lat.toFixed(6));
                      setLongitude(lng.toFixed(6));
                    }}
                    onAddressResolved={(info) => {
                      if (info.city && !city) setCity(info.city);
                      if (info.province && !province) setProvince(info.province);
                      if (info.formatted_address && !address) setAddress(info.formatted_address);
                    }}
                    serviceRadiusKm={parseFloat(serviceRadius) || 5}
                    label="Titik lokasi gerai di peta"
                  />
                </div>
              </div>

              {store && (
                <div className="text-xs text-slate-500 flex flex-wrap items-center gap-x-6 gap-y-2 pt-3 border-t border-slate-100">
                  <div className="flex items-center gap-1.5">
                    <span>Status gerai:</span>
                    <strong className="capitalize text-navy-800 font-bold bg-navy-50 px-2.5 py-0.5 rounded-lg border border-navy-100">
                      {store.status || '-'}
                    </strong>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <span>Status operasional:</span>
                    <strong
                      className={cn(
                        'px-2.5 py-0.5 rounded-lg font-bold border',
                        store.is_active
                          ? 'text-emerald-800 bg-emerald-50 border-emerald-200'
                          : 'text-slate-600 bg-slate-100 border-slate-200'
                      )}
                    >
                      {store.is_active ? 'Aktif menerima cucian' : 'Nonaktif'}
                    </strong>
                  </div>
                </div>
              )}
            </div>

            {/* Section 2: Radius Jangkauan Layanan */}
            <div className="rounded-3xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-7 space-y-4">
              <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div className="w-10 h-10 rounded-2xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100 shrink-0">
                  <MapPin className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
                </div>
                <div>
                  <h2 className="font-heading font-bold text-base text-navy-900 leading-tight">
                    Radius jangkauan layanan
                  </h2>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Batas jarak maksimum antar-jemput cucian pelanggan oleh kurir toko
                  </p>
                </div>
              </div>

              <div className="max-w-xs space-y-1.5">
                <label className="block text-xs font-semibold text-slate-700">
                  Radius jangkauan (kilometer)
                </label>
                <div className="relative flex items-center">
                  <Input
                    type="number"
                    step="0.1"
                    min="0.5"
                    max="50"
                    value={serviceRadius}
                    onChange={(e) => setServiceRadius(e.target.value)}
                    required
                    disabled={saving || !isOwner}
                    className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm pr-12 font-semibold"
                  />
                  <span className="absolute right-3.5 text-xs text-slate-400 font-bold pointer-events-none">
                    KM
                  </span>
                </div>
                <p className="text-[11px] text-slate-500 leading-relaxed">
                  Batas validasi: minimal 0,5 km hingga maksimal 50 km (maksimal 2 desimal).
                </p>
              </div>
            </div>

            {/* Section 3: Jam Operasional (7 Hari, WIB) */}
            <div className="rounded-3xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-7 space-y-5">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-2xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100 shrink-0">
                    <Clock className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
                  </div>
                  <div>
                    <h2 className="font-heading font-bold text-base text-navy-900 leading-tight">
                      Jam operasional toko
                    </h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                      Jadwal operasional mingguan penerimaan cucian pelanggan
                    </p>
                  </div>
                </div>

                <span className="inline-flex items-center text-xs font-bold text-navy-800 bg-navy-50 px-3 py-1 rounded-xl border border-navy-100 self-start sm:self-auto shadow-2xs">
                  WIB (UTC+7)
                </span>
              </div>

              <div className="space-y-2.5">
                {DAYS.map((day) => {
                  const schedule = operationalHours[day.key] || {
                    open: '08:00',
                    close: '20:00',
                    is_closed: false,
                  };

                  return (
                    <div
                      key={day.key}
                      className={cn(
                        'p-3.5 rounded-2xl border transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs',
                        schedule.is_closed
                          ? 'border-slate-200/60 bg-slate-50/50'
                          : 'border-slate-200/90 bg-white hover:border-slate-300'
                      )}
                    >
                      <div className="w-28 font-bold text-slate-800 flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full bg-navy-700"></span>
                        <span>{day.label}</span>
                      </div>

                      <div className="flex flex-wrap items-center gap-3">
                        <label className="inline-flex items-center gap-2 cursor-pointer select-none">
                          <input
                            type="checkbox"
                            checked={schedule.is_closed}
                            onChange={(e) =>
                              handleDayChange(day.key, 'is_closed', e.target.checked)
                            }
                            disabled={saving || !isOwner}
                            className="rounded-md border-slate-300 text-navy-700 focus:ring-navy-700 w-4 h-4 cursor-pointer"
                          />
                          <span
                            className={cn(
                              'text-xs font-semibold',
                              schedule.is_closed ? 'text-rose-600' : 'text-slate-600'
                            )}
                          >
                            Tutup
                          </span>
                        </label>

                        {!schedule.is_closed ? (
                          <div className="flex items-center gap-2">
                            <input
                              type="time"
                              value={schedule.open}
                              onChange={(e) =>
                                handleDayChange(day.key, 'open', e.target.value)
                              }
                              required={!schedule.is_closed}
                              disabled={saving || !isOwner}
                              className="px-2.5 py-1.5 border border-slate-200 rounded-xl text-xs text-slate-800 bg-white focus:outline-hidden focus:border-navy-700 font-medium"
                            />
                            <span className="text-slate-400 font-medium">s/d</span>
                            <input
                              type="time"
                              value={schedule.close}
                              onChange={(e) =>
                                handleDayChange(day.key, 'close', e.target.value)
                              }
                              required={!schedule.is_closed}
                              disabled={saving || !isOwner}
                              className="px-2.5 py-1.5 border border-slate-200 rounded-xl text-xs text-slate-800 bg-white focus:outline-hidden focus:border-navy-700 font-medium"
                            />
                            <span className="text-[11px] text-slate-400 font-bold ml-0.5">WIB</span>
                          </div>
                        ) : (
                          <span className="text-xs text-rose-500 italic px-2">
                            Gerai tidak melayani penerimaan cucian pada hari ini
                          </span>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Submit Action */}
            {isOwner && (
              <div className="flex items-center justify-end gap-3 pt-2">
                <Button
                  type="submit"
                  variant="primary"
                  size="sm"
                  className="bg-navy-700 hover:bg-navy-800 active:scale-[0.98] text-white rounded-xl text-xs sm:text-sm font-bold px-6 py-2.5 shadow-2xs cursor-pointer"
                  disabled={saving || loading}
                >
                  {saving ? 'Menyimpan pengaturan...' : 'Simpan semua pengaturan'}
                </Button>
              </div>
            )}
          </form>
        )}
      </div>
    </DashboardLayout>
  );
}
