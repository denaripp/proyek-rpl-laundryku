'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Input, Textarea, Badge } from '@/components/ui';
import { getStoredUser, fetchPortalSession, AuthUser } from '@/lib/auth';
import { reverseGeocode } from '@/lib/profile';
import {
  Store,
  User,
  Clock,
  MapPin,
  CheckCircle2,
  AlertCircle,
  ShieldAlert,
  Compass,
  Navigation,
} from 'lucide-react';

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
              Konfigurasi profil gerai laundry, lokasi, jam operasional, dan radius jangkauan layanan.
            </p>
          </div>

          <Link href="/store/profile">
            <Button
              variant="outline"
              size="sm"
              className="text-xs inline-flex items-center gap-1.5 border-slate-300"
            >
              <User className="w-3.5 h-3.5 text-[#1A3A5C]" />
              <span>Buka Profil Akun</span>
            </Button>
          </Link>
        </div>

        {/* Status / Alert Messages */}
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

        {loading ? (
          /* Skeleton Loader (eliminates layout shift & provides immediate feedback) */
          <div className="space-y-6 animate-pulse">
            <div className="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
              <div className="h-5 bg-slate-200 rounded w-1/4"></div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="h-9 bg-slate-100 rounded"></div>
                <div className="h-9 bg-slate-100 rounded"></div>
              </div>
              <div className="h-20 bg-slate-100 rounded"></div>
              <div className="h-16 bg-slate-100 rounded"></div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="h-48 bg-slate-100 rounded-lg"></div>
              <div className="h-48 bg-slate-100 rounded-lg"></div>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSaveStore} className="space-y-6">
            {/* Section 1: Profil & Lokasi Gerai */}
            <div className="bg-white border border-slate-200 rounded-lg p-6 space-y-4 shadow-xs">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div className="flex items-center gap-2">
                  <Store className="w-5 h-5 text-[#1A3A5C]" />
                  <h2 className="text-sm font-bold text-slate-900">Profil & Lokasi Gerai</h2>
                </div>
                {!isOwner && (
                  <Badge
                    variant="amber"
                    size="sm"
                    className="text-[11px] bg-amber-50 text-amber-700 border border-amber-200"
                  >
                    Mode Lihat Saja (Staf Toko)
                  </Badge>
                )}
              </div>

              {!isOwner && (
                <div className="p-3 bg-amber-50 border border-amber-200 rounded-md text-amber-800 text-xs flex items-center gap-2">
                  <ShieldAlert className="w-4 h-4 shrink-0 text-amber-600" />
                  <span>
                    Hanya pemilik toko yang memiliki wewenang untuk mengubah informasi profil dan pengaturan gerai.
                  </span>
                </div>
              )}

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
                    disabled={saving || !isOwner}
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
                    disabled={saving || !isOwner}
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
                  rows={2}
                  disabled={saving || !isOwner}
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
                  placeholder="Nama jalan, nomor ruko/rumah, RT/RW, kelurahan, kecamatan"
                  rows={2}
                  required
                  disabled={saving || !isOwner}
                  className="text-xs"
                />
              </div>

              {/* Kota & Provinsi (Editable) */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Kota / Kabupaten
                  </label>
                  <Input
                    value={city}
                    onChange={(e) => setCity(e.target.value)}
                    placeholder="Contoh: Kota Bandung"
                    disabled={saving || !isOwner}
                    className="text-xs"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Provinsi
                  </label>
                  <Input
                    value={province}
                    onChange={(e) => setProvince(e.target.value)}
                    placeholder="Contoh: Jawa Barat"
                    disabled={saving || !isOwner}
                    className="text-xs"
                  />
                </div>
              </div>

              {/* Koordinat GPS & Tombol Deteksi */}
              <div className="p-3.5 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5">
                    <Compass className="w-4 h-4 text-slate-600" />
                    <span className="text-xs font-semibold text-slate-800">
                      Titik Koordinat Gerai (GPS)
                    </span>
                  </div>
                  {isOwner && (
                    <button
                      type="button"
                      onClick={handleDetectGps}
                      disabled={isLocating || saving}
                      className="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium text-[#1A3A5C] bg-white border border-slate-300 rounded hover:bg-slate-100 transition-colors cursor-pointer disabled:opacity-50"
                    >
                      <Navigation className="w-3 h-3" />
                      <span>{isLocating ? 'Mendeteksi...' : 'Deteksi GPS Saya'}</span>
                    </button>
                  )}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[10px] text-slate-500 mb-0.5">Latitude</label>
                    <input
                      type="number"
                      step="any"
                      value={latitude}
                      onChange={(e) => setLatitude(e.target.value)}
                      placeholder="-6.914744"
                      disabled={saving || !isOwner}
                      className="w-full px-2.5 py-1.5 text-xs bg-white border border-slate-300 rounded font-mono text-slate-800 focus:outline-hidden focus:border-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] text-slate-500 mb-0.5">Longitude</label>
                    <input
                      type="number"
                      step="any"
                      value={longitude}
                      onChange={(e) => setLongitude(e.target.value)}
                      placeholder="107.609810"
                      disabled={saving || !isOwner}
                      className="w-full px-2.5 py-1.5 text-xs bg-white border border-slate-300 rounded font-mono text-slate-800 focus:outline-hidden focus:border-blue-500"
                    />
                  </div>
                </div>

                {store && (
                  <div className="text-[11px] text-slate-500 flex flex-wrap gap-x-4 pt-1 border-t border-slate-200">
                    <span>
                      Status Gerai:{' '}
                      <strong className="capitalize text-[#1A3A5C]">
                        {store.status || '-'}
                      </strong>
                    </span>
                    <span>
                      Operasional:{' '}
                      <strong className={store.is_active ? 'text-emerald-600' : 'text-slate-500'}>
                        {store.is_active ? 'Aktif' : 'Nonaktif'}
                      </strong>
                    </span>
                  </div>
                )}
              </div>
            </div>

            {/* Section 2: Radius Jangkauan Layanan */}
            <div className="bg-white border border-slate-200 rounded-lg p-6 space-y-4 shadow-xs">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div className="flex items-center gap-2">
                  <MapPin className="w-5 h-5 text-[#1A3A5C]" />
                  <div>
                    <h2 className="text-sm font-bold text-slate-900">
                      Radius Jangkauan Layanan
                    </h2>
                    <p className="text-[11px] text-slate-500">
                      Batas jarak maksimum antar-jemput cucian pelanggan oleh kurir toko.
                    </p>
                  </div>
                </div>
              </div>

              <div className="max-w-xs space-y-1.5">
                <label className="block text-xs font-semibold text-slate-700">
                  Radius Jangkauan (Kilometer)
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
                    className="text-xs pr-12 font-medium"
                  />
                  <span className="absolute right-3 text-xs text-slate-400 font-semibold pointer-events-none">
                    KM
                  </span>
                </div>
                <p className="text-[11px] text-slate-500">
                  Batas validasi: minimal 0,5 km hingga maksimal 50 km (maksimal 2 desimal).
                </p>
              </div>
            </div>

            {/* Section 3: Jam Operasional (7 Hari, WIB) */}
            <div className="bg-white border border-slate-200 rounded-lg p-6 space-y-4 shadow-xs">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div className="flex items-center gap-2">
                  <Clock className="w-5 h-5 text-[#1A3A5C]" />
                  <div>
                    <h2 className="text-sm font-bold text-slate-900">Jam Operasional Toko</h2>
                    <p className="text-[11px] text-slate-500">
                      Jadwal operasional 7 hari dalam Waktu Indonesia Barat (WIB).
                    </p>
                  </div>
                </div>
                <span className="text-[11px] font-semibold text-[#1A3A5C] bg-blue-50 px-2.5 py-1 rounded border border-blue-200">
                  WIB (UTC+7)
                </span>
              </div>

              <div className="divide-y divide-slate-100">
                {DAYS.map((day) => {
                  const schedule = operationalHours[day.key] || {
                    open: '08:00',
                    close: '20:00',
                    is_closed: false,
                  };

                  return (
                    <div
                      key={day.key}
                      className="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                    >
                      <div className="w-28 font-medium text-slate-800 flex items-center gap-2">
                        <span>{day.label}</span>
                      </div>

                      <div className="flex items-center gap-3">
                        <label className="inline-flex items-center gap-1.5 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={schedule.is_closed}
                            onChange={(e) =>
                              handleDayChange(day.key, 'is_closed', e.target.checked)
                            }
                            disabled={saving || !isOwner}
                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
                          />
                          <span
                            className={
                              schedule.is_closed
                                ? 'text-rose-600 font-semibold'
                                : 'text-slate-600'
                            }
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
                              className="px-2 py-1 border border-slate-300 rounded text-xs text-slate-800 bg-white focus:outline-hidden focus:border-blue-500"
                            />
                            <span className="text-slate-400">s/d</span>
                            <input
                              type="time"
                              value={schedule.close}
                              onChange={(e) =>
                                handleDayChange(day.key, 'close', e.target.value)
                              }
                              required={!schedule.is_closed}
                              disabled={saving || !isOwner}
                              className="px-2 py-1 border border-slate-300 rounded text-xs text-slate-800 bg-white focus:outline-hidden focus:border-blue-500"
                            />
                            <span className="text-[10px] text-slate-400 font-semibold">WIB</span>
                          </div>
                        ) : (
                          <span className="text-[11px] text-rose-500 italic px-2">
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
                  className="bg-[#1A3A5C] text-xs font-semibold px-6 py-2 shadow-sm"
                  disabled={saving || loading}
                >
                  {saving ? 'Menyimpan Pengaturan...' : 'Simpan Semua Pengaturan'}
                </Button>
              </div>
            )}
          </form>
        )}
      </div>
    </DashboardLayout>
  );
}
