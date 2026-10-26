'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Store,
  User,
  Mail,
  Phone,
  Lock,
  MapPin,
  Building,
  AlertCircle,
  ArrowRight,
  ShieldCheck,
  X,
  AlertTriangle,
} from 'lucide-react';
import { reverseGeocode } from '@/lib/profile';

export default function MitraRegisterClient() {
  const router = useRouter();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [storeName, setStoreName] = useState('');
  const [storeAddress, setStoreAddress] = useState('');
  const [city, setCity] = useState('');
  const [province, setProvince] = useState('');
  const [latitude, setLatitude] = useState('-6.2088');
  const [longitude, setLongitude] = useState('106.8456');
  const [isLocating, setIsLocating] = useState(false);

  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [showDuplicateModal, setShowDuplicateModal] = useState(false);
  const [duplicateModalMsg, setDuplicateModalMsg] = useState(
    'Email ini sudah terdaftar sebagai akun lain. Gunakan email berbeda untuk akun mitra.'
  );

  const handleDetectLocation = () => {
    if (typeof window === 'undefined' || !navigator.geolocation) {
      setErrorMessage('Browser Anda tidak mendukung deteksi lokasi otomatis.');
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
          if (result.city) {
            setCity(result.city);
          }
          if (result.province) {
            setProvince(result.province);
          }
          if (result.formatted_address && !storeAddress) {
            setStoreAddress(result.formatted_address);
          }
        } catch {
          // Fallback gracefully if reverse geocoding is unavailable
        } finally {
          setIsLocating(false);
        }
      },
      () => {
        setIsLocating(false);
        setErrorMessage('Gagal mendeteksi lokasi GPS. Masukkan koordinat gerai secara manual.');
      },
      { timeout: 10000 }
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (password.length < 8) {
      setErrorMessage('Kata sandi minimal terdiri dari 8 karakter.');
      return;
    }

    if (password !== passwordConfirmation) {
      setErrorMessage('Konfirmasi kata sandi tidak cocok.');
      return;
    }

    const latNum = parseFloat(latitude);
    const lngNum = parseFloat(longitude);
    if (isNaN(latNum) || latNum < -90 || latNum > 90) {
      setErrorMessage('Koordinat latitude tidak valid (harus antara -90 dan 90).');
      return;
    }
    if (isNaN(lngNum) || lngNum < -180 || lngNum > 180) {
      setErrorMessage('Koordinat longitude tidak valid (harus antara -180 dan 180).');
      return;
    }

    if (latNum === 0 && lngNum === 0) {
      setErrorMessage('Koordinat lokasi tidak boleh titik default (0, 0). Tentukan titik lokasi toko yang valid.');
      return;
    }

    setLoading(true);

    try {
      const res = await fetch('/api/auth/mitra/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          name: name.trim(),
          email: email.trim(),
          phone: phone.trim(),
          password,
          password_confirmation: passwordConfirmation,
          store_name: storeName.trim(),
          store_address: storeAddress.trim(),
          city: city.trim(),
          province: province.trim(),
          latitude: latNum,
          longitude: lngNum,
        }),
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.success) {
        const errorDetail = json.errors ? Object.values(json.errors).flat().join(', ') : null;
        const msg = errorDetail || json.message || 'Registrasi mitra gagal. Periksa kembali isian form Anda.';

        // Detect duplicate email/phone error
        const isDuplicate =
          res.status === 422 &&
          (msg.toLowerCase().includes('sudah terdaftar') ||
            json.errors?.email ||
            json.errors?.phone);

        if (isDuplicate) {
          setDuplicateModalMsg(
            'Email ini sudah terdaftar sebagai akun lain. Gunakan email berbeda untuk akun mitra.'
          );
          setShowDuplicateModal(true);
        } else {
          setErrorMessage(msg);
        }
        return;
      }

      if (typeof window !== 'undefined' && json.data?.user) {
        localStorage.setItem('laundryku_mitra_user', JSON.stringify(json.data.user));
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: json.data.user }));
      }

      // Hapus flag entri segera setelah pendaftaran sukses
      if (typeof document !== 'undefined') {
        document.cookie = 'mitra_entry_flag=; path=/; max-age=0; SameSite=Lax';
      }
      if (typeof window !== 'undefined') {
        sessionStorage.removeItem('mitra_entry_flag');
      }

      // Store created with status 'submitted' (pending review)
      router.push('/mitra/status');
    } catch {
      setErrorMessage('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-900 py-12 px-4 sm:px-6 lg:px-8">
      {/* Pop up / Modal for duplicate email/phone error */}
      {showDuplicateModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs animate-fadeIn">
          <div className="w-full max-w-md bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl p-6 space-y-4 text-center">
            <div className="w-12 h-12 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center mx-auto">
              <AlertTriangle className="w-6 h-6" strokeWidth={1.5} />
            </div>
            <div className="space-y-2">
              <h3 className="text-base font-bold text-white">Akun Sudah Terdaftar</h3>
              <p className="text-xs text-slate-300 leading-relaxed">
                {duplicateModalMsg}
              </p>
            </div>
            <div className="pt-2">
              <button
                type="button"
                onClick={() => setShowDuplicateModal(false)}
                className="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white transition-colors cursor-pointer"
              >
                Ganti Email & Lanjutkan
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="max-w-xl mx-auto">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-600/20 border border-blue-500/30 text-blue-400 mb-3 shadow-md">
            <Store className="w-6 h-6" strokeWidth={1.5} />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">
            Pendaftaran Mitra Toko Baru
          </h1>
          <p className="mt-1 text-xs text-slate-400">
            Bergabunglah dengan ekosistem LaundryKu untuk menjangkau ribuan pelanggan
          </p>
        </div>

        <div className="bg-slate-800/80 backdrop-blur-md border border-slate-700/80 rounded-2xl shadow-xl p-6 sm:p-8 space-y-6">
          {errorMessage && (
            <div className="p-3.5 rounded-lg bg-rose-950/60 border border-rose-800 text-xs text-rose-200 flex items-start gap-2.5">
              <AlertCircle className="w-4 h-4 text-rose-400 shrink-0 mt-0.5" strokeWidth={1.5} />
              <span>{errorMessage}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Section 1: Data Pemilik Toko */}
            <div className="space-y-3 pb-4 border-b border-slate-700/60">
              <span className="text-[11px] font-semibold text-blue-400 tracking-wider uppercase">
                1. Data Akun Mitra
              </span>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Nama Lengkap Pemilik
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <User className="w-4 h-4" strokeWidth={1.5} />
                  </div>
                  <input
                    type="text"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Contoh: Budi Santoso"
                    className="w-full pl-9 pr-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Email Akun Mitra
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                      <Mail className="w-4 h-4" strokeWidth={1.5} />
                    </div>
                    <input
                      type="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="mitra@laundryku.com"
                      className="w-full pl-9 pr-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                    />
                  </div>
                  <p className="mt-1 text-[10px] text-slate-400">
                    Gunakan email terpisah dari akun customer.
                  </p>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    No. WhatsApp / Telepon
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                      <Phone className="w-4 h-4" strokeWidth={1.5} />
                    </div>
                    <input
                      type="tel"
                      required
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="081234567890"
                      className="w-full pl-9 pr-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                    />
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Kata Sandi
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                      <Lock className="w-4 h-4" strokeWidth={1.5} />
                    </div>
                    <input
                      type="password"
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="Min. 8 karakter"
                      className="w-full pl-9 pr-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Konfirmasi Kata Sandi
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                      <Lock className="w-4 h-4" strokeWidth={1.5} />
                    </div>
                    <input
                      type="password"
                      required
                      value={passwordConfirmation}
                      onChange={(e) => setPasswordConfirmation(e.target.value)}
                      placeholder="Ketik ulang kata sandi"
                      className="w-full pl-9 pr-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Section 2: Informasi Toko / Gerai */}
            <div className="space-y-3">
              <span className="text-[11px] font-semibold text-blue-400 tracking-wider uppercase">
                2. Data Gerai Laundry
              </span>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Nama Gerai Laundry
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <Building className="w-4 h-4" strokeWidth={1.5} />
                  </div>
                  <input
                    type="text"
                    required
                    value={storeName}
                    onChange={(e) => setStoreName(e.target.value)}
                    placeholder="Contoh: Berkah Laundry Express"
                    className="w-full pl-9 pr-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Alamat Lengkap Gerai
                </label>
                <div className="relative">
                  <div className="absolute top-2.5 left-3 flex items-start pointer-events-none text-slate-500">
                    <MapPin className="w-4 h-4" strokeWidth={1.5} />
                  </div>
                  <textarea
                    required
                    rows={2}
                    value={storeAddress}
                    onChange={(e) => setStoreAddress(e.target.value)}
                    placeholder="Nama jalan, nomor ruko/rumah, RT/RW, kelurahan"
                    className="w-full pl-9 pr-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors resize-none"
                  />
                </div>
              </div>

              {/* Kota & Provinsi */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Kota / Kabupaten
                  </label>
                  <input
                    type="text"
                    required
                    value={city}
                    onChange={(e) => setCity(e.target.value)}
                    placeholder="Contoh: Kota Bandung"
                    className="w-full px-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Provinsi
                  </label>
                  <input
                    type="text"
                    required
                    value={province}
                    onChange={(e) => setProvince(e.target.value)}
                    placeholder="Contoh: Jawa Barat"
                    className="w-full px-3 py-2 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                  />
                </div>
              </div>

              {/* Koordinat Gerai (Latitude & Longitude) */}
              <div className="space-y-2 pt-1">
                <div className="flex items-center justify-between">
                  <label className="block text-xs font-medium text-slate-300">
                    Koordinat Lokasi Gerai (GPS)
                  </label>
                  <button
                    type="button"
                    onClick={handleDetectLocation}
                    disabled={isLocating}
                    className="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium text-blue-400 hover:text-blue-300 bg-blue-950/40 hover:bg-blue-900/60 border border-blue-800/60 rounded-md transition-colors cursor-pointer disabled:opacity-50"
                  >
                    <MapPin className="w-3 h-3" strokeWidth={1.5} />
                    <span>{isLocating ? 'Mendeteksi...' : 'Deteksi Lokasi Saya'}</span>
                  </button>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[10px] text-slate-400 mb-0.5">Latitude</label>
                    <input
                      type="number"
                      step="any"
                      required
                      value={latitude}
                      onChange={(e) => setLatitude(e.target.value)}
                      placeholder="-6.2088"
                      className="w-full px-3 py-1.5 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white font-mono placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] text-slate-400 mb-0.5">Longitude</label>
                    <input
                      type="number"
                      step="any"
                      required
                      value={longitude}
                      onChange={(e) => setLongitude(e.target.value)}
                      placeholder="106.8456"
                      className="w-full px-3 py-1.5 text-xs bg-slate-900 border border-slate-700 rounded-lg text-white font-mono placeholder-slate-500 focus:outline-hidden focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="pt-2">
              <button
                type="submit"
                disabled={loading}
                className="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-50 transition-colors shadow-lg shadow-blue-950/50 cursor-pointer"
              >
                <span>{loading ? 'Memproses Pendaftaran...' : 'Daftarkan Gerai Saya'}</span>
                <ArrowRight className="w-4 h-4" />
              </button>
            </div>
          </form>

          <div className="pt-4 border-t border-slate-700/60 flex items-center justify-center gap-1.5 text-center">
            <ShieldCheck className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.5} />
            <span className="text-[11px] text-slate-500">
              Pendaftaran akan ditinjau oleh tim administrator sebelum toko diaktifkan.
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
