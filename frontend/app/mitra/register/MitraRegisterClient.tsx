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
  ArrowLeft,
  ShieldCheck,
  X,
  AlertTriangle,
} from 'lucide-react';
import { reverseGeocode } from '@/lib/profile';
import { StoreLocationPicker, isDefaultCoordinates } from '@/components/map/StoreLocationPicker';

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
  const [latitude, setLatitude] = useState('');
  const [longitude, setLongitude] = useState('');
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
    if (!latitude || !longitude || isNaN(latNum) || isNaN(lngNum) || isDefaultCoordinates(latNum, lngNum)) {
      setErrorMessage('Titik koordinat lokasi toko wajib ditentukan pada peta. Koordinat tidak boleh kosong atau masih nilai default (-6.2088, 106.8456).');
      return;
    }

    if (latNum < -90 || latNum > 90) {
      setErrorMessage('Koordinat latitude tidak valid (harus antara -90 dan 90).');
      return;
    }
    if (lngNum < -180 || lngNum > 180) {
      setErrorMessage('Koordinat longitude tidak valid (harus antara -180 dan 180).');
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
    <div className="min-h-screen bg-navy-50/60 py-10 px-4 sm:px-6 lg:px-8 relative overflow-hidden flex flex-col justify-between font-sans">
      {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
      <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
        {/* Bubble 1 (Top Left) */}
        <div className="absolute top-12 left-10 w-24 h-24 rounded-full border border-sky-300/40 bg-sky-200/20 backdrop-blur-[1px] shadow-inner animate-gentle-float">
          <div className="w-2.5 h-2.5 rounded-full bg-white/70 ml-3.5 mt-3.5" />
        </div>

        {/* Bubble 2 (Top Right) */}
        <div className="absolute top-24 right-12 w-32 h-32 rounded-full border border-sky-300/30 bg-sky-300/15 animate-gentle-float-slow">
          <div className="w-3.5 h-3.5 rounded-full bg-white/60 ml-4 mt-4" />
        </div>

        {/* Bubble 3 (Center Right - Hidden on mobile) */}
        <div className="hidden sm:block absolute top-1/2 right-16 w-20 h-20 rounded-full border border-sky-200/40 bg-sky-200/20 animate-gentle-float">
          <div className="w-2 h-2 rounded-full bg-white/60 ml-3 mt-3" />
        </div>

        {/* Bubble 4 (Bottom Left - Hidden on mobile) */}
        <div className="hidden sm:block absolute bottom-28 left-16 w-16 h-16 rounded-full border border-sky-300/25 bg-sky-200/15 animate-gentle-float-slow">
          <div className="w-1.5 h-1.5 rounded-full bg-white/50 ml-2 mt-2" />
        </div>
      </div>

      {/* Bottom Decorative Wave Accent (aria-hidden="true") */}
      <div className="absolute bottom-0 left-0 right-0 pointer-events-none overflow-hidden leading-none z-0" aria-hidden="true">
        <svg
          viewBox="0 0 1440 80"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          className="w-full h-12 sm:h-16 text-navy-100/60 preserve-3d"
        >
          <path
            d="M0 35C240 70 480 15 720 40C960 65 1200 20 1440 35V80H0V35Z"
            fill="currentColor"
          />
        </svg>
      </div>

      {/* Pop up / Modal for duplicate email/phone error */}
      {showDuplicateModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy-950/50 backdrop-blur-xs animate-fadeIn">
          <div className="w-full max-w-md bg-white border border-navy-200 rounded-3xl shadow-xl p-6 sm:p-8 space-y-4 text-center">
            <div className="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center mx-auto shadow-2xs">
              <AlertTriangle className="w-6 h-6" strokeWidth={1.75} />
            </div>
            <div className="space-y-1.5">
              <h3 className="text-base font-bold text-navy-900">Akun Sudah Terdaftar</h3>
              <p className="text-xs text-slate-600 leading-relaxed">
                {duplicateModalMsg}
              </p>
            </div>
            <div className="pt-2">
              <button
                type="button"
                onClick={() => setShowDuplicateModal(false)}
                className="w-full py-2.5 px-4 rounded-xl bg-navy-700 hover:bg-navy-800 text-xs font-semibold text-white transition-colors cursor-pointer min-h-[44px]"
              >
                Ganti email &amp; lanjutkan
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Top Back Navigation Link */}
      <div className="max-w-2xl mx-auto w-full mb-4 z-10 relative">
        <Link
          href="/"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-navy-700 transition-colors py-1"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>Kembali ke beranda</span>
        </Link>
      </div>

      <div className="max-w-2xl mx-auto w-full z-10 relative space-y-6 my-auto">
        {/* Page Title & Subtitle */}
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-navy-700 text-white shadow-xs border border-navy-600/40 mb-1">
            <Store className="w-6 h-6" strokeWidth={1.75} />
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-navy-900">
            Pendaftaran Mitra Laundry
          </h1>
          <p className="text-xs sm:text-sm text-slate-600 max-w-md mx-auto leading-relaxed">
            Kembangkan usaha laundry Anda dan jangkau lebih banyak pelanggan di sekitar.
          </p>
        </div>

        {/* Main Card Container */}
        <div className="bg-white rounded-3xl border border-navy-200/90 shadow-sm p-6 sm:p-10 space-y-6">
          {errorMessage && (
            <div className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-start gap-2.5 leading-relaxed">
              <AlertCircle className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" strokeWidth={1.75} />
              <span>{errorMessage}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Section 1: Data Pemilik Akun */}
            <div className="space-y-4 pb-6 border-b border-slate-100">
              <div className="flex items-center gap-2 pb-1">
                <div className="w-7 h-7 rounded-lg bg-navy-50 border border-navy-200/60 text-navy-700 flex items-center justify-center shadow-2xs">
                  <User className="w-4 h-4" strokeWidth={1.75} />
                </div>
                <h2 className="text-sm font-bold text-navy-900">
                  1. Data Pemilik Akun
                </h2>
              </div>

              <div>
                <label className="block text-xs font-bold text-navy-900 mb-1.5">
                  Nama Lengkap Pemilik
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <User className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                  <input
                    type="text"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Contoh: Budi Santoso"
                    className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-navy-900 mb-1.5">
                    Email Akun Mitra
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                      <Mail className="w-4 h-4" strokeWidth={1.75} />
                    </div>
                    <input
                      type="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="mitra@laundryku.com"
                      className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                    />
                  </div>
                  <p className="mt-1 text-[11px] text-slate-500">
                    Gunakan email yang belum terdaftar sebagai akun pelanggan.
                  </p>
                </div>

                <div>
                  <label className="block text-xs font-bold text-navy-900 mb-1.5">
                    No. WhatsApp / Telepon
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                      <Phone className="w-4 h-4" strokeWidth={1.75} />
                    </div>
                    <input
                      type="tel"
                      required
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="081234567890"
                      className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                    />
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-navy-900 mb-1.5">
                    Kata Sandi
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                      <Lock className="w-4 h-4" strokeWidth={1.75} />
                    </div>
                    <input
                      type="password"
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="Minimal 8 karakter"
                      className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-navy-900 mb-1.5">
                    Konfirmasi Kata Sandi
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                      <Lock className="w-4 h-4" strokeWidth={1.75} />
                    </div>
                    <input
                      type="password"
                      required
                      value={passwordConfirmation}
                      onChange={(e) => setPasswordConfirmation(e.target.value)}
                      placeholder="Ulangi kata sandi"
                      className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Section 2: Data Usaha Laundry */}
            <div className="space-y-4">
              <div className="flex items-center gap-2 pb-1">
                <div className="w-7 h-7 rounded-lg bg-navy-50 border border-navy-200/60 text-navy-700 flex items-center justify-center shadow-2xs">
                  <Store className="w-4 h-4" strokeWidth={1.75} />
                </div>
                <h2 className="text-sm font-bold text-navy-900">
                  2. Data usaha laundry
                </h2>
              </div>

              <div>
                <label className="block text-xs font-bold text-navy-900 mb-1.5">
                  Nama laundry
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <Building className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                  <input
                    type="text"
                    required
                    value={storeName}
                    onChange={(e) => setStoreName(e.target.value)}
                    placeholder="Contoh: Berkah Laundry Express"
                    className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-navy-900 mb-1.5">
                  Alamat lengkap laundry
                </label>
                <div className="relative">
                  <div className="absolute top-3 left-3.5 flex items-start pointer-events-none text-slate-400">
                    <MapPin className="w-4 h-4" strokeWidth={1.75} />
                  </div>
                  <textarea
                    required
                    rows={2}
                    value={storeAddress}
                    onChange={(e) => setStoreAddress(e.target.value)}
                    placeholder="Nama jalan, nomor ruko/rumah, RT/RW, kelurahan"
                    className="w-full pl-10 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all resize-none min-h-[64px]"
                  />
                </div>
              </div>

              {/* Kota & Provinsi */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-navy-900 mb-1.5">
                    Kota / Kabupaten
                  </label>
                  <input
                    type="text"
                    required
                    value={city}
                    onChange={(e) => setCity(e.target.value)}
                    placeholder="Contoh: Kota Bandung"
                    className="w-full px-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-navy-900 mb-1.5">
                    Provinsi
                  </label>
                  <input
                    type="text"
                    required
                    value={province}
                    onChange={(e) => setProvince(e.target.value)}
                    placeholder="Contoh: Jawa Barat"
                    className="w-full px-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl text-navy-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-navy-700 focus:ring-1 focus:ring-navy-700 transition-all min-h-[42px]"
                  />
                </div>
              </div>

              {/* Peta Penentuan Titik Gerai */}
              <div className="pt-2">
                <div className="p-3 bg-slate-50/60 rounded-2xl border border-slate-200/80">
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
                      if (info.formatted_address && !storeAddress) setStoreAddress(info.formatted_address);
                    }}
                    label="Titik lokasi laundry di peta"
                  />
                </div>
              </div>
            </div>

            <div className="pt-2 space-y-4">
              <button
                type="submit"
                disabled={loading}
                className="w-full flex items-center justify-center gap-2 py-3 px-5 rounded-xl text-xs font-bold text-white bg-navy-700 hover:bg-navy-800 focus:outline-hidden focus:ring-2 focus:ring-navy-700 focus:ring-offset-2 disabled:opacity-50 transition-colors shadow-xs cursor-pointer min-h-[46px]"
              >
                <span>{loading ? 'Memproses pendaftaran...' : 'Daftarkan laundry saya'}</span>
                <ArrowRight className="w-4 h-4 ml-0.5" />
              </button>

              <div className="p-3.5 rounded-xl bg-navy-50/70 border border-navy-100 flex items-center justify-center gap-2 text-center">
                <ShieldCheck className="w-4 h-4 text-navy-700 shrink-0" strokeWidth={1.75} />
                <span className="text-xs text-navy-800 font-medium">
                  Pendaftaran akan ditinjau oleh admin sebelum laundry Anda tampil di LaundryKu.
                </span>
              </div>

              <div className="pt-2 text-center border-t border-slate-100">
                <p className="text-xs text-slate-600">
                  Sudah punya akun mitra?{' '}
                  <Link
                    href="/mitra/login"
                    className="text-navy-700 hover:text-navy-800 font-semibold underline transition-colors"
                  >
                    Masuk di sini
                  </Link>
                </p>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
