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
} from 'lucide-react';

export default function MitraRegisterPage() {
  const router = useRouter();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [storeName, setStoreName] = useState('');
  const [storeAddress, setStoreAddress] = useState('');
  const [city, setCity] = useState('Jakarta');
  const [province, setProvince] = useState('DKI Jakarta');

  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

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
        }),
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.success) {
        const errorDetail = json.errors ? Object.values(json.errors).flat().join(', ') : null;
        const msg = errorDetail || json.message || 'Registrasi mitra gagal. Periksa kembali isian form Anda.';
        setErrorMessage(msg);
        return;
      }

      if (typeof window !== 'undefined' && json.data?.user) {
        localStorage.setItem('laundryku_user', JSON.stringify(json.data.user));
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: json.data.user }));
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

        <div className="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-xl">
          {errorMessage && (
            <div className="mb-6 p-3.5 rounded-lg bg-rose-950/60 border border-rose-800/60 flex items-start gap-2.5 text-rose-300 text-xs leading-relaxed">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-400 mt-0.5" strokeWidth={1.5} />
              <span>{errorMessage}</span>
            </div>
          )}

          <form className="space-y-6" onSubmit={handleSubmit}>
            {/* Bagian 1: Data Penanggung Jawab */}
            <div className="space-y-4">
              <h2 className="text-xs font-bold uppercase tracking-wider text-blue-400 border-b border-slate-700 pb-1.5">
                Data Penanggung Jawab Gerai
              </h2>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Nama Lengkap Pemilik / Penanggung Jawab
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
                    placeholder="Budi Santoso"
                    className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                      placeholder="budi@laundry.id"
                      className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Nomor WhatsApp / HP
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
                      className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                      placeholder="Minimal 8 karakter"
                      className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
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
                      placeholder="Ulangi kata sandi"
                      className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Bagian 2: Informasi Gerai Toko */}
            <div className="space-y-4 pt-2">
              <h2 className="text-xs font-bold uppercase tracking-wider text-blue-400 border-b border-slate-700 pb-1.5">
                Informasi Gerai Laundry
              </h2>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Nama Gerai / Toko Laundry
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <Store className="w-4 h-4" strokeWidth={1.5} />
                  </div>
                  <input
                    type="text"
                    required
                    value={storeName}
                    onChange={(e) => setStoreName(e.target.value)}
                    placeholder="Contoh: Berkah Laundry Express"
                    className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
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
                    placeholder="Jl. Merdeka No. 123, RT 01/RW 02"
                    className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500 resize-none"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Kota / Kabupaten
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                      <Building className="w-4 h-4" strokeWidth={1.5} />
                    </div>
                    <input
                      type="text"
                      required
                      value={city}
                      onChange={(e) => setCity(e.target.value)}
                      placeholder="Jakarta Selatan"
                      className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Provinsi
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                      <Building className="w-4 h-4" strokeWidth={1.5} />
                    </div>
                    <input
                      type="text"
                      required
                      value={province}
                      onChange={(e) => setProvince(e.target.value)}
                      placeholder="DKI Jakarta"
                      className="block w-full pl-9 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 text-sm focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="pt-4">
              <button
                type="submit"
                disabled={loading}
                className="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 focus:ring-2 focus:ring-blue-500 disabled:opacity-50 transition-colors shadow-md cursor-pointer"
              >
                {loading ? (
                  <span>Mendaftarkan Gerai...</span>
                ) : (
                  <>
                    <span>Daftarkan Gerai Mitra Sekarang</span>
                    <ArrowRight className="w-4 h-4" strokeWidth={1.5} />
                  </>
                )}
              </button>
            </div>

            <div className="text-center pt-2">
              <p className="text-xs text-slate-400">
                Sudah memiliki akun mitra?{' '}
                <Link href="/mitra/login" className="font-semibold text-blue-400 hover:text-blue-300 underline underline-offset-2">
                  Masuk ke Portal Mitra
                </Link>
              </p>
            </div>
          </form>

          <div className="mt-6 pt-4 border-t border-slate-700/60 flex items-center justify-center gap-1.5 text-center">
            <ShieldCheck className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.5} />
            <span className="text-[11px] text-slate-500">
              Registrasi gerai baru akan diverifikasi oleh tim platform dalam 1x24 jam kerja.
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
